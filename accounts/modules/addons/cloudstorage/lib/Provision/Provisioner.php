<?php

namespace WHMCS\Module\Addon\CloudStorage\Provision;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365StorageBootstrapService;

class Provisioner
{
    private static function ensureCometLib(): void
    {
        // Load Comet server module helpers to build a Server client from a product PID
        if (!function_exists('comet_ProductParams')) {
            $path = dirname(__DIR__, 3) . '/../servers/comet/functions.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
        // Ensure SDK autoloaded (functions.php already requires vendor/autoload.php)
    }

    private static function cometUsernameExists(string $username, int $pid): bool
    {
        try {
            self::ensureCometLib();
            if (!function_exists('comet_ProductParams') || !function_exists('comet_Server')) {
                // If Comet helpers are not available, skip preflight
                try { logModuleCall('cloudstorage', 'comet_preflight_helpers_missing', ['pid' => $pid], []); } catch (\Throwable $_) {}
                return false;
            }
            // Build server connection params from product PID
            $serverParams = \comet_ProductParams($pid);
            // Instantiate Comet Server client
            $server = \comet_Server($serverParams);
            // Query profile; success means user exists (username taken)
            $server->AdminGetUserProfile($username);
            return true;
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            $msg  = strtolower($e->getMessage() ?? '');
            // 404 = not found => username is available
            if ($code === 404 || strpos($msg, '404') !== false) {
                return false;
            }
            // Any other error: log and treat as unknown (do not block order)
            try { logModuleCall('cloudstorage', 'comet_preflight_exception', ['pid' => $pid, 'username' => $username], ['code' => $code, 'message' => $e->getMessage()]); } catch (\Throwable $_) {}
            return false;
        } catch (\Throwable $t) {
            // Non-\Exception throwables - skip preflight
            try { logModuleCall('cloudstorage', 'comet_preflight_throwable', ['pid' => $pid, 'username' => $username], $t->getMessage()); } catch (\Throwable $_) {}
            return false;
        }
    }

    public static function getSetting(string $key, $default = null)
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
     * Ensure the client has an active Cloud Storage service.
     *
     * Idempotent: if an active service already exists, it returns success without creating anything.
     * When autoOrder=true and no active service exists, it creates + accepts an order and verifies
     * that an Active service record exists before returning success.
     */
    public static function ensureCloudStorageProductActive(int $clientId, bool $autoOrder = true): array
    {
        $pid = (int) self::getSetting('pid_cloud_storage', 0);
        if ($pid <= 0) {
            return [
                'status' => 'fail',
                'message' => 'Cloud Storage Product PID is not configured.',
            ];
        }

        // Fully provisioned = active service AND non-empty tblhosting.username
        // (which is the tenant$uid we hand off to RGW). An "active" row with
        // an empty username is what the e3 Cloud Backup signup used to leave
        // behind because the old AcceptOrder call passed no serviceusername
        // and did not create the matching RGW user / s3_users row. We now
        // treat that state as "needs provisioning" and let the full
        // provisionCloudStorage() flow backfill the missing pieces.
        $active = DBController::getActiveProduct($clientId, $pid);
        $fullyProvisioned = $active && !empty($active->username);
        if ($fullyProvisioned) {
            return [
                'status' => 'success',
                'service_id' => (int) ($active->id ?? 0),
                'already_active' => true,
                'ordered' => false,
            ];
        }

        if (!$autoOrder) {
            return [
                'status' => 'fail',
                'message' => 'Cloud Storage service is not fully provisioned for this account.',
            ];
        }

        $lockName = 'cloudstorage:ensure_active:' . $clientId;
        $lockAcquired = self::acquireNamedDbLock($lockName, 15);

        try {
            // Re-check under lock so concurrent provisioning attempts don't
            // double-order or both try to backfill the same row.
            $activeUnderLock = DBController::getActiveProduct($clientId, $pid);
            if ($activeUnderLock && !empty($activeUnderLock->username)) {
                return [
                    'status' => 'success',
                    'service_id' => (int) ($activeUnderLock->id ?? 0),
                    'already_active' => true,
                    'ordered' => false,
                ];
            }

            // Delegate to provisionCloudStorage() so we get the full flow:
            //   - tenant + ceph_uid derivation
            //   - AddOrder + AcceptOrder (skipped when an active row already
            //     exists with empty username - repair path)
            //   - tblhosting.username writeback
            //   - AdminOps::createUser + s3_users insert
            //   - trial quota
            // This unifies the e3-Cloud-Backup signup path with the
            // standalone storage-signup path so both produce identical
            // tblhosting / s3_users / RGW state.
            self::provisionCloudStorage($clientId);

            $activeAfter = DBController::getActiveProduct($clientId, $pid);
            if (!$activeAfter || empty($activeAfter->username)) {
                return [
                    'status' => 'fail',
                    'message' => 'Cloud Storage provisioning ran but did not produce a fully-provisioned service.',
                ];
            }

            return [
                'status' => 'success',
                'service_id' => (int) ($activeAfter->id ?? 0),
                'already_active' => $active !== null,
                'ordered' => $active === null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'message' => 'Failed to ensure Cloud Storage provisioning: ' . $e->getMessage(),
            ];
        } finally {
            if ($lockAcquired) {
                self::releaseNamedDbLock($lockName);
            }
        }
    }

    /**
     * Best-effort named DB lock using MySQL GET_LOCK.
     */
    private static function acquireNamedDbLock(string $name, int $timeoutSeconds = 10): bool
    {
        try {
            $row = Capsule::select('SELECT GET_LOCK(?, ?) AS l', [$name, $timeoutSeconds]);
            if (is_array($row) && isset($row[0]) && isset($row[0]->l)) {
                return ((int) $row[0]->l) === 1;
            }
        } catch (\Throwable $e) {
            // Continue without lock on unsupported DB engines.
        }
        return false;
    }

    private static function releaseNamedDbLock(string $name): void
    {
        try {
            Capsule::select('SELECT RELEASE_LOCK(?) AS l', [$name]);
        } catch (\Throwable $e) {
            // no-op
        }
    }

    /**
     * Apply default config options (qty=1) for a newly provisioned service.
     *
     * This ensures trial orders get the correct initial quantities for:
     * - Device config option (ID 67)
     * - Cloud Storage config option (ID 88)
     *
     * The mapping is consistent with the eazybackup module's logic.
     *
     * @param int $serviceId The WHMCS service ID (tblhosting.id)
     * @param int $pid       The WHMCS product ID
     */
    private static function applyDefaultConfigOptions(int $serviceId, int $pid): void
    {
        // First, try to use the eazybackup module's function if it exists (for consistency)
        if (function_exists('eazybackup_apply_default_config_options')) {
            try {
                \eazybackup_apply_default_config_options($serviceId, $pid);
                try { logModuleCall('cloudstorage', 'apply_config_options_via_eazybackup', ['serviceId' => $serviceId, 'pid' => $pid], 'Used eazybackup function'); } catch (\Throwable $_) {}
                return;
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'apply_config_options_eazybackup_failed', ['serviceId' => $serviceId, 'pid' => $pid], $e->getMessage()); } catch (\Throwable $_) {}
                // Fall through to inline implementation
            }
        }

        // Map of product id => array of config option ids to set qty=1
        // These are the standard eazyBackup/OBC config options:
        // - 67: Device (Protected Item)
        // - 88: Cloud Storage (GB)
        $map = [
            58 => [67, 88], // eazyBackup
            60 => [67, 88], // OBC (Office Backup Cloud)
        ];

        // If this PID isn't in the map, check if it should inherit the default config options.
        // This handles cases where pid_cloud_backup is set to a different product ID.
        if (!isset($map[$pid])) {
            // For Cloud Backup products not in the hardcoded map, apply the same defaults
            // as eazyBackup (PID 58) since they share the same config option structure.
            $configuredBackupPid = (int) self::getSetting('pid_cloud_backup', 0);
            if ($pid === $configuredBackupPid && $configuredBackupPid > 0) {
                // Use the same config options as eazyBackup
                $map[$pid] = [67, 88];
            }
        }

        if (!isset($map[$pid])) {
            try { logModuleCall('cloudstorage', 'apply_config_options_skip', ['serviceId' => $serviceId, 'pid' => $pid], 'PID not in config options map'); } catch (\Throwable $_) {}
            return;
        }

