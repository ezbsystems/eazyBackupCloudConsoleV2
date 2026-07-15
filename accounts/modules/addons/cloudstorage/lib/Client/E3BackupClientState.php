<?php

require_once __DIR__ . '/E3BackupUserScope.php';

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

/**
 * Product flags and smart landing resolution for e3 Cloud Backup client area.
 */
class E3BackupClientState
{
    public static function clientHasE3AgentProduct(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        $e3Pid = (int) ProductConfig::e3CloudBackupPid();
        if ($e3Pid <= 0) {
            return false;
        }

        $product = DBController::getActiveProduct($clientId, $e3Pid);
        if (!$product) {
            $product = DBController::getProduct($clientId, $e3Pid);
        }

        return $product && !empty($product->username);
    }

    /**
     * Whether the client may enroll a local backup agent (legacy e3 Cloud Backup
     * product or unified e3 Backup User + active backup user row).
     */
    public static function clientHasLocalAgentEntitlement(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        if (self::clientHasE3AgentProduct($clientId)) {
            return true;
        }

        if (!self::isUnifiedEnabled()) {
            return false;
        }

        $bootstrapPath = dirname(__DIR__) . '/Provision/E3BackupUserProductBootstrap.php';
        if (is_file($bootstrapPath)) {
            require_once $bootstrapPath;
        }
        if (!class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Provision\\E3BackupUserProductBootstrap')) {
            return false;
        }

        $unifiedPid = (int) \WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::getPid();
        if ($unifiedPid > 0) {
            $product = DBController::getActiveProduct($clientId, $unifiedPid);
            if (!$product) {
                $product = DBController::getProduct($clientId, $unifiedPid);
            }
            if ($product && !empty($product->username)) {
                return true;
            }
        }

        try {
            if (Capsule::schema()->hasTable('s3_backup_users')) {
                $query = Capsule::table('s3_backup_users')
                    ->where('client_id', $clientId)
                    ->where('status', 'active');
                if (class_exists(E3BackupUserScope::class)) {
                    E3BackupUserScope::applyNotDeletedScope($query, '');
                }
                if (Capsule::schema()->hasColumn('s3_backup_users', 'backup_type')) {
                    $query->whereIn('backup_type', ['local', 'both']);
                }

                return $query->exists();
            }
        } catch (\Throwable $_) {
        }

        return false;
    }

    public static function clientHasMs365Product(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        $ms365Pid = (int) ProductConfig::ms365BackupPid();
        if ($ms365Pid <= 0) {
            return false;
        }

        $product = DBController::getActiveProduct($clientId, $ms365Pid);
        if (!$product) {
            $product = DBController::getProduct($clientId, $ms365Pid);
        }

        return $product && !empty($product->username);
    }

    public static function clientHasCloudStorageProduct(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        $storagePid = (int) ProductConfig::cloudStoragePid();
        if ($storagePid <= 0) {
            return false;
        }

        return DBController::getActiveProduct($clientId, $storagePid) !== null;
    }

    public static function clientCanAccessE3BackupShell(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        if (self::clientHasE3AgentProduct($clientId)
            || self::clientHasMs365Product($clientId)
            || self::clientHasCloudStorageProduct($clientId)) {
            return true;
        }

        try {
            if (Capsule::schema()->hasTable('s3_backup_users')) {
                $existsQuery = Capsule::table('s3_backup_users')->where('client_id', $clientId);
                if (class_exists(E3BackupUserScope::class)) {
                    E3BackupUserScope::applyNotDeletedScope($existsQuery, '');
                }

                return $existsQuery->exists();
            }
        } catch (\Throwable $_) {
        }

        return false;
    }

    /**
     * @return 'ms365_getting_started'|'getting_started'|'dashboard'
     */
    public static function resolveLandingView(int $clientId): string
    {
        if ($clientId <= 0) {
            return 'dashboard';
        }

        if (self::isUnifiedEnabled()) {
            return self::resolveUnifiedLanding($clientId)['view'];
        }

        if (self::clientHasMs365Product($clientId)) {
            $defaultBu = E3BackupAccess::defaultBackupUser($clientId);
            if ($defaultBu) {
                $ms365Autoload = dirname(__DIR__, 3) . '/ms365backup/ms365backup_autoload.php';
                if (is_file($ms365Autoload)) {
                    require_once $ms365Autoload;
                }
                if (class_exists('\\Ms365Backup\\Ms365Onboarding')) {
                    try {
                        $msOb = \Ms365Backup\Ms365Onboarding::computeForBackupUser(
                            $clientId,
                            (int) $defaultBu['id']
                        );
                        if (empty($msOb['all_complete'])) {
                            return 'ms365_getting_started';
                        }
                    } catch (\Throwable $_) {
                        return 'ms365_getting_started';
                    }
                }
            } else {
                return 'ms365_getting_started';
            }
        }

        if (self::clientHasE3AgentProduct($clientId)) {
            if (class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Client\\OnboardingState')) {
                try {
                    $obState = OnboardingState::compute($clientId);
                    if (empty($obState['all_complete'])) {
                        return 'getting_started';
                    }
                } catch (\Throwable $_) {
                    return 'getting_started';
                }
            } else {
                $obPath = __DIR__ . '/OnboardingState.php';
                if (is_file($obPath)) {
                    require_once $obPath;
                    try {
                        $obState = OnboardingState::compute($clientId);
                        if (empty($obState['all_complete'])) {
                            return 'getting_started';
                        }
                    } catch (\Throwable $_) {
                        return 'getting_started';
                    }
                }
            }
        }

        return 'dashboard';
    }

