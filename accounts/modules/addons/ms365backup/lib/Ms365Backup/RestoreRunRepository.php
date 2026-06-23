<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

final class RestoreRunRepository
{
    /** @param array<string, mixed> $data */
    public static function create(array $data): string
    {
        $id = self::uuid();
        $now = time();
        $insert = [
            'id' => $id,
            'tenant_record_id' => (int) ($data['tenant_record_id'] ?? 0),
            'whmcs_client_id' => (int) ($data['whmcs_client_id'] ?? 0),
            'resource_type' => (string) ($data['resource_type'] ?? 'mail'),
            'target_graph_id' => (string) ($data['target_graph_id'] ?? ''),
            'backup_run_id' => $data['backup_run_id'] ?? null,
            'status' => 'queued',
            'phase' => '',
            'scope_json' => self::encodeJson($data['scope_json'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Capsule::schema()->hasColumn('ms365_restore_runs', 'e3_batch_run_id')) {
            $insert['e3_batch_run_id'] = $data['e3_batch_run_id'] ?? null;
            $insert['source_batch_run_id'] = $data['source_batch_run_id'] ?? null;
            $insert['selection_json'] = self::encodeJson($data['selection_json'] ?? []);
            $insert['target_resource_id'] = (string) ($data['target_resource_id'] ?? '');
            $insert['conflict_policy'] = (string) ($data['conflict_policy'] ?? 'skip_duplicates');
            $insert['source_manifest_id'] = (string) ($data['source_manifest_id'] ?? '');
            $insert['items_total'] = (int) ($data['items_total'] ?? 0);
            $insert['items_done'] = (int) ($data['items_done'] ?? 0);
            $insert['items_skipped'] = (int) ($data['items_skipped'] ?? 0);
        }

        if (Capsule::schema()->hasColumn('ms365_restore_runs', 'restore_mode')) {
            $insert['restore_mode'] = (string) ($data['restore_mode'] ?? 'tenant');
        }
        if (Capsule::schema()->hasColumn('ms365_restore_runs', 'archive_object_key')) {
            $insert['archive_object_key'] = $data['archive_object_key'] ?? null;
        }
        if (Capsule::schema()->hasColumn('ms365_restore_runs', 'archive_bucket')) {
            $insert['archive_bucket'] = $data['archive_bucket'] ?? null;
        }
        if (Capsule::schema()->hasColumn('ms365_restore_runs', 'archive_size_bytes')) {
            $insert['archive_size_bytes'] = isset($data['archive_size_bytes']) ? (int) $data['archive_size_bytes'] : null;
        }
        if (Capsule::schema()->hasColumn('ms365_restore_runs', 'archive_expires_at')) {
            $insert['archive_expires_at'] = isset($data['archive_expires_at']) ? (int) $data['archive_expires_at'] : null;
        }

        Capsule::table('ms365_restore_runs')->insert($insert);

        return $id;
    }

    public static function get(string $id): ?array
    {
        $row = Capsule::table('ms365_restore_runs')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public static function update(string $id, array $fields): void
    {
        $fields = self::filterPersistedFields($fields);
        $fields['updated_at'] = time();
        if (isset($fields['selection_json']) && !is_string($fields['selection_json'])) {
            $fields['selection_json'] = self::encodeJson($fields['selection_json']);
        }
        if (isset($fields['scope_json']) && !is_string($fields['scope_json'])) {
            $fields['scope_json'] = self::encodeJson($fields['scope_json']);
        }
        Capsule::table('ms365_restore_runs')->where('id', $id)->update($fields);
    }

    /** @param array<string, mixed> $fields */
    private static function filterPersistedFields(array $fields): array
    {
        foreach ([
            'restore_mode',
            'archive_object_key',
            'archive_bucket',
            'archive_size_bytes',
            'archive_expires_at',
        ] as $column) {
            if (array_key_exists($column, $fields) && !Capsule::schema()->hasColumn('ms365_restore_runs', $column)) {
                unset($fields[$column]);
            }
        }

        return $fields;
    }

    public static function isRestoreRun(string $runId): bool
    {
        return Capsule::table('ms365_restore_runs')->where('id', $runId)->exists();
    }

    /** @param mixed $value */
    private static function encodeJson($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value ?? [], JSON_THROW_ON_ERROR);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
