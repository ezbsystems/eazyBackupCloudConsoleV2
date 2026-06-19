<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Diagnose and normalize s3_user_access_keys ciphertext when module encryption
 * keys diverge (cloudbackup_encryption_key vs encryption_key).
 */
class AccessKeyEncryptionService
{
    private static string $module = 'cloudstorage';

    /** @return array<string, string> */
    public static function loadModuleEncryptionKeys(): array
    {
        try {
            return Capsule::table('tbladdonmodules')
                ->where('module', self::$module)
                ->whereIn('setting', ['cloudbackup_encryption_key', 'encryption_key'])
                ->pluck('value', 'setting')
                ->map(static function ($value) {
                    return trim((string) $value);
                })
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function getCanonicalEncryptionKey(): string
    {
        $keys = self::loadModuleEncryptionKeys();
        $cloudBackupKey = trim((string) ($keys['cloudbackup_encryption_key'] ?? ''));
        if ($cloudBackupKey !== '') {
            return $cloudBackupKey;
        }

        return trim((string) ($keys['encryption_key'] ?? ''));
    }

    public static function getCanonicalEncryptionKeySetting(): string
    {
        $keys = self::loadModuleEncryptionKeys();
        if (trim((string) ($keys['cloudbackup_encryption_key'] ?? '')) !== '') {
            return 'cloudbackup_encryption_key';
        }
        if (trim((string) ($keys['encryption_key'] ?? '')) !== '') {
            return 'encryption_key';
        }

        return '';
    }

    /** @return array<string, mixed> */
    public static function diagnoseModuleKeys(): array
    {
        $keys = self::loadModuleEncryptionKeys();
        $cloudBackupKey = trim((string) ($keys['cloudbackup_encryption_key'] ?? ''));
        $legacyKey = trim((string) ($keys['encryption_key'] ?? ''));

        return [
            'cloudbackup_encryption_key_set' => $cloudBackupKey !== '',
            'encryption_key_set' => $legacyKey !== '',
            'keys_differ' => $cloudBackupKey !== '' && $legacyKey !== '' && $cloudBackupKey !== $legacyKey,
            'canonical_key_setting' => self::getCanonicalEncryptionKeySetting(),
            'canonical_key_configured' => self::getCanonicalEncryptionKey() !== '',
        ];
    }

    /**
     * Identify which module setting successfully decrypts a stored credential.
     */
    public static function detectDecryptKeySetting(string $encryptedKey): ?string
    {
        if ($encryptedKey === '') {
            return null;
        }

        $keys = self::loadModuleEncryptionKeys();
        foreach (['cloudbackup_encryption_key', 'encryption_key'] as $setting) {
            $material = trim((string) ($keys[$setting] ?? ''));
            if ($material === '') {
                continue;
            }
            $decrypted = HelperController::decryptKey($encryptedKey, $material);
            if (is_string($decrypted) && $decrypted !== '') {
                return $setting;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public static function diagnoseUserKey(int $userId): array
    {
        $user = Capsule::table('s3_users')->where('id', $userId)->first();
        if (!$user) {
            return ['status' => 'fail', 'message' => 'User not found.', 'user_id' => $userId];
        }

        $keyRow = Capsule::table('s3_user_access_keys')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first(['id', 'access_key', 'secret_key', 'access_key_hint']);

        $canonical = self::getCanonicalEncryptionKey();
        $canonicalSetting = self::getCanonicalEncryptionKeySetting();
        $hasKeyRow = $keyRow !== null;
        $accessDecryptSetting = null;
        $secretDecryptSetting = null;
        $decryptWithCanonical = false;
        $decryptWithFallback = false;

        if ($keyRow) {
            $accessDecryptSetting = self::detectDecryptKeySetting((string) $keyRow->access_key);
            $secretDecryptSetting = self::detectDecryptKeySetting((string) $keyRow->secret_key);
            if ($canonical !== '') {
                $accessCanonical = HelperController::decryptKey((string) $keyRow->access_key, $canonical);
                $secretCanonical = HelperController::decryptKey((string) $keyRow->secret_key, $canonical);
                $decryptWithCanonical = is_string($accessCanonical) && $accessCanonical !== ''
                    && is_string($secretCanonical) && $secretCanonical !== '';
            }
            $accessFallback = HelperController::decryptKeyWithFallback((string) $keyRow->access_key, $canonical);
            $secretFallback = HelperController::decryptKeyWithFallback((string) $keyRow->secret_key, $canonical);
            $decryptWithFallback = $accessFallback !== '' && $secretFallback !== '';
        }

        $buckets = Capsule::table('s3_buckets')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->pluck('name')
            ->all();

        return [
            'status' => 'success',
            'user_id' => $userId,
            'username' => (string) ($user->username ?? ''),
            'system_key' => (string) ($user->system_key ?? ''),
            'is_system_managed' => (int) ($user->is_system_managed ?? 0) === 1,
            'has_key_row' => $hasKeyRow,
            'access_key_hint' => $keyRow ? (string) ($keyRow->access_key_hint ?? '') : '',
            'decrypt_with_canonical' => $decryptWithCanonical,
            'decrypt_with_fallback' => $decryptWithFallback,
            'access_decrypt_key_setting' => $accessDecryptSetting,
            'secret_decrypt_key_setting' => $secretDecryptSetting,
            'needs_reencrypt' => $hasKeyRow && $decryptWithFallback && !$decryptWithCanonical,
            'cannot_decrypt' => $hasKeyRow && !$decryptWithFallback,
            'canonical_key_setting' => $canonicalSetting,
            'buckets' => array_values(array_map('strval', $buckets)),
            'ms365_buckets' => array_values(array_filter($buckets, static function ($name) {
                return stripos((string) $name, 'e3ms365-') === 0;
            })),
        ];
    }

    /** @return array<string, mixed> */
    public static function diagnoseMs365BucketOwners(?int $clientId = null): array
    {
        $query = Capsule::table('s3_buckets')
            ->where('is_active', 1)
            ->where('name', 'like', 'e3ms365-%');

        if ($clientId !== null && $clientId > 0 && Capsule::schema()->hasTable('ms365_tenant_records')) {
            $bucketNames = Capsule::table('ms365_tenant_records')
                ->where('whmcs_client_id', $clientId)
                ->where('is_active', 1)
                ->pluck('s3_bucket_name')
                ->filter(static function ($name) {
                    return trim((string) $name) !== '';
                })
                ->values()
                ->all();
            if (!empty($bucketNames)) {
                $query->whereIn('name', $bucketNames);
            }
        }

        $ownerIds = $query->pluck('user_id')->unique()->filter()->values()->all();
        $users = [];
        foreach ($ownerIds as $ownerId) {
            $users[] = self::diagnoseUserKey((int) $ownerId);
        }

        return self::buildSummaryReport($users, 'ms365_bucket_owners');
    }

    /** @return array<string, mixed> */
    public static function diagnoseBackupOwners(): array
    {
        $ownerIds = Capsule::table('s3_users')
            ->where('system_key', 'cloudbackup_owner')
            ->where('is_system_managed', 1)
            ->pluck('id')
            ->all();

        $users = [];
        foreach ($ownerIds as $ownerId) {
            $users[] = self::diagnoseUserKey((int) $ownerId);
        }

        return self::buildSummaryReport($users, 'cloudbackup_owner_users');
    }

    /** @return array<string, mixed> */
    public static function diagnoseBucket(string $bucketName): array
    {
        $bucket = Capsule::table('s3_buckets')
            ->where('name', $bucketName)
            ->where('is_active', 1)
            ->first();

        if (!$bucket) {
            return ['status' => 'fail', 'message' => 'Bucket not found or inactive.', 'bucket' => $bucketName];
        }

        $userDiag = self::diagnoseUserKey((int) $bucket->user_id);

        return [
            'status' => 'success',
            'bucket' => $bucketName,
            'bucket_id' => (int) $bucket->id,
            'owner' => $userDiag,
            'module_keys' => self::diagnoseModuleKeys(),
        ];
    }

    /**
     * Re-encrypt stored credentials under the canonical module encryption key.
     *
     * @return array{status: string, action?: string, message?: string, user_id?: int, from_setting?: string|null, to_setting?: string}
     */
    public static function normalizeStoredAccessKeyEncryption(int $userId, bool $dryRun = false): array
    {
        $canonical = self::getCanonicalEncryptionKey();
        $canonicalSetting = self::getCanonicalEncryptionKeySetting();
        if ($canonical === '' || $canonicalSetting === '') {
            return ['status' => 'fail', 'message' => 'Canonical module encryption key is not configured.', 'user_id' => $userId];
        }

        $keyRow = Capsule::table('s3_user_access_keys')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first(['id', 'access_key', 'secret_key']);

        if (!$keyRow) {
            return ['status' => 'skip', 'message' => 'No access key row for user.', 'user_id' => $userId, 'action' => 'none'];
        }

        $accessPlain = HelperController::decryptKeyWithFallback((string) $keyRow->access_key, $canonical);
        $secretPlain = HelperController::decryptKeyWithFallback((string) $keyRow->secret_key, $canonical);
        if ($accessPlain === '' || $secretPlain === '') {
            return ['status' => 'fail', 'message' => 'Stored credentials cannot be decrypted.', 'user_id' => $userId];
        }

        $accessCanonical = HelperController::decryptKey((string) $keyRow->access_key, $canonical);
        $secretCanonical = HelperController::decryptKey((string) $keyRow->secret_key, $canonical);
        if ($accessCanonical !== '' && $secretCanonical !== '') {
            return [
                'status' => 'success',
                'message' => 'Already encrypted with canonical module key.',
                'user_id' => $userId,
                'action' => 'none',
                'to_setting' => $canonicalSetting,
            ];
        }

        $fromSetting = self::detectDecryptKeySetting((string) $keyRow->access_key);
        if ($dryRun) {
            return [
                'status' => 'success',
                'message' => 'Would re-encrypt stored credentials under canonical module key.',
                'user_id' => $userId,
                'action' => 'would_reencrypt',
                'from_setting' => $fromSetting,
                'to_setting' => $canonicalSetting,
            ];
        }

        Capsule::table('s3_user_access_keys')
            ->where('id', (int) $keyRow->id)
            ->update([
                'access_key' => HelperController::encryptKey($accessPlain, $canonical),
                'secret_key' => HelperController::encryptKey($secretPlain, $canonical),
            ]);

        logModuleCall(self::$module, __FUNCTION__, [
            'user_id' => $userId,
            'from_setting' => $fromSetting,
            'to_setting' => $canonicalSetting,
        ], ['status' => 'success', 'action' => 'reencrypted']);

        return [
            'status' => 'success',
            'message' => 'Re-encrypted stored credentials under canonical module key.',
            'user_id' => $userId,
            'action' => 'reencrypted',
            'from_setting' => $fromSetting,
            'to_setting' => $canonicalSetting,
        ];
    }

    /**
     * @param int[] $userIds
     * @return array<string, mixed>
     */
    public static function normalizeUsers(array $userIds, bool $dryRun = false): array
    {
        $results = [];
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId <= 0) {
                continue;
            }
            $results[] = self::normalizeStoredAccessKeyEncryption($userId, $dryRun);
        }

        $summary = [
            'total' => count($results),
            'reencrypted' => 0,
            'would_reencrypt' => 0,
            'already_normalized' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];
        foreach ($results as $result) {
            $action = (string) ($result['action'] ?? '');
            $status = (string) ($result['status'] ?? '');
            if ($action === 'reencrypted') {
                $summary['reencrypted']++;
            } elseif ($action === 'would_reencrypt') {
                $summary['would_reencrypt']++;
            } elseif ($action === 'none' && $status === 'success') {
                $summary['already_normalized']++;
            } elseif ($status === 'skip') {
                $summary['skipped']++;
            } elseif ($status === 'fail') {
                $summary['failed']++;
            }
        }

        return [
            'status' => 'success',
            'summary' => $summary,
            'results' => $results,
        ];
    }

    /** @return array<string, mixed> */
    public static function normalizeBackupOwners(bool $dryRun = false): array
    {
        $ownerIds = Capsule::table('s3_users')
            ->where('system_key', 'cloudbackup_owner')
            ->where('is_system_managed', 1)
            ->pluck('id')
            ->all();

        $report = self::normalizeUsers($ownerIds, $dryRun);
        $report['scope'] = 'cloudbackup_owner_users';

        return $report;
    }

    /** @return array<string, mixed> */
    public static function normalizeMs365BucketOwners(?int $clientId = null, bool $dryRun = false): array
    {
        $diag = self::diagnoseMs365BucketOwners($clientId);
        $ownerIds = [];
        foreach (($diag['users'] ?? []) as $user) {
            if (!empty($user['user_id'])) {
                $ownerIds[] = (int) $user['user_id'];
            }
        }

        $report = self::normalizeUsers(array_values(array_unique($ownerIds)), $dryRun);
        $report['scope'] = 'ms365_bucket_owners';

        return $report;
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @return array<string, mixed>
     */
    private static function buildSummaryReport(array $users, string $scope): array
    {
        $summary = [
            'total' => count($users),
            'has_key_row' => 0,
            'decrypt_with_canonical' => 0,
            'decrypt_with_fallback' => 0,
            'needs_reencrypt' => 0,
            'cannot_decrypt' => 0,
        ];

        foreach ($users as $user) {
            if (!empty($user['has_key_row'])) {
                $summary['has_key_row']++;
            }
            if (!empty($user['decrypt_with_canonical'])) {
                $summary['decrypt_with_canonical']++;
            }
            if (!empty($user['decrypt_with_fallback'])) {
                $summary['decrypt_with_fallback']++;
            }
            if (!empty($user['needs_reencrypt'])) {
                $summary['needs_reencrypt']++;
            }
            if (!empty($user['cannot_decrypt'])) {
                $summary['cannot_decrypt']++;
            }
        }

        return [
            'status' => 'success',
            'scope' => $scope,
            'module_keys' => self::diagnoseModuleKeys(),
            'summary' => $summary,
            'users' => $users,
        ];
    }
}
