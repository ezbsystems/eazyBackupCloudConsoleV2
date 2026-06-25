#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 4) . '/init.php';

use Ms365Backup\Ms365BatchClaimRepository;
use Ms365Backup\WorkerClaimService;
use WHMCS\Database\Capsule;

$batchId = $argv[1] ?? '210ad311-97b8-4780-b155-e9f0ee65c8f5';
$claim = Capsule::table('ms365_batch_claims')->where('batch_run_id', $batchId)->first();
if ($claim === null) {
    echo "No batch claim for $batchId\n";
    exit(1);
}
$nodeId = (string) $claim->worker_node_id;
$now = time();

$env = Capsule::table('tbladdonmodules')->where('module', 'ms365backup')->where('setting', 'ms365_server_environment')->value('value');
$childIds = WorkerClaimService::activeClaimRunIds($nodeId);
$batchIds = Ms365BatchClaimRepository::activeBatchRunIdsForNode($nodeId);
$node = Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->first();

echo "server_environment: $env\n";
echo "batch_status: {$claim->status}\n";
echo "worker_node: $nodeId ({$node->hostname})\n";
echo "worker_version: {$node->version}\n";
echo "worker_load: {$node->current_load}\n";
echo "worker_config_status: {$node->config_status}\n";
echo "worker_config_error: {$node->config_error}\n";
echo "batch_age_s: " . ($now - (int) $claim->claimed_at) . "\n";
echo "batch_heartbeat_stale_in_s: " . ((int) $claim->last_heartbeat_at + 180 - $now) . "\n";
echo "batch_lease_expires_in_s: " . ((int) $claim->lease_expires_at - $now) . "\n";
echo "active_claims_children: " . count($childIds) . "\n";
echo "active_claims_batches: " . json_encode($batchIds) . "\n";
echo "child_status: " . json_encode(Capsule::table('ms365_backup_runs')->where('e3_batch_run_id', $batchId)->groupBy('status')->selectRaw('status, count(*) c')->pluck('c', 'status')->all()) . "\n";
