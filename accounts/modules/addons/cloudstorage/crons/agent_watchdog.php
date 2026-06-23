<?php

require __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/AgentTimingConfig.php';
require_once __DIR__ . '/../lib/Client/RunHeartbeatSupport.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\AgentTimingConfig;
use WHMCS\Module\Addon\CloudStorage\Client\RunHeartbeatSupport;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

function getBoolEnv(string $key, bool $default): bool
{
    $val = getenv($key);
    if ($val === false) {
        return $default;
    }
    $normalized = strtolower(trim((string) $val));
    if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
        return false;
    }
    if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
        return true;
    }
    return $default;
}

function formatHeartbeat(?string $heartbeat): string
{
    return $heartbeat ?: 'unknown';
}

$timing = AgentTimingConfig::get();
$hasRunIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$batchSize = max(1, (int) (getenv('AGENT_WATCHDOG_BATCH_SIZE') ?: 50));

$processed = [];
$totalCount = 0;

while (true) {
    $batchResult = Capsule::connection()->transaction(function () use ($timing, $hasRunIdPk, $batchSize) {
        $staleQuery = Capsule::table('s3_cloudbackup_runs as r')
            ->whereIn('r.status', ['starting', 'running']);
        RunHeartbeatSupport::applyStaleOlderThan($staleQuery, (int) $timing['watchdog_timeout_seconds'], 'r');

        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine')) {
            $staleQuery->where(function ($query) {
                $query->whereNull('r.engine')->orWhere('r.engine', '!=', 'ms365');
            });
        }

        $heartbeatSelect = RunHeartbeatSupport::selectHeartbeatColumn('r');
        $staleQuery->select('r.*', Capsule::raw("{$heartbeatSelect} as last_heartbeat_at"));
        if ($hasRunIdPk) {
            $staleQuery->addSelect(Capsule::raw('BIN_TO_UUID(r.run_id) as run_id_str'));
        }
        if (RunHeartbeatSupport::hasColumn()) {
            $staleQuery->orderBy('r.last_heartbeat_at', 'asc');
        } else {
            $staleQuery->orderByRaw("{$heartbeatSelect} ASC");
        }
        $staleQuery->limit($batchSize);
        $staleRuns = $staleQuery->lockForUpdate()->get();

        if ($staleRuns->isEmpty()) {
            return ['processed' => [], 'count' => 0, 'done' => true];
        }

        $processedBatch = [];
        $events = [];

        foreach ($staleRuns as $run) {
            $runIdentifier = $hasRunIdPk ? ($run->run_id_str ?? '') : ($run->id ?? 0);
            $lastHeartbeat = $run->last_heartbeat_at ?? null;
            $message = 'Agent offline / no heartbeat since ' . formatHeartbeat($lastHeartbeat);
            $isCancelRequested = !empty($run->cancel_requested);

            $whereRunClause = function ($query) use ($hasRunIdPk, $runIdentifier) {
                if ($hasRunIdPk && UuidBinary::isUuid($runIdentifier)) {
                    $query->whereRaw('run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($runIdentifier)));
                } else {
                    $query->where('id', $runIdentifier);
                }
            };

            $runIdForEvent = $hasRunIdPk
                ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($runIdentifier)))
                : $runIdentifier;

            if (!$isCancelRequested) {
                $update = RunHeartbeatSupport::mergeHeartbeat(['cancel_requested' => 1]);
                $updateQuery = Capsule::table('s3_cloudbackup_runs');
                $whereRunClause($updateQuery);
                $updateQuery->update($update);

                $events[] = [
                    'run_id' => $runIdForEvent,
                    'ts' => date('Y-m-d H:i:s.u'),
                    'type' => 'cancelled',
                    'level' => 'warn',
                    'code' => 'CANCEL_REQUESTED',
                    'message_id' => 'CANCEL_REQUESTED',
                    'params_json' => json_encode([
                        'last_heartbeat_at' => $lastHeartbeat,
                        'watchdog_timeout_seconds' => $timing['watchdog_timeout_seconds'],
                    ]),
                ];

                $processedBatch[] = [
                    'run_id' => $runIdentifier,
                    'agent_id' => $run->agent_id ?? null,
                    'last_heartbeat_at' => $lastHeartbeat,
                    'status' => 'cancel_requested',
                ];
                continue;
            }

            $update = RunHeartbeatSupport::mergeHeartbeat([
                'status' => 'cancelled',
                'error_summary' => 'Cancellation requested; ' . $message,
                'finished_at' => Capsule::raw('NOW()'),
            ]);
            $updateQuery = Capsule::table('s3_cloudbackup_runs');
            $whereRunClause($updateQuery);
            $updateQuery->update($update);

            $events[] = [
                'run_id' => $runIdForEvent,
                'ts' => date('Y-m-d H:i:s.u'),
                'type' => 'cancelled',
                'level' => 'warn',
                'code' => 'CANCELLED',
                'message_id' => 'CANCELLED',
                'params_json' => json_encode([
                    'last_heartbeat_at' => $lastHeartbeat,
                    'watchdog_timeout_seconds' => $timing['watchdog_timeout_seconds'],
                    'cancel_requested' => 1,
                ]),
            ];

            $processedBatch[] = [
                'run_id' => $runIdentifier,
                'agent_id' => $run->agent_id ?? null,
                'last_heartbeat_at' => $lastHeartbeat,
                'status' => 'cancelled',
            ];
        }

        if (!empty($events)) {
            Capsule::table('s3_cloudbackup_run_events')->insert($events);
        }

        return [
            'processed' => $processedBatch,
            'count' => count($processedBatch),
            'done' => count($processedBatch) < $batchSize,
        ];
    });

    $processed = array_merge($processed, $batchResult['processed']);
    $totalCount += $batchResult['count'];

    if (!empty($batchResult['done'])) {
        break;
    }
}

$result = ['processed' => $processed, 'count' => $totalCount];

echo "[agent_watchdog] watchdog_timeout_seconds={$timing['watchdog_timeout_seconds']} reclaim_grace_seconds={$timing['reclaim_grace_seconds']}\n";
echo "[agent_watchdog] processed stale runs: {$result['count']}\n";

if (!empty($result['processed'])) {
    foreach ($result['processed'] as $run) {
        echo sprintf(
            " - run_id=%s agent_id=%s status=%s last_heartbeat=%s\n",
            $run['run_id'],
            $run['agent_id'] ?? 'null',
            $run['status'] ?? 'unknown',
            formatHeartbeat($run['last_heartbeat_at'] ?? null)
        );
    }
}

$logNoopRuns = getBoolEnv('AGENT_WATCHDOG_LOG_NOOP', false);
if ($result['count'] > 0 || $logNoopRuns) {
    logModuleCall(
        'cloudstorage',
        'agent_watchdog',
        [
            'watchdog_timeout_seconds' => $timing['watchdog_timeout_seconds'],
            'reclaim_grace_seconds' => $timing['reclaim_grace_seconds'],
        ],
        $result
    );
}
