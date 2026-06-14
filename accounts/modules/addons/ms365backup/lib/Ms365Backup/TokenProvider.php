<?php
declare(strict_types=1);

namespace Ms365Backup;

use GuzzleHttp\Client;

final class TokenProvider implements GraphAccessTokenProvider
{
    private ?string $cachedToken = null;
    private int $expiresAt = 0;

    public function __construct(
        private readonly string $region,
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly ?Client $http = null,
    ) {
    }

    public function getAccessToken(): string
    {
        if ($this->cachedToken !== null && time() < ($this->expiresAt - 60)) {
            return $this->cachedToken;
        }

        $endpoints = RegionEndpoints::forRegion($this->region);
        $url = rtrim($endpoints['login'], '/') . '/' . rawurlencode($this->tenantId) . '/oauth2/v2.0/token';
        $scope = rtrim($endpoints['graph'], '/') . '/.default';

        $client = $this->http ?? new Client(['timeout' => 30]);
        $response = $client->post($url, [
            'form_params' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $scope,
                'grant_type' => 'client_credentials',
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if (!is_array($body) || empty($body['access_token'])) {
            $err = is_array($body) ? ($body['error_description'] ?? $body['error'] ?? 'unknown') : 'invalid response';
            throw new \RuntimeException('Failed to obtain access token: ' . (string) $err);
        }

        $this->cachedToken = (string) $body['access_token'];
        $this->expiresAt = time() + (int) ($body['expires_in'] ?? 3600);
        return $this->cachedToken;
    }

    public static function fromTenantRow(?array $row = null): self
    {
        $creds = TenantRepository::credentials($row);
        return new self(
            $creds['region'],
            $creds['tenant_id'],
            $creds['client_id'],
            $creds['client_secret'],
        );
    }
}
