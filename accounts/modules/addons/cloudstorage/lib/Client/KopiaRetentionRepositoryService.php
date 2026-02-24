<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Kopia retention control-plane repository mapping and vault policy pinning.
 * Ensures s3_kopia_repos rows exist for repositories and pins vault_policy_version_id.
 */
class KopiaRetentionRepositoryService
{
    private const MODULE = 'cloudstorage';
    private const DEFAULT_POLICY = [
        'schema' => 1,
        'timezone' => 'UTC',
        'retention' => [
            'hourly' => 24,
            'daily' => 30,
            'weekly' => 8,
            'monthly' => 12,
            'yearly' => 3,
        ],
    ];

    /**
     * Create or get the default vault policy version from module setting.
     * Returns the policy_version id (from s3_kopia_policy_versions).
     */
    public static function ensureDefaultVaultPolicyVersion(): ?int
    {
        try {
            if (!Capsule::schema()->hasTable('s3_kopia_policy_versions')) {
                return null;
            }

            $raw = (string) Capsule::table('tbladdonmodules')
                ->where('module', self::MODULE)
                ->where('setting', 'kopia_vault_default_retention_policy_json')
                ->value('value');

            $policy = self::parsePolicy($raw);
            $policyJson = json_encode($policy);

            $existing = Capsule::table('s3_kopia_policy_versions')
                ->where('policy_json', $policyJson)
                ->orderBy('id', 'desc')
                ->first();

            if ($existing) {
                return (int) $existing->id;
            }

            Capsule::table('s3_kopia_policy_versions')->insert([
                'policy_json' => $policyJson,
                'schema_version' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $row = Capsule::table('s3_kopia_policy_versions')
                ->where('policy_json', $policyJson)
                ->orderBy('id', 'desc')
                ->first();

            return $row ? (int) $row->id : null;
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'ensureDefaultVaultPolicyVersion', [], $e->getMessage(), [], []);
            return null;
        }
    }

    /**
     * Ensure a mapping row exists in s3_kopia_repos for the given repository_id,
     * pinning vault_policy_version_id. Creates the row if it does not exist.
     *
     * @param string $repositoryId Repository ID from s3_cloudbackup_repositories
     * @param array|null $hints Optional client_id, tenant_id, bucket_id from repo
     * @return object|null The s3_kopia_repos row or null on failure
     */
    public static function ensureRepoRecordForRepositoryId(string $repositoryId, ?array $hints = []): ?object
    {
        $repositoryId = trim($repositoryId);
        if ($repositoryId === '') {
            return null;
        }

        try {
            if (!Capsule::schema()->hasTable('s3_kopia_repos')) {
                return null;
            }

            $existing = Capsule::table('s3_kopia_repos')
                ->where('repository_id', $repositoryId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $policyVersionId = self::ensureDefaultVaultPolicyVersion();
            if ($policyVersionId === null) {
                logModuleCall(self::MODULE, 'ensureRepoRecordForRepositoryId', [
                    'repository_id' => $repositoryId,
                ], 'Could not get default vault policy version', [], []);
                return null;
            }

            $clientId = (int) ($hints['client_id'] ?? 0);
            $tenantId = isset($hints['tenant_id']) && $hints['tenant_id'] !== '' ? (int) $hints['tenant_id'] : null;
            $bucketId = (int) ($hints['bucket_id'] ?? 0);

            Capsule::table('s3_kopia_repos')->insert([
                'repository_id' => $repositoryId,
                'vault_policy_version_id' => $policyVersionId,
                'client_id' => $clientId,
                'tenant_id' => $tenantId,
                'bucket_id' => $bucketId,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return Capsule::table('s3_kopia_repos')
                ->where('repository_id', $repositoryId)
                ->first();
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'ensureRepoRecordForRepositoryId', [
                'repository_id' => $repositoryId,
            ], $e->getMessage(), [], []);
            return null;
        }
    }

    private static function parsePolicy(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return self::DEFAULT_POLICY;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::DEFAULT_POLICY;
        }

        $retention = $decoded['retention'] ?? [];
        if (!is_array($retention) || empty($retention)) {
            return self::DEFAULT_POLICY;
        }

        [$valid] = KopiaRetentionPolicyService::validate($retention);
        if (!$valid) {
            return self::DEFAULT_POLICY;
        }

        return array_merge(self::DEFAULT_POLICY, [
            'schema' => (int) ($decoded['schema'] ?? 1),
            'timezone' => (string) ($decoded['timezone'] ?? 'UTC'),
            'retention' => $retention,
        ]);
    }
}
