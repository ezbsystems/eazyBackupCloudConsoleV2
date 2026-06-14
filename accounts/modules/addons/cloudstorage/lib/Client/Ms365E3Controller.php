<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

require_once dirname(__DIR__) . '/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\AccessHealthService;
use Ms365Backup\BackupRunRepository;
use Ms365Backup\BackupUserResolver;
use Ms365Backup\CustomerBackupService;
use Ms365Backup\CustomerInventoryService;
use Ms365Backup\EntraConsentService;
use Ms365Backup\FailedEngineRetryService;
use Ms365Backup\Ms365CustomerJobService;
use Ms365Backup\Ms365CustomerError;
use Ms365Backup\Ms365DisconnectService;
use Ms365Backup\Ms365Onboarding;
use Ms365Backup\Ms365ReconnectRequiredException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Ms365Backup\KopiaSnapshotBrowseService;
use Ms365Backup\Ms365RestoreSnapshotService;
use Ms365Backup\RestoreJobService;
use Ms365Backup\TenantRecordRepository;

/**
 * Bridge from e3 Cloud Backup UI to ms365backup services.
 */
final class Ms365E3Controller
{
    public static function customerErrorMessage(\Throwable $e, string $context = 'ms365_api'): string
    {
        Ms365CustomerError::log($context, $e);

        return Ms365CustomerError::message($e);
    }

    public static function apiErrorResponse(\Throwable $e, string $context): JsonResponse
    {
        $reconnect = $e instanceof Ms365ReconnectRequiredException;

        return new JsonResponse([
            'status' => 'fail',
            'message' => $reconnect ? $e->getMessage() : self::customerErrorMessage($e, $context),
            'reconnect_required' => $reconnect,
        ], $reconnect ? 403 : 500);
    }

    /** @return array{id: int, client_id: int, username: string, public_id: string} */
    public static function resolveBackupUser(int $clientId, string $userIdRaw): array
    {
        return BackupUserResolver::resolveForClient($clientId, $userIdRaw);
    }

    public static function status(int $clientId, string $userIdRaw = ''): array
    {
        if ($userIdRaw !== '') {
            $user = self::resolveBackupUser($clientId, $userIdRaw);

            return CustomerBackupService::statusForBackupUser($clientId, $user['id']);
        }

        return CustomerBackupService::statusForClient($clientId);
    }

    public static function connectStartUrl(
        int $clientId,
        string $userIdRaw = '',
        string $returnPath = '',
        string $consentMode = 'redirect',
    ): string {
        $backupUserId = 0;
        if ($userIdRaw !== '') {
            $user = self::resolveBackupUser($clientId, $userIdRaw);
            $backupUserId = $user['id'];
        }
        $tenantRecordId = TenantRecordRepository::ensureForClient($clientId, '', $backupUserId);

        return EntraConsentService::buildAdminConsentUrl(
            $clientId,
            $tenantRecordId,
            $backupUserId,
            $returnPath,
            $consentMode,
        );
    }

    /** @param array<string, mixed> $query */
    public static function handleConnectCallback(array $query): array
    {
        return EntraConsentService::handleCallback($query);
    }

    public static function peekReturnPathFromState(string $stateRaw): string
    {
        return EntraConsentService::peekReturnPath($stateRaw);
    }

    public static function peekConsentModeFromState(string $stateRaw): string
    {
        return EntraConsentService::peekConsentMode($stateRaw);
    }

    public static function buildWizardReturnUrl(int $clientId, int $backupUserId, bool $connectOk, string $error = ''): string
    {
        return EntraConsentService::buildWizardReturnUrl($clientId, $backupUserId, $connectOk, $error);
    }

    /** @return array{run_ids: list<string>, count: int} */
    public static function startBackup(int $clientId, string $preset): array
    {
        return CustomerBackupService::startPresetBackup($clientId, $preset);
    }

    /** @return list<array<string, mixed>> */
    public static function listRuns(int $clientId, ?string $status = null, ?int $since = null, int $limit = 50): array
    {
        return BackupRunRepository::searchForClient($clientId, $status, $since, $limit);
    }

    public static function health(int $clientId): array
    {
        return AccessHealthService::summaryForClient($clientId);
    }

    public static function retryRun(string $runId): string
    {
        return FailedEngineRetryService::retryRun($runId);
    }

    /** @return array<string, mixed> */
    public static function disconnect(int $clientId, string $userIdRaw = ''): array
    {
        if ($userIdRaw !== '') {
            $user = self::resolveBackupUser($clientId, $userIdRaw);
            Ms365DisconnectService::disconnectForBackupUser($clientId, $user['id']);

            return CustomerBackupService::statusForBackupUser($clientId, $user['id']);
        }

        Ms365DisconnectService::disconnectForBackupUser($clientId, 0);

        return CustomerBackupService::statusForClient($clientId);
    }

    /** @return array<string, mixed> */
    public static function refreshInventory(int $clientId, string $userIdRaw): array
    {
        $user = self::resolveBackupUser($clientId, $userIdRaw);

        return CustomerInventoryService::refreshForBackupUser($clientId, $user['id']);
    }

    /** @return array<string, mixed> */
    public static function inventoryFull(int $clientId, string $userIdRaw): array
    {
        $user = self::resolveBackupUser($clientId, $userIdRaw);

        return CustomerInventoryService::loadForBackupUser($clientId, $user['id']);
    }

    /** @return array<string, mixed> */
    public static function inventoryDiscoveryProgress(int $clientId, string $userIdRaw): array
    {
        $user = self::resolveBackupUser($clientId, $userIdRaw);

        return CustomerInventoryService::discoveryProgressForBackupUser($clientId, $user['id']);
    }

