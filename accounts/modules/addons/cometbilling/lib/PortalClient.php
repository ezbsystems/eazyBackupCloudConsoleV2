<?php
namespace CometBilling;

use GuzzleHttp\Client;

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

    private function post(string $path, array $form = []): array
    {
        $resp = $this->client()->post($path, [
            'headers'     => $this->headers(),
            'form_params' => array_merge(['format' => 'json', 'auth_type' => 'token'], $form),
            'read_timeout'=> $this->timeoutSeconds,
        ]);
        $json = (string) $resp->getBody();
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
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


