<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

class RepositoryService
{
    private static $module = 'cloudstorage';

    public static function isFeatureReady(): bool
    {
        try {
            $schema = Capsule::schema();
            if (!$schema->hasTable('s3_cloudbackup_repositories') || !$schema->hasTable('s3_cloudbackup_repository_keys')) {
                return false;
            }
            $requiredRepoCols = ['repository_id', 'client_id', 'bucket_id', 'root_prefix', 'engine', 'status'];
            foreach ($requiredRepoCols as $col) {
                if (!$schema->hasColumn('s3_cloudbackup_repositories', $col)) {
                    return false;
                }
            }
            $requiredKeyCols = ['repository_ref', 'key_version', 'wrapped_repo_secret'];
            foreach ($requiredKeyCols as $col) {
                if (!$schema->hasColumn('s3_cloudbackup_repository_keys', $col)) {
                    return false;
                }
            }
            return true;
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, [], $e->getMessage(), [], []);
            return false;
        }
    }

    public static function createOrAttachForAgent(
        int $agentId,
        string $engine = 'kopia',
        string $mode = 'managed_recovery',
        ?int $createdBy = null
    ): array {
        try {
            if (!self::isFeatureReady()) {
                return ['status' => 'skip', 'message' => 'Repository feature is not available on this installation yet'];
            }

            $agent = Capsule::table('s3_cloudbackup_agents')->where('id', $agentId)->first();
            if (!$agent) {
                return ['status' => 'fail', 'message' => 'Agent not found'];
            }
            $agentUuid = trim((string) ($agent->agent_uuid ?? ''));
            if ($agentUuid === '') {
                return ['status' => 'fail', 'message' => 'Agent UUID is missing'];
            }

            $destQuery = Capsule::table('s3_cloudbackup_agent_destinations');
            if (Capsule::schema()->hasColumn('s3_cloudbackup_agent_destinations', 'agent_uuid')) {
                $destQuery->where('agent_uuid', $agentUuid);
            } else {
                $destQuery->where('agent_id', $agentId);
            }
            $destination = $destQuery->first();

            if (!$destination) {
                $ensure = CloudBackupBootstrapService::ensureAgentDestination($agentUuid);
                if (($ensure['status'] ?? 'fail') !== 'success') {
                    return ['status' => 'fail', 'message' => $ensure['message'] ?? 'Unable to resolve agent destination'];
                }
                $destQuery = Capsule::table('s3_cloudbackup_agent_destinations');
                if (Capsule::schema()->hasColumn('s3_cloudbackup_agent_destinations', 'agent_uuid')) {
                    $destQuery->where('agent_uuid', $agentUuid);
                } else {
                    $destQuery->where('agent_id', $agentId);
                }
                $destination = $destQuery->first();
            }
            if (!$destination) {
                return ['status' => 'fail', 'message' => 'Agent destination is not configured'];
            }

            return self::createOrAttach([
                'client_id' => (int) $agent->client_id,
                'tenant_id' => !empty($agent->tenant_id) ? (int) $agent->tenant_id : null,
                'tenant_user_id' => !empty($agent->tenant_user_id) ? (int) $agent->tenant_user_id : null,
                'bucket_id' => (int) $destination->dest_bucket_id,
                'root_prefix' => (string) $destination->root_prefix,
                'engine' => $engine,
                'mode' => $mode,
                'created_by' => $createdBy,
            ]);
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['agent_id' => $agentId], $e->getMessage(), [], []);
            return ['status' => 'fail', 'message' => 'Failed to create or attach repository for agent'];
        }
    }

    public static function createOrAttach(array $params): array
    {
        try {
            if (!self::isFeatureReady()) {
                return ['status' => 'skip', 'message' => 'Repository feature is not available on this installation yet'];
            }

            $clientId = (int) ($params['client_id'] ?? 0);
            $bucketId = (int) ($params['bucket_id'] ?? 0);
            $rootPrefix = self::normalizePrefix((string) ($params['root_prefix'] ?? ''));
            $tenantId = isset($params['tenant_id']) && $params['tenant_id'] !== '' ? (int) $params['tenant_id'] : null;
            $tenantUserId = isset($params['tenant_user_id']) && $params['tenant_user_id'] !== '' ? (int) $params['tenant_user_id'] : null;
            $engine = self::normalizeEngine((string) ($params['engine'] ?? 'kopia'));
            $mode = self::normalizeMode((string) ($params['mode'] ?? 'managed_recovery'));
            $createdBy = isset($params['created_by']) && $params['created_by'] !== '' ? (int) $params['created_by'] : null;

            if ($clientId <= 0 || $bucketId <= 0 || $rootPrefix === '') {
                return ['status' => 'fail', 'message' => 'Missing required repository scope fields'];
            }

            $result = Capsule::connection()->transaction(function () use (
                $clientId,
                $tenantId,
                $tenantUserId,
                $bucketId,
                $rootPrefix,
                $engine,
                $mode,
                $createdBy
            ) {
                $existing = self::queryByScope($clientId, $tenantId, $tenantUserId, $bucketId, $rootPrefix, $engine)->first();
                if ($existing) {
                    self::pinKopiaRetentionRepo($existing, $clientId, $tenantId, $bucketId);
                    return ['status' => 'success', 'repository' => $existing, 'created' => false];
                }

                $repositoryId = self::generateRepositoryId();
                $repoSecret = RepoSecretService::generateSecret(32);
                $wrap = KeyWrapService::wrapRepoSecret($repoSecret, $mode);
                if (($wrap['status'] ?? 'fail') !== 'success') {
                    throw new \RuntimeException($wrap['message'] ?? 'Failed to wrap repository secret');
                }

                Capsule::table('s3_cloudbackup_repositories')->insert([
                    'repository_id' => $repositoryId,
                    'client_id' => $clientId,
                    'tenant_id' => $tenantId,
                    'tenant_user_id' => $tenantUserId,
                    'bucket_id' => $bucketId,
                    'root_prefix' => $rootPrefix,
                    'engine' => $engine,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                Capsule::table('s3_cloudbackup_repository_keys')->insert([
                    'repository_ref' => $repositoryId,
                    'key_version' => 1,
                    'wrap_alg' => (string) ($wrap['wrap_alg'] ?? 'aes-256-cbc'),
                    'wrapped_repo_secret' => (string) $wrap['wrapped_repo_secret'],
                    'kek_ref' => (string) ($wrap['kek_ref'] ?? ''),
                    'mode' => (string) ($wrap['mode'] ?? 'managed_recovery'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $createdBy,
                ]);

                $repository = Capsule::table('s3_cloudbackup_repositories')
                    ->where('repository_id', $repositoryId)
                    ->first();
                if (!$repository) {
                    throw new \RuntimeException('Repository row was not persisted');
                }

                logModuleCall(self::$module, 'repository_create', [
                    'repository_id' => $repositoryId,
                    'client_id' => $clientId,
                    'tenant_id' => $tenantId,
                    'tenant_user_id' => $tenantUserId,
                    'bucket_id' => $bucketId,
                    'root_prefix' => $rootPrefix,
                    'engine' => $engine,
                ], ['status' => 'success'], [], []);

                logModuleCall(self::$module, 'repository_key_create', [
                    'repository_id' => $repositoryId,
                    'key_version' => 1,
                    'wrap_alg' => (string) ($wrap['wrap_alg'] ?? 'aes-256-cbc'),
                    'kek_ref' => (string) ($wrap['kek_ref'] ?? ''),
                    'mode' => (string) ($wrap['mode'] ?? 'managed_recovery'),
                ], ['status' => 'success'], [], []);

                self::pinKopiaRetentionRepo($repository, $clientId, $tenantId, $bucketId);

                return ['status' => 'success', 'repository' => $repository, 'created' => true];
            });

            return is_array($result) ? $result : ['status' => 'fail', 'message' => 'Failed to persist repository'];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, $params, $e->getMessage(), [], []);
            return ['status' => 'fail', 'message' => 'Failed to create or attach repository'];
        }
    }

    public static function getByRepositoryId(string $repositoryId): ?object
    {
        $repositoryId = trim($repositoryId);
        if ($repositoryId === '') {
            return null;
        }
        if (!self::isFeatureReady()) {
            return null;
        }

        try {
            return Capsule::table('s3_cloudbackup_repositories')
                ->where('repository_id', $repositoryId)
                ->first();
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['repository_id' => $repositoryId], $e->getMessage(), [], []);
            return null;
        }
    }

    public static function getRepositoryPassword(string $repositoryId): array
    {
        $unwrap = KeyWrapService::unwrapRepositorySecret($repositoryId);
        if (($unwrap['status'] ?? 'fail') !== 'success') {
            return $unwrap;
        }

        return [
            'status' => 'success',
            'repository_password' => (string) $unwrap['repo_secret'],
            'key_version' => (int) ($unwrap['key_version'] ?? 1),
            'mode' => (string) ($unwrap['mode'] ?? 'managed_recovery'),
        ];
    }

    private static function queryByScope(
        int $clientId,
        ?int $tenantId,
        ?int $tenantUserId,
        int $bucketId,
        string $rootPrefix,
        string $engine
    ) {
        $query = Capsule::table('s3_cloudbackup_repositories')
            ->where('client_id', $clientId)
            ->where('bucket_id', $bucketId)
            ->where('root_prefix', $rootPrefix)
            ->where('engine', $engine)
            ->where('status', 'active');

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        if ($tenantUserId === null) {
            $query->whereNull('tenant_user_id');
        } else {
            $query->where('tenant_user_id', $tenantUserId);
        }

        return $query;
    }

    private static function generateRepositoryId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }

    private static function normalizePrefix(string $prefix): string
    {
        return trim(trim($prefix), '/');
    }

    private static function normalizeMode(string $mode): string
    {
        $mode = trim(strtolower($mode));
        if ($mode === 'strict_customer_managed') {
            return 'strict_customer_managed';
        }
        return 'managed_recovery';
    }

    private static function normalizeEngine(string $engine): string
    {
        $engine = trim(strtolower($engine));
        if ($engine === '') {
            return 'kopia';
        }
        return $engine;
    }

    /**
     * Best-effort pin vault policy to repo (Kopia retention control plane).
     * Non-fatal; logs on failure.
     */
    private static function pinKopiaRetentionRepo(object $repository, int $clientId, ?int $tenantId, int $bucketId): void
    {
        try {
            $repoId = (string) ($repository->repository_id ?? '');
            if ($repoId === '') {
                return;
            }
            $hints = [
                'client_id' => $clientId,
                'tenant_id' => $tenantId,
                'bucket_id' => $bucketId,
            ];
            $pinned = KopiaRetentionRepositoryService::ensureRepoRecordForRepositoryId($repoId, $hints);
            if ($pinned === null) {
                logModuleCall(self::$module, 'pinKopiaRetentionRepo', [
                    'repository_id' => $repoId,
                ], 'Kopia retention repo record ensure returned null (best-effort, non-fatal)', [], []);
            }
        } catch (\Throwable $e) {
            logModuleCall(self::$module, 'pinKopiaRetentionRepo', [
                'repository_id' => (string) ($repository->repository_id ?? ''),
            ], $e->getMessage() . ' (best-effort, non-fatal)', [], []);
        }
    }
}
