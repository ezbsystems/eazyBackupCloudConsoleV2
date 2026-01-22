<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    $response = new JsonResponse($data, $httpCode);
    $response->send();
    exit;
}

function authenticateAgent(): object
{
    $agentId = $_SERVER['HTTP_X_AGENT_ID'] ?? ($_POST['agent_id'] ?? null);
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
    if (!$agentId || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->first();

    if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }

    Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);

    return $agent;
}

function getIntEnv(string $key, int $default): int
{
    $val = getenv($key);
    if ($val === false) {
        return $default;
    }

    $val = (int) $val;
    return $val > 0 ? $val : $default;
}

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

function sanitizePathInput(string $value): string
{
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $trimmed = trim($decoded);
    return trim($trimmed, " \t\n\r\0\x0B\"'");
}

function getModuleSetting(string $key, $default = null)
{
    try {
        $val = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', $key)
            ->value('value');
        return ($val !== null && $val !== '') ? $val : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

function getAgentTimingConfig(): array
{
    $defaultWatchdog = 720;
    $defaultReclaim = 180;
    $defaultReclaimEnabled = true;

    $dbWatchdog = (int) getModuleSetting('cloudbackup_agent_watchdog_timeout_seconds', $defaultWatchdog);
    $dbReclaim = (int) getModuleSetting('cloudbackup_agent_reclaim_grace_seconds', $defaultReclaim);
    $dbReclaimEnabledRaw = getModuleSetting('cloudbackup_agent_reclaim_enabled', $defaultReclaimEnabled ? '1' : '0');
    $dbReclaimEnabled = !in_array(strtolower((string) $dbReclaimEnabledRaw), ['0', 'false', 'off', 'no'], true);

    $watchdog = getIntEnv('AGENT_WATCHDOG_TIMEOUT_SECONDS', $dbWatchdog);
    $reclaim = getIntEnv('AGENT_RECLAIM_GRACE_SECONDS', $dbReclaim);
    $reclaimEnabled = getBoolEnv('AGENT_RECLAIM_ENABLED', $dbReclaimEnabled);

    if ($reclaim >= $watchdog) {
        $reclaim = max(60, (int) floor($watchdog * 0.25));
        if ($reclaim >= $watchdog) {
            $reclaim = max(60, $watchdog - 60);
        }
    }

    return [
        'watchdog_timeout_seconds' => $watchdog,
        'reclaim_grace_seconds' => $reclaim,
        'reclaim_enabled' => $reclaimEnabled,
    ];
}

$agent = authenticateAgent();
$timing = getAgentTimingConfig();
$hasAgentIdRuns = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_id');
$hasAgentIdJobs = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_id');
$hasUpdatedAtRuns = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at');
$hasDiskSource = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'disk_source_volume');
$hasDiskFormat = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'disk_image_format');
$hasDiskTemp = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'disk_temp_dir');