    /** @return array{has_inventory: bool, fetched_at: string, counts: array<string, int>, total_resources: int} */
    public static function inventorySummary(int $clientId, string $userIdRaw = ''): array
    {
        if ($userIdRaw !== '') {
            $user = self::resolveBackupUser($clientId, $userIdRaw);

            return CustomerInventoryService::summaryForBackupUser($clientId, $user['id']);
        }

        return CustomerInventoryService::summaryForClient($clientId);
    }

    /** @return array<string, mixed> */
    public static function onboardingStatus(int $clientId, string $userIdRaw = ''): array
    {
        if ($userIdRaw !== '') {
            $user = self::resolveBackupUser($clientId, $userIdRaw);

            return Ms365Onboarding::computeForBackupUser($clientId, $user['id']);
        }

        return Ms365Onboarding::compute($clientId);
    }

    /** @return array<string, mixed> */
    public static function runDetail(int $clientId, string $runId): array
    {
        return CustomerBackupService::runDetailForClient($clientId, $runId);
    }

    /**
     * @return array{lines: list<array<string, mixed>>, last_id: int}
     */
    public static function runLogs(int $clientId, string $runId, int $sinceId = 0): array
    {
        return CustomerBackupService::runLogsForClient($clientId, $runId, $sinceId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{job_id: string}
     */
    public static function saveJob(int $clientId, string $userIdRaw, array $payload, ?string $jobId = null): array
    {
        $user = self::resolveBackupUser($clientId, $userIdRaw);
        if ($jobId !== null && $jobId !== '') {
            return Ms365CustomerJobService::update($clientId, $user['id'], $jobId, $payload);
        }

        return Ms365CustomerJobService::create($clientId, $user['id'], $payload);
    }

    /** @return array<string, mixed>|null */
    public static function getJob(int $clientId, string $userIdRaw, string $jobId): ?array
    {
        $user = self::resolveBackupUser($clientId, $userIdRaw);

        return Ms365CustomerJobService::getForClient($clientId, $user['id'], $jobId);
    }

    /** @return array{run_ids: list<string>, batch_run_id: string, count: int} */
    public static function runJobNow(int $clientId, string $userIdRaw, string $jobId): array
    {
        $user = self::resolveBackupUser($clientId, $userIdRaw);

        return Ms365CustomerJobService::runNow($clientId, $user['id'], $jobId);
    }

    /** @return list<array<string, mixed>> */
    public static function listRestoreSnapshots(int $clientId, int $backupUserId, string $jobId, int $limit = 50, int $offset = 0): array
    {
        return Ms365RestoreSnapshotService::listForJob($clientId, $backupUserId, $jobId, $limit, $offset);
    }

    /**
     * @return list<array{name: string, path: string, type: string, has_children: bool, size: int}>
     */
    public static function browseRestoreSnapshot(
        int $clientId,
        int $backupUserId,
        string $batchRunId,
        string $manifestId,
        string $path,
        string $childRunId = '',
    ): array {
        $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
            ?? TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null) {
            throw new \RuntimeException('Microsoft 365 is not connected.');
        }

        if ($manifestId === '' && $childRunId !== '') {
            $backupRun = \Ms365Backup\BackupRunRepository::get($childRunId);
            $manifestId = (string) ($backupRun['manifest_id'] ?? '');
        }
        if ($manifestId === '' && $path === '') {
            $childRuns = \Ms365Backup\Ms365BatchRunRepository::getChildrenForBatch($batchRunId);
            $roots = [];
            foreach ($childRuns as $child) {
                if (trim((string) ($child['manifest_id'] ?? '')) === '') {
                    continue;
                }
                $label = trim((string) ($child['user_display_name'] ?? $child['physical_key'] ?? 'Workload'));
                $roots[] = [
                    'name' => $label,
                    'label' => $label,
                    'path' => '',
                    'type' => 'resource',
                    'has_children' => true,
                    'size' => 0,
                    'manifest_id' => (string) $child['manifest_id'],
                    'child_run_id' => (string) ($child['id'] ?? ''),
                    'physical_key' => (string) ($child['physical_key'] ?? ''),
                ];
            }

            return $roots;
        }

        $childRun = null;
        if ($childRunId !== '') {
            $childRun = \Ms365Backup\BackupRunRepository::get($childRunId);
        }

        return \Ms365Backup\RestoreTreeBrowseService::list($record, $manifestId, $path, $childRun);
    }

    /**
     * @param array<string, mixed> $selection
     * @return array{batch_run_id: string, restore_run_ids: list<string>}
     */
    public static function startRestore(int $clientId, int $backupUserId, string $jobId, array $selection): array
    {
        return RestoreJobService::start($clientId, $backupUserId, $jobId, $selection);
    }

    /** @deprecated Use startRestore() */
    public static function startMailRestore(int $clientId, string $targetUserId, ?string $backupRunId = null): string
    {
        $record = TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null) {
            throw new \RuntimeException('Microsoft 365 is not connected.');
        }

        $result = RestoreJobService::start($clientId, 0, '', [
            'snapshot_batch_run_id' => $backupRunId ?? '',
            'items' => [['type' => 'mail_mailbox', 'path_prefix' => '']],
            'targets' => [['graph_id' => $targetUserId, 'resource_type' => 'user']],
            'conflict_policy' => 'skip_duplicates',
        ]);

        return $result['restore_run_ids'][0] ?? '';
    }
}
