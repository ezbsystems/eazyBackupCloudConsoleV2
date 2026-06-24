<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\Ms365EngineConfig;

/**
 * Server environment and active fleet target (development vs production workers).
 */
final class FleetContext
{
    public const FLEET_DEVELOPMENT = 'development';
    public const FLEET_PRODUCTION = 'production';

    public const ENV_DEVELOPMENT = 'development';
    public const ENV_PRODUCTION = 'production';

    private const SESSION_KEY = 'ms365_fleet_target';

    public static function serverEnvironment(): string
    {
        $env = strtolower(trim(Ms365EngineConfig::moduleSettingPublic('ms365_server_environment', self::ENV_DEVELOPMENT)));
        if ($env === self::ENV_PRODUCTION) {
            return self::ENV_PRODUCTION;
        }

        return self::ENV_DEVELOPMENT;
    }

    public static function isProductionServer(): bool
    {
        return self::serverEnvironment() === self::ENV_PRODUCTION;
    }

    public static function isDevelopmentServer(): bool
    {
        return !self::isProductionServer();
    }

    public static function activeFleet(): string
    {
        if (self::isProductionServer()) {
            return self::FLEET_PRODUCTION;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $target = strtolower(trim((string) ($_SESSION[self::SESSION_KEY] ?? self::FLEET_DEVELOPMENT)));
        if ($target === self::FLEET_PRODUCTION) {
            return self::FLEET_PRODUCTION;
        }

        return self::FLEET_DEVELOPMENT;
    }

    public static function isRemoteFleet(?string $fleet = null): bool
    {
        $fleet = $fleet ?? self::activeFleet();

        return self::isDevelopmentServer() && $fleet === self::FLEET_PRODUCTION;
    }

    public static function normalizeFleet(?string $fleet): string
    {
        $fleet = strtolower(trim((string) $fleet));
        if ($fleet === self::FLEET_PRODUCTION) {
            return self::FLEET_PRODUCTION;
        }

        return self::FLEET_DEVELOPMENT;
    }

    public static function setActiveFleet(string $fleet): void
    {
        if (!self::isDevelopmentServer()) {
            throw new \RuntimeException('Fleet target selection is only available on the development server');
        }
        $fleet = self::normalizeFleet($fleet);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION[self::SESSION_KEY] = $fleet;
    }

    public static function productionSystemUrl(): string
    {
        $url = trim(Ms365EngineConfig::moduleSettingPublic(
            'ms365_production_system_url',
            'http://192.168.92.75/accounts'
        ));
        if ($url === '') {
            $url = 'http://192.168.92.75/accounts';
        }
        $url = rtrim($url, '/');
        if (!str_ends_with($url, '/accounts')) {
            $url .= '/accounts';
        }

        return $url;
    }

    public static function developmentSystemUrl(): string
    {
        return trim(Ms365EngineConfig::moduleSettingPublic('ms365_development_system_url', '')) !== ''
            ? rtrim(trim(Ms365EngineConfig::moduleSettingPublic('ms365_development_system_url', '')), '/')
            : FleetSettings::systemUrl();
    }

    public static function workerApiBaseUrl(?string $fleet = null): string
    {
        $fleet = self::normalizeFleet($fleet ?? self::activeFleet());
        if ($fleet === self::FLEET_PRODUCTION && self::isDevelopmentServer()) {
            return self::productionWorkerApiBaseUrl();
        }

        return FleetSettings::systemUrl() . '/modules/addons/cloudstorage/api';
    }

    public static function productionWorkerApiBaseUrl(): string
    {
        return self::productionSystemUrl() . '/modules/addons/cloudstorage/api';
    }

    public static function remoteFleetApiUrl(string $op): string
    {
        return self::productionSystemUrl()
            . '/admin/addonmodules.php?module=ms365backup&action=fleet_remote&op='
            . rawurlencode($op);
    }

    public static function releaseSyncEnabledOnProd(): bool
    {
        $val = strtolower(trim(Ms365EngineConfig::moduleSettingPublic('ms365_production_release_sync_enabled', '')));

        return in_array($val, ['on', '1', 'yes', 'true'], true);
    }

    /** @return array<string, mixed> */
    public static function uiMeta(): array
    {
        $fleet = self::activeFleet();

        return [
            'server_environment' => self::serverEnvironment(),
            'active_fleet' => $fleet,
            'is_remote_fleet' => self::isRemoteFleet($fleet),
            'can_select_fleet' => self::isDevelopmentServer(),
            'production_system_url' => self::productionSystemUrl(),
            'worker_api_base_url' => self::workerApiBaseUrl($fleet),
            'builds_enabled' => self::isDevelopmentServer(),
        ];
    }
}
