<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;
use Ms365Backup\RegionEndpoints;
use GuzzleHttp\Client;

final class SeederOAuthService
{
    private const STATE_TTL = 3600;

    public static function buildAuthorizeUrl(): string
    {
        $creds = SeederConfigRepository::credentials();
        $endpoints = RegionEndpoints::forRegion($creds['region']);
        $state = self::encodeState([
            'ts' => time(),
            'nonce' => bin2hex(random_bytes(16)),
        ]);

        $params = [
            'client_id' => $creds['client_id'],
            'response_type' => 'code',
            'redirect_uri' => SeederEntraConfig::redirectUri(),
            'response_mode' => 'query',
            'scope' => implode(' ', SeederEntraConfig::delegatedScopes()),
            'state' => $state,
        ];

        return rtrim($endpoints['login'], '/') . '/' . rawurlencode($creds['tenant_id'])
            . '/oauth2/v2.0/authorize?' . http_build_query($params);
    }

    /** @return array{upn: string, user_id: string} */
    public static function handleCallback(array $query): array
    {
        self::decodeState((string) ($query['state'] ?? ''));

        $error = (string) ($query['error'] ?? '');
        if ($error !== '') {
            throw new \RuntimeException('OAuth failed: ' . ((string) ($query['error_description'] ?? $error)));
        }

        $code = trim((string) ($query['code'] ?? ''));
        if ($code === '') {
            throw new \RuntimeException('Missing authorization code');
        }

        $creds = SeederConfigRepository::credentials();
        $endpoints = RegionEndpoints::forRegion($creds['region']);
        $tokenUrl = rtrim($endpoints['login'], '/') . '/' . rawurlencode($creds['tenant_id']) . '/oauth2/v2.0/token';

        $client = new Client(['timeout' => 30]);
        $response = $client->post($tokenUrl, [
            'form_params' => [
                'client_id' => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'code' => $code,
                'redirect_uri' => SeederEntraConfig::redirectUri(),
                'grant_type' => 'authorization_code',
                'scope' => implode(' ', SeederEntraConfig::delegatedScopes()),
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if (!is_array($body) || empty($body['access_token']) || empty($body['refresh_token'])) {
            $err = is_array($body) ? ($body['error_description'] ?? $body['error'] ?? 'unknown') : 'invalid response';
            throw new \RuntimeException('Token exchange failed: ' . (string) $err);
        }

        $graph = new GraphClient(
            new class ((string) $body['access_token']) implements \Ms365Backup\GraphAccessTokenProvider {
                public function __construct(private readonly string $token) {}
                public function getAccessToken(): string { return $this->token; }
            },
            $creds['region'],
        );
        $me = $graph->get('me', ['$select' => 'id,userPrincipalName,displayName']);
        $upn = (string) ($me['userPrincipalName'] ?? '');
        $userId = (string) ($me['id'] ?? '');
        if ($upn === '' || $userId === '') {
            throw new \RuntimeException('Could not resolve seed user profile');
        }

        $expiresAt = time() + (int) ($body['expires_in'] ?? 3600);
        SeederConfigRepository::saveDelegatedUser($upn, $userId, (string) $body['refresh_token'], $expiresAt);

        return ['upn' => $upn, 'user_id' => $userId];
    }

    /** @param array<string, mixed> $payload */
    private static function encodeState(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha256', $json, self::stateSecret());

        return rtrim(strtr(base64_encode($json . '|' . $sig), '+/', '-_'), '=');
    }

    /** @return array<string, mixed> */
    private static function decodeState(string $raw): array
    {
        if ($raw === '') {
            throw new \RuntimeException('Missing OAuth state');
        }
        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);
        if ($decoded === false || !str_contains($decoded, '|')) {
            throw new \RuntimeException('Invalid OAuth state');
        }
        [$json, $sig] = explode('|', $decoded, 2);
        $expected = hash_hmac('sha256', $json, self::stateSecret());
        if (!hash_equals($expected, $sig)) {
            throw new \RuntimeException('OAuth state signature mismatch');
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid OAuth state payload');
        }
        $ts = (int) ($data['ts'] ?? 0);
        if ($ts <= 0 || (time() - $ts) > self::STATE_TTL) {
            throw new \RuntimeException('OAuth state expired');
        }

        return $data;
    }

    private static function stateSecret(): string
    {
        if (class_exists(\WHMCS\Config\Setting::class)) {
            $hash = (string) \WHMCS\Config\Setting::getValue('cc_encryption_hash');
            if ($hash !== '') {
                return $hash;
            }
        }

        return 'ms365-seeder-oauth-state-dev';
    }
}
