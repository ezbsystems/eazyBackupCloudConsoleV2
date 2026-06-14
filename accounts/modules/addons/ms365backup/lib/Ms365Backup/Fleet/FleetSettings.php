<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\Ms365EngineConfig;
use WHMCS\Database\Capsule;

final class FleetSettings
{
    public static function repoPath(): string
    {
        $path = trim(Ms365EngineConfig::moduleSettingPublic('ms365_worker_repo_path', ''));
        if ($path === '') {
            $path = dirname(__DIR__, 7) . '/ms365-backup-worker';
        }

        return $path;
    }

    public static function goBinary(): string
    {
        $go = trim(Ms365EngineConfig::moduleSettingPublic('ms365_worker_build_go', ''));
        if ($go !== '') {
            return $go;
        }
        $which = trim((string) shell_exec('command -v go 2>/dev/null'));

        return $which !== '' ? $which : '/usr/local/go/bin/go';
    }

    public static function artifactRoot(): string
    {
        $configured = trim(Ms365EngineConfig::moduleSettingPublic('ms365_worker_artifact_dir', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return dirname(__DIR__, 3) . '/storage/worker-releases';
    }

    public static function buildStorageRoot(): string
    {
        return dirname(__DIR__, 3) . '/storage/worker-builds';
    }

    public static function staleHeartbeatSeconds(): int
    {
        return max(60, (int) Ms365EngineConfig::moduleSettingPublic('ms365_worker_stale_seconds', '120'));
    }

    public static function offlineAlertMinutes(): int
    {
        return max(5, (int) Ms365EngineConfig::moduleSettingPublic('ms365_worker_offline_alert_minutes', '10'));
    }

    public static function artifactNonceTtlSeconds(): int
    {
        return max(60, (int) Ms365EngineConfig::moduleSettingPublic('ms365_worker_artifact_nonce_ttl', '600'));
    }

    public static function systemUrl(): string
    {
        if (class_exists(\WHMCS\Config\Setting::class)) {
            $url = trim((string) \WHMCS\Config\Setting::getValue('SystemURL'));
            if ($url !== '') {
                return rtrim($url, '/');
            }
        }
        $row = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');

        return rtrim((string) $row, '/');
    }

    public static function workerApiBaseUrl(): string
    {
        return self::systemUrl() . '/modules/addons/cloudstorage/api';
    }

    /** @return array<string, string> */
    public static function publicConfig(): array
    {
        return [
            'repo_path' => self::repoPath(),
            'go_binary' => self::goBinary(),
            'artifact_root' => self::artifactRoot(),
            'api_base_url' => self::workerApiBaseUrl(),
            'stale_heartbeat_seconds' => (string) self::staleHeartbeatSeconds(),
        ];
    }
}