try {
    $runData = null;
    $debugInfo = [];
    $isReclaim = false;
    $lastHeartbeatAt = null;

    Capsule::connection()->transaction(function () use (&$runData, &$debugInfo, &$isReclaim, &$lastHeartbeatAt, $agent, $hasAgentIdRuns, $hasAgentIdJobs, $hasUpdatedAtRuns, $timing) {
        $heartbeatExpr = $hasUpdatedAtRuns ? "COALESCE(r.updated_at, r.started_at, r.created_at)" : "COALESCE(r.started_at, r.created_at)";
        $debugInfo['timing'] = [
            'watchdog_timeout_seconds' => $timing['watchdog_timeout_seconds'],
            'reclaim_grace_seconds' => $timing['reclaim_grace_seconds'],
            'reclaim_enabled' => $timing['reclaim_enabled'],
        ];

        // Attempt to reclaim an in-progress run for this agent
        $run = null;
        $hasRunTypeColumnReclaim = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');
        if (!empty($timing['reclaim_enabled'])) {
            $reclaimQuery = Capsule::table('s3_cloudbackup_runs as r')
                ->join('s3_cloudbackup_jobs as j', 'j.id', '=', 'r.job_id')
                ->where('j.client_id', $agent->client_id)
                ->where('j.source_type', 'local_agent')
                ->where('j.status', 'active')
                ->whereIn('r.status', ['starting', 'running'])
                ->whereNull('r.finished_at')
                ->where('r.cancel_requested', 0);
            
            // Exclude restore runs from reclaim - these are handled by poll_pending_commands
            if ($hasRunTypeColumnReclaim) {
                $reclaimQuery->where(function ($q) {
                    $q->whereNull('r.run_type')
                      ->orWhereNotIn('r.run_type', ['restore', 'hyperv_restore']);
                });
            }

            if ($hasAgentIdRuns) {
                $reclaimQuery->where('r.agent_id', $agent->id);
            } elseif ($hasAgentIdJobs) {
                $reclaimQuery->where('j.agent_id', $agent->id);
            }

            $reclaimQuery
                ->whereRaw("TIMESTAMPDIFF(SECOND, $heartbeatExpr, NOW()) >= ?", [$timing['reclaim_grace_seconds']])
                ->whereRaw("TIMESTAMPDIFF(SECOND, $heartbeatExpr, NOW()) < ?", [$timing['watchdog_timeout_seconds']]);

            $reclaimRun = $reclaimQuery
                ->select('r.*', Capsule::raw("$heartbeatExpr as last_heartbeat_at"))
                ->orderBy('r.id', 'asc')
                ->lockForUpdate()
                ->first();

            if ($reclaimRun) {
                $run = $reclaimRun;
                $isReclaim = true;
                $lastHeartbeatAt = $reclaimRun->last_heartbeat_at ?? null;
                $debugInfo['reclaim_run_id'] = $reclaimRun->id ?? null;
                $debugInfo['reclaim_last_heartbeat_at'] = $lastHeartbeatAt;
            }
        }

        $base = Capsule::table('s3_cloudbackup_runs as r')
            ->join('s3_cloudbackup_jobs as j', 'j.id', '=', 'r.job_id')
            ->where('j.client_id', $agent->client_id)
            ->where('j.source_type', 'local_agent')
            ->where('j.status', 'active')
            ->where('r.status', 'queued')
            ->where('r.cancel_requested', 0);
        
        // Exclude restore runs - these are handled by poll_pending_commands
        $hasRunTypeColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');
        if ($hasRunTypeColumn) {
            $base->where(function ($q) {
                $q->whereNull('r.run_type')
                  ->orWhereNotIn('r.run_type', ['restore', 'hyperv_restore']);
            });
        }

        $debugInfo['has_agent_id_runs'] = $hasAgentIdRuns;
        $debugInfo['has_agent_id_jobs'] = $hasAgentIdJobs;
        $debugInfo['base_count'] = (clone $base)->count();
        $debugInfo['base_sample'] = (clone $base)
            ->select('r.id as run_id', 'r.agent_id as run_agent_id', 'j.agent_id as job_agent_id', 'r.status as run_status', 'j.status as job_status')
            ->orderBy('r.id', 'asc')
            ->limit(5)
            ->get();

        if (!$isReclaim) {
            $query = clone $base;

            if ($hasAgentIdRuns) {
                $query->where(function($q) use ($agent, $hasAgentIdJobs) {
                    $q->where('r.agent_id', $agent->id);
                    if ($hasAgentIdJobs) {
                        $q->orWhere(function($qq) use ($agent) {
                            $qq->whereNull('r.agent_id')
                               ->where('j.agent_id', $agent->id);
                        });
                    }
                });
            } elseif ($hasAgentIdJobs) {
                $query->where('j.agent_id', $agent->id);
            }

            $debugInfo['filtered_count'] = (clone $query)->count();

            // Capture count for debugging
            $debugInfo['candidate_count'] = $debugInfo['filtered_count'];

            $runQuery = (clone $query)->select('r.*');

            $run = $runQuery->orderBy('r.id', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$run) {
                logModuleCall('cloudstorage', 'agent_next_run', ['agent_id' => $agent->id, 'debug' => $debugInfo], 'no_run');
                return;
            }

            $debugInfo['selected_run_id'] = $run->id ?? null;

            // Claim the run
            $claimData = [
                'status' => 'starting',
                'worker_host' => 'agent-' . $agent->id,
                'started_at' => Capsule::raw('NOW()'),
            ];
            if ($hasAgentIdRuns) {
                $claimData['agent_id'] = $agent->id;
            }

            $updated = Capsule::table('s3_cloudbackup_runs')
                ->where('id', $run->id)
                ->where('status', 'queued')
                ->update($claimData);

            if (!$updated) {
                $debugInfo['claim_failed'] = true;
                logModuleCall('cloudstorage', 'agent_next_run', ['agent_id' => $agent->id, 'debug' => $debugInfo], 'no_run_claim_failed');
                return;
            }
        }

        if (!$run) {
            logModuleCall('cloudstorage', 'agent_next_run', ['agent_id' => $agent->id, 'debug' => $debugInfo], 'no_run');
            return;
        }

        // Fetch related data
        $job = Capsule::table('s3_cloudbackup_jobs')->where('id', $run->job_id)->first();
        $bucket = $job ? Capsule::table('s3_buckets')->where('id', $job->dest_bucket_id)->first() : null;
        $keyUserId = $job->s3_user_id ?? 0;
        $keys = $job ? Capsule::table('s3_user_access_keys')
            ->where('user_id', $keyUserId)
            ->orderByDesc('id')
            ->first() : null;

        // Addon settings for endpoint/region
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->pluck('value', 'setting');
        $settingsMap = [];
        foreach ($settings as $k => $v) {
            $settingsMap[$k] = $v;
        }
        $debugInfo['settings_keys'] = array_keys($settingsMap);

        $agentEndpoint = $settingsMap['cloudbackup_agent_s3_endpoint'] ?? '';
        if (empty($agentEndpoint)) {
            $agentEndpoint = $settingsMap['s3_endpoint'] ?? '';
        }
        if (empty($agentEndpoint)) {
            $agentEndpoint = 'https://s3.ca-central-1.eazybackup.com';
        }
        if (array_key_exists('cloudbackup_agent_s3_region', $settingsMap)) {
            $agentRegion = $settingsMap['cloudbackup_agent_s3_region'];
        } else {
            $agentRegion = $settingsMap['s3_region'] ?? '';
        }
        $debugInfo['agent_endpoint'] = $agentEndpoint;
        $debugInfo['agent_region'] = $agentRegion;
        $encKeyPrimary = $settingsMap['cloudbackup_encryption_key'] ?? '';
        $encKeySecondary = $settingsMap['encryption_key'] ?? '';
        $usedEncKey = '';
        $accessKeyRaw = $keys->access_key ?? '';
        $secretKeyRaw = $keys->secret_key ?? '';
        $debugInfo['used_encryption_key'] = $usedEncKey;
        $debugInfo['enc_key_present'] = !empty($encKeyPrimary) || !empty($encKeySecondary);
        $debugInfo['access_key_raw_len'] = strlen($keys->access_key ?? '');
        $debugInfo['secret_key_raw_len'] = strlen($keys->secret_key ?? '');
        $debugInfo['enc_keys_tried'] = [];

        $decryptWith = function (?string $key) use (&$debugInfo, $accessKeyRaw, $secretKeyRaw) {
            $ak = $accessKeyRaw;
            $sk = $secretKeyRaw;
            if ($key && $ak) {
                $ak = HelperController::decryptKey($ak, $key);
            }
            if ($key && $sk) {
                $sk = HelperController::decryptKey($sk, $key);
            }
            return [
                is_string($ak) ? $ak : '',
                is_string($sk) ? $sk : '',
            ];
        };

        [$decAkPrimary, $decSkPrimary] = $decryptWith($encKeyPrimary);
        $debugInfo['enc_keys_tried'][] = ['key' => 'cloudbackup_encryption_key', 'access_len' => strlen($decAkPrimary), 'secret_len' => strlen($decSkPrimary)];

        $decAk = $decAkPrimary;
        $decSk = $decSkPrimary;
        if ($decAk === '' || $decSk === '') {
            [$decAkSecondary, $decSkSecondary] = $decryptWith($encKeySecondary);
            $debugInfo['enc_keys_tried'][] = ['key' => 'encryption_key', 'access_len' => strlen($decAkSecondary), 'secret_len' => strlen($decSkSecondary)];
            if ($decAkSecondary !== '' && $decSkSecondary !== '') {
                $decAk = $decAkSecondary;
                $decSk = $decSkSecondary;
                $usedEncKey = 'encryption_key';
            } else {
                $usedEncKey = ($decAkPrimary !== '' || $decSkPrimary !== '') ? 'cloudbackup_encryption_key_partial' : '';
            }
        } else {
            $usedEncKey = 'cloudbackup_encryption_key';
        }

        $accessKeyRaw = $decAk;
        $secretKeyRaw = $decSk;
        $debugInfo['used_encryption_key'] = $usedEncKey;
        $debugInfo['access_key_suffix'] = $accessKeyRaw ? substr($accessKeyRaw, -4) : '';
        $debugInfo['access_key_len'] = strlen($accessKeyRaw);
        $debugInfo['secret_key_len'] = strlen($secretKeyRaw);
        $debugInfo['key_user_id'] = $keyUserId;
        $debugInfo['key_row_id'] = $keys->id ?? null;

        $engineVal = $job->engine ?? 'sync';
        $sourceTypeVal = $job->source_type ?? '';
        if (empty($engineVal) && $sourceTypeVal === 'local_agent') {
            $engineVal = 'kopia'; // sensible default for local agent jobs
        }

        // Decrypt network credentials if present in source_config
        $sourceConfig = json_decode($job->source_config ?? '{}', true) ?? [];
        $networkCreds = null;
        if (isset($sourceConfig['network_credentials']) && is_array($sourceConfig['network_credentials'])) {
            $encNetCreds = $sourceConfig['network_credentials'];
            $encUser = $encNetCreds['username'] ?? '';
            $encPass = $encNetCreds['password'] ?? '';
            $domain = $encNetCreds['domain'] ?? '';

            // Try decrypting with both encryption keys
            $decUser = '';
            $decPass = '';
            if ($encUser && $encKeyPrimary) {
                $decUser = HelperController::decryptKey($encUser, $encKeyPrimary);
            }
            if ($decUser === '' && $encUser && $encKeySecondary) {
                $decUser = HelperController::decryptKey($encUser, $encKeySecondary);
            }
            if ($encPass && $encKeyPrimary) {
                $decPass = HelperController::decryptKey($encPass, $encKeyPrimary);
            }
            if ($decPass === '' && $encPass && $encKeySecondary) {
                $decPass = HelperController::decryptKey($encPass, $encKeySecondary);
            }

            if ($decUser !== '' && $decPass !== '') {
                $networkCreds = [
                    'username' => $decUser,
                    'password' => $decPass,
                    'domain' => $domain,
                ];
            }
        }

        $sourcePath = sanitizePathInput((string) ($job->source_path ?? ''));
        $sourcePaths = [];
        $sourcePathsRaw = $job->source_paths_json ?? null;
        if (is_array($sourcePathsRaw)) {
            $sourcePaths = $sourcePathsRaw;
        } elseif (is_string($sourcePathsRaw) && trim($sourcePathsRaw) !== '') {
            $decoded = json_decode($sourcePathsRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = json_decode(html_entity_decode($sourcePathsRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), true);
            }
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $sourcePaths = $decoded;
            }
        }
        $sourcePaths = array_values(array_filter(array_map('sanitizePathInput', $sourcePaths), fn($p) => $p !== ''));
        $runData = [
            'run_id' => $run->id,
            'job_id' => $run->job_id,
            'engine' => $engineVal,
            'source_type' => $sourceTypeVal,
            'dest_type' => $job->dest_type ?? 's3',
            'bucket_auto_create' => (bool) ($job->bucket_auto_create ?? false),
            'local_include_glob' => $job->local_include_glob ?? '',
            'local_exclude_glob' => $job->local_exclude_glob ?? '',
            'local_bandwidth_limit_kbps' => $job->local_bandwidth_limit_kbps ?? 0,
            'dest_bucket_name' => $bucket->name ?? '',
            'dest_prefix' => $job->dest_prefix ?? '',
            'dest_local_path' => $job->dest_local_path ?? '',
            'dest_endpoint' => $agentEndpoint,
            'dest_region' => $agentRegion,
            'dest_access_key' => $accessKeyRaw,
            'dest_secret_key' => $secretKeyRaw,
            'schedule_json' => json_decode($job->schedule_json ?? 'null', true),
            'retention_json' => json_decode($job->retention_json ?? 'null', true),
            'policy_json' => json_decode($job->policy_json ?? 'null', true),
            'compression_enabled' => (bool) ($job->compression_enabled ?? false),
            'network_credentials' => $networkCreds,
        ];
        if ($sourceTypeVal === 'local_agent') {
            $runData['source_paths'] = $sourcePaths;
        } else {
            $runData['source_path'] = $sourcePath;
        }
        if ($hasDiskSource) {
            $runData['disk_source_volume'] = $job->disk_source_volume ?? '';
        }
        if ($hasDiskFormat) {
            $runData['disk_image_format'] = $job->disk_image_format ?? '';
        }
        if ($hasDiskTemp) {
            $runData['disk_temp_dir'] = $job->disk_temp_dir ?? '';
        }
        
        // Hyper-V specific data
        if ($engineVal === 'hyperv') {
            $hypervConfig = json_decode($job->hyperv_config ?? '{}', true);
            // Ensure hyperv_config is always an object, not an array (for Go unmarshaling)
            if (!is_array($hypervConfig) || empty($hypervConfig)) {
                $hypervConfig = new \stdClass();
            }
            $runData['hyperv_config'] = $hypervConfig;
            
            // Fetch VMs configured for this job with last checkpoint info
            $hypervVMs = [];
            try {
                $vms = Capsule::table('s3_hyperv_vms')
                    ->where('job_id', $job->id)
                    ->where('backup_enabled', true)
                    ->get();
                
                foreach ($vms as $vm) {
                    $lastCheckpoint = Capsule::table('s3_hyperv_checkpoints')
                        ->where('vm_id', $vm->id)
                        ->where('is_active', true)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    $lastRctIds = json_decode($lastCheckpoint->rct_ids ?? '{}', true);
                    // Ensure last_rct_ids is always an object
                    if (!is_array($lastRctIds) || empty($lastRctIds)) {
                        $lastRctIds = new \stdClass();
                    }
                    
                    $hypervVMs[] = [
                        'vm_id' => (int) $vm->id,
                        'vm_name' => $vm->vm_name,
                        'vm_guid' => $vm->vm_guid,
                        'last_checkpoint_id' => $lastCheckpoint->checkpoint_id ?? null,
                        'last_rct_ids' => $lastRctIds,
                    ];
                }
            } catch (\Throwable $e) {
                // Tables may not exist yet, log but continue
                logModuleCall('cloudstorage', 'agent_next_run_hyperv', ['job_id' => $job->id], $e->getMessage());
            }
            
            // Fallback: if no VMs found in s3_hyperv_vms, try to extract from source_path
            // (for jobs created before VM registration was added)
            if (empty($hypervVMs) && !empty($job->source_path)) {
                $sourcePathStr = $job->source_path;
                // source_path may contain comma-separated GUIDs or "Hyper-V VMs (N)" format
                if (strpos($sourcePathStr, 'Hyper-V VMs') === false) {
                    // Looks like GUIDs - parse them
                    $guids = array_filter(array_map('trim', explode(',', $sourcePathStr)));
                    $guidPattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';
                    foreach ($guids as $guid) {
                        if (preg_match($guidPattern, $guid)) {
                            $hypervVMs[] = [
                                'vm_id' => 0, // No DB record
                                'vm_name' => $guid, // Agent will resolve to real name
                                'vm_guid' => $guid,
                                'last_checkpoint_id' => null,
                                'last_rct_ids' => new \stdClass(),
                            ];
                        }
                    }
                    if (!empty($hypervVMs)) {
                        logModuleCall('cloudstorage', 'agent_next_run_hyperv_fallback', [
                            'job_id' => $job->id,
                            'source_path' => $sourcePathStr,
                            'vm_count' => count($hypervVMs),
                        ], 'Used source_path fallback for VM discovery');
                    }
                }
            }
            
            $runData['hyperv_vms'] = $hypervVMs;
        }
        
        $debugInfo['has_access_key'] = !empty($runData['dest_access_key']);
        $debugInfo['has_secret_key'] = !empty($runData['dest_secret_key']);
        $debugInfo['dest_bucket'] = $runData['dest_bucket_name'];
        $debugInfo['dest_prefix'] = $runData['dest_prefix'];
        $debugInfo['reclaimed'] = $isReclaim;
        $debugInfo['last_heartbeat_at'] = $lastHeartbeatAt;
        $runData['resume'] = $isReclaim;
        $runData['last_heartbeat_at'] = $lastHeartbeatAt ? (string) $lastHeartbeatAt : null;
    });

    if (!$runData) {
        logModuleCall('cloudstorage', 'agent_next_run', ['agent_id' => $agent->id, 'debug' => $debugInfo], 'no_run');
        respond(['status' => 'no_run']);
    }

    logModuleCall(
        'cloudstorage',
        'agent_next_run',
        ['agent_id' => $agent->id, 'debug' => $debugInfo],
        [
            'status' => 'success',
            'run' => $runData['run_id'] ?? null,
            'dest_endpoint' => $runData['dest_endpoint'] ?? '',
            'dest_region' => $runData['dest_region'] ?? '',
            'dest_bucket' => $runData['dest_bucket_name'] ?? '',
            'dest_prefix' => $runData['dest_prefix'] ?? '',
            'has_access_key' => $debugInfo['has_access_key'] ?? null,
            'has_secret_key' => $debugInfo['has_secret_key'] ?? null,
        ]
    );

    respond([
        'status' => 'success',
        'run' => $runData,
    ]);
} catch (\Exception $e) {
    logModuleCall('cloudstorage', 'agent_next_run', [], $e->getMessage(), $e->getTraceAsString());
    respond(['status' => 'fail', 'message' => 'Server error'], 500);
}

