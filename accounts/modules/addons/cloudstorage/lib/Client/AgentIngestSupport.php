<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Shared helpers for agent ingest endpoints (push events, push agent events,
 * push log chunks, update run). Centralizes:
 *  - addon setting reads
 *  - minimum-agent-version gate (UUIDv7-style cutover)
 *  - simple semver comparison
 *  - per-run event hard cap reads
 */
class AgentIngestSupport
{
    /**
     * Read a cloudstorage addon setting from tbladdonmodules.
     */
    public static function getModuleSetting(string $key, $default = null)
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

    /**
     * Compare two semver-ish version strings.
     * Returns true if $a is strictly less than $b.
     * Tolerates leading 'v', missing minor/patch, and ignores pre-release suffixes.
     */
    public static function versionLessThan(string $a, string $b): bool
    {
        $a = self::normalizeVersion($a);
        $b = self::normalizeVersion($b);
        if ($a === '' || $b === '') {
            return false;
        }
        return version_compare($a, $b, '<');
    }

    private static function normalizeVersion(string $v): string
    {
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        if ($v[0] === 'v' || $v[0] === 'V') {
            $v = substr($v, 1);
        }
        if (preg_match('/^[0-9]+(?:\.[0-9]+){0,3}/', $v, $m)) {
            return $m[0];
        }
        return '';
    }

    /**
     * Determine whether an agent should be rejected with 426 Upgrade Required.
     * Returns ['status'=>'fail','code'=>'agent_version_too_old',...] payload + http code,
     * or null if the agent version is acceptable.
     *
     * Reads the reported version from request body / POST first, then falls back
     * to the persisted s3_cloudbackup_agents.agent_version column.
     *
     * @return array{0:array,1:int}|null Tuple [responsePayload, httpCode] or null when allowed.
     */
    public static function checkMinAgentVersion($agent, array $body): ?array
    {
        $minVersion = (string) self::getModuleSetting('cloudbackup_min_local_agent_version', '');
        if ($minVersion === '') {
            return null;
        }
        $reported = trim((string) (
            $_POST['agent_version']
                ?? ($body['agent_version'] ?? '')
        ));
        if ($reported === '' && is_object($agent) && isset($agent->agent_version)) {
            $reported = (string) $agent->agent_version;
        }
        if ($reported === '') {
            // No version reported and no recorded version: treat as too old.
            return [[
                'status' => 'fail',
                'code' => 'agent_version_too_old',
                'message' => 'Local agent version not reported. Please update the eazyBackup agent.',
                'min_version' => $minVersion,
                'reported_version' => null,
            ], 426];
        }
        if (self::versionLessThan($reported, $minVersion)) {
            return [[
                'status' => 'fail',
                'code' => 'agent_version_too_old',
                'message' => 'Local agent is older than the minimum supported version. Please update the eazyBackup agent.',
                'min_version' => $minVersion,
                'reported_version' => $reported,
            ], 426];
        }
        return null;
    }

    /**
     * Maximum events accepted per run before EVENTS_TRUNCATED is recorded.
     */
    public static function maxEventsPerRun(): int
    {
        $v = (int) self::getModuleSetting('cloudbackup_event_max_per_run', 5000);
        return $v > 0 ? $v : 5000;
    }

    /**
     * Maximum agent_events rows per UTC day per agent.
     */
    public static function maxAgentEventsPerDayPerAgent(): int
    {
        $v = (int) self::getModuleSetting('cloudbackup_agent_events_max_per_day_per_agent', 1000);
        return $v > 0 ? $v : 1000;
    }

    /**
     * Maximum admin log chunks accepted per run.
     */
    public static function maxChunksPerRun(): int
    {
        $v = (int) self::getModuleSetting('cloudbackup_chunks_max_per_run', 60);
        return $v > 0 ? $v : 60;
    }

    /**
     * Ensure the s3_cloudbackup_agent_events table exists. The table is normally
     * created by the addon activate/upgrade hooks, but those only run when the
     * admin manually triggers a (re)activation. This helper allows the agent
     * ingest and admin viewer endpoints to be self-healing on environments
     * where the upgrade hook has not been re-run after the events table was
     * introduced.
     *
     * Returns true when the table exists (or was created), false on failure.
     */
    public static function ensureAgentEventsTable(): bool
    {
        try {
            $schema = Capsule::schema();
            if ($schema->hasTable('s3_cloudbackup_agent_events')) {
                return true;
            }
            $schema->create('s3_cloudbackup_agent_events', function ($table) {
                $table->bigIncrements('id');
                $table->string('agent_uuid', 36);
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('backup_user_id')->nullable();
                $table->dateTime('ts');
                $table->enum('source', ['agent', 'tray'])->default('agent');
                $table->enum('level', ['info', 'warn', 'error'])->default('info');
                $table->string('code', 64);
                $table->string('message_id', 64);
                $table->mediumText('params_json')->nullable();
                $table->string('dedupe_key', 191)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['agent_uuid', 'ts'], 'idx_agent_ts');
                $table->index(['client_id', 'ts'], 'idx_client_ts');
                $table->index(['agent_uuid', 'dedupe_key', 'ts'], 'idx_agent_dedupe_ts');
                $table->index(['source', 'ts'], 'idx_source_ts');
            });
            if (function_exists('logModuleCall')) {
                logModuleCall('cloudstorage', 'ensure_agent_events_table', [], 'Created s3_cloudbackup_agent_events table on demand', [], []);
            }
            return true;
        } catch (\Throwable $e) {
            if (function_exists('logModuleCall')) {
                logModuleCall('cloudstorage', 'ensure_agent_events_table_error', [], $e->getMessage(), [], []);
            }
            return false;
        }
    }
}
