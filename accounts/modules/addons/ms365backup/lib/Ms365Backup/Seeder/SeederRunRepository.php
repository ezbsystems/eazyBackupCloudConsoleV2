<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use WHMCS\Database\Capsule;

final class SeederRunRepository
{
    public static function create(string $profile, array $options): string
    {
        $id = self::uuid();
        Capsule::table('ms365_seeder_runs')->insert([
            'id' => $id,
            'status' => 'queued',
            'profile' => $profile,
            'options_json' => json_encode($options, JSON_THROW_ON_ERROR),
            'stats_json' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public static function get(string $id): ?array
    {
        $row = Capsule::table('ms365_seeder_runs')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /** @param array<string, mixed> $fields */
    public static function update(string $id, array $fields): void
    {
        Capsule::table('ms365_seeder_runs')->where('id', $id)->update($fields);
    }

    public static function markRunning(string $id): void
    {
        self::update($id, [
            'status' => 'running',
            'started_at' => time(),
        ]);
    }

    /** @param array<string, mixed> $stats */
    public static function markSuccess(string $id, array $stats): void
    {
        self::update($id, [
            'status' => 'success',
            'stats_json' => json_encode($stats, JSON_THROW_ON_ERROR),
            'finished_at' => time(),
        ]);
    }

    public static function markError(string $id, string $error, ?array $stats = null): void
    {
        self::update($id, [
            'status' => 'error',
            'error' => $error,
            'stats_json' => json_encode($stats ?? [], JSON_THROW_ON_ERROR),
            'finished_at' => time(),
        ]);
    }

    public static function requestCancel(string $id): bool
    {
        $run = self::get($id);
        if (!$run || !in_array($run['status'] ?? '', ['queued', 'running'], true)) {
            return false;
        }
        self::update($id, ['status' => 'cancelled', 'finished_at' => time()]);

        return true;
    }

    public static function isCancelled(string $id): bool
    {
        $run = self::get($id);

        return is_array($run) && ($run['status'] ?? '') === 'cancelled';
    }

    /** @return list<array<string, mixed>> */
    public static function listRecent(int $limit = 25): array
    {
        $rows = Capsule::table('ms365_seeder_runs')
            ->orderBy('created_at', 'desc')
            ->limit(max(1, min(100, $limit)))
            ->get();

        return array_map(static fn ($r) => (array) $r, $rows->all());
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
