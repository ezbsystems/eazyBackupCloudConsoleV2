<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphAccessTokenProvider;
use Ms365Backup\RegionEndpoints;
use GuzzleHttp\Client;

final class SeederDelegatedTokenProvider implements GraphAccessTokenProvider
{
    private ?string $cachedToken = null;
    private int $expiresAt = 0;

    public function __construct(
        private readonly string $region,
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
        private readonly ?Client $http = null,
    ) {
    }

    public static function fromConfig(): self
    {
        $creds = SeederConfigRepository::credentials();

        return new self(
            $creds['region'],
            $creds['tenant_id'],
            $creds['client_id'],
            $creds['client_secret'],
            SeederConfigRepository::refreshToken(),
        );
    }

    public function getAccessToken(): string
    {
        if ($this->cachedToken !== null && time() < ($this->expiresAt - 60)) {
            return $this->cachedToken;
        }

        $endpoints = RegionEndpoints::forRegion($this->region);
        $url = rtrim($endpoints['login'], '/') . '/' . rawurlencode($this->tenantId) . '/oauth2/v2.0/token';
        $scope = implode(' ', SeederEntraConfig::delegatedScopes());

        $client = $this->http ?? new Client(['timeout' => 30]);
        $response = $client->post($url, [
            'form_params' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
                'scope' => $scope,
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if (!is_array($body) || empty($body['access_token'])) {
            $err = is_array($body) ? ($body['error_description'] ?? $body['error'] ?? 'unknown') : 'invalid response';
            throw new \RuntimeException('Failed to refresh delegated token: ' . (string) $err);
        }

        $this->cachedToken = (string) $body['access_token'];
        $this->expiresAt = time() + (int) ($body['expires_in'] ?? 3600);

        if (!empty($body['refresh_token'])) {
            SeederConfigRepository::saveDelegatedUser(
                (string) (SeederConfigRepository::get()['seed_user_upn'] ?? ''),
                (string) (SeederConfigRepository::get()['seed_user_id'] ?? ''),
                (string) $body['refresh_token'],
                $this->expiresAt,
            );
        }

        return $this->cachedToken;
    }
}
