<?php
declare(strict_types=1);

namespace Ms365Backup\Comet;

use Ms365Backup\CustomerInventoryService;
use Ms365Backup\CustomerSelectionCodec;
use Ms365Backup\Ms365CustomerJobService;
use Ms365Backup\Ms365ScheduleAssigner;
use WHMCS\Database\Capsule;

/**
 * Dry-run / apply orchestration for Comet → e3 MS365 selection import.
 */
final class CometSelectionImportService
{
    /**
     * @return array{id: int, public_id: string, username: string, whmcs_service_id: int, client_id: int}
     */
    public static function resolveBackupUser(int $clientId, string $backupUserRef): array
    {
        $backupUserRef = trim($backupUserRef);
        if ($backupUserRef === '') {
            throw new \InvalidArgumentException('backup-user-id is required.');
        }
        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            throw new \RuntimeException('s3_backup_users table not found.');
        }

        $q = Capsule::table('s3_backup_users')->where('client_id', $clientId);
        if (Capsule::schema()->hasColumn('s3_backup_users', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        if (ctype_digit($backupUserRef)) {
            $q->where('id', (int) $backupUserRef);
        } elseif (Capsule::schema()->hasColumn('s3_backup_users', 'public_id')) {
            $q->where('public_id', $backupUserRef);
        } else {
            throw new \InvalidArgumentException(
                'public_id column missing; pass numeric s3_backup_users.id for --backup-user-id.'
            );
        }

        $row = $q->first();
        if ($row === null) {
            throw new \RuntimeException(
                "Backup user '{$backupUserRef}' not found for WHMCS client {$clientId}."
            );
        }

        return [
            'id' => (int) $row->id,
            'public_id' => (string) ($row->public_id ?? ''),
            'username' => (string) ($row->username ?? ''),
            'whmcs_service_id' => (int) ($row->whmcs_service_id ?? 0),
            'client_id' => (int) $row->client_id,
        ];
    }

    /**
     * @param array{id: int, public_id: string, username: string, whmcs_service_id: int, client_id: int} $user
     */
    public static function assertServiceLinkage(array $user, int $serviceId): void
    {
        if ($serviceId <= 0) {
            throw new \InvalidArgumentException('service-id must be a positive integer.');
        }

        if (Capsule::schema()->hasColumn('s3_backup_users', 'whmcs_service_id')
            && (int) ($user['whmcs_service_id'] ?? 0) > 0
        ) {
            if ((int) $user['whmcs_service_id'] !== $serviceId) {
                throw new \RuntimeException(sprintf(
                    'Service id mismatch: backup user whmcs_service_id=%d, requested=%d.',
                    (int) $user['whmcs_service_id'],
                    $serviceId
                ));
            }

            return;
        }

        $hosting = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->where('userid', (int) $user['client_id'])
            ->first(['id', 'username', 'userid']);
        if ($hosting === null) {
            throw new \RuntimeException(
                "WHMCS service {$serviceId} not found for client {$user['client_id']}."
            );
        }

        $serviceUsername = trim((string) ($hosting->username ?? ''));
        $backupUsername = trim((string) ($user['username'] ?? ''));
        if ($serviceUsername === '' || $backupUsername === ''
            || strcasecmp($serviceUsername, $backupUsername) !== 0
        ) {
            throw new \RuntimeException(sprintf(
                'Service %d username "%s" does not match backup user "%s".',
                $serviceId,
                $serviceUsername,
                $backupUsername
            ));
        }
    }

    public static function unmatchedPct(int $unmatched, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return ($unmatched / $total) * 100.0;
    }

    /**
     * @param array{
     *   profile?: array<string, mixed>|null,
     *   profile_path?: string,
     *   client_id: int,
     *   service_id: int,
     *   backup_user_ref: string,
     *   schedule_frequency?: string,
     *   timezone?: string|null,
     *   job_name?: string,
     *   max_unmatched_pct?: float,
     *   apply?: bool,
     *   out_selection?: string|null
     * } $opts
     * @return array<string, mixed>
     */
    public static function run(array $opts): array
    {
        $clientId = (int) ($opts['client_id'] ?? 0);
        $serviceId = (int) ($opts['service_id'] ?? 0);
        $backupUserRef = (string) ($opts['backup_user_ref'] ?? '');
        $apply = (bool) ($opts['apply'] ?? false);
        $maxUnmatchedPct = (float) ($opts['max_unmatched_pct'] ?? 25.0);
        $scheduleFrequency = (string) ($opts['schedule_frequency'] ?? Ms365ScheduleAssigner::FREQUENCY_ONCE_DAILY);
        $timezone = isset($opts['timezone']) && $opts['timezone'] !== null && $opts['timezone'] !== ''
            ? (string) $opts['timezone']
            : null;
        $jobName = trim((string) ($opts['job_name'] ?? ''));
        $outSelection = isset($opts['out_selection']) ? (string) $opts['out_selection'] : '';

        if ($clientId <= 0) {
            throw new \InvalidArgumentException('whmcs-userid is required.');
        }

        $profile = $opts['profile'] ?? null;
        if (!is_array($profile)) {
            $path = (string) ($opts['profile_path'] ?? '');
            $profile = self::loadProfileFile($path);
        }

        $user = self::resolveBackupUser($clientId, $backupUserRef);
        self::assertServiceLinkage($user, $serviceId);

        $parsed = CometOffice365SelectionParser::parseProfile($profile);
        if ($timezone === null && $parsed['local_timezone'] !== '') {
            $timezone = $parsed['local_timezone'];
        }

        $inventory = CustomerInventoryService::loadForBackupUser($clientId, (int) $user['id']);
        if (empty($inventory['resources'])) {
            throw new \RuntimeException('Refresh tenant inventory before importing selection.');
        }

        $mapped = CometSelectionMapper::map($parsed, $inventory);
        $report = $mapped['report'];
        $unmatched = count($report['unmatched_backup_option_keys'] ?? []);
        $total = (int) ($report['backup_options_total'] ?? 0);
        $pct = self::unmatchedPct($unmatched, $total);

        CustomerSelectionCodec::validate(
            $mapped['selected_resource_ids'],
            $mapped['scope_overrides'],
            $inventory,
        );

        $selectionPayload = [
            'selected_resource_ids' => $mapped['selected_resource_ids'],
            'scope_overrides' => $mapped['scope_overrides'],
        ];

        if ($outSelection !== '') {
            self::writeSelectionFile($outSelection, $selectionPayload);
        }

        $result = [
            'dry_run' => !$apply,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'backup_user_id' => (int) $user['id'],
            'backup_user_public_id' => (string) $user['public_id'],
            'backup_username' => (string) $user['username'],
            'source_guid' => $parsed['source_guid'],
            'source_description' => $parsed['description'],
            'selected_count' => count($mapped['selected_resource_ids']),
            'unmatched_pct' => round($pct, 2),
            'max_unmatched_pct' => $maxUnmatchedPct,
            'report' => $report,
            'job_id' => null,
        ];

        if (!$apply) {
            return $result;
        }

        if ($pct > $maxUnmatchedPct) {
            throw new \RuntimeException(sprintf(
                'Unmatched BackupOptions %.2f%% exceeds max-unmatched-pct %.2f%% (%d/%d). Dry-run and review before --apply.',
                $pct,
                $maxUnmatchedPct,
                $unmatched,
                $total
            ));
        }

        if ($jobName === '') {
            $jobName = 'M365 (imported from Comet)';
            if ($parsed['description'] !== '') {
                $jobName = 'M365 (imported from Comet: ' . $parsed['description'] . ')';
            }
        }

        $created = Ms365CustomerJobService::create($clientId, (int) $user['id'], [
            'name' => $jobName,
            'selected_resource_ids' => $mapped['selected_resource_ids'],
            'scope_overrides' => $mapped['scope_overrides'],
            'schedule_frequency' => $scheduleFrequency,
            'timezone' => $timezone,
        ]);

        $result['dry_run'] = false;
        $result['job_id'] = $created['job_id'] ?? null;

        return $result;
    }

    /** @return array<string, mixed> */
    public static function loadProfileFile(string $path): array
    {
        $path = trim($path);
        if ($path === '' || !is_file($path)) {
            throw new \InvalidArgumentException('comet-profile file not found: ' . $path);
        }
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new \RuntimeException('comet-profile file is empty: ' . $path);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('comet-profile is not valid JSON: ' . $path);
        }

        return $decoded;
    }

    /**
     * @param array{selected_resource_ids: list<string>, scope_overrides: array<string, array<string, bool>>} $payload
     */
    private static function writeSelectionFile(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode selection JSON.');
        }
        if (file_put_contents($path, $json . "\n") === false) {
            throw new \RuntimeException('Failed to write out-selection file: ' . $path);
        }
    }
}
