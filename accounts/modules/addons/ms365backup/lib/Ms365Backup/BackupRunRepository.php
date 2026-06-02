<?php
declare(strict_types=1);

namespace Ms365Backup;

use PDO;
use WHMCS\Database\Capsule;

final class BackupRunRepository
{
    public static function create(
        string $userId,
        string $userUpn,
        string $displayName,
        string $backupPath,
        bool $backupMail = true,
        bool $backupCalendar = true,
    ): string {
        $scope = new BackupScope([
            BackupScope::MAIL => $backupMail,
            BackupScope::CALENDAR => $backupCalendar,
        ]);

        $resourceType = TenantResource::TYPE_USER;
        $resourceId = TenantResource::makeId($resourceType, $userId);

        $job = new PhysicalBackupJob(
            'user:' . $userId,
            [
                'id' => $resourceId,
                'resource_type' => $resourceType,
                'graph_id' => $userId,
                'display_name' => $displayName,
                'email' => $userUpn,
            ],
            [
                [
                    'id' => $resourceId,
                    'resource_type' => $resourceType,
                    'display_name' => $displayName,
                ],
            ],
            $scope,
            PhysicalBackupJob::STATUS_RUNNABLE,
        );

        $creds = TenantRepository::credentials();
        $storage = new StorageLayout($creds['tenant_id']);
        $runId = self::createFromPhysicalJob($job, $storage);
        if ($backupPath !== '') {
            self::update($runId, ['backup_path' => $backupPath]);
        }

        return $runId;
    }

    public static function createFromPhysicalJob(PhysicalBackupJob $job, StorageLayout $storage): string
    {
        $id = self::uuid();
        $now = time();
        $resourceType = $job->resourceType();
        $graphId = $job->graphId();
        $scope = $job->scope;

        $userId = '';
        $userUpn = '';
        $displayName = $job->displayName();

        if (in_array($resourceType, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
            $userId = $graphId;
            $userUpn = $job->email();
        }

        $runDir = $storage->runDirForJob($job->physicalKey, $id);

        $logicalJson = json_encode($job->logicalSources, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $row = [
            'id' => $id,
            'user_id' => $userId,
            'user_upn' => $userUpn,
            'user_display_name' => $displayName,
            'resource_id' => $job->resourceId(),
            'resource_type' => $resourceType,
            'graph_id' => $graphId,
            'physical_key' => $job->physicalKey,
            'status' => 'queued',
            'phase' => '',
            'items_done' => 0,
            'items_total' => 0,
            'percent' => 0,
            'backup_path' => $runDir,
            'backup_mail' => $scope->isEnabled(BackupScope::MAIL) ? 1 : 0,
            'backup_calendar' => $scope->isEnabled(BackupScope::CALENDAR) ? 1 : 0,
            'scope_json' => $scope->toJson(),
            'logical_sources_json' => is_string($logicalJson) ? $logicalJson : '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        Capsule::table('ms365_backup_runs')->insert($row);

        return $id;
    }

    public static function get(string $id): ?array
    {
        $row = Capsule::table('ms365_backup_runs')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public static function update(string $id, array $fields): void
    {
        $fields['updated_at'] = time();
        Capsule::table('ms365_backup_runs')->where('id', $id)->update($fields);
    }

    public static function isCancelled(string $id): bool
    {
        $run = self::get($id);

        return $run !== null && ($run['status'] ?? '') === 'cancelled';
    }

    public static function isCancellable(string $id): bool
    {
        $run = self::get($id);
        if ($run === null) {
            return false;
        }

        return in_array($run['status'] ?? '', ['queued', 'running'], true);
    }

    public static function requestCancel(string $id): bool
    {
        if (!self::isCancellable($id)) {
            return false;
        }
        self::update($id, [
            'status' => 'cancelled',
            'phase' => 'cancelled',
            'error_message' => 'Cancelled by administrator',
            'finished_at' => time(),
        ]);

        return true;
    }

    public static function setPhase(string $id, string $phase, ?int $done = null, ?int $total = null): void
    {
        if (self::isCancelled($id)) {
            throw new RunCancelledException('Backup cancelled by administrator');
        }
        $fields = ['phase' => $phase, 'status' => 'running'];
        if ($done !== null) {
            $fields['items_done'] = $done;
        }
        if ($total !== null) {
            $fields['items_total'] = $total;
        }
        if ($total !== null && $total > 0 && $done !== null) {
            $fields['percent'] = min(100, round(($done / $total) * 100, 2));
        }
        $run = self::get($id);
        if ($run && ($run['started_at'] ?? null) === null) {
            $fields['started_at'] = time();
        }
        self::update($id, $fields);
    }

    /** @return list<array<string, mixed>> */
    public static function listRecent(int $limit = 25): array
    {
        return Capsule::table('ms365_backup_runs')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    public static function getFromPdo(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM ms365_backup_runs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function updatePdo(PDO $pdo, string $id, array $fields): void
    {
        $fields['updated_at'] = time();
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            $sets[] = "`$k` = ?";
            $vals[] = $v;
        }
        $vals[] = $id;
        $pdo->prepare('UPDATE ms365_backup_runs SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
