<?php
/**
 * Agent Poll Pending Commands API
 * 
 * Allows agents to poll for pending commands (restore, maintenance) independently
 * of active backup runs. This is essential for restore operations because the
 * original backup run is already completed when restore is requested.
 * 
 * The agent calls this endpoint in its main polling loop to check for any
 * pending commands that need to be executed.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
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

$agent = authenticateAgent();

if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    respond(['status' => 'success', 'commands' => []]);
}

$hasAgentIdJobs = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_id');

try {
    $commands = [];
    
    // First, check for NAS commands (these are tied directly to agent_id, not jobs)
    $nasCommands = Capsule::table('s3_cloudbackup_run_commands')
        ->where('agent_id', $agent->id)
        ->where('status', 'pending')
        ->whereIn('type', ['nas_mount', 'nas_unmount', 'nas_mount_snapshot', 'nas_unmount_snapshot'])
        ->orderBy('id', 'asc')
        ->limit(5)
        ->get(['id as command_id', 'run_id', 'type', 'payload_json']);

    // Next, check for filesystem browse and discovery commands (agent-scoped, no job context)
    $browseCommands = Capsule::table('s3_cloudbackup_run_commands')
        ->where('agent_id', $agent->id)
        ->where('status', 'pending')
        ->whereIn('type', ['browse_directory', 'list_hyperv_vms'])
        ->orderBy('id', 'asc')
        ->limit(5)
        ->get(['id as command_id', 'run_id', 'type', 'payload_json']);
    
    foreach ($nasCommands as $cmd) {
        $payload = [];
        if (!empty($cmd->payload_json)) {
            $dec = json_decode($cmd->payload_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                $payload = $dec;
            }
        }
        
        $commands[] = [
            'command_id' => (int) $cmd->command_id,
            'type' => $cmd->type,
            'run_id' => (int) ($cmd->run_id ?? 0),
            'job_id' => 0,
            'payload' => empty($payload) ? new \stdClass() : $payload, // Ensure {} not [] in JSON
            'job_context' => null,
        ];
        
        // Mark as processing
        Capsule::table('s3_cloudbackup_run_commands')
            ->where('id', $cmd->command_id)
            ->update(['status' => 'processing']);
    }

    // Handle browse_directory and list_hyperv_vms commands (no job context needed)
    foreach ($browseCommands as $cmd) {
        $payload = [];
        if (!empty($cmd->payload_json)) {
            $dec = json_decode($cmd->payload_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                $payload = $dec;
            }
        }

        $commands[] = [
            'command_id' => (int) $cmd->command_id,
            'type' => $cmd->type,
            'run_id' => (int) ($cmd->run_id ?? 0),
            'job_id' => 0,
            'payload' => empty($payload) ? new \stdClass() : $payload, // Ensure {} not [] in JSON
            'job_context' => null,
        ];

        Capsule::table('s3_cloudbackup_run_commands')
            ->where('id', $cmd->command_id)
            ->update(['status' => 'processing']);
    }
    
    // Then, find pending commands for jobs owned by this agent
    // We join through: commands -> runs -> jobs to verify ownership
    $cmdQuery = Capsule::table('s3_cloudbackup_run_commands as c')
        ->join('s3_cloudbackup_runs as r', 'c.run_id', '=', 'r.id')
        ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
        ->where('c.status', 'pending')
        ->where('j.client_id', $agent->client_id)
        ->where('j.source_type', 'local_agent');
    
    // Filter by agent ownership
    if ($hasAgentIdJobs) {
        $cmdQuery->where('j.agent_id', $agent->id);
    }
    
    // Only get restore and maintenance commands (cancel is handled during active runs)
    $cmdQuery->whereIn('c.type', ['restore', 'hyperv_restore', 'maintenance_quick', 'maintenance_full']);
    
    $cmdRows = $cmdQuery
        ->select(
            'c.id as command_id',
            'c.run_id',
            'c.type',
            'c.payload_json',
            'r.job_id',
            'r.log_ref as manifest_id', // The manifest_id from the backup run
            'j.source_path',
            'j.engine',
            'j.dest_bucket_id',
            'j.dest_prefix',
            'j.dest_type',
            'j.dest_local_path',
            'j.s3_user_id',
            'j.local_bandwidth_limit_kbps'
        )
        ->orderBy('c.id', 'asc')
        ->limit(5)
        ->get();
    
    foreach ($cmdRows as $cmd) {
        $payload = [];
        if (!empty($cmd->payload_json)) {
            $dec = json_decode($cmd->payload_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                $payload = $dec;
            }
        }
        
        // For restore commands, we need to provide full job context so agent can connect to repo
        $jobContext = null;
        if (in_array($cmd->type, ['restore', 'hyperv_restore', 'maintenance_quick', 'maintenance_full'])) {
            // Get bucket info
            $bucket = Capsule::table('s3_buckets')
                ->where('id', $cmd->dest_bucket_id)
                ->first();
            
            // Get access keys
            $keys = Capsule::table('s3_user_access_keys')
                ->where('user_id', $cmd->s3_user_id ?? 0)
                ->orderByDesc('id')
                ->first();
            
            // Get addon settings for endpoint/region
            $settings = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->pluck('value', 'setting');
            $settingsMap = [];
            foreach ($settings as $k => $v) {
                $settingsMap[$k] = $v;
            }
            
            $agentEndpoint = $settingsMap['cloudbackup_agent_s3_endpoint'] ?? '';
            if (empty($agentEndpoint)) {
                $agentEndpoint = $settingsMap['s3_endpoint'] ?? '';
            }
            if (empty($agentEndpoint)) {
                $agentEndpoint = 'https://s3.ca-central-1.eazybackup.com';
            }
            $agentRegion = $settingsMap['cloudbackup_agent_s3_region'] ?? ($settingsMap['s3_region'] ?? '');
            
            // Decrypt access keys - match agent_next_run.php pattern exactly
            $encKeyPrimary = $settingsMap['cloudbackup_encryption_key'] ?? '';
            $encKeySecondary = $settingsMap['encryption_key'] ?? '';
            
            $accessKeyRaw = $keys->access_key ?? '';
            $secretKeyRaw = $keys->secret_key ?? '';
            
            // Decryption helper (same as agent_next_run.php)
            $decryptWith = function (?string $key) use ($accessKeyRaw, $secretKeyRaw) {
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
            
            // Try primary key first
            [$decAkPrimary, $decSkPrimary] = $decryptWith($encKeyPrimary);
            $decAk = $decAkPrimary;
            $decSk = $decSkPrimary;
            
            // Fallback to secondary key if primary failed
            if ($decAk === '' || $decSk === '') {
                [$decAkSecondary, $decSkSecondary] = $decryptWith($encKeySecondary);
                if ($decAkSecondary !== '' && $decSkSecondary !== '') {
                    $decAk = $decAkSecondary;
                    $decSk = $decSkSecondary;
                }
            }
            
            $jobContext = [
                'job_id' => (int) $cmd->job_id,
                'run_id' => (int) $cmd->run_id,
                'engine' => $cmd->engine ?? 'kopia',
                'source_path' => $cmd->source_path ?? '',
                'dest_type' => $cmd->dest_type ?? 's3',
                'dest_bucket_name' => $bucket->name ?? '',
                'dest_prefix' => $cmd->dest_prefix ?? '',
                'dest_local_path' => $cmd->dest_local_path ?? '',
                'dest_endpoint' => $agentEndpoint,
                'dest_region' => $agentRegion,
                'dest_access_key' => is_string($decAk) ? $decAk : '',
                'dest_secret_key' => is_string($decSk) ? $decSk : '',
                'local_bandwidth_limit_kbps' => (int) ($cmd->local_bandwidth_limit_kbps ?? 0),
                'manifest_id' => $cmd->manifest_id ?? '', // From the backup run's log_ref
            ];
        }
        
        $commands[] = [
            'command_id' => (int) $cmd->command_id,
            'type' => $cmd->type,
            'run_id' => (int) $cmd->run_id,
            'job_id' => (int) $cmd->job_id,
            'payload' => empty($payload) ? new \stdClass() : $payload, // Ensure {} not [] in JSON
            'job_context' => $jobContext,
        ];
        
        // Mark as processing to avoid duplicate dispatch
        Capsule::table('s3_cloudbackup_run_commands')
            ->where('id', $cmd->command_id)
            ->update(['status' => 'processing']);
    }
    
    respond(['status' => 'success', 'commands' => $commands]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_poll_pending_commands', ['agent_id' => $agent->id], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Server error'], 500);
}

