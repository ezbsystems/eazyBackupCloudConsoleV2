<?php
declare(strict_types=1);

namespace Ms365Backup;

use PDO;
use WHMCS\Database\Capsule;

final class TenantRepository
{
    private const ROW_ID = 1;

    public static function get(): ?array
    {
        if (class_exists(Capsule::class)) {
            $row = Capsule::table('ms365_tenant_config')->where('id', self::ROW_ID)->first();
            return $row ? (array) $row : null;
        }
        return null;
    }

    public static function getFromPdo(PDO $pdo): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM ms365_tenant_config WHERE id = ? LIMIT 1');
        $stmt->execute([self::ROW_ID]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function save(array $data): void
    {
        $secretEnc = null;
        if (isset($data['app_secret']) && $data['app_secret'] !== '') {
            $secretEnc = self::encryptSecret((string) $data['app_secret']);
        } elseif (isset($data['app_secret_enc'])) {
            $secretEnc = $data['app_secret_enc'];
        } else {
            $existing = self::get();
            $secretEnc = $existing['app_secret_enc'] ?? null;
        }

        $payload = [
            'region' => (string) ($data['region'] ?? 'GlobalPublicCloud'),
            'tenant_id' => trim((string) ($data['tenant_id'] ?? '')),
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'app_secret_enc' => $secretEnc,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (class_exists(Capsule::class)) {
            $exists = Capsule::table('ms365_tenant_config')->where('id', self::ROW_ID)->exists();
            if ($exists) {
                Capsule::table('ms365_tenant_config')->where('id', self::ROW_ID)->update($payload);
            } else {
                Capsule::table('ms365_tenant_config')->insert(array_merge(['id' => self::ROW_ID], $payload));
            }
            return;
        }

        throw new \RuntimeException('Database not available');
    }

    public static function decryptSecret(?string $enc): string
    {
        if ($enc === null || $enc === '') {
            return '';
        }
        if (function_exists('decrypt')) {
            try {
                return (string) decrypt($enc);
            } catch (\Throwable $e) {
                return '';
            }
        }
        return '';
    }

    public static function encryptSecret(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        if (function_exists('encrypt')) {
            return (string) encrypt($plain);
        }
        throw new \RuntimeException('WHMCS encrypt() is not available');
    }

    /** @return array{region: string, tenant_id: string, client_id: string, client_secret: string} */
    public static function credentials(?array $row = null): array
    {
        $row = $row ?? self::get();
        if (!$row) {
            throw new \RuntimeException('Tenant credentials are not configured');
        }
        $secret = self::decryptSecret($row['app_secret_enc'] ?? null);
        if ($secret === '' || ($row['tenant_id'] ?? '') === '' || ($row['client_id'] ?? '') === '') {
            throw new \RuntimeException('Tenant credentials are incomplete');
        }
        return [
            'region' => (string) ($row['region'] ?? 'GlobalPublicCloud'),
            'tenant_id' => (string) $row['tenant_id'],
            'client_id' => (string) $row['client_id'],
            'client_secret' => $secret,
        ];
    }
}
