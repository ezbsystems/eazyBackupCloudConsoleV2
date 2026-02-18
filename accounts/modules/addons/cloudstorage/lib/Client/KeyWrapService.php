<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

class KeyWrapService
{
    private static $module = 'cloudstorage';

    public static function wrapRepoSecret(
        string $repoSecret,
        string $mode = 'managed_recovery',
        ?string $kekRef = null,
        ?string $kekMaterial = null
    ): array {
        try {
            if ($repoSecret === '') {
                return ['status' => 'fail', 'message' => 'Repository secret is empty'];
            }

            $resolved = self::resolveKek($kekMaterial, $kekRef);
            if (($resolved['status'] ?? 'fail') !== 'success') {
                return $resolved;
            }

            $wrapped = HelperController::encryptKey($repoSecret, (string) $resolved['kek_material']);
            if (!is_string($wrapped) || $wrapped === '') {
                return ['status' => 'fail', 'message' => 'Failed to wrap repository secret'];
            }

            return [
                'status' => 'success',
                'wrap_alg' => 'aes-256-cbc',
                'wrapped_repo_secret' => $wrapped,
                'kek_ref' => (string) $resolved['kek_ref'],
                'mode' => self::normalizeMode($mode),
            ];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['mode' => $mode], $e->getMessage(), [], []);
            return ['status' => 'fail', 'message' => 'Failed to wrap repository secret'];
        }
    }

    public static function unwrapRepoSecret(
        string $wrappedRepoSecret,
        ?string $kekMaterial = null,
        ?string $kekRef = null
    ): array {
        try {
            if ($wrappedRepoSecret === '') {
                return ['status' => 'fail', 'message' => 'Wrapped repository secret is empty'];
            }

            $resolved = self::resolveKek($kekMaterial, $kekRef);
            if (($resolved['status'] ?? 'fail') !== 'success') {
                return $resolved;
            }

            $secret = (string) HelperController::decryptKey($wrappedRepoSecret, (string) $resolved['kek_material']);
            if ($secret === '') {
                return ['status' => 'fail', 'message' => 'Unable to unwrap repository secret'];
            }

            return [
                'status' => 'success',
                'repo_secret' => $secret,
                'kek_ref' => (string) $resolved['kek_ref'],
            ];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['kek_ref' => $kekRef], $e->getMessage(), [], []);
            return ['status' => 'fail', 'message' => 'Failed to unwrap repository secret'];
        }
    }

    public static function unwrapRepositorySecret(string $repositoryId): array
    {
        try {
            $repositoryId = trim($repositoryId);
            if ($repositoryId === '') {
                return ['status' => 'fail', 'message' => 'repository_id is required'];
            }

            $keyRow = Capsule::table('s3_cloudbackup_repository_keys')
                ->where('repository_ref', $repositoryId)
                ->orderByDesc('key_version')
                ->orderByDesc('id')
                ->first();
            if (!$keyRow) {
                return ['status' => 'fail', 'message' => 'Repository key version not found'];
            }

            $unwrap = self::unwrapRepoSecret(
                (string) ($keyRow->wrapped_repo_secret ?? ''),
                null,
                (string) ($keyRow->kek_ref ?? '')
            );
            if (($unwrap['status'] ?? 'fail') !== 'success') {
                return $unwrap;
            }

            return [
                'status' => 'success',
                'repo_secret' => (string) $unwrap['repo_secret'],
                'key_version' => (int) ($keyRow->key_version ?? 1),
                'mode' => (string) ($keyRow->mode ?? 'managed_recovery'),
                'kek_ref' => (string) ($keyRow->kek_ref ?? ''),
            ];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['repository_id' => $repositoryId], $e->getMessage(), [], []);
            return ['status' => 'fail', 'message' => 'Failed to resolve repository secret'];
        }
    }

    public static function getModuleEncryptionKey(): array
    {
        try {
            $cloudBackupKey = trim((string) Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'cloudbackup_encryption_key')
                ->value('value'));
            if ($cloudBackupKey !== '') {
                return [
                    'status' => 'success',
                    'kek_material' => $cloudBackupKey,
                    'kek_ref' => 'module:cloudbackup_encryption_key',
                ];
            }

            $fallbackKey = trim((string) Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'encryption_key')
                ->value('value'));
            if ($fallbackKey !== '') {
                return [
                    'status' => 'success',
                    'kek_material' => $fallbackKey,
                    'kek_ref' => 'module:encryption_key',
                ];
            }

            return ['status' => 'fail', 'message' => 'Module encryption key is not configured'];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, [], $e->getMessage(), [], []);
            return ['status' => 'fail', 'message' => 'Failed to resolve module encryption key'];
        }
    }

    private static function resolveKek(?string $kekMaterial, ?string $kekRef): array
    {
        $providedMaterial = trim((string) $kekMaterial);
        if ($providedMaterial !== '') {
            $resolvedRef = trim((string) $kekRef);
            if ($resolvedRef === '') {
                $resolvedRef = 'custom:runtime';
            }
            return [
                'status' => 'success',
                'kek_material' => $providedMaterial,
                'kek_ref' => $resolvedRef,
            ];
        }

        $moduleKey = self::getModuleEncryptionKey();
        if (($moduleKey['status'] ?? 'fail') !== 'success') {
            return $moduleKey;
        }

        return [
            'status' => 'success',
            'kek_material' => (string) $moduleKey['kek_material'],
            'kek_ref' => trim((string) $kekRef) !== '' ? trim((string) $kekRef) : (string) $moduleKey['kek_ref'],
        ];
    }

    private static function normalizeMode(string $mode): string
    {
        $mode = trim(strtolower($mode));
        if ($mode === 'strict_customer_managed') {
            return 'strict_customer_managed';
        }
        return 'managed_recovery';
    }
}
