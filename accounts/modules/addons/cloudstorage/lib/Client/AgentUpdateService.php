<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

require_once __DIR__ . '/AgentIngestSupport.php';

use WHMCS\Database\Capsule;
use WHMCS\Config\Setting;

/**
 * Shared logic for the remote agent-update feature, used by both the client
 * portal trigger (api/e3backup_agent_request_update.php) and the admin Agents
 * page trigger (api/admin_cloudbackup_request_command.php).
 *
 * Responsibilities:
 *  - resolve the latest published release for an agent's platform
 *  - guard against updating an already-current or offline agent
 *  - guard against duplicate in-flight updates
 *  - create the s3_agent_update_jobs row and the agent_update command, linked
 *    together, with a self-contained, absolute-URL payload for the agent
 */
class AgentUpdateService
{
    /** States that count as an update still being in progress. */
    public const ACTIVE_STATES = [
        'queued', 'downloading', 'verifying', 'applying', 'restarting', 'verifying_online',
    ];

    /**
     * Map the agent's reported OS to a release platform key.
     * Returns 'windows', 'linux', or '' when unsupported.
     */
    public static function platformForAgent($agent): string
    {
        $os = strtolower(trim((string) ($agent->agent_os ?? '')));
        if ($os === '' ) {
            return '';
        }
        if (strpos($os, 'win') !== false) {
            return 'windows';
        }
        if (strpos($os, 'linux') !== false) {
            return 'linux';
        }
        return '';
    }

    /**
     * Return the latest published release row for a platform, or null.
     */
    public static function latestRelease(string $platform)
    {
        if (!Capsule::schema()->hasTable('s3_agent_releases')) {
            return null;
        }
        return Capsule::table('s3_agent_releases')
            ->where('platform', $platform)
            ->where('is_latest', 1)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Build an absolute download URL from a stored (often relative) download_url.
     */
    public static function absoluteUrl(string $downloadUrl): string
    {
        $downloadUrl = trim($downloadUrl);
        if ($downloadUrl === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $downloadUrl)) {
            return $downloadUrl;
        }
        $base = rtrim((string) Setting::getValue('SystemURL'), '/');
        return $base . '/' . ltrim($downloadUrl, '/');
    }

    /**
     * Return the most recent update job for an agent that is still in progress,
     * or null when none is active.
     */
    public static function activeJobForAgent(string $agentUuid)
    {
        if (!Capsule::schema()->hasTable('s3_agent_update_jobs')) {
            return null;
        }
        return Capsule::table('s3_agent_update_jobs')
            ->where('agent_uuid', $agentUuid)
            ->whereIn('status', self::ACTIVE_STATES)
            ->orderByDesc('id')
            ->first();
    }

    private static function onlineThresholdSeconds(): int
    {
        $v = (int) AgentIngestSupport::getModuleSetting('cloudbackup_agent_online_threshold_seconds', 180);
        return $v > 0 ? $v : 180;
    }

    private static function isOnline($agent): bool
    {
        if (empty($agent->last_seen_at)) {
            return false;
        }
        $row = Capsule::table('s3_cloudbackup_agents')
            ->where('agent_uuid', $agent->agent_uuid)
            ->whereRaw('TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) <= ?', [self::onlineThresholdSeconds()])
            ->exists();
        return $row;
    }

