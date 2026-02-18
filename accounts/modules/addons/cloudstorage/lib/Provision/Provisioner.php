<?php

namespace WHMCS\Module\Addon\CloudStorage\Provision;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

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

        $active = DBController::getActiveProduct($clientId, $pid);
        if ($active) {
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
                'message' => 'Cloud Storage service is not active for this account.',
            ];
        }

        $lockName = 'cloudstorage:ensure_active:' . $clientId;
        $lockAcquired = self::acquireNamedDbLock($lockName, 15);

        try {
            // Double-check under lock to avoid duplicate orders in concurrent calls.
            $activeUnderLock = DBController::getActiveProduct($clientId, $pid);
            if ($activeUnderLock) {
                return [
                    'status' => 'success',
                    'service_id' => (int) ($activeUnderLock->id ?? 0),
                    'already_active' => true,
                    'ordered' => false,
                ];
            }

            $adminUser = 'API';
            $order = localAPI('AddOrder', [
                'clientid'      => $clientId,
                'pid'           => [$pid],
                'billingcycle'  => ['monthly'],
                'paymentmethod' => 'stripe',
                'noinvoice'     => true,
                'noemail'       => true,
            ], $adminUser);

            if (($order['result'] ?? '') !== 'success') {
                return [
                    'status' => 'fail',
                    'message' => 'Unable to create Cloud Storage order: ' . ($order['message'] ?? 'unknown error'),
                    'order_result' => $order,
                ];
            }

            $accept = localAPI('AcceptOrder', [
                'orderid'   => $order['orderid'],
                'autosetup' => true,
                'sendemail' => true,
            ], $adminUser);

            if (($accept['result'] ?? '') !== 'success') {
                return [
                    'status' => 'fail',
                    'message' => 'Cloud Storage order was created but activation failed: ' . ($accept['message'] ?? 'unknown error'),
                    'order_result' => $order,
                    'accept_result' => $accept,
                ];
            }

            $activeAfter = DBController::getActiveProduct($clientId, $pid);
            if (!$activeAfter) {
                return [
                    'status' => 'fail',
                    'message' => 'Cloud Storage order was accepted but no active service was found.',
                    'order_result' => $order,
                    'accept_result' => $accept,
                ];
            }

            return [
                'status' => 'success',
                'service_id' => (int) ($activeAfter->id ?? 0),
                'already_active' => false,
                'ordered' => true,
                'order_result' => $order,
                'accept_result' => $accept,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'message' => 'Failed to ensure active Cloud Storage service: ' . $e->getMessage(),
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
        $pid = 52; // Provided by requirements
        $adminUser = 'API';
        try { logModuleCall('cloudstorage', 'ms365_entry', ['clientId' => $clientId, 'username' => $username], []); } catch (\Throwable $_) {}
        // Preflight: ensure username is available before placing an order
        if (self::cometUsernameExists($username, $pid)) {
            throw new \Exception('The username ' . $username . ' is already taken');
        }
        $order = localAPI('AddOrder', [
            'clientid'      => $clientId,
            'pid'           => [$pid],
            'billingcycle'  => ['monthly'],
            'promocode'     => 'trial',
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
            'autosetup'       => true,
            'sendemail'       => true,
            'serviceusername' => $username,
            'servicepassword' => $password,
        ], $adminUser);
        try { logModuleCall('cloudstorage', 'ms365_accept_res', ['orderid' => $order['orderid'] ?? null], $accept); } catch (\Throwable $_) {}
        if (($accept['result'] ?? '') !== 'success') {
            throw new \Exception('AcceptOrder failed: ' . ($accept['message'] ?? 'unknown'));
        }
        // Attempt MS365 LXD container provisioning (same behavior as eazybackup flow)
        try {
            // Try autoload; otherwise require the class file manually
            if (!class_exists('\\WHMCS\\Module\\Addon\\Eazybackup\\EazybackupObcMs365')) {
                $ms365Lib = dirname(__DIR__, 3) . '/eazybackup/lib/EazybackupObcMs365.php';
                try { logModuleCall('cloudstorage', 'ms365_lxd_require_path', ['path' => $ms365Lib], []); } catch (\Throwable $_) {}
                if (is_file($ms365Lib)) {
                    require_once $ms365Lib;
                }
            }
            try { logModuleCall('cloudstorage', 'ms365_lxd_class_exists', [], ['exists' => class_exists('\\WHMCS\\Module\\Addon\\Eazybackup\\EazybackupObcMs365') ? 'yes' : 'no']); } catch (\Throwable $_) {}
            if (class_exists('\\WHMCS\\Module\\Addon\\Eazybackup\\EazybackupObcMs365')) {
                $resp = \WHMCS\Module\Addon\Eazybackup\EazybackupObcMs365::provisionLXDContainer($username, $password, (string)$pid);
                try { logModuleCall('cloudstorage', 'ms365_lxd_provision', ['clientId' => $clientId, 'username' => $username], $resp); } catch (\Throwable $_) {}
            } else {
                try { logModuleCall('cloudstorage', 'ms365_lxd_missing_class', ['clientId' => $clientId], 'Class not found'); } catch (\Throwable $_) {}
            }
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'ms365_lxd_exception', ['clientId' => $clientId], $e->getMessage()); } catch (\Throwable $_) {}
            // Non-fatal: continue redirect; admin can inspect logs
        }
        // Resolve created service ID for this order (so the ms365 page can display username)
        try {
            $service = Capsule::table('tblhosting')
                ->where('orderid', (int)($order['orderid'] ?? 0))
                ->orderBy('id', 'desc')
                ->first();
            if ($service && (int)$service->userid === $clientId && (int)$service->packageid === $pid) {
                return 'index.php?m=eazybackup&a=ms365&serviceid=' . (int)$service->id;
            }
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'ms365_service_lookup_exception', ['orderid' => $order['orderid'] ?? null], $e->getMessage()); } catch (\Throwable $_) {}
        }
        return 'index.php?m=eazybackup&a=ms365';
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
        // If client already has this product active, skip order
        try {
            $has = Capsule::table('tblhosting')
                ->where('userid', $clientId)
                ->where('packageid', $pid)
                ->where('domainstatus', 'Active')
                ->exists();
            if ($has) {
                try { logModuleCall('cloudstorage', 'provision_storage_already_active', ['clientId' => $clientId, 'pid' => $pid], ''); } catch (\Throwable $e) {}
                return 'index.php?m=cloudstorage&page=dashboard';
            }
        } catch (\Throwable $e) {}
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

        try { logModuleCall('cloudstorage', 'provision_storage_begin', ['clientId' => $clientId, 'pid' => $pid, 'serviceUsername' => $serviceUsername, 'baseUsername' => $baseUsername, 'tenant' => $tenantId], ''); } catch (\Throwable $e) {}
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
                Capsule::table('tblhosting')
                    ->where('id', $serviceId)
                    ->update([
                        'username'        => $serviceUsername,
                        'nextduedate'     => $formattedDue,
                        'nextinvoicedate' => $formattedDue,
                    ]);
                try { logModuleCall('cloudstorage', 'provision_storage_service_username_update', ['serviceid' => $serviceId, 'username' => $serviceUsername], 'updated'); } catch (\Throwable $_) {}
            } catch (\Throwable $e) {
                try { logModuleCall('cloudstorage', 'provision_storage_next_due_fail', ['serviceid' => $serviceId, 'clientId' => $clientId, 'pid' => $pid], $e->getMessage()); } catch (\Throwable $_) {}
            }
        } else {
            try { logModuleCall('cloudstorage', 'provision_storage_service_missing_for_next_due', ['orderid' => $order['orderid'] ?? null, 'clientId' => $clientId, 'pid' => $pid], ''); } catch (\Throwable $e) {}
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
        return 'index.php?m=cloudstorage&page=e3backup&view=jobs';
    }
}


