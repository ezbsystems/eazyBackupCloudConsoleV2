#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Reset canonical and legacy delta state for scoped physical keys.
 *
 * Dry-run by default; pass --apply to persist tombstones and delete canonical rows.
 *
 * Usage:
 *   php bin/ms365_delta_reset.php --tenant-record-id=6 --e3-job-id=... --physical-key='drive:b!...'
 *   php bin/ms365_delta_reset.php --apply ...
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 4) . '/init.php';

use Ms365Backup\DeltaStateRepository;
use Ms365Backup\WorkerClaimService;
use WHMCS\Database\Capsule;

$options = getopt('', [
    'tenant-record-id:',
    'e3-job-id:',
    'physical-key:',
    'reason:',
    'operator:',
    'apply',
    'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: ms365_delta_reset.php --tenant-record-id=N --e3-job-id=UUID --physical-key=KEY [--physical-key=KEY ...] [--reason=text] [--operator=name] [--apply]\n");
    exit(0);
}

$tenantRecordId = (int) ($options['tenant-record-id'] ?? 0);
$e3JobId = trim((string) ($options['e3-job-id'] ?? ''));
$reason = trim((string) ($options['reason'] ?? 'manual delta reset'));
$operator = trim((string) ($options['operator'] ?? 'cli'));
$apply = array_key_exists('apply', $options);

$physicalKeys = [];
if (isset($options['physical-key'])) {
    $raw = $options['physical-key'];
    $physicalKeys = is_array($raw) ? $raw : [$raw];
}

$physicalKeys = array_values(array_unique(array_filter(array_map(
    static fn ($key) => trim((string) $key),
    $physicalKeys,
))));

if ($tenantRecordId <= 0 || $e3JobId === '' || $physicalKeys === []) {
    fwrite(STDERR, "tenant-record-id, e3-job-id, and at least one physical-key are required.\n");
    exit(1);
}

if (!DeltaStateRepository::resetsTableReady()) {
    fwrite(STDERR, "ms365_delta_resets table is not ready; run module migrations first.\n");
    exit(1);
}

$mode = $apply ? 'APPLY' : 'DRY-RUN';
fwrite(STDOUT, "[$mode] tenant_record_id=$tenantRecordId e3_job_id=$e3JobId keys=" . count($physicalKeys) . "\n");

foreach ($physicalKeys as $physicalKey) {
    fwrite(STDOUT, "\n== $physicalKey ==\n");

    $canonical = DeltaStateRepository::getStatesForSource($tenantRecordId, $physicalKey, $e3JobId);
    fwrite(STDOUT, 'canonical_rows: ' . json_encode($canonical, JSON_UNESCAPED_SLASHES) . "\n");

    $priorReset = DeltaStateRepository::resetActiveAt($tenantRecordId, $physicalKey, $e3JobId);
    fwrite(STDOUT, 'prior_reset_at: ' . ($priorReset !== null ? (string) $priorReset : 'none') . "\n");

    $run = Capsule::table('ms365_backup_runs')
        ->where('tenant_record_id', $tenantRecordId)
        ->where('physical_key', $physicalKey)
        ->where('status', 'queued')
        ->orderByDesc('created_at')
        ->first(['id']);
    if ($run !== null) {
        $payload = WorkerClaimService::buildRunPayload((string) $run->id);
        $delta = $payload['delta_states'] ?? null;
        if ($delta instanceof stdClass) {
            $delta = (array) $delta;
        }
        fwrite(STDOUT, 'queued_claim_delta_states: ' . json_encode($delta, JSON_UNESCAPED_SLASHES) . "\n");
    } else {
        fwrite(STDOUT, "queued_claim_delta_states: (no queued run for key)\n");
    }

    if (!$apply) {
        fwrite(STDOUT, "would record reset tombstone and delete canonical delta state\n");
        continue;
    }

    DeltaStateRepository::recordReset($tenantRecordId, $physicalKey, $e3JobId, $reason, $operator);
    $deleted = DeltaStateRepository::clearCanonicalForSource($tenantRecordId, $physicalKey, $e3JobId);
    fwrite(STDOUT, "recorded reset tombstone; deleted_canonical_rows=$deleted\n");

    $afterCanonical = DeltaStateRepository::getStatesForSource($tenantRecordId, $physicalKey, $e3JobId);
    $afterReset = DeltaStateRepository::resetActiveAt($tenantRecordId, $physicalKey, $e3JobId);
    fwrite(STDOUT, 'verify_canonical: ' . json_encode($afterCanonical, JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, 'verify_reset_at: ' . ($afterReset !== null ? (string) $afterReset : 'missing') . "\n");

    if ($run !== null) {
        $payload = WorkerClaimService::buildRunPayload((string) $run->id);
        $delta = $payload['delta_states'] ?? null;
        if ($delta instanceof stdClass) {
            $delta = (array) $delta;
        }
        fwrite(STDOUT, 'verify_claim_delta_states: ' . json_encode($delta, JSON_UNESCAPED_SLASHES) . "\n");
    }
}

fwrite(STDOUT, "\nDone ($mode).\n");