        foreach ($map[$pid] as $configId) {
            try {
                // Find the first sub-option ID for this config option (the "unit" option)
                $subId = Capsule::table('tblproductconfigoptionssub')
                    ->where('configid', $configId)
                    ->orderBy('sortorder')
                    ->orderBy('id')
                    ->value('id');
                $optionId = $subId ? (int) $subId : (int) $configId; // fallback

                // Upsert the hosting config option row with qty=1
                $exists = Capsule::table('tblhostingconfigoptions')
                    ->where('relid', $serviceId)
                    ->where('configid', $configId)
                    ->exists();

                if ($exists) {
                    Capsule::table('tblhostingconfigoptions')
                        ->where('relid', $serviceId)
                        ->where('configid', $configId)
                        ->update(['optionid' => $optionId, 'qty' => 1]);
                } else {
                    Capsule::table('tblhostingconfigoptions')->insert([
                        'relid'    => $serviceId,
                        'configid' => $configId,
                        'optionid' => $optionId,
                        'qty'      => 1,
                    ]);
                }
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'apply_config_option_fail', ['serviceId' => $serviceId, 'pid' => $pid, 'configId' => $configId], $e->getMessage()); } catch (\Throwable $_) {}
            }
        }

        try { logModuleCall('cloudstorage', 'apply_config_options_success', ['serviceId' => $serviceId, 'pid' => $pid, 'configIds' => $map[$pid]], 'Config options applied'); } catch (\Throwable $_) {}
    }

    public static function provisionCloudBackup(int $clientId, string $username, string $password): string
    {
        $pid = (int) self::getSetting('pid_cloud_backup', 0);
        if ($pid <= 0) {
            // Fall back to configured MS365 pid if left blank? Keep separate – require config.
            throw new \Exception('Cloud Backup Product PID is not configured.');
        }
        // Preflight: ensure username is available before placing an order
        if (self::cometUsernameExists($username, $pid)) {
            throw new \Exception('The username ' . $username . ' is already taken');
        }
        $adminUser = 'API';
        // Create order
        $order = localAPI('AddOrder', [
            'clientid'      => $clientId,
            'pid'           => [$pid],
            'billingcycle'  => ['monthly'],
            'promocode'     => 'trial',
            'paymentmethod' => 'stripe',
            'noinvoice'     => true,
            'noemail'       => true,
        ], $adminUser);
        if (($order['result'] ?? '') !== 'success') {
            throw new \Exception('AddOrder failed: ' . ($order['message'] ?? 'unknown'));
        }
        // Accept with service credentials
        $accept = localAPI('AcceptOrder', [
            'orderid'         => $order['orderid'],
            'autosetup'       => true,
            'sendemail'       => true,
            'serviceusername' => $username,
            'servicepassword' => $password,
        ], $adminUser);
        if (($accept['result'] ?? '') !== 'success') {
            throw new \Exception('AcceptOrder failed: ' . ($accept['message'] ?? 'unknown'));
        }

        $serviceId = (int) ($accept['serviceid'] ?? 0);
        if ($serviceId <= 0 && !empty($order['orderid'])) {
            try {
                $serviceId = (int) Capsule::table('tblhosting')
                    ->where('orderid', (int) $order['orderid'])
                    ->orderBy('id', 'desc')
                    ->value('id');
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'provision_cloud_backup_service_lookup_fail', ['orderid' => $order['orderid']], $e->getMessage()); } catch (\Throwable $_) {}
            }
        }
        if ($serviceId > 0) {
            // Apply default config options (Device qty=1, Cloud Storage qty=1)
            try {
                self::applyDefaultConfigOptions($serviceId, $pid);
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'provision_cloud_backup_config_options_fail', ['serviceid' => $serviceId, 'pid' => $pid], $e->getMessage()); } catch (\Throwable $_) {}
            }

            // Set trial period (14 days)
            try {
                $tz = new \DateTimeZone('America/Toronto');
                $nextDue = new \DateTime('now', $tz);
                $nextDue->add(new \DateInterval('P14D'));
                $formattedDue = $nextDue->format('Y-m-d');
                Capsule::table('tblhosting')
                    ->where('id', $serviceId)
                    ->update([
                        'nextduedate'    => $formattedDue,
                        'nextinvoicedate' => $formattedDue,
                    ]);
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'provision_cloud_backup_next_due_fail', ['serviceid' => $serviceId], $e->getMessage()); } catch (\Throwable $_) {}
            }
        }
        // Redirect to onboarding download page for first-time signup
        return 'index.php?m=eazybackup&a=eazybackup-download';
    }

    public static function provisionMs365(int $clientId, string $username, string $password): string
    {
        $ms365Autoload = dirname(__DIR__, 3) . '/ms365backup/ms365backup_autoload.php';
        if (is_file($ms365Autoload)) {
            require_once $ms365Autoload;
        }

        $pid = class_exists('\\Ms365Backup\\Ms365BillingConfig')
            ? (int) \Ms365Backup\Ms365BillingConfig::getPid()
            : 0;
        if ($pid <= 0) {
            if (class_exists('\\Ms365Backup\\Ms365ProductBootstrap')) {
                \Ms365Backup\Ms365ProductBootstrap::ensure('provision');
                $pid = (int) \Ms365Backup\Ms365BillingConfig::getPid();
            }
        }
        if ($pid <= 0) {
            throw new \Exception('MS365 Backup product is not configured. Activate the ms365backup addon to bootstrap the product.');
        }

        $clean = preg_replace('/[^A-Za-z0-9_.-]+/', '', $username);
        if ($clean === '' || strlen($clean) < 6) {
            throw new \Exception('Backup username must be at least 6 characters and may contain only a-z, A-Z, 0-9, _, ., -');
        }
        $username = $clean;

        $adminUser = 'API';
        try { logModuleCall('cloudstorage', 'ms365_entry', ['clientId' => $clientId, 'username' => $username, 'pid' => $pid], []); } catch (\Throwable $_) {}

        $existingSvc = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('packageid', $pid)
            ->whereIn('domainstatus', ['Active', 'Suspended', 'Pending'])
            ->orderBy('id', 'desc')
            ->first();
        $serviceId = $existingSvc ? (int) $existingSvc->id : 0;

        if ($serviceId <= 0) {
            if (self::ms365ServiceUsernameTakenForClient($clientId, $pid, $username)) {
                throw new \Exception('The username ' . $username . ' is already taken');
            }
            if (self::ms365BackupUserUsernameTaken($clientId, $username)) {
                throw new \Exception('The username ' . $username . ' is already taken');
            }

            $order = localAPI('AddOrder', [
                'clientid'      => $clientId,
                'pid'           => [$pid],
                'billingcycle'  => ['monthly'],
                'paymentmethod' => 'stripe',
                'noinvoice'     => true,
                'noemail'       => true,
            ], $adminUser);
            try { logModuleCall('cloudstorage', 'ms365_addorder_res', ['clientId' => $clientId], $order); } catch (\Throwable $_) {}
            if (($order['result'] ?? '') !== 'success') {
                throw new \Exception('AddOrder failed: ' . ($order['message'] ?? 'unknown'));
            }
            $accept = localAPI('AcceptOrder', [
                'orderid'         => $order['orderid'],
                'autosetup'       => false,
                'sendemail'       => false,
                'serviceusername' => $username,
                'servicepassword' => $password,
            ], $adminUser);
            try { logModuleCall('cloudstorage', 'ms365_accept_res', ['orderid' => $order['orderid'] ?? null], $accept); } catch (\Throwable $_) {}
            if (($accept['result'] ?? '') !== 'success') {
                throw new \Exception('AcceptOrder failed: ' . ($accept['message'] ?? 'unknown'));
            }
            $serviceId = (int) ($accept['serviceid'] ?? 0);
            if ($serviceId <= 0 && !empty($order['orderid'])) {
                try {
                    $serviceId = (int) Capsule::table('tblhosting')
                        ->where('orderid', (int) $order['orderid'])
                        ->orderBy('id', 'desc')
                        ->value('id');
                } catch (\Throwable $_) {}
            }
        }

        if ($serviceId <= 0) {
            throw new \Exception('MS365 Backup service could not be resolved after provisioning.');
        }

        $backupUserId = self::ensureMs365DefaultBackupUser($clientId, $username, $password);
        if ($backupUserId <= 0) {
            throw new \Exception('Failed to create backup user for Microsoft 365 Backup.');
        }

        self::finalizeMs365BackupUserService($serviceId, $clientId, $backupUserId);

        if (class_exists('\\Ms365Backup\\Ms365BillingTrial')) {
            try {
                \Ms365Backup\Ms365BillingTrial::startTrial($serviceId, $clientId);
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'ms365_start_trial_fail', ['service_id' => $serviceId], $e->getMessage()); } catch (\Throwable $_) {}
            }
        }

        try {
            $trialDays = class_exists('\\Ms365Backup\\Ms365BillingConfig')
                ? (int) \Ms365Backup\Ms365BillingConfig::trialDays()
                : 30;
            if ($trialDays <= 0) {
                $trialDays = 30;
            }
            $tz = new \DateTimeZone('America/Toronto');
            $nextDue = new \DateTime('now', $tz);
            $nextDue->add(new \DateInterval('P' . $trialDays . 'D'));
            $formattedDue = $nextDue->format('Y-m-d');
            Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->update([
                    'amount'          => 0.00,
                    'nextduedate'     => $formattedDue,
                    'nextinvoicedate' => $formattedDue,
                    'domainstatus'    => 'Active',
                ]);
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'ms365_anchor_due_fail', ['service_id' => $serviceId], $e->getMessage()); } catch (\Throwable $_) {}
        }

        $bucketRes = Ms365StorageBootstrapService::ensureForBackupUser($clientId, $backupUserId);
        if (($bucketRes['status'] ?? '') !== 'success') {
            $msg = (string) ($bucketRes['message'] ?? 'Failed to provision MS365 backup storage.');
            try { logModuleCall('cloudstorage', 'ms365_bucket_bootstrap_fail', ['clientId' => $clientId, 'backupUserId' => $backupUserId], $msg); } catch (\Throwable $_) {}
            throw new \Exception($msg);
        }
        try { logModuleCall('cloudstorage', 'ms365_bucket_bootstrap_ok', ['clientId' => $clientId, 'backupUserId' => $backupUserId], $bucketRes); } catch (\Throwable $_) {}

        return 'index.php?m=cloudstorage&page=e3backup&view=ms365_getting_started&serviceid=' . $serviceId;
    }

    /**
     * Ensure a WHMCS MS365 Backup service exists for one backup user (MSP child).
     * Called at first MS365 job creation. No trial; first bill due +1 month.
     *
     * @return int Service id or 0 on failure
     */
    public static function ensureMs365ServiceForBackupUser(int $clientId, int $backupUserId): int
    {
        if ($clientId <= 0 || $backupUserId <= 0) {
            return 0;
        }

        $ms365Autoload = dirname(__DIR__, 3) . '/ms365backup/ms365backup_autoload.php';
        if (is_file($ms365Autoload)) {
            require_once $ms365Autoload;
        }

        $pid = class_exists('\\Ms365Backup\\Ms365BillingConfig')
            ? (int) \Ms365Backup\Ms365BillingConfig::getPid()
            : 0;
        if ($pid <= 0 && class_exists('\\Ms365Backup\\Ms365ProductBootstrap')) {
            \Ms365Backup\Ms365ProductBootstrap::ensure('ensure_backup_user');
            $pid = (int) \Ms365Backup\Ms365BillingConfig::getPid();
        }
        if ($pid <= 0) {
            try {
                logModuleCall('cloudstorage', 'ms365_ensure_no_pid', ['clientId' => $clientId, 'backupUserId' => $backupUserId], '');
            } catch (\Throwable $_) {
            }

            return 0;
        }

        $backupUser = Capsule::table('s3_backup_users')
            ->where('id', $backupUserId)
            ->where('client_id', $clientId)
            ->first(['id', 'username']);
        if (!$backupUser || trim((string) ($backupUser->username ?? '')) === '') {
            try {
                logModuleCall('cloudstorage', 'ms365_ensure_no_backup_user', ['clientId' => $clientId, 'backupUserId' => $backupUserId], '');
            } catch (\Throwable $_) {
            }

            return 0;
        }
        $username = trim((string) $backupUser->username);

        $existingServiceId = 0;
        if (class_exists('\\Ms365Backup\\Ms365BillingService')) {
            $existingServiceId = \Ms365Backup\Ms365BillingService::resolveServiceIdForBackupUser($clientId, $backupUserId);
        }
        if ($existingServiceId <= 0) {
            try {
                $bound = Capsule::table('ms365_tenant_records')
                    ->where('whmcs_client_id', $clientId)
                    ->where('backup_user_id', $backupUserId)
                    ->where('is_active', 1)
                    ->where('whmcs_service_id', '>', 0)
                    ->orderByDesc('id')
                    ->value('whmcs_service_id');
                if ($bound) {
                    $existingServiceId = (int) $bound;
                }
            } catch (\Throwable $_) {
            }
        }
        if ($existingServiceId <= 0) {
            $svcRow = Capsule::table('tblhosting')
                ->where('userid', $clientId)
                ->where('packageid', $pid)
                ->where('username', $username)
                ->whereIn('domainstatus', ['Active', 'Suspended', 'Pending'])
                ->orderByDesc('id')
                ->first();
            $existingServiceId = $svcRow ? (int) $svcRow->id : 0;
        }

        if ($existingServiceId > 0) {
            self::finalizeMs365BackupUserService($existingServiceId, $clientId, $backupUserId);

            return $existingServiceId;
        }

        if (self::ms365ServiceUsernameTakenForClient($clientId, $pid, $username)) {
            try {
                logModuleCall('cloudstorage', 'ms365_ensure_username_taken', [
                    'clientId' => $clientId,
                    'backupUserId' => $backupUserId,
                    'username' => $username,
                ], '');
            } catch (\Throwable $_) {
            }

            return 0;
        }

        $adminUser = 'API';
        $servicePassword = bin2hex(random_bytes(16));
        try {
            $order = localAPI('AddOrder', [
                'clientid' => $clientId,
                'pid' => [$pid],
                'billingcycle' => ['monthly'],
                'paymentmethod' => 'stripe',
                'noinvoice' => true,
                'noemail' => true,
            ], $adminUser);
            if (($order['result'] ?? '') !== 'success') {
                throw new \Exception('AddOrder failed: ' . ($order['message'] ?? 'unknown'));
            }
            $accept = localAPI('AcceptOrder', [
                'orderid' => $order['orderid'],
                'autosetup' => false,
                'sendemail' => false,
                'serviceusername' => $username,
                'servicepassword' => $servicePassword,
            ], $adminUser);
            if (($accept['result'] ?? '') !== 'success') {
                throw new \Exception('AcceptOrder failed: ' . ($accept['message'] ?? 'unknown'));
            }
            $serviceId = (int) ($accept['serviceid'] ?? 0);
            if ($serviceId <= 0 && !empty($order['orderid'])) {
                $serviceId = (int) Capsule::table('tblhosting')
                    ->where('orderid', (int) $order['orderid'])
                    ->orderByDesc('id')
                    ->value('id');
            }
            if ($serviceId <= 0) {
                throw new \Exception('MS365 service could not be resolved after AcceptOrder');
            }

            self::anchorMs365ServiceBillingDates($serviceId);
            self::finalizeMs365BackupUserService($serviceId, $clientId, $backupUserId);

            try {
                logModuleCall('cloudstorage', 'ms365_ensure_service_created', [
                    'clientId' => $clientId,
                    'backupUserId' => $backupUserId,
                    'serviceId' => $serviceId,
                    'username' => $username,
                ], 'ok');
            } catch (\Throwable $_) {
            }

            return $serviceId;
        } catch (\Throwable $e) {
            try {
                logModuleCall('cloudstorage', 'ms365_ensure_service_fail', [
                    'clientId' => $clientId,
                    'backupUserId' => $backupUserId,
                ], $e->getMessage());
            } catch (\Throwable $_) {
            }

            return 0;
        }
    }

    private static function ms365ServiceUsernameTakenForClient(int $clientId, int $pid, string $username, int $excludeServiceId = 0): bool
    {
        $q = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('packageid', $pid)
            ->where('username', $username)
            ->whereIn('domainstatus', ['Active', 'Suspended', 'Pending']);
        if ($excludeServiceId > 0) {
            $q->where('id', '!=', $excludeServiceId);
        }

        return $q->exists();
    }

    private static function anchorMs365ServiceBillingDates(int $serviceId): void
    {
        try {
            $tz = new \DateTimeZone('America/Toronto');
            $nextDue = new \DateTime('now', $tz);
            $nextDue->add(new \DateInterval('P1M'));
            $formatted = $nextDue->format('Y-m-d');
            Capsule::table('tblhosting')->where('id', $serviceId)->update([
                'amount' => 0.00,
                'nextduedate' => $formatted,
                'nextinvoicedate' => $formatted,
                'domainstatus' => 'Active',
            ]);
        } catch (\Throwable $e) {
            try {
                logModuleCall('cloudstorage', 'ms365_anchor_due_fail', ['service_id' => $serviceId], $e->getMessage());
            } catch (\Throwable $_) {
            }
        }
    }

    private static function finalizeMs365BackupUserService(int $serviceId, int $clientId, int $backupUserId): void
    {
        if ($serviceId <= 0) {
            return;
        }
        if (class_exists('\\Ms365Backup\\Ms365BillingService')) {
            try {
                \Ms365Backup\Ms365BillingService::applyDefaultConfigOptions($serviceId);
            } catch (\Throwable $_) {
            }
            try {
                \Ms365Backup\Ms365BillingService::linkServiceToBackupUser($clientId, $backupUserId, $serviceId);
            } catch (\Throwable $_) {
            }
        }
    }

    public static function provisionCloudStorage(int $clientId): string
    {
        $pid = (int) self::getSetting('pid_cloud_storage', 0);
        if ($pid <= 0) {
            // If not configured, simply send user to dashboard; assume product already exists or not required at this step
            try { logModuleCall('cloudstorage', 'provision_storage_skip_pid', ['clientId' => $clientId], ''); } catch (\Throwable $e) {}
            return 'index.php?m=cloudstorage&page=dashboard';
        }
        $adminUser = 'API';
        // Detect an existing Active service for this client + product.
        //
        // We deliberately DO NOT short-circuit on "active row exists" alone -
        // historically that caused half-provisioned services to stay broken
        // forever (tblhosting.username empty, no s3_users row, no RGW user).
        // Instead we only short-circuit when the service is *fully*
        // provisioned (active + non-empty username). Otherwise we re-enter
        // the provisioning flow and treat AddOrder as a no-op.
        $existingService = null;
        try {
            $existingService = Capsule::table('tblhosting')
                ->where('userid', $clientId)
                ->where('packageid', $pid)
                ->where('domainstatus', 'Active')
                ->orderBy('id', 'desc')
                ->first();
        } catch (\Throwable $e) {}
        if ($existingService && !empty($existingService->username)) {
            try { logModuleCall('cloudstorage', 'provision_storage_already_active', ['clientId' => $clientId, 'pid' => $pid, 'serviceid' => (int) $existingService->id], ''); } catch (\Throwable $e) {}
            return 'index.php?m=cloudstorage&page=dashboard';
        }
        if ($existingService) {
            try { logModuleCall('cloudstorage', 'provision_storage_repair_unprovisioned', ['clientId' => $clientId, 'pid' => $pid, 'serviceid' => (int) $existingService->id], 'Active service has empty username - running provisioning to backfill'); } catch (\Throwable $e) {}
        }
        // Compute service username and RGW uid from email (no '@' or '.' in RGW uid).
        // The username is derived from the customer's email with '@' and '.' stripped.
        // Example: newuser@mycompany.com → newusermycompanycom
        $email = '';
        try { $email = (string) Capsule::table('tblclients')->where('id', $clientId)->value('email'); } catch (\Throwable $e) { $email = ''; }
        $legacyUsername = preg_replace('/[^a-z0-9._@-]+/', '', strtolower($email));
        if ($legacyUsername === '') { $legacyUsername = 'e3user' . $clientId; }

        $baseUsername = '';
        try {
            if (Capsule::schema()->hasTable('cloudstorage_trial_verifications')) {
                $trialRow = Capsule::table('cloudstorage_trial_verifications')
                    ->where('client_id', $clientId)
                    ->orderBy('id', 'desc')
                    ->first();
                if ($trialRow && !empty($trialRow->meta)) {
                    $meta = json_decode($trialRow->meta, true);
                    if (is_array($meta)) {
                        $baseUsername = (string) ($meta['username'] ?? '');
                    }
                }
            }
        } catch (\Throwable $e) {}
        $baseUsername = preg_replace('/[^a-z0-9-]+/', '', strtolower($baseUsername));
        if ($baseUsername === '') {
            // Derive from full email with '@' and '.' stripped (e.g. user@example.com → userexamplecom)
            $baseUsername = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::sanitizeEmailForUsername($email);
        }
        if ($baseUsername === '') {
            $baseUsername = 'e3user' . $clientId;
        }

        $tenantId = '';
        $cephBaseUid = '';
        $existingUser = null;
        try {
            // Search including inactive/deactivated users so we can reactivate instead of
            // creating duplicates when a customer cancels and re-signs up.
            $existingUser = \WHMCS\Module\Addon\CloudStorage\Client\DBController::getUser($legacyUsername, false);
            if (!$existingUser) {
                $existingUser = \WHMCS\Module\Addon\CloudStorage\Client\DBController::getUser($baseUsername, false);
            }
        } catch (\Throwable $e) {}
        if ($existingUser) {
            $tenantId = (string) ($existingUser->tenant_id ?? '');
            $cephBaseUid = (string) (\WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($existingUser));
            if ($cephBaseUid === '') {
                $cephBaseUid = $baseUsername;
            }
        } else {
            $tenantId = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::getUniqueTenantId();
            $cephBaseUid = $baseUsername;
        }

        // Safety net: always strip '@' and '.' from the uid regardless of source
        // (existing user, trial meta, or email fallback) to ensure clean RGW uids.
        $cephBaseUid = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::sanitizeEmailForUsername($cephBaseUid);
        if ($cephBaseUid === '') {
            $cephBaseUid = $baseUsername;
        }

        $serviceUsername = $tenantId !== '' ? ($tenantId . '$' . $cephBaseUid) : $cephBaseUid;

        try { logModuleCall('cloudstorage', 'provision_storage_begin', ['clientId' => $clientId, 'pid' => $pid, 'serviceUsername' => $serviceUsername, 'baseUsername' => $baseUsername, 'tenant' => $tenantId, 'repair' => $existingService !== null], ''); } catch (\Throwable $e) {}

        $serviceId = 0;
        $order = null;
        if ($existingService) {
            // Repair path: skip AddOrder/AcceptOrder, but still run the rest
            // of the provisioning (username writeback, AdminOps user, s3_users
            // insert, quota setup) so the empty-username service becomes fully
            // usable.
            $serviceId = (int) $existingService->id;
        } else {
            $order = localAPI('AddOrder', [
                'clientid'      => $clientId,
                'pid'           => [$pid],
                'billingcycle'  => ['monthly'],
                'paymentmethod' => 'stripe',
                'noinvoice'     => true,
                'noemail'       => true,
            ], $adminUser);
            if (($order['result'] ?? '') !== 'success') {
                // Even if order fails, route to dashboard; Welcome page will allow retry
                try { logModuleCall('cloudstorage', 'provision_storage_addorder_fail', ['clientId' => $clientId, 'pid' => $pid], $order); } catch (\Throwable $e) {}
                return 'index.php?m=cloudstorage&page=dashboard';
            }
            try { logModuleCall('cloudstorage', 'provision_storage_addorder_ok', ['orderid' => $order['orderid'] ?? null], $order); } catch (\Throwable $e) {}
            $accept = localAPI('AcceptOrder', [
                'orderid'   => $order['orderid'],
                'autosetup' => true,
                'sendemail' => true,
                'serviceusername' => $serviceUsername,
            ], $adminUser);
            try { logModuleCall('cloudstorage', 'provision_storage_accept', ['orderid' => $order['orderid'] ?? null], $accept); } catch (\Throwable $e) {}
            if (($accept['result'] ?? '') !== 'success') {
                // If WHMCS couldn't accept/provision the order, do not proceed with date overrides or AdminOps calls.
                try { logModuleCall('cloudstorage', 'provision_storage_accept_fail', ['orderid' => $order['orderid'] ?? null, 'clientId' => $clientId, 'pid' => $pid], $accept); } catch (\Throwable $e) {}
                return 'index.php?m=cloudstorage&page=dashboard';
            }

            // Enforce a 30-day free trial window by pushing next due/invoice date 30 days out.
            // This keeps WHMCS automation from generating an invoice until day 31.
            $serviceId = (int) ($accept['serviceid'] ?? 0);
            if ($serviceId <= 0) {
                try {
                    $serviceId = (int) Capsule::table('tblhosting')
                        ->where('orderid', (int)($order['orderid'] ?? 0))
                        ->where('userid', $clientId)
                        ->where('packageid', $pid)
                        ->orderBy('id', 'desc')
                        ->value('id');
                } catch (\Throwable $e) {
                    try { logModuleCall('cloudstorage', 'provision_storage_service_lookup_fail', ['orderid' => $order['orderid'] ?? null, 'clientId' => $clientId, 'pid' => $pid], $e->getMessage()); } catch (\Throwable $_) {}
                }
            }
        }
        if ($serviceId > 0) {
            try {
                $tz = new \DateTimeZone('America/Toronto');
                $nextDue = new \DateTime('now', $tz);
                $nextDue->add(new \DateInterval('P30D'));
                $formattedDue = $nextDue->format('Y-m-d');
                // Ensure WHMCS service username matches RGW (tenant-qualified uid).
                try {
                    $upd = localAPI('UpdateClientProduct', [
                        'serviceid'       => $serviceId,
                        'serviceusername' => $serviceUsername,
                    ], $adminUser);
                    try { logModuleCall('cloudstorage', 'provision_storage_update_service_username', ['serviceid' => $serviceId, 'username' => $serviceUsername], $upd); } catch (\Throwable $_) {}
                } catch (\Throwable $e) {
                    try { logModuleCall('cloudstorage', 'provision_storage_update_service_username_fail', ['serviceid' => $serviceId, 'username' => $serviceUsername], $e->getMessage()); } catch (\Throwable $_) {}
                }
                // For fresh orders, anchor the trial window 30 days out.
                // For repair runs on an existing service we ONLY backfill the
                // username (the trial dates were set the first time the
                // service was created; rewriting them would shift the
                // customer's billing schedule forward).
                $hostingUpdate = ['username' => $serviceUsername];
                if (!$existingService) {
                    $hostingUpdate['nextduedate']     = $formattedDue;
                    $hostingUpdate['nextinvoicedate'] = $formattedDue;
                }
                Capsule::table('tblhosting')
                    ->where('id', $serviceId)
                    ->update($hostingUpdate);
                try { logModuleCall('cloudstorage', 'provision_storage_service_username_update', ['serviceid' => $serviceId, 'username' => $serviceUsername, 'repair' => $existingService !== null], 'updated'); } catch (\Throwable $_) {}
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'provision_storage_next_due_fail', ['serviceid' => $serviceId, 'clientId' => $clientId, 'pid' => $pid], $e->getMessage()); } catch (\Throwable $_) {}
            }
        } else {
            try { logModuleCall('cloudstorage', 'provision_storage_service_missing_for_next_due', ['orderid' => isset($order['orderid']) ? $order['orderid'] : null, 'clientId' => $clientId, 'pid' => $pid], ''); } catch (\Throwable $e) {}
        }

        // If module create didn't also create Ceph user, create via AdminOps now.
        // Option B: do NOT persist initial auto-generated keys; user must explicitly create their first key.
        try {
            $endpoint   = (string) self::getSetting('s3_endpoint', '');
            $accessKey  = (string) self::getSetting('ceph_access_key', '');
            $secretKey  = (string) self::getSetting('ceph_secret_key', '');
            $encKey     = (string) self::getSetting('encryption_key', '');
            if ($endpoint && $accessKey && $secretKey) {
                // Check for existing RGW user (try base uid first, then legacy email uid)
                $info = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::getUserInfo($endpoint, $accessKey, $secretKey, $cephBaseUid, $tenantId ?: null);
                try { logModuleCall('cloudstorage', 'provision_storage_adminops_get_user', ['u' => $serviceUsername, 'ceph_uid' => $cephBaseUid, 'tenant' => $tenantId], $info); } catch (\Throwable $e) {}
                if (!is_array($info) || ($info['status'] ?? '') !== 'success') {
                    $legacyInfo = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::getUserInfo($endpoint, $accessKey, $secretKey, $legacyUsername, $tenantId ?: null);
                    try { logModuleCall('cloudstorage', 'provision_storage_adminops_get_user_legacy', ['u' => $serviceUsername, 'tenant' => $tenantId], $legacyInfo); } catch (\Throwable $e) {}
                    if (is_array($legacyInfo) && ($legacyInfo['status'] ?? '') === 'success') {
                        // Legacy RGW uid is the email itself; sanitize to strip '@' and '.' for
                        // consistent tenant$username format (e.g. 147617887552$newusermycompanycom).
                        $cephBaseUid = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::sanitizeEmailForUsername($legacyUsername);
                        if ($cephBaseUid === '') {
                            $cephBaseUid = $baseUsername;
                        }
                        $serviceUsername = $tenantId !== '' ? ($tenantId . '$' . $cephBaseUid) : $cephBaseUid;
                    }
                }

                if (!is_array($info) || ($info['status'] ?? '') !== 'success') {
                    // Final safety net: ensure uid is free of '@' and '.' before creating Ceph user
                    $cephBaseUid = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::sanitizeEmailForUsername($cephBaseUid);
                    if ($cephBaseUid === '') { $cephBaseUid = $baseUsername; }
                    $serviceUsername = $tenantId !== '' ? ($tenantId . '$' . $cephBaseUid) : $cephBaseUid;

                    $params = [
                        'uid' => $cephBaseUid,
                        'name' => $serviceUsername,
                        'email' => $serviceUsername,
                        'tenant' => $tenantId
                    ];
                    $create = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::createUser($endpoint, $accessKey, $secretKey, $params);
                    try { logModuleCall('cloudstorage', 'provision_storage_adminops_create_user', $params, $create); } catch (\Throwable $e) {}
                    if (is_array($create) && ($create['status'] ?? '') === 'success') {
                        try {
                            if ($existingUser && isset($existingUser->id)) {
                                // Re-provisioning: reactivate the existing s3_users row instead of
                                // creating a duplicate. This handles the cancel→re-signup scenario.
                                $s3UserId = (int) $existingUser->id;
                                Capsule::table('s3_users')->where('id', $s3UserId)->update([
                                    'username'   => $serviceUsername,
                                    'ceph_uid'   => $cephBaseUid,
                                    'tenant_id'  => $tenantId,
                                    'is_active'  => 1,
                                    'deleted_at' => null,
                                ]);
                                // Purge stale access keys from the previous subscription so the
                                // customer starts fresh (they must create a new key pair).
                                try {
                                    Capsule::table('s3_user_access_keys')->where('user_id', $s3UserId)->delete();
                                } catch (\Throwable $_) {}
                                try { logModuleCall('cloudstorage', 'provision_storage_reactivate_user', ['id' => $s3UserId, 'username' => $serviceUsername, 'ceph_uid' => $cephBaseUid, 'tenant' => $tenantId], 'Reactivated existing s3_users row and purged stale keys'); } catch (\Throwable $_) {}
                            } else {
                                // Brand-new user — insert a fresh row.
                                $s3UserId = \WHMCS\Module\Addon\CloudStorage\Client\DBController::saveUser([
                                    'username'  => $serviceUsername,
                                    'ceph_uid'  => $cephBaseUid,
                                    'tenant_id' => $tenantId,
                                ]);
                                try { logModuleCall('cloudstorage', 'provision_storage_save_user', ['username' => $serviceUsername, 'ceph_uid' => $cephBaseUid, 'tenant' => $tenantId], $s3UserId); } catch (\Throwable $_) {}
                            }
                            // Option B: revoke the auto-generated initial key(s) so there are no unseen/ghost credentials.
                            // The user will create their first keypair explicitly from the Access Keys page.
                            $keys = $create['data']['keys'] ?? [];
                            if (is_array($keys) && count($keys) > 0) {
                                $cephUid = $tenantId ? ($tenantId . '$' . $cephBaseUid) : $cephBaseUid;
                                foreach ($keys as $k) {
                                    $ak = is_array($k) ? ($k['access_key'] ?? '') : '';
                                    if (!empty($ak)) {
                                        try {
                                            $rm = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::removeKey($endpoint, $accessKey, $secretKey, $ak, $cephUid);
                                            try { logModuleCall('cloudstorage', 'provision_storage_remove_autokey', ['u' => $serviceUsername, 'cephUid' => $cephUid, 'ceph_uid' => $cephBaseUid, 'tenant' => $tenantId], $rm); } catch (\Throwable $_) {}
                                        } catch (\Throwable $e) {
                                            try { logModuleCall('cloudstorage', 'provision_storage_remove_autokey_exception', ['u' => $serviceUsername, 'cephUid' => $cephUid], $e->getMessage()); } catch (\Throwable $_) {}
                                        }
                                    }
                                }
                            }
                            try { logModuleCall('cloudstorage', 'provision_storage_option_b_keys_skipped', ['u' => $serviceUsername, 's3_user_id' => $s3UserId], 'No initial keys persisted (Option B)'); } catch (\Throwable $e) {}
                        } catch (\Throwable $e) {
                            try { logModuleCall('cloudstorage', 'provision_storage_db_save_fail', ['u' => $serviceUsername], $e->getMessage()); } catch (\Throwable $_) {}
                        }
                    } else {
                        try { logModuleCall('cloudstorage', 'provision_storage_adminops_create_user_fail', $params, $create); } catch (\Throwable $e) {}
                    }
                } else if ($existingUser && isset($existingUser->id)) {
                    // RGW user already exists — just ensure the s3_users row is aligned and active.
                    try {
                        $updates = [
                            'is_active'  => 1,
                            'deleted_at' => null,
                        ];
                        if (empty($existingUser->ceph_uid)) {
                            $updates['ceph_uid'] = $cephBaseUid;
                        }
                        if (empty($existingUser->tenant_id) && $tenantId !== '') {
                            $updates['tenant_id'] = $tenantId;
                        }
                        if (!empty($serviceUsername) && (string)($existingUser->username ?? '') !== $serviceUsername) {
                            $updates['username'] = $serviceUsername;
                        }
                        Capsule::table('s3_users')->where('id', (int)$existingUser->id)->update($updates);
                        try { logModuleCall('cloudstorage', 'provision_storage_sync_s3_user', ['id' => (int)$existingUser->id], $updates); } catch (\Throwable $_) {}
                    } catch (\Throwable $e) {
                        try { logModuleCall('cloudstorage', 'provision_storage_sync_s3_user_fail', ['u' => $serviceUsername], $e->getMessage()); } catch (\Throwable $_) {}
                    }
                }

                // Apply trial quota for trial-limited storage, or remove quota for paid status.
                try {
                    $trialSelection = null;
                    if (Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
                        $trialSelection = Capsule::table('cloudstorage_trial_selection')
                            ->where('client_id', $clientId)
                            ->first();
                    } else {
                        try { logModuleCall('cloudstorage', 'provision_storage_trial_selection_table_missing', ['clientId' => $clientId], 'cloudstorage_trial_selection missing'); } catch (\Throwable $_) {}
                    }
                    $storageTier = is_object($trialSelection) ? strtolower((string)($trialSelection->storage_tier ?? '')) : '';
                    $trialStatus = is_object($trialSelection) ? strtolower((string)($trialSelection->trial_status ?? '')) : '';
                    try {
                        logModuleCall('cloudstorage', 'provision_storage_trial_selection', [
                            'clientId' => $clientId,
                            'storage_tier' => $storageTier,
                            'trial_status' => $trialStatus,
                        ], is_object($trialSelection) ? (array) $trialSelection : 'no selection');
                    } catch (\Throwable $_) {}
                    if (!$trialSelection) {
                        try { logModuleCall('cloudstorage', 'provision_storage_trial_selection_missing', ['clientId' => $clientId], 'no selection'); } catch (\Throwable $_) {}
                    }

                    $quotaUid = $cephBaseUid;
                    $quotaTenant = $tenantId ?: null;
                    $quotaUser = null;
                    try {
                        $quotaUser = \WHMCS\Module\Addon\CloudStorage\Client\DBController::getUser($serviceUsername);
                    } catch (\Throwable $e) {}
                    if ($quotaUser && !empty($quotaUser->ceph_uid)) {
                        $quotaUid = (string) $quotaUser->ceph_uid;
                        $quotaTenant = !empty($quotaUser->tenant_id) ? (string) $quotaUser->tenant_id : $quotaTenant;
                    }
                    try {
                        logModuleCall('cloudstorage', 'provision_storage_quota_identity', [
                            'clientId' => $clientId,
                            'uid' => $quotaUid,
                            'tenant' => $quotaTenant,
                        ], [
                            'service_username' => $serviceUsername,
                            'ceph_base_uid' => $cephBaseUid,
                        ]);
                    } catch (\Throwable $_) {}

                    if ($quotaUid !== '') {
                        if ($storageTier === 'trial_limited') {
                            $quota = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::setUserQuota($endpoint, $accessKey, $secretKey, [
                                'uid' => $quotaUid,
                                'tenant' => $quotaTenant,
                                'enabled' => true,
                                'max_size_kb' => \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::USER_TRIAL_QUOTA_KB,
                            ]);
                            try { logModuleCall('cloudstorage', 'provision_storage_apply_trial_quota', ['uid' => $quotaUid, 'tenant' => $quotaTenant, 'tier' => $storageTier, 'max_size_kb' => \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::USER_TRIAL_QUOTA_KB], $quota); } catch (\Throwable $_) {}
                        } elseif ($trialStatus === 'paid') {
                            $quota = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::setUserQuota($endpoint, $accessKey, $secretKey, [
                                'uid' => $quotaUid,
                                'tenant' => $quotaTenant,
                                'enabled' => false,
                            ]);
                            try { logModuleCall('cloudstorage', 'provision_storage_remove_trial_quota', ['uid' => $quotaUid, 'tenant' => $quotaTenant, 'status' => $trialStatus], $quota); } catch (\Throwable $_) {}
                        } else {
                            try {
                                logModuleCall('cloudstorage', 'provision_storage_quota_noop', [
                                    'clientId' => $clientId,
                                    'uid' => $quotaUid,
                                    'tenant' => $quotaTenant,
                                ], [
                                    'storage_tier' => $storageTier,
                                    'trial_status' => $trialStatus,
                                ]);
                            } catch (\Throwable $_) {}
                        }
                    } else {
                        try { logModuleCall('cloudstorage', 'provision_storage_quota_skip_missing_uid', ['clientId' => $clientId], 'No uid'); } catch (\Throwable $_) {}
                    }
                } catch (\Throwable $e) {
                    try { logModuleCall('cloudstorage', 'provision_storage_quota_exception', ['clientId' => $clientId], $e->getMessage()); } catch (\Throwable $_) {}
                }
            }
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'provision_storage_adminops_exception', ['u' => $serviceUsername], $e->getMessage()); } catch (\Throwable $_) {}
        }
        // Option B onboarding: direct the user to create their first access key.
        return 'index.php?m=cloudstorage&page=access_keys';
    }

    public static function provisionCloudToCloud(int $clientId): string
    {
        // Reuse cloud storage provisioning path to ensure Ceph user/account exists
        $redirect = self::provisionCloudStorage($clientId);
        // Route into the e3 backup UI
        return 'index.php?m=cloudstorage&page=e3backup&view=users';
    }

    /**
     * Provision the new e3 Cloud Backup product for a client.
     *
     * Sequence:
     *   1. Ensure the e3 Object Storage service is active (the agent needs a
     *      destination bucket).
     *   2. Create + accept a WHMCS order for the pid_e3_cloud_backup product
     *      (zero-recurring; usage is line-itemised via config options).
     *   3. Seed tblhostingconfigoptions rows with qty=0 so every metric appears
     *      on the very first invoice once the trial converts.
     *   4. Insert a s3_cloudbackup_trial_state row (trialing, 30 days).
     *   5. Move both products' nextduedate out to the end of the trial.
     *   6. Create a default s3_backup_users row plus a one-time enrollment
     *      token so the customer can immediately enroll an agent.
     *
     * Returns the redirect URL the caller should send the user to.
     */
    public static function provisionE3CloudBackup(int $clientId, string $username, string $password, array $opts = []): string
    {
        // Existing-customer onboarding: the client already pays for their e3
        // object storage and is simply opting into the beta backup product.
        // We skip the per-service 30-day trial and the storage due-date
        // deferral so their existing object storage keeps billing normally.
        $isExistingClient = !empty($opts['existing']);

        $bootstrapPath = __DIR__ . '/E3CloudBackupProductBootstrap.php';
        if (is_file($bootstrapPath)) {
            require_once $bootstrapPath;
        }
        $billingPath = dirname(__DIR__) . '/Admin/E3CloudBackupBilling.php';
        $pricingPath = dirname(__DIR__) . '/Admin/E3CloudBackupPricing.php';
        $trialPath = dirname(__DIR__) . '/Admin/E3CloudBackupTrial.php';
        foreach ([$pricingPath, $billingPath, $trialPath] as $p) {
            if (is_file($p)) {
                require_once $p;
            }
        }

        // Step 1: ensure storage product is active (idempotent).
        try {
            self::ensureCloudStorageProductActive($clientId, true);
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'provision_e3cb_storage_active_fail', ['clientId' => $clientId], $e->getMessage()); } catch (\Throwable $_) {}
        }

        // Step 2: create + accept the e3 Cloud Backup order.
        $pid = (int) \WHMCS\Module\Addon\CloudStorage\Provision\E3CloudBackupProductBootstrap::getPid();
        if ($pid <= 0) {
            throw new \Exception('e3 Cloud Backup PID is not configured. Run cloudstorage_activate() to bootstrap the product.');
        }

        $existingSvc = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('packageid', $pid)
            ->whereIn('domainstatus', ['Active', 'Suspended', 'Pending'])
            ->orderBy('id', 'desc')
            ->first();
        $serviceId = $existingSvc ? (int) $existingSvc->id : 0;

        if ($serviceId <= 0) {
            $adminUser = 'API';
            $order = localAPI('AddOrder', [
                'clientid'      => $clientId,
                'pid'           => [$pid],
                'billingcycle'  => ['monthly'],
                'paymentmethod' => 'stripe',
                'noinvoice'     => true,
                'noemail'       => true,
            ], $adminUser);
            try { logModuleCall('cloudstorage', 'provision_e3cb_addorder', ['clientId' => $clientId, 'pid' => $pid], $order); } catch (\Throwable $_) {}

            if (($order['result'] ?? '') !== 'success') {
                throw new \Exception('e3 Cloud Backup AddOrder failed: ' . ($order['message'] ?? 'unknown'));
            }
            $accept = localAPI('AcceptOrder', [
                'orderid'         => $order['orderid'],
                'autosetup'       => false,
                'sendemail'       => false,
                'serviceusername' => $username,
                'servicepassword' => $password,
            ], $adminUser);
            try { logModuleCall('cloudstorage', 'provision_e3cb_acceptorder', ['orderid' => $order['orderid']], $accept); } catch (\Throwable $_) {}

            if (($accept['result'] ?? '') !== 'success') {
                throw new \Exception('e3 Cloud Backup AcceptOrder failed: ' . ($accept['message'] ?? 'unknown'));
            }
            $serviceId = (int) ($accept['serviceid'] ?? 0);
            if ($serviceId <= 0 && !empty($order['orderid'])) {
                try {
                    $serviceId = (int) Capsule::table('tblhosting')
                        ->where('orderid', (int) $order['orderid'])
                        ->orderBy('id', 'desc')
                        ->value('id');
                } catch (\Throwable $_) {}
            }
        }

        if ($serviceId <= 0) {
            throw new \Exception('e3 Cloud Backup service could not be resolved after provisioning.');
        }

        // Step 3: seed config option rows so the line items always exist.
        try {
            \WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupBilling::applyDefaultConfigOptions($serviceId);
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'provision_e3cb_apply_default_config_options_fail', ['service_id' => $serviceId], $e->getMessage()); } catch (\Throwable $_) {}
        }

        // Step 4 + 5: create trial state and push nextduedate out.
        // For existing customers we skip both: they are not on a new trial and
        // their object storage continues to bill on its normal schedule.
        $trialDays = (int) self::getSetting('e3cb_trial_days', 30);
        if ($trialDays <= 0) {
            $trialDays = 30;
        }
        if (!$isExistingClient) {
            try {
                \WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupTrial::startTrial($serviceId, $clientId, $trialDays);
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'provision_e3cb_start_trial_fail', ['service_id' => $serviceId], $e->getMessage()); } catch (\Throwable $_) {}
            }
        }

        try {
            $tz = new \DateTimeZone('America/Toronto');
            $nextDue = new \DateTime('now', $tz);
            $nextDue->add(new \DateInterval("P{$trialDays}D"));
            $formattedDue = $nextDue->format('Y-m-d');
            // The e3 Cloud Backup service itself is always $0 recurring (usage is
            // line-itemised); keep it Active regardless of client type.
            Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->update([
                    'amount'          => 0.00,
                    'nextduedate'     => $formattedDue,
                    'nextinvoicedate' => $formattedDue,
                    'domainstatus'    => 'Active',
                ]);

            // Mirror the trial end on the storage product too so WHMCS doesn't
            // invoice storage early — but only for new trial customers. An
            // existing customer's storage must keep its current billing dates.
            if (!$isExistingClient) {
                $storagePid = (int) self::getSetting('pid_cloud_storage', 0);
                if ($storagePid > 0) {
                    Capsule::table('tblhosting')
                        ->where('userid', $clientId)
                        ->where('packageid', $storagePid)
                        ->whereIn('domainstatus', ['Active', 'Pending'])
                        ->update([
                            'nextduedate'     => $formattedDue,
                            'nextinvoicedate' => $formattedDue,
                        ]);
                }
            }
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'provision_e3cb_anchor_due_fail', ['service_id' => $serviceId], $e->getMessage()); } catch (\Throwable $_) {}
        }

        // Step 6: ensure a default s3_backup_users row + a one-time enrollment token.
        $backupUserId = self::ensureDefaultBackupUser($clientId, $username, $password);
        if ($backupUserId > 0) {
            try {
                self::ensureEnrollmentToken($clientId, $backupUserId);
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'provision_e3cb_enrollment_token_fail', ['client_id' => $clientId], $e->getMessage()); } catch (\Throwable $_) {}
            }
        }

        // Land the new customer on the Getting Started page (a purpose-built
        // first-run hub). The driver.js tour auto-starts there. Customers can
        // navigate to the user detail page via the stepper once an agent
        // enrolls.
        return 'index.php?m=cloudstorage&page=e3backup&view=getting_started';
    }

    /**
     * Provision a unified e3 Backup User (one WHMCS service per backup user row).
     *
     * @param array{
     *   username:string,
     *   password:string,
     *   encryption_mode?:string,
     *   intent?:string,
     *   email?:string,
     *   tenant_id?:int|null,
     *   status?:string,
     *   notify_emails?:array,
     *   notifications_enabled?:bool,
     *   notify_on_success?:bool,
     *   notify_on_warning?:bool,
     *   notify_on_failure?:bool,
     *   existing?:bool
     * } $spec
     * @return array{user_id:int, public_id:?string, service_id:int, redirect:string, intent:string}
     */
    public static function provisionE3BackupUser(int $clientId, array $spec): array
    {
        $bootstrapPath = __DIR__ . '/E3BackupUserProductBootstrap.php';
        if (is_file($bootstrapPath)) {
            require_once $bootstrapPath;
        }
        foreach ([
            dirname(__DIR__) . '/Admin/E3CloudBackupBilling.php',
            dirname(__DIR__) . '/Admin/E3CloudBackupTrial.php',
        ] as $p) {
            if (is_file($p)) {
                require_once $p;
            }
        }
        $ms365Autoload = dirname(__DIR__, 3) . '/ms365backup/ms365backup_autoload.php';
        if (is_file($ms365Autoload)) {
            require_once $ms365Autoload;
        }

        if (!\WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::isUnifiedEnabled()) {
            throw new \Exception('Unified e3 Backup User provisioning is not enabled.');
        }

        $username = preg_replace('/[^A-Za-z0-9_.-]+/', '', (string) ($spec['username'] ?? ''));
        if ($username === '' || strlen($username) < 3) {
            throw new \Exception('Backup username must be at least 3 characters.');
        }
        $password = (string) ($spec['password'] ?? '');
        if ($password === '') {
            throw new \Exception('Password is required.');
        }

        $intent = strtolower(trim((string) ($spec['intent'] ?? '')));
        if (!in_array($intent, ['local', 'ms365', 'saas'], true)) {
            $intent = '';
        }

        $encryptionMode = strtolower(trim((string) ($spec['encryption_mode'] ?? 'managed')));
        if (!in_array($encryptionMode, ['managed', 'strict'], true)) {
            $encryptionMode = 'managed';
        }
        if (in_array($intent, ['ms365', 'saas'], true)) {
            $encryptionMode = 'managed';
        }
        if ($intent === '') {
            $intent = $encryptionMode === 'strict' ? 'local' : 'local';
        }
        if ($encryptionMode === 'strict') {
            $intent = 'local';
        }
        $backupType = $encryptionMode === 'strict' ? 'local' : 'both';

        $isExistingClient = !empty($spec['existing']);
        $tenantId = array_key_exists('tenant_id', $spec) ? $spec['tenant_id'] : null;
        $tenantId = $tenantId !== null ? (int) $tenantId : null;
        if ($tenantId !== null && $tenantId <= 0) {
            $tenantId = null;
        }

        $email = strtolower(trim((string) ($spec['email'] ?? '')));
        if ($email === '') {
            try {
                $email = strtolower((string) Capsule::table('tblclients')->where('id', $clientId)->value('email'));
            } catch (\Throwable $_) {
                $email = '';
            }
        }
        $status = strtolower(trim((string) ($spec['status'] ?? 'active')));
        if (!in_array($status, ['active', 'disabled'], true)) {
            $status = 'active';
        }

        $notifyEmails = $spec['notify_emails'] ?? [];
        if (!is_array($notifyEmails)) {
            $notifyEmails = [];
        }
        $notifyEmailsJson = $notifyEmails === [] ? null : json_encode(array_values($notifyEmails));
        $notificationsEnabled = array_key_exists('notifications_enabled', $spec)
            ? (bool) $spec['notifications_enabled'] : true;
        $notifyOnSuccess = array_key_exists('notify_on_success', $spec)
            ? (bool) $spec['notify_on_success'] : true;
        $notifyOnWarning = array_key_exists('notify_on_warning', $spec)
            ? (bool) $spec['notify_on_warning'] : true;
        $notifyOnFailure = array_key_exists('notify_on_failure', $spec)
            ? (bool) $spec['notify_on_failure'] : true;

        $storageRes = self::ensureCloudStorageProductActive($clientId, true);
        if (($storageRes['status'] ?? '') !== 'success') {
            throw new \Exception((string) ($storageRes['message'] ?? 'Cloud Storage is not available.'));
        }

        \WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::ensure('provision');
        $pid = (int) \WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::getPid();
        if ($pid <= 0) {
            throw new \Exception('e3 Backup User PID is not configured. Run cloudstorage_upgrade().');
        }

        $dupQuery = Capsule::table('s3_backup_users')
            ->where('client_id', $clientId)
            ->where('username', $username);
        if ($tenantId === null) {
            $dupQuery->whereNull('tenant_id');
        } else {
            $dupQuery->where('tenant_id', $tenantId);
        }
        if ($dupQuery->exists()) {
            throw new \Exception('The username ' . $username . ' is already taken');
        }

        $publicId = strtoupper(bin2hex(random_bytes(13)));
        $insert = [
            'client_id' => $clientId,
            'tenant_id' => $tenantId,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT) ?: '',
            'email' => $email,
            'status' => $status,
            'backup_type' => $backupType,
            'notifications_enabled' => $notificationsEnabled ? 1 : 0,
            'notify_emails' => $notifyEmailsJson,
            'notify_on_success' => $notifyOnSuccess ? 1 : 0,
            'notify_on_warning' => $notifyOnWarning ? 1 : 0,
            'notify_on_failure' => $notifyOnFailure ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (Capsule::schema()->hasColumn('s3_backup_users', 'public_id')) {
            $insert['public_id'] = $publicId;
        }
        if (Capsule::schema()->hasColumn('s3_backup_users', 'encryption_mode')) {
            $insert['encryption_mode'] = $encryptionMode;
        }

        $backupUserId = (int) Capsule::table('s3_backup_users')->insertGetId($insert);
        if ($backupUserId <= 0) {
            throw new \Exception('Failed to create backup user row.');
        }

        $adminUser = 'API';
        $order = localAPI('AddOrder', [
            'clientid' => $clientId,
            'pid' => [$pid],
            'billingcycle' => ['monthly'],
            'paymentmethod' => 'stripe',
            'noinvoice' => true,
            'noemail' => true,
        ], $adminUser);
        if (($order['result'] ?? '') !== 'success') {
            throw new \Exception('AddOrder failed: ' . ($order['message'] ?? 'unknown'));
        }
        $accept = localAPI('AcceptOrder', [
            'orderid' => $order['orderid'],
            'autosetup' => false,
            'sendemail' => false,
            'serviceusername' => $username,
            'servicepassword' => $password,
        ], $adminUser);
        if (($accept['result'] ?? '') !== 'success') {
            throw new \Exception('AcceptOrder failed: ' . ($accept['message'] ?? 'unknown'));
        }

        $serviceId = (int) ($accept['serviceid'] ?? 0);
        if ($serviceId <= 0 && !empty($order['orderid'])) {
            try {
                $serviceId = (int) Capsule::table('tblhosting')
                    ->where('orderid', (int) $order['orderid'])
                    ->orderByDesc('id')
                    ->value('id');
            } catch (\Throwable $_) {
            }
        }
        if ($serviceId <= 0) {
            throw new \Exception('e3 Backup User service could not be resolved after provisioning.');
        }

        if (Capsule::schema()->hasColumn('s3_backup_users', 'whmcs_service_id')) {
            Capsule::table('s3_backup_users')->where('id', $backupUserId)->update([
                'whmcs_service_id' => $serviceId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        try {
            \WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupBilling::applyDefaultConfigOptions($serviceId);
        } catch (\Throwable $_) {
        }
        if (class_exists('\\Ms365Backup\\Ms365BillingService')) {
            try {
                \Ms365Backup\Ms365BillingService::applyDefaultConfigOptions($serviceId);
            } catch (\Throwable $_) {
            }
            try {
                \Ms365Backup\Ms365BillingService::linkServiceToBackupUser($clientId, $backupUserId, $serviceId);
            } catch (\Throwable $_) {
            }
        }

        $trialDays = (int) self::getSetting('e3cb_trial_days', 30);
        if ($trialDays <= 0) {
            $trialDays = 30;
        }
        if (!$isExistingClient) {
            try {
                \WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupTrial::startTrial($serviceId, $clientId, $trialDays);
            } catch (\Throwable $_) {
            }
        }

        try {
            $tz = new \DateTimeZone('America/Toronto');
            $nextDue = new \DateTime('now', $tz);
            $nextDue->add(new \DateInterval("P{$trialDays}D"));
            $formattedDue = $nextDue->format('Y-m-d');
            Capsule::table('tblhosting')->where('id', $serviceId)->update([
                'amount' => 0.00,
                'nextduedate' => $formattedDue,
                'nextinvoicedate' => $formattedDue,
                'domainstatus' => 'Active',
            ]);
            if (!$isExistingClient) {
                $storagePid = (int) self::getSetting('pid_cloud_storage', 0);
                if ($storagePid > 0) {
                    Capsule::table('tblhosting')
                        ->where('userid', $clientId)
                        ->where('packageid', $storagePid)
                        ->whereIn('domainstatus', ['Active', 'Pending'])
                        ->update([
                            'nextduedate' => $formattedDue,
                            'nextinvoicedate' => $formattedDue,
                        ]);
                }
            }
        } catch (\Throwable $_) {
        }

        try {
            self::ensureEnrollmentToken($clientId, $backupUserId);
        } catch (\Throwable $_) {
        }

        $routeUserId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id') && $publicId !== ''
            ? $publicId
            : (string) $backupUserId;

        return [
            'user_id' => $backupUserId,
            'public_id' => Capsule::schema()->hasColumn('s3_backup_users', 'public_id') ? $publicId : null,
            'service_id' => $serviceId,
            'intent' => $intent,
            'redirect' => 'index.php?m=cloudstorage&page=e3backup&view=getting_started'
                . '&user_id=' . rawurlencode($routeUserId)
                . '&intent=' . rawurlencode($intent),
        ];
    }

    /**
     * Create or return the id of a default s3_backup_users row for MS365 signup (cloud_only).
     */
    private static function ensureMs365DefaultBackupUser(int $clientId, string $usernameHint, string $password): int
    {
        try {
            $existing = Capsule::table('s3_backup_users')
                ->where('client_id', $clientId)
                ->orderBy('id', 'asc')
                ->first();
            if ($existing) {
                return (int) $existing->id;
            }
        } catch (\Throwable $e) {
        }

        $clean = preg_replace('/[^A-Za-z0-9_.-]+/', '', $usernameHint);
        if ($clean === '' || strlen($clean) < 3) {
            $clean = 'ms365' . $clientId;
        }
        try {
            $email = (string) Capsule::table('tblclients')->where('id', $clientId)->value('email');
        } catch (\Throwable $e) {
            $email = '';
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $publicId = strtoupper(bin2hex(random_bytes(13)));
        try {
            $id = (int) Capsule::table('s3_backup_users')->insertGetId([
                'public_id'     => $publicId,
                'client_id'     => $clientId,
                'tenant_id'     => null,
                'username'      => $clean,
                'password_hash' => $hash ?: '',
                'email'         => $email,
                'status'        => 'active',
                'backup_type'   => 'cloud_only',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            try { logModuleCall('cloudstorage', 'provision_ms365_backup_user_created', ['client_id' => $clientId, 'id' => $id], $clean); } catch (\Throwable $_) {}

            return $id;
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'provision_ms365_backup_user_insert_fail', ['client_id' => $clientId], $e->getMessage()); } catch (\Throwable $_) {}

            return 0;
        }
    }

    private static function ms365BackupUserUsernameTaken(int $clientId, string $username, int $excludeId = 0): bool
    {
        try {
            if (!Capsule::schema()->hasTable('s3_backup_users')) {
                return false;
            }
            $q = Capsule::table('s3_backup_users')
                ->where('client_id', $clientId)
                ->where('username', $username);
            if ($excludeId > 0) {
                $q->where('id', '!=', $excludeId);
            }

            return $q->exists();
        } catch (\Throwable $_) {
            return false;
        }
    }

    /**
     * Create or return the id of a default s3_backup_users row for this client.
     */
    private static function ensureDefaultBackupUser(int $clientId, string $usernameHint, string $password): int
    {
        try {
            $existing = Capsule::table('s3_backup_users')
                ->where('client_id', $clientId)
                ->orderBy('id', 'asc')
                ->first();
            if ($existing) {
                return (int) $existing->id;
            }
        } catch (\Throwable $e) {
        }

        $clean = preg_replace('/[^A-Za-z0-9_.-]+/', '', $usernameHint);
        if ($clean === '' || strlen($clean) < 3) {
            $clean = 'agent' . $clientId;
        }
        try {
            $email = (string) Capsule::table('tblclients')->where('id', $clientId)->value('email');
        } catch (\Throwable $e) {
            $email = '';
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $publicId = strtoupper(bin2hex(random_bytes(13))); // 26 chars
        try {
            $id = (int) Capsule::table('s3_backup_users')->insertGetId([
                'public_id'     => $publicId,
                'client_id'     => $clientId,
                'tenant_id'     => null,
                'username'      => $clean,
                'password_hash' => $hash ?: '',
                'email'         => $email,
                'status'        => 'active',
                'backup_type'   => 'both',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            try { logModuleCall('cloudstorage', 'provision_e3cb_backup_user_created', ['client_id' => $clientId, 'id' => $id], $clean); } catch (\Throwable $_) {}
            return $id;
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'provision_e3cb_backup_user_insert_fail', ['client_id' => $clientId], $e->getMessage()); } catch (\Throwable $_) {}
            return 0;
        }
    }

    /**
     * Create a one-time enrollment token for the given backup user.
     */
    private static function ensureEnrollmentToken(int $clientId, int $backupUserId): int
    {
        try {
            $token = strtoupper(bin2hex(random_bytes(16)));
            $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
            return (int) Capsule::table('s3_agent_enrollment_tokens')->insertGetId([
                'client_id'      => $clientId,
                'tenant_id'      => null,
                'backup_user_id' => $backupUserId,
                'token'          => $token,
                'description'    => 'Auto-generated at provisioning',
                'max_uses'       => 1,
                'use_count'      => 0,
                'expires_at'     => $expires,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}


