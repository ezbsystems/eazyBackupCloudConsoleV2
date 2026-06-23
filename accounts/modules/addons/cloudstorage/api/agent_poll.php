<?php
/**
 * Combined agent idle poll: single auth heartbeat + pending commands + repo operations.
 *
 * Reduces idle HTTP/DB load versus separate agent_poll_pending_commands.php and
 * agent_poll_repo_operations.php on the 1s command loop. Agents should still call
 * agent_next_run.php on poll_interval_secs for scheduled backup pickup.
 */

require_once __DIR__ . '/../lib/Bootstrap/agent_bootstrap.php';
require_once __DIR__ . '/../lib/Client/AgentAuth.php';
require_once __DIR__ . '/../lib/Client/AgentUpdateService.php';
require_once __DIR__ . '/../lib/Client/AgentIngestSupport.php';

define('AGENT_POLL_FUNCTIONS_ONLY', true);
require_once __DIR__ . '/agent_poll_pending_commands.php';
require_once __DIR__ . '/agent_poll_repo_operations.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\AgentAuth;
use WHMCS\Module\Addon\CloudStorage\Client\AgentUpdateService;
use WHMCS\Module\Addon\CloudStorage\Client\AgentIngestSupport;
use WHMCS\Module\Addon\CloudStorage\Client\RedisConnection;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function respondPoll(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function cloudstorage_agent_poll_next_secs(): int
{
    $hint = (int) AgentIngestSupport::getModuleSetting('cloudbackup_agent_command_poll_secs', 15);
    return $hint > 0 ? $hint : 15;
}

function cloudstorage_agent_poll_long_poll_enabled(): bool
{
    $env = getenv('CLOUDBACKUP_AGENT_LONG_POLL');
    if ($env !== false && $env !== '') {
        return !in_array(strtolower(trim((string) $env)), ['0', 'false', 'off', 'no'], true);
    }
    $q = strtolower(trim((string) ($_GET['long_poll'] ?? '')));
    return in_array($q, ['1', 'true', 'yes'], true);
}

function cloudstorage_agent_poll_has_pending_work(object $agent): bool
{
    if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
        return false;
    }

    $hasPendingCommands = Capsule::table('s3_cloudbackup_run_commands')
        ->where('agent_uuid', $agent->agent_uuid)
        ->where('status', 'pending')
        ->exists();

    if ($hasPendingCommands) {
        return true;
    }

    if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')
        || !Capsule::schema()->hasTable('s3_kopia_repos')) {
        return false;
    }

    $query = Capsule::table('s3_kopia_repo_operations as op')
        ->join('s3_kopia_repos as r', 'op.repo_id', '=', 'r.id')
        ->where('op.status', 'queued')
        ->where('r.client_id', (int) $agent->client_id);

    if (Capsule::schema()->hasColumn('s3_kopia_repos', 'tenant_id')
        && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'tenant_id')) {
        $tenantId = $agent->tenant_id ?? null;
        if ($tenantId !== null && $tenantId !== '') {
            $query->where('r.tenant_id', (int) $tenantId);
        } else {
            $query->whereNull('r.tenant_id');
        }
    }

    return $query->exists();
}

function cloudstorage_agent_poll_maybe_wait(object $agent): void
{
    if (!cloudstorage_agent_poll_long_poll_enabled()) {
        return;
    }

    if (cloudstorage_agent_poll_has_pending_work($agent)) {
        return;
    }

    $maxWait = 25;
    $redis = RedisConnection::get();
    $signalKey = 'agent:poll:signal:' . $agent->agent_uuid;
    if ($redis->isAvailable()) {
        if ($redis->blpop($signalKey, $maxWait)) {
            return;
        }
    }

    $deadline = time() + $maxWait;
    while (time() < $deadline) {
        sleep(1);
        if (cloudstorage_agent_poll_has_pending_work($agent)) {
            return;
        }
    }
}

$agent = AgentAuth::authenticate(fn(array $data, int $code) => respondPoll($data, $code));

AgentUpdateService::noteAgentVersion(
    (string) $agent->agent_uuid,
    $_SERVER['HTTP_X_AGENT_VERSION'] ?? null,
    $_SERVER['HTTP_X_AGENT_OS'] ?? null,
    $_SERVER['HTTP_X_AGENT_ARCH'] ?? null
);

cloudstorage_agent_poll_maybe_wait($agent);

$commandsResult = cloudstorage_fetch_pending_commands($agent);
if (($commandsResult['status'] ?? '') === 'fail') {
    respondPoll($commandsResult, 500);
}

$repoResult = cloudstorage_fetch_repo_operation($agent);
if (($repoResult['status'] ?? '') === 'fail') {
    respondPoll($repoResult, 500);
}

$hasWork = !empty($commandsResult['commands'] ?? []) || !empty($repoResult['operation'] ?? null);
$nextPoll = cloudstorage_agent_poll_next_secs();
if ($hasWork) {
    $nextPoll = max(5, (int) floor($nextPoll / 2));
}

respondPoll([
    'status' => 'success',
    'poll_version' => 1,
    'commands' => $commandsResult['commands'] ?? [],
    'repo_operation' => $repoResult['operation'] ?? null,
    'next_poll_secs' => $nextPoll,
]);