    public static function resolveLandingUrl(int $clientId): string
    {
        $dashboardUrl = 'index.php?m=cloudstorage&page=e3backup';
        if ($clientId <= 0) {
            return $dashboardUrl;
        }

        if (self::isUnifiedEnabled()) {
            $landing = self::resolveUnifiedLanding($clientId);
            if ($landing['view'] === 'dashboard') {
                return $dashboardUrl;
            }

            $params = [
                'm' => 'cloudstorage',
                'page' => 'e3backup',
                'view' => 'getting_started',
            ];
            if ($landing['user_id'] !== '') {
                $params['user_id'] = $landing['user_id'];
            }
            if ($landing['intent'] !== '') {
                $params['intent'] = $landing['intent'];
            }

            return 'index.php?' . http_build_query($params);
        }

        $view = self::resolveLandingView($clientId);
        if ($view === 'dashboard') {
            return $dashboardUrl;
        }

        return 'index.php?m=cloudstorage&page=e3backup&view=' . rawurlencode($view);
    }

    /**
     * Map welcome/signup product_choice to unified Getting Started workload intent.
     */
    public static function preferredWorkloadIntent(int $clientId): string
    {
        if ($clientId <= 0) {
            return '';
        }
        try {
            if (!Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
                return '';
            }
            $row = Capsule::table('cloudstorage_trial_selection')
                ->where('client_id', $clientId)
                ->first();
            if (!$row) {
                return '';
            }
            $meta = [];
            if (!empty($row->meta)) {
                $decoded = json_decode((string) $row->meta, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            $provisionIntent = strtolower(trim((string) ($meta['provision_intent'] ?? '')));
            if (in_array($provisionIntent, ['local', 'ms365', 'saas'], true)) {
                return $provisionIntent;
            }
            $choice = strtolower(trim((string) $row->product_choice));
            if (in_array($choice, ['e3backup', 'backup', 'e3_backup', 'e3-backup', 'cloudbackup_e3'], true)) {
                return 'local';
            }
            if (in_array($choice, ['ms365', 'm365'], true)) {
                return 'ms365';
            }
            if (in_array($choice, ['cloud2cloud', 'cloud-to-cloud'], true)) {
                return 'saas';
            }
        } catch (\Throwable $_) {
        }

        return '';
    }

    /**
     * Persist welcome/signup product_choice for Getting Started intent resolution.
     */
    public static function persistProductChoice(int $clientId, string $choice, array $metaMerge = []): void
    {
        if ($clientId <= 0) {
            return;
        }
        $choice = strtolower(trim($choice));
        if ($choice === '') {
            return;
        }
        try {
            if (!Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
                return;
            }
            $now = date('Y-m-d H:i:s');
            $exists = Capsule::table('cloudstorage_trial_selection')->where('client_id', $clientId)->first();
            $meta = [];
            if ($exists && !empty($exists->meta)) {
                $decoded = json_decode((string) $exists->meta, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            foreach ($metaMerge as $key => $value) {
                $meta[$key] = $value;
            }
            $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES);
            if ($exists) {
                Capsule::table('cloudstorage_trial_selection')
                    ->where('client_id', $clientId)
                    ->update([
                        'product_choice' => $choice,
                        'meta' => $metaJson,
                        'updated_at' => $now,
                    ]);
            } else {
                Capsule::table('cloudstorage_trial_selection')->insert([
                    'client_id' => $clientId,
                    'product_choice' => $choice,
                    'trial_status' => 'trial',
                    'meta' => $metaJson,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $_) {
        }
    }

    /**
     * Map unified provision intent to cloudstorage_trial_selection.product_choice.
     */
    public static function productChoiceFromProvisionIntent(string $intent): string
    {
        $intent = strtolower(trim($intent));
        if ($intent === 'ms365') {
            return 'ms365';
        }
        if ($intent === 'saas') {
            return 'cloud2cloud';
        }

        return 'e3backup';
    }

    /**
     * Choose the active Getting Started workload tab from signup intent + onboarding state.
     */
    public static function resolveGettingStartedIntent(
        int $clientId,
        bool $localIncomplete,
        bool $ms365Incomplete,
        string $urlIntent = ''
    ): string {
        $urlIntent = strtolower(trim($urlIntent));
        $preferred = self::preferredWorkloadIntent($clientId);

        if ($preferred === 'local' && $localIncomplete) {
            return 'local';
        }
        if ($preferred === 'ms365' && $ms365Incomplete) {
            return 'ms365';
        }
        if ($preferred === 'saas') {
            return 'saas';
        }
        if ($localIncomplete) {
            return 'local';
        }
        if ($ms365Incomplete) {
            return 'ms365';
        }
        if (in_array($urlIntent, ['local', 'ms365', 'saas'], true)) {
            return $urlIntent;
        }
        if ($preferred !== '') {
            return $preferred;
        }

        return 'local';
    }

    public static function showEnableAgentCard(int $clientId, bool $ms365OnboardingComplete = false): bool
    {
        if ($clientId <= 0 || self::clientHasLocalAgentEntitlement($clientId)) {
            return false;
        }

        $hasMs365 = self::clientHasMs365Product($clientId);
        $hasStorage = self::clientHasCloudStorageProduct($clientId);

        if ($hasStorage && !$hasMs365) {
            return true;
        }

        return $hasMs365 && $ms365OnboardingComplete;
    }

    public static function showEnableMs365Card(int $clientId, bool $e3OnboardingComplete = false): bool
    {
        if ($clientId <= 0 || self::clientHasMs365Product($clientId)) {
            return false;
        }

        $hasAgent = self::clientHasLocalAgentEntitlement($clientId);
        $hasStorage = self::clientHasCloudStorageProduct($clientId);

        if ($hasStorage && !$hasAgent) {
            return true;
        }

        return $hasAgent && $e3OnboardingComplete;
    }

    /**
     * @return array{view: string, user_id: string, intent: string}
     */
    private static function resolveUnifiedLanding(int $clientId): array
    {
        $dashboard = ['view' => 'dashboard', 'user_id' => '', 'intent' => ''];

        if (!self::clientCanAccessE3BackupShell($clientId)) {
            return $dashboard;
        }

        $defaultBu = E3BackupAccess::defaultBackupUser($clientId);
        if (!$defaultBu) {
            return ['view' => 'getting_started', 'user_id' => '', 'intent' => 'local'];
        }

        $routeUserId = ($defaultBu['public_id'] ?? '') !== ''
            ? (string) $defaultBu['public_id']
            : (string) $defaultBu['id'];
        $backupUserId = (int) $defaultBu['id'];

        $ms365Incomplete = false;
        $localIncomplete = false;

        $ms365Autoload = dirname(__DIR__, 3) . '/ms365backup/ms365backup_autoload.php';
        if (is_file($ms365Autoload)) {
            require_once $ms365Autoload;
        }
        if (class_exists('\\Ms365Backup\\Ms365Onboarding')) {
            try {
                $msOb = \Ms365Backup\Ms365Onboarding::computeForBackupUser($clientId, $backupUserId);
                $ms365Incomplete = empty($msOb['all_complete']);
            } catch (\Throwable $_) {
                $ms365Incomplete = true;
            }
        }

        if (class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Client\\OnboardingState')) {
            try {
                $obState = OnboardingState::compute($clientId);
                $localIncomplete = empty($obState['all_complete']);
            } catch (\Throwable $_) {
                $localIncomplete = true;
            }
        } else {
            $obPath = __DIR__ . '/OnboardingState.php';
            if (is_file($obPath)) {
                require_once $obPath;
                try {
                    $obState = OnboardingState::compute($clientId);
                    $localIncomplete = empty($obState['all_complete']);
                } catch (\Throwable $_) {
                    $localIncomplete = true;
                }
            }
        }

        if (!$ms365Incomplete && !$localIncomplete) {
            return $dashboard;
        }

        // MS365-only / legacy MS365 clients are not blocked by incomplete local-agent
        // onboarding (download, agent, local job) when their M365 setup is complete.
        if (!$ms365Incomplete) {
            if (E3BackupAccess::clientIsMs365Only($clientId)) {
                return $dashboard;
            }
            if (!self::clientHasLocalAgentEntitlement($clientId) && self::clientHasMs365Product($clientId)) {
                return $dashboard;
            }
            if (self::preferredWorkloadIntent($clientId) === 'ms365') {
                return $dashboard;
            }
        }

        $intent = self::resolveGettingStartedIntent($clientId, $localIncomplete, $ms365Incomplete);

        return [
            'view' => 'getting_started',
            'user_id' => $routeUserId,
            'intent' => $intent,
        ];
    }

    private static function isUnifiedEnabled(): bool
    {
        $bootstrapPath = dirname(__DIR__) . '/Provision/E3BackupUserProductBootstrap.php';
        if (is_file($bootstrapPath)) {
            require_once $bootstrapPath;
        }

        return class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Provision\\E3BackupUserProductBootstrap')
            && \WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::isUnifiedEnabled();
    }
}
