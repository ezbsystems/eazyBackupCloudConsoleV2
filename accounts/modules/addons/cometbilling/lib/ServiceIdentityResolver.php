<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Resolve device identity from usage rows against comet_devices.
 */
class ServiceIdentityResolver
{
    /**
     * @return array{
     *   status: string,
     *   device_hash: ?string,
     *   account: ?string,
     *   device_name: ?string,
     *   match_method: ?string
     * }
     */
    public static function resolve(?string $deviceId, ?string $account, ?string $itemDesc = null): array
    {
        $deviceId = strtolower(trim((string) $deviceId));
        $account = trim((string) $account);
        $index = self::loadIndex();

        if ($deviceId !== '' && isset($index['by_hash'][$deviceId])) {
            $d = $index['by_hash'][$deviceId];
            return [
                'status' => 'exact',
                'device_hash' => $deviceId,
                'account' => $account !== '' ? $account : $d['username'],
                'device_name' => $d['name'],
                'match_method' => 'full_hash',
            ];
        }

        if ($deviceId === '' && $itemDesc !== null) {
            $extracted = self::extractDeviceFromDesc($itemDesc);
            if ($extracted !== null) {
                return self::resolve($extracted, $account, null);
            }
        }

        if ($deviceId === '') {
            return self::unmatched();
        }

        $prefix = strlen($deviceId) >= 6 ? substr($deviceId, 0, 6) : $deviceId;
        $candidates = $index['by_prefix'][$prefix] ?? [];
        if ($candidates === []) {
            return self::unmatched();
        }

        if (count($candidates) === 1) {
            $d = $candidates[0];
            return [
                'status' => 'unique_prefix',
                'device_hash' => $d['hash'],
                'account' => $account !== '' ? $account : $d['username'],
                'device_name' => $d['name'],
                'match_method' => 'prefix_unique',
            ];
        }

        if ($account !== '') {
            $normAccount = self::normalizeName($account);
            $matched = [];
            foreach ($candidates as $candidate) {
                $normUser = self::normalizeName($candidate['username'] ?? '');
                if ($normUser === $normAccount
                    || str_contains($normUser, $normAccount)
                    || str_contains($normAccount, $normUser)) {
                    $matched[] = $candidate;
                }
            }
            if (count($matched) === 1) {
                $d = $matched[0];
                return [
                    'status' => 'account_disambiguated',
                    'device_hash' => $d['hash'],
                    'account' => $account,
                    'device_name' => $d['name'],
                    'match_method' => 'prefix_account',
                ];
            }
            if (count($matched) > 1) {
                return [
                    'status' => 'ambiguous',
                    'device_hash' => null,
                    'account' => $account,
                    'device_name' => null,
                    'match_method' => 'prefix_account_collision',
                ];
            }
        }

        return [
            'status' => 'ambiguous',
            'device_hash' => null,
            'account' => $account !== '' ? $account : null,
            'device_name' => null,
            'match_method' => 'prefix_collision',
        ];
    }

    /**
     * @return array{by_hash: array<string, array>, by_prefix: array<string, list<array>>}
     */
    public static function loadIndex(): array
    {
        $byHash = [];
        $byPrefix = [];

        if (!Capsule::schema()->hasTable('comet_devices')) {
            return ['by_hash' => $byHash, 'by_prefix' => $byPrefix];
        }

        $rows = Capsule::table('comet_devices')
            ->select(['hash', 'username', 'name', 'revoked_at', 'content'])
            ->get();

        foreach ($rows as $row) {
            $hash = strtolower((string) $row->hash);
            $registrationAt = null;
            $content = json_decode((string) ($row->content ?? ''), true);
            if (is_array($content)) {
                $rt = (int) ($content['RegistrationTime'] ?? 0);
                if ($rt > 0) {
                    $registrationAt = gmdate('Y-m-d', $rt);
                }
            }
            $entry = [
                'hash' => $hash,
                'username' => (string) $row->username,
                'name' => $row->name,
                'revoked_at' => $row->revoked_at,
                'registered_at' => $registrationAt,
            ];
            $byHash[$hash] = $entry;
            $prefix = substr($hash, 0, 6);
            $byPrefix[$prefix][] = $entry;
        }

        return ['by_hash' => $byHash, 'by_prefix' => $byPrefix];
    }

    private static function unmatched(): array
    {
        return [
            'status' => 'unmatched',
            'device_hash' => null,
            'account' => null,
            'device_name' => null,
            'match_method' => null,
        ];
    }

    private static function extractDeviceFromDesc(string $desc): ?string
    {
        if (preg_match('/Device\s+ID:\s*([a-f0-9]+)/i', $desc, $m)) {
            return strtolower(trim($m[1]));
        }

        return null;
    }

    private static function normalizeName(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/', '', $name));
    }
}
