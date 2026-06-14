<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\TenantRepository;
use WHMCS\Database\Capsule;

final class SeederConfigRepository
{
    private const ROW_ID = 1;

    public static function get(): ?array
    {
        if (!class_exists(Capsule::class)) {
            return null;
        }
        $row = Capsule::table('ms365_seeder_config')->where('id', self::ROW_ID)->first();

        return $row ? (array) $row : null;
    }

    public static function save(array $data): void
    {
        $existing = self::get();
        $secretEnc = $existing['app_secret_enc'] ?? null;
        if (isset($data['app_secret']) && (string) $data['app_secret'] !== '') {
            $secretEnc = TenantRepository::encryptSecret((string) $data['app_secret']);
        } elseif (isset($data['app_secret_enc'])) {
            $secretEnc = $data['app_secret_enc'];
        }

        $payload = [
            'region' => (string) ($data['region'] ?? 'GlobalPublicCloud'),
            'tenant_id' => trim((string) ($data['tenant_id'] ?? '')),
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'app_secret_enc' => $secretEnc,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        self::upsert($payload);
    }

    public static function saveDelegatedUser(string $upn, string $userId, string $refreshToken, ?int $expiresAt): void
    {
        $payload = [
            'seed_user_upn' => trim($upn),
            'seed_user_id' => trim($userId),
            'refresh_token_enc' => TenantRepository::encryptSecret($refreshToken),
            'token_expires_at' => $expiresAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        self::upsert($payload);
    }

    public static function clearDelegatedUser(): void
    {
        self::upsert([
            'seed_user_upn' => '',
            'seed_user_id' => '',
            'refresh_token_enc' => null,
            'token_expires_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{region: string, tenant_id: string, client_id: string, client_secret: string} */
    public static function credentials(?array $row = null): array
    {
        $row = $row ?? self::get();
        if (!$row) {
            throw new \RuntimeException('Seeder app credentials are not configured');
        }
        $secret = TenantRepository::decryptSecret($row['app_secret_enc'] ?? null);
        if ($secret === '' || ($row['tenant_id'] ?? '') === '' || ($row['client_id'] ?? '') === '') {
            throw new \RuntimeException('Seeder app credentials are incomplete');
        }

        return [
            'region' => (string) ($row['region'] ?? 'GlobalPublicCloud'),
            'tenant_id' => (string) $row['tenant_id'],
            'client_id' => (string) $row['client_id'],
            'client_secret' => $secret,
        ];
    }

    public static function hasDelegatedUser(): bool
    {
        $row = self::get();

        return is_array($row)
            && ($row['refresh_token_enc'] ?? '') !== ''
            && ($row['seed_user_upn'] ?? '') !== '';
    }

    public static function refreshToken(): string
    {
        $row = self::get();
        if (!$row || ($row['refresh_token_enc'] ?? '') === '') {
            throw new \RuntimeException('Seed user is not connected');
        }
        $token = TenantRepository::decryptSecret($row['refresh_token_enc']);
        if ($token === '') {
            throw new \RuntimeException('Seed user refresh token is invalid');
        }

        return $token;
    }

    /** @param array<string, mixed> $payload */
    private static function upsert(array $payload): void
    {
        if (!class_exists(Capsule::class)) {
            throw new \RuntimeException('Database not available');
        }
        $exists = Capsule::table('ms365_seeder_config')->where('id', self::ROW_ID)->exists();
        if ($exists) {
            Capsule::table('ms365_seeder_config')->where('id', self::ROW_ID)->update($payload);
        } else {
            Capsule::table('ms365_seeder_config')->insert(array_merge(['id' => self::ROW_ID], $payload));
        }
    }
}
