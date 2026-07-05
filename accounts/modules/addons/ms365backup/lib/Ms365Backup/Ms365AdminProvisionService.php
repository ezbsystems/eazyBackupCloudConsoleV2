<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap;
use WHMCS\Module\Addon\CloudStorage\Provision\Provisioner;

/**
 * Admin-side MS365 backup provisioning (unified e3 Backup User path only).
 */
final class Ms365AdminProvisionService
{
    private static function cloudStorageProvisionerBootstrap(): void
    {
        $base = dirname(__DIR__, 3) . '/cloudstorage/lib/Provision';
        require_once $base . '/E3BackupUserProductBootstrap.php';
        require_once $base . '/Provisioner.php';
    }

    public static function isUnifiedEnabled(): bool
    {
        self::cloudStorageProvisionerBootstrap();

        return E3BackupUserProductBootstrap::isUnifiedEnabled();
    }

    private static function cloudStoragePid(): int
    {
        try {
            $pid = (int) Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'pid_cloud_storage')
                ->value('value');

            return $pid > 0 ? $pid : 0;
        } catch (\Throwable $_) {
            return 0;
        }
    }

    private static function unifiedBackupUserPid(): int
    {
        try {
            $pid = (int) Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'pid_e3_backup_user')
                ->value('value');

            return $pid > 0 ? $pid : 0;
        } catch (\Throwable $_) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function preview(int $clientId): array
    {
        if ($clientId <= 0) {
            throw new \InvalidArgumentException('client_id is required.');
        }

        $unifiedEnabled = self::isUnifiedEnabled();
        $blockers = [];
        $warnings = [];

        if (!$unifiedEnabled) {
            $blockers[] = 'Unified e3 Backup User provisioning is not enabled (cloudstorage setting e3_backup_user_unified_enabled).';
        }

        $client = null;
        try {
            $client = Capsule::table('tblclients')
                ->where('id', $clientId)
                ->select('id', 'firstname', 'lastname', 'companyname', 'email', 'status', 'datecreated')
                ->first();
        } catch (\Throwable $_) {
        }

        if (!$client) {
            throw new \InvalidArgumentException('Client not found.');
        }

        $clientStatus = (string) ($client->status ?? '');
        if (strcasecmp($clientStatus, 'Closed') === 0) {
            $blockers[] = 'Client account is Closed.';
        }

        $storagePid = self::cloudStoragePid();
        $e3Pid = self::unifiedBackupUserPid();

        $cloudStorageServices = [];
        if ($storagePid > 0) {
            try {
                $cloudStorageServices = Capsule::table('tblhosting')
                    ->where('userid', $clientId)
                    ->where('packageid', $storagePid)
                    ->select('id', 'username', 'domainstatus', 'regdate')
                    ->orderByDesc('id')
                    ->get()
                    ->map(static function ($row) {
                        return [
                            'id' => (int) $row->id,
                            'username' => (string) ($row->username ?? ''),
                            'domainstatus' => (string) ($row->domainstatus ?? ''),
                            'regdate' => (string) ($row->regdate ?? ''),
                        ];
                    })
                    ->all();
            } catch (\Throwable $_) {
            }
        }

        $hasActiveCloudStorage = false;
        foreach ($cloudStorageServices as $svc) {
            if (in_array($svc['domainstatus'], ['Active', 'Suspended', 'Pending'], true)) {
                $hasActiveCloudStorage = true;
                break;
            }
        }
        if (!$hasActiveCloudStorage) {
            $warnings[] = 'Base Cloud Storage service is missing — it will be auto-ordered during provision.';
        }

        $e3Services = [];
        if ($e3Pid > 0) {
            try {
                $e3Services = Capsule::table('tblhosting')
                    ->where('userid', $clientId)
                    ->where('packageid', $e3Pid)
                    ->select('id', 'username', 'domainstatus', 'regdate', 'nextduedate')
                    ->orderByDesc('id')
                    ->get()
                    ->map(static function ($row) {
                        return [
                            'id' => (int) $row->id,
                            'username' => (string) ($row->username ?? ''),
                            'domainstatus' => (string) ($row->domainstatus ?? ''),
                            'regdate' => (string) ($row->regdate ?? ''),
                            'nextduedate' => (string) ($row->nextduedate ?? ''),
                        ];
                    })
                    ->all();
            } catch (\Throwable $_) {
            }
        }

        $backupUsers = [];
        try {
            if (Capsule::schema()->hasTable('s3_backup_users')) {
                $cols = ['id', 'username', 'status', 'backup_type', 'created_at'];
                if (Capsule::schema()->hasColumn('s3_backup_users', 'public_id')) {
                    $cols[] = 'public_id';
                }
                if (Capsule::schema()->hasColumn('s3_backup_users', 'whmcs_service_id')) {
                    $cols[] = 'whmcs_service_id';
                }
                if (Capsule::schema()->hasColumn('s3_backup_users', 'encryption_mode')) {
                    $cols[] = 'encryption_mode';
                }
                $backupUsers = Capsule::table('s3_backup_users')
                    ->where('client_id', $clientId)
                    ->select($cols)
                    ->orderBy('id')
                    ->get()
                    ->map(static function ($row) {
                        return [
                            'id' => (int) $row->id,
                            'public_id' => isset($row->public_id) ? (string) $row->public_id : null,
                            'username' => (string) ($row->username ?? ''),
                            'status' => (string) ($row->status ?? ''),
                            'backup_type' => (string) ($row->backup_type ?? ''),
                            'encryption_mode' => isset($row->encryption_mode) ? (string) $row->encryption_mode : null,
                            'whmcs_service_id' => isset($row->whmcs_service_id) ? (int) $row->whmcs_service_id : null,
                            'created_at' => (string) ($row->created_at ?? ''),
                        ];
                    })
                    ->all();
            }
        } catch (\Throwable $_) {
        }

        $ms365Tenants = [];
        try {
            if (Capsule::schema()->hasTable('ms365_tenant_records')) {
                $ms365Tenants = Capsule::table('ms365_tenant_records')
                    ->where('whmcs_client_id', $clientId)
                    ->select('id', 'backup_user_id', 'connection_status', 'azure_tenant_id', 'connection_auth_mode')
                    ->orderByDesc('id')
                    ->get()
                    ->map(static function ($row) {
                        return [
                            'id' => (int) $row->id,
                            'backup_user_id' => (int) ($row->backup_user_id ?? 0),
                            'connection_status' => (string) ($row->connection_status ?? ''),
                            'azure_tenant_id' => (string) ($row->azure_tenant_id ?? ''),
                            'connection_auth_mode' => (string) ($row->connection_auth_mode ?? ''),
                        ];
                    })
                    ->all();
            }
        } catch (\Throwable $_) {
        }

        $fullName = trim(((string) ($client->firstname ?? '')) . ' ' . ((string) ($client->lastname ?? '')));

        return [
            'client_id' => $clientId,
            'unified_enabled' => $unifiedEnabled,
            'can_provision' => $unifiedEnabled && $blockers === [],
            'blockers' => $blockers,
            'warnings' => $warnings,
            'client' => [
                'id' => $clientId,
                'name' => $fullName !== '' ? $fullName : null,
                'companyname' => (string) ($client->companyname ?? ''),
                'email' => (string) ($client->email ?? ''),
                'status' => $clientStatus,
                'datecreated' => (string) ($client->datecreated ?? ''),
            ],
            'cloud_storage' => [
                'pid' => $storagePid,
                'has_active' => $hasActiveCloudStorage,
                'services' => $cloudStorageServices,
            ],
            'e3_backup_user' => [
                'pid' => $e3Pid,
                'services' => $e3Services,
            ],
            'backup_users' => $backupUsers,
            'ms365_tenants' => $ms365Tenants,
            'impersonate_url' => '/modules/addons/cloudstorage/api/admin/impersonate_client.php?client_id=' . $clientId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function provision(
        int $clientId,
        string $username,
        string $password,
        bool $existingClient,
        int $adminId
    ): array {
        if ($clientId <= 0) {
            throw new \InvalidArgumentException('client_id is required.');
        }

        if (!self::isUnifiedEnabled()) {
            throw new \RuntimeException('Unified e3 Backup User provisioning is not enabled.');
        }

        $username = trim($username);
        if ($username === '' || !preg_match('/^[A-Za-z0-9_.-]{6,}$/', $username)) {
            throw new \InvalidArgumentException('Backup username must be at least 6 characters and may contain only a-z, A-Z, 0-9, _, ., -');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Backup password must be at least 8 characters.');
        }

        $preview = self::preview($clientId);
        if (empty($preview['can_provision'])) {
            $blockers = $preview['blockers'] ?? [];
            throw new \RuntimeException($blockers !== [] ? (string) $blockers[0] : 'Provisioning is not allowed for this client.');
        }

        self::cloudStorageProvisionerBootstrap();

        try {
            $result = Provisioner::provisionE3BackupUser($clientId, [
                'username' => $username,
                'password' => $password,
                'encryption_mode' => 'managed',
                'intent' => 'ms365',
                'existing' => $existingClient,
                'notify_on_success' => true,
                'notify_on_warning' => true,
                'notify_on_failure' => true,
            ]);
        } catch (\Throwable $e) {
            try {
                logModuleCall('ms365backup', 'admin_provision_ms365_fail', [
                    'client_id' => $clientId,
                    'username' => $username,
                    'admin_id' => $adminId,
                ], $e->getMessage());
            } catch (\Throwable $_) {
            }
            throw $e;
        }

        self::seedMs365TrialSelection($clientId);

        try {
            logModuleCall('ms365backup', 'admin_provision_ms365_ok', [
                'client_id' => $clientId,
                'username' => $username,
                'admin_id' => $adminId,
                'service_id' => (int) ($result['service_id'] ?? 0),
                'user_id' => (int) ($result['user_id'] ?? 0),
            ], $result);
        } catch (\Throwable $_) {
        }

        $redirect = (string) ($result['redirect'] ?? '');
        $gettingStartedSsoUrl = self::buildClientSsoUrl($clientId, $redirect);

        return [
            'client_id' => $clientId,
            'user_id' => (int) ($result['user_id'] ?? 0),
            'public_id' => $result['public_id'] ?? null,
            'service_id' => (int) ($result['service_id'] ?? 0),
            'intent' => (string) ($result['intent'] ?? 'ms365'),
            'redirect' => $redirect,
            'getting_started_sso_url' => $gettingStartedSsoUrl,
            'impersonate_url' => self::buildImpersonateUrl($clientId, $redirect),
            'product_note' => 'Unified MS365 uses the e3 Backup User WHMCS service (not a separate pid_ms365_backup row). MS365 billing is linked to this service.',
        ];
    }

    private static function seedMs365TrialSelection(int $clientId): void
    {
        if ($clientId <= 0 || !Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        try {
            $existing = Capsule::table('cloudstorage_trial_selection')->where('client_id', $clientId)->first();
            if ($existing) {
                Capsule::table('cloudstorage_trial_selection')
                    ->where('client_id', $clientId)
                    ->update([
                        'product_choice' => 'ms365',
                        'updated_at' => $now,
                    ]);
            } else {
                Capsule::table('cloudstorage_trial_selection')->insert([
                    'client_id' => $clientId,
                    'product_choice' => 'ms365',
                    'storage_tier' => null,
                    'trial_status' => 'trial',
                    'meta' => json_encode([], JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $_) {
        }
    }

    private static function buildClientSsoUrl(int $clientId, string $redirectPath): ?string
    {
        if ($clientId <= 0 || $redirectPath === '') {
            return null;
        }
        try {
            $sso = localAPI('CreateSsoToken', [
                'client_id' => $clientId,
                'destination' => 'sso:custom_redirect',
                'sso_redirect_path' => ltrim($redirectPath, '/'),
            ], 'API');
            if (($sso['result'] ?? '') === 'success' && !empty($sso['redirect_url'])) {
                return (string) $sso['redirect_url'];
            }
        } catch (\Throwable $_) {
        }

        return null;
    }

    private static function buildImpersonateUrl(int $clientId, string $redirectPath): string
    {
        $url = '/modules/addons/cloudstorage/api/admin/impersonate_client.php?client_id=' . $clientId;
        if ($redirectPath !== '') {
            $url .= '&redirect_path=' . rawurlencode($redirectPath);
        }

        return $url;
    }
}
