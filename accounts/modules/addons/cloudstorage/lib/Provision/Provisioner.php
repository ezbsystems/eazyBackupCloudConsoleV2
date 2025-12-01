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
        // Compute Ceph username from client email (sanitize but keep @ . _ -)
        $email = '';
        try { $email = (string) Capsule::table('tblclients')->where('id', $clientId)->value('email'); } catch (\Throwable $e) { $email = ''; }
        $serviceUsername = preg_replace('/[^a-z0-9._@-]+/', '', strtolower($email));
        if ($serviceUsername === '') { $serviceUsername = 'e3user' . $clientId; }
        try { logModuleCall('cloudstorage', 'provision_storage_begin', ['clientId' => $clientId, 'pid' => $pid, 'serviceUsername' => $serviceUsername], ''); } catch (\Throwable $e) {}
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
        // If module create didn't also create Ceph user, create via AdminOps now
        try {
            $endpoint   = (string) self::getSetting('s3_endpoint', '');
            $accessKey  = (string) self::getSetting('ceph_access_key', '');
            $secretKey  = (string) self::getSetting('ceph_secret_key', '');
            $encKey     = (string) self::getSetting('encryption_key', '');
            if ($endpoint && $accessKey && $secretKey) {
                $info = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::getUserInfo($endpoint, $accessKey, $secretKey, $serviceUsername);
                try { logModuleCall('cloudstorage', 'provision_storage_adminops_get_user', ['u' => $serviceUsername], $info); } catch (\Throwable $e) {}
                if (!is_array($info) || ($info['status'] ?? '') !== 'success') {
                    $tenantId = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::getUniqueTenantId();
                    $params = ['uid' => $serviceUsername, 'name' => $serviceUsername, 'tenant' => $tenantId];
                    $create = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::createUser($endpoint, $accessKey, $secretKey, $params);
                    try { logModuleCall('cloudstorage', 'provision_storage_adminops_create_user', $params, $create); } catch (\Throwable $e) {}
                    if (is_array($create) && ($create['status'] ?? '') === 'success') {
                        try {
                            $s3UserId = \WHMCS\Module\Addon\CloudStorage\Client\DBController::saveUser([
                                'username'  => $serviceUsername,
                                'tenant_id' => $tenantId,
                            ]);
                            $keys = $create['data']['keys'] ?? [];
                            if (is_array($keys) && count($keys) > 0) {
                                $ak = $keys[0]['access_key'] ?? '';
                                $sk = $keys[0]['secret_key'] ?? '';
                                if ($ak && $sk && $encKey) {
                                    $akEnc = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::encryptKey($ak, $encKey);
                                    $skEnc = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::encryptKey($sk, $encKey);
                                    \WHMCS\Module\Addon\CloudStorage\Client\DBController::insertRecord('s3_user_access_keys', [
                                        'user_id'    => $s3UserId,
                                        'access_key' => $akEnc,
                                        'secret_key' => $skEnc,
                                    ]);
                                    try { logModuleCall('cloudstorage', 'provision_storage_keys_saved', ['u' => $serviceUsername, 's3_user_id' => $s3UserId], ['ak_len' => strlen($akEnc), 'sk_len' => strlen($skEnc)]); } catch (\Throwable $e) {}
                                }
                            }
                        } catch (\Throwable $e) {
                            try { logModuleCall('cloudstorage', 'provision_storage_db_save_fail', ['u' => $serviceUsername], $e->getMessage()); } catch (\Throwable $_) {}
                        }
                    } else {
                        try { logModuleCall('cloudstorage', 'provision_storage_adminops_create_user_fail', $params, $create); } catch (\Throwable $e) {}
                    }
                }
            }
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'provision_storage_adminops_exception', ['u' => $serviceUsername], $e->getMessage()); } catch (\Throwable $_) {}
        }
        return 'index.php?m=cloudstorage&page=dashboard';
    }

    public static function provisionCloudToCloud(int $clientId): string
    {
        // Reuse cloud storage provisioning path to ensure Ceph user/account exists
        $redirect = self::provisionCloudStorage($clientId);
        // Route into the cloud backup UI inside Cloud Storage module
        return 'index.php?m=cloudstorage&page=cloudbackup';
    }
}


