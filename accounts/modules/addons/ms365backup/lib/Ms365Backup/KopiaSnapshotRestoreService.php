<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Phase 5 restore bridge: list snapshot paths from Kopia manifest metadata stored on runs.
 * Full Kopia browse/restore is executed by ms365-backup-worker restore mode (future CLI).
 */
final class KopiaSnapshotRestoreService
{
    /**
     * @return list<array{run_id: string, manifest_id: string, finished_at: int, physical_key: string}>
     */
    public static function listRestorePoints(int $tenantRecordId, string $physicalKey): array
    {
        if (!class_exists(\WHMCS\Database\Capsule::class)) {
            return [];
        }
        $q = \WHMCS\Database\Capsule::table('ms365_backup_runs')
            ->where('tenant_record_id', $tenantRecordId)
            ->where('status', 'success')
            ->where('manifest_id', '!=', '');
        if ($physicalKey !== '') {
            $q->where('physical_key', $physicalKey);
        }

        return $q->orderByDesc('finished_at')
            ->limit(50)
            ->get(['id', 'manifest_id', 'finished_at', 'physical_key'])
            ->map(static fn ($r) => [
                'run_id' => (string) $r->id,
                'manifest_id' => (string) $r->manifest_id,
                'finished_at' => (int) $r->finished_at,
                'physical_key' => (string) $r->physical_key,
            ])
            ->all();
    }

    public static function prefersKopiaStorage(?array $run): bool
    {
        if ($run === null) {
            return false;
        }
        $engine = (string) ($run['engine_mode'] ?? '');
        $manifest = (string) ($run['manifest_id'] ?? '');

        return $engine === 'kopia' && $manifest !== '';
    }
}