    /**
     * Request a remote update for an agent.
     *
     * @param object   $agent           Row from s3_cloudbackup_agents.
     * @param string   $requestedByType 'client' or 'admin'.
     * @param int|null $requestedById   Client or admin id, for audit.
     *
     * @return array {status, message, code?, update_job_id?, command_id?, target_version?}
     */
    public static function requestUpdate($agent, string $requestedByType, ?int $requestedById): array
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
            return ['status' => 'fail', 'message' => 'Commands not supported on this installation'];
        }
        if (!Capsule::schema()->hasTable('s3_agent_update_jobs')) {
            return ['status' => 'fail', 'message' => 'Update tracking is not available on this installation'];
        }
        if (!Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
            return ['status' => 'fail', 'message' => 'agent_uuid column not available on this installation'];
        }

        $platform = self::platformForAgent($agent);
        if ($platform === '') {
            return ['status' => 'fail', 'code' => 'unsupported_platform', 'message' => 'Remote update is not supported for this agent platform'];
        }

        $release = self::latestRelease($platform);
        if (!$release || empty($release->download_url)) {
            return ['status' => 'fail', 'code' => 'no_release', 'message' => 'No published agent release is available for this platform yet'];
        }

        $current = trim((string) ($agent->agent_version ?? ''));
        $target = trim((string) ($release->version_label ?? ''));
        if ($target === '') {
            return ['status' => 'fail', 'code' => 'no_release', 'message' => 'Latest release has no version label'];
        }

        // Already current: reported version is not strictly less than the target.
        if ($current !== '' && !AgentIngestSupport::versionLessThan($current, $target)) {
            return ['status' => 'fail', 'code' => 'already_current', 'message' => 'Agent is already running the latest version (' . $current . ')'];
        }

        if (!self::isOnline($agent)) {
            return ['status' => 'fail', 'code' => 'agent_offline', 'message' => 'Agent is offline. It must be online to receive an update.'];
        }

        // Idempotency: do not stack updates.
        $existing = self::activeJobForAgent($agent->agent_uuid);
        if ($existing) {
            return [
                'status' => 'fail',
                'code' => 'update_in_progress',
                'message' => 'An update is already in progress for this agent',
                'update_job_id' => (int) $existing->id,
            ];
        }

        $absoluteUrl = self::absoluteUrl((string) $release->download_url);

        try {
            $now = date('Y-m-d H:i:s');
            $jobId = Capsule::table('s3_agent_update_jobs')->insertGetId([
                'agent_uuid' => $agent->agent_uuid,
                'release_id' => (int) $release->id,
                'platform' => $platform,
                'from_version' => $current !== '' ? $current : null,
                'target_version' => $target,
                'download_url' => $absoluteUrl,
                'sha256' => $release->sha256 ?? null,
                'size_bytes' => isset($release->size_bytes) ? (int) $release->size_bytes : null,
                'status' => 'queued',
                'detail' => 'Update requested',
                'requested_by_type' => $requestedByType,
                'requested_by_id' => $requestedById,
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $payload = [
                'update_job_id' => (int) $jobId,
                'version_label' => $target,
                'download_url' => $absoluteUrl,
                'sha256' => (string) ($release->sha256 ?? ''),
                'size_bytes' => isset($release->size_bytes) ? (int) $release->size_bytes : 0,
                'platform' => $platform,
            ];

            $cmdId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId([
                'run_id' => null,
                'agent_uuid' => $agent->agent_uuid,
                'type' => 'agent_update',
                'payload_json' => json_encode($payload),
                'status' => 'pending',
                'created_at' => Capsule::raw('NOW()'),
            ]);

            Capsule::table('s3_agent_update_jobs')
                ->where('id', $jobId)
                ->update(['command_id' => (int) $cmdId, 'updated_at' => Capsule::raw('NOW()')]);

            return [
                'status' => 'success',
                'message' => 'Update queued',
                'update_job_id' => (int) $jobId,
                'command_id' => (int) $cmdId,
                'target_version' => $target,
            ];
        } catch (\Throwable $e) {
            if (function_exists('logModuleCall')) {
                logModuleCall('cloudstorage', 'agent_update_request', ['agent_uuid' => $agent->agent_uuid], $e->getMessage());
            }
            return ['status' => 'fail', 'message' => 'Unable to queue update'];
        }
    }

    /**
     * Record agent-reported progress against an update job.
     * Maps agent states to the job status lifecycle.
     */
    public static function recordProgress(string $agentUuid, int $jobId, string $state, string $detail): bool
    {
        if (!Capsule::schema()->hasTable('s3_agent_update_jobs')) {
            return false;
        }
        $allowed = ['downloading', 'verifying', 'applying', 'restarting', 'failed'];
        $state = strtolower(trim($state));
        if (!in_array($state, $allowed, true)) {
            return false;
        }

        $update = [
            'status' => $state,
            'detail' => mb_substr($detail, 0, 1000),
            'updated_at' => Capsule::raw('NOW()'),
        ];
        if ($state === 'failed') {
            $update['finished_at'] = Capsule::raw('NOW()');
        }

        $affected = Capsule::table('s3_agent_update_jobs')
            ->where('id', $jobId)
            ->where('agent_uuid', $agentUuid)
            ->whereIn('status', self::ACTIVE_STATES)
            ->update($update);

        return $affected > 0;
    }

    /**
     * Persist a freshly reported agent version (typically from the
     * X-Agent-Version header on a poll) and finalize any in-flight update job if
     * the reported version meets the target. Safe and cheap to call on the hot
     * poll path. Best-effort: never throws.
     */
    public static function noteAgentVersion(string $agentUuid, ?string $reportedVersion, ?string $reportedOs = null, ?string $reportedArch = null): void
    {
        $reportedVersion = trim((string) $reportedVersion);
        $reportedOs = trim((string) $reportedOs);
        $reportedArch = trim((string) $reportedArch);
        if ($agentUuid === '') {
            return;
        }
        try {
            if ($reportedVersion !== '' && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_version')) {
                Capsule::table('s3_cloudbackup_agents')
                    ->where('agent_uuid', $agentUuid)
                    ->where(function ($q) use ($reportedVersion) {
                        $q->where('agent_version', '!=', $reportedVersion)
                          ->orWhereNull('agent_version');
                    })
                    ->update([
                        'agent_version' => $reportedVersion,
                        'updated_at' => Capsule::raw('NOW()'),
                    ]);
            }

            // Backfill OS/arch when the agent reports them and the stored value
            // is missing or stale. This is what makes platform detection (and
            // the update button) light up on the hot poll path.
            if ($reportedOs !== '' && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_os')) {
                Capsule::table('s3_cloudbackup_agents')
                    ->where('agent_uuid', $agentUuid)
                    ->where(function ($q) use ($reportedOs) {
                        $q->whereNull('agent_os')->orWhere('agent_os', '');
                    })
                    ->update([
                        'agent_os' => $reportedOs,
                        'updated_at' => Capsule::raw('NOW()'),
                    ]);
            }
            if ($reportedArch !== '' && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_arch')) {
                Capsule::table('s3_cloudbackup_agents')
                    ->where('agent_uuid', $agentUuid)
                    ->where(function ($q) use ($reportedArch) {
                        $q->whereNull('agent_arch')->orWhere('agent_arch', '');
                    })
                    ->update([
                        'agent_arch' => $reportedArch,
                        'updated_at' => Capsule::raw('NOW()'),
                    ]);
            }

            if ($reportedVersion !== '') {
                self::markSuccessIfUpgraded($agentUuid, $reportedVersion);
            }
        } catch (\Throwable $e) {
            // Hot path: swallow.
        }
    }

    /**
     * Mark an agent's in-flight update job successful when it reports the target
     * version while online. Safe to call on every authenticated agent metadata
     * refresh. Returns true when a job was flipped to success.
     */
    public static function markSuccessIfUpgraded(string $agentUuid, string $reportedVersion): bool
    {
        if (!Capsule::schema()->hasTable('s3_agent_update_jobs')) {
            return false;
        }
        $reportedVersion = trim($reportedVersion);
        if ($reportedVersion === '') {
            return false;
        }

        $job = Capsule::table('s3_agent_update_jobs')
            ->where('agent_uuid', $agentUuid)
            ->whereIn('status', self::ACTIVE_STATES)
            ->orderByDesc('id')
            ->first();
        if (!$job) {
            return false;
        }

        $target = trim((string) ($job->target_version ?? ''));
        if ($target === '') {
            return false;
        }

        // Success when reported >= target (not strictly less than target).
        if (AgentIngestSupport::versionLessThan($reportedVersion, $target)) {
            return false;
        }

        $affected = Capsule::table('s3_agent_update_jobs')
            ->where('id', $job->id)
            ->whereIn('status', self::ACTIVE_STATES)
            ->update([
                'status' => 'success',
                'detail' => 'Agent online and reporting version ' . $reportedVersion,
                'finished_at' => Capsule::raw('NOW()'),
                'updated_at' => Capsule::raw('NOW()'),
            ]);
        return $affected > 0;
    }
}
