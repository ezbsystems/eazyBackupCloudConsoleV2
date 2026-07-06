<?php
namespace CometBilling;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class PortalClient
{
    public function __construct(
        private string $baseUrl,
        private string $authType, // 'token'
        private string $token,
        private int $timeoutSeconds = 60
    ) {}

    private function client(): Client
    {
        return new Client([
            'base_uri'        => rtrim($this->baseUrl, '/'),
            'timeout'         => $this->timeoutSeconds,
            'connect_timeout' => 15,
            'http_errors'     => false,
        ]);
    }

    private function headers(): array
    {
        $h = ['Accept' => 'application/json'];
        if ($this->authType === 'token' && $this->token) {
            $h['Authorization'] = 'Bearer ' . $this->token;
        }
        return $h;
    }

  /**
     * @throws \RuntimeException on HTTP or transport errors
     */
    private function post(string $path, array $form = []): array
    {
        try {
            $resp = $this->client()->post($path, [
                'headers'      => $this->headers(),
                'form_params'  => array_merge(['format' => 'json', 'auth_type' => 'token'], $form),
                'read_timeout' => $this->timeoutSeconds,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "Portal API request failed ({$path}): " . $e->getMessage(),
                0,
                $e
            );
        }

        $status = $resp->getStatusCode();
        $body = (string) $resp->getBody();

        if ($status < 200 || $status >= 300) {
            $snippet = mb_substr(trim($body), 0, 200);
            throw new \RuntimeException(
                "Portal API returned HTTP {$status} for {$path}" . ($snippet !== '' ? ": {$snippet}" : '')
            );
        }

        if ($body === '') {
            throw new \RuntimeException("Portal API returned empty response for {$path}");
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException(
                "Portal API returned non-JSON response for {$path}: " . mb_substr($body, 0, 200)
            );
        }

        return $data;
    }

    public function reportBillingHistory(array $params = []): array
    {
        return $this->post('/api/v1/report/billing_history', $params);
    }

    public function reportActiveServices(array $params = []): array
    {
        return $this->post('/api/v1/report/active_services', $params);
    }
}
