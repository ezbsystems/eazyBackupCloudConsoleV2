<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

require_once __DIR__ . '/AgentIngestSupport.php';

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Centralized agent API authentication with debounced last_seen_at writes.
 */
class AgentAuth
{
    public const DEFAULT_HEARTBEAT_DEBOUNCE_SECONDS = 60;

    /**
     * Minimum seconds between persisted last_seen_at updates per agent.
     */
    public static function getHeartbeatDebounceSeconds(): int
    {
        $fromEnv = getenv('AGENT_HEARTBEAT_DEBOUNCE_SECONDS');
        if ($fromEnv !== false) {
            $parsed = (int) $fromEnv;
            if ($parsed > 0) {
                return $parsed;
            }
        }

        $fromDb = (int) AgentIngestSupport::getModuleSetting(
            'cloudbackup_agent_heartbeat_debounce_seconds',
            self::DEFAULT_HEARTBEAT_DEBOUNCE_SECONDS
        );

        return max(15, $fromDb > 0 ? $fromDb : self::DEFAULT_HEARTBEAT_DEBOUNCE_SECONDS);
    }

    /**
     * Authenticate the calling agent and touch last_seen_at only when stale.
     *
     * @param callable(array,int):void $fail Json responder, e.g. fn($d,$c) => respond($d,$c)
     */
    public static function authenticate(callable $fail): object
    {
        $agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? ($_POST['agent_uuid'] ?? null);
        $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
        if (!$agentUuid || !$agentToken) {
            $fail(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
        }

        $agent = Capsule::table('s3_cloudbackup_agents')
            ->where('agent_uuid', $agentUuid)
            ->first();

        if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
            $fail(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        self::touchLastSeenIfStale((string) $agentUuid, $agent->last_seen_at ?? null);

        return $agent;
    }

    /**
     * Persist last_seen_at only when the stored value is null or older than the debounce window.
     */
    public static function touchLastSeenIfStale(string $agentUuid, ?string $lastSeenAt): void
    {
        if (!class_exists(AgentLiveness::class, false)) {
            $livenessFile = __DIR__ . '/AgentLiveness.php';
            if (is_file($livenessFile)) {
                require_once __DIR__ . '/RedisConnection.php';
                require_once $livenessFile;
            }
        }
        if (class_exists(AgentLiveness::class, false)) {
            AgentLiveness::touchRedis($agentUuid);
        }

        $debounce = self::getHeartbeatDebounceSeconds();
        if ($lastSeenAt !== null && $lastSeenAt !== '') {
            $lastTs = strtotime($lastSeenAt);
            if ($lastTs !== false && (time() - $lastTs) < $debounce) {
                return;
            }
        }

        Capsule::table('s3_cloudbackup_agents')
            ->where('agent_uuid', $agentUuid)
            ->update(['last_seen_at' => Capsule::raw('NOW()')]);
    }
}
