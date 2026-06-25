<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

/**
 * HTTP client from dev WHMCS to production fleet_remote API.
 */
final class FleetRemoteClient
{
    /** @param array<string, scalar|null> $params */
    public static function get(string $op, array $params = []): array
    {
        $url = FleetContext::remoteFleetApiUrl($op);
        if ($params !== []) {
            $url .= '&' . http_build_query($params);
        }

        return self::request('GET', $url);
    }

    /** @param array<string, scalar|null> $params */
    public static function post(string $op, array $params = [], ?string $uploadPath = null, string $uploadField = 'artifact'): array
    {
        $url = FleetContext::remoteFleetApiUrl($op);

        return self::request('POST', $url, $params, $uploadPath, $uploadField);
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array<string, mixed>
     */
    private static function request(
        string $method,
        string $url,
        array $params = [],
        ?string $uploadPath = null,
        string $uploadField = 'artifact'
    ): array {
        if (!FleetContext::isDevelopmentServer()) {
            throw new \RuntimeException('Remote fleet client is only used from the development server');
        }
        $token = FleetRemoteAuth::sharedToken();
        if ($token === '') {
            throw new \RuntimeException('ms365_fleet_deploy_shared_secret is not configured on the development server');
        }

        $headers = FleetRemoteAuth::authHeaders();
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl init failed');
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $uploadPath !== null ? 600 : 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($uploadPath !== null && is_file($uploadPath)) {
                $params[$uploadField] = new \CURLFile($uploadPath, 'application/octet-stream', basename($uploadPath));
                $opts[CURLOPT_POSTFIELDS] = $params;
            } else {
                $opts[CURLOPT_POSTFIELDS] = http_build_query(array_map(
                    static fn ($v) => $v === null ? '' : (string) $v,
                    $params
                ));
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $opts[CURLOPT_HTTPHEADER] = $headers;
            }
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Remote fleet request failed' . ($err !== '' ? ': ' . $err : ''));
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $snippet = trim(preg_replace('/\s+/', ' ', substr(strip_tags((string) $raw), 0, 160)));
            $detail = 'Remote fleet returned non-JSON (HTTP ' . $code . ')';
            if ($snippet !== '') {
                $detail .= ': ' . $snippet;
            }

            throw new \RuntimeException($detail);
        }
        if ($code >= 400 || empty($decoded['ok'])) {
            $message = (string) ($decoded['error'] ?? $decoded['message'] ?? 'Remote fleet error HTTP ' . $code);
            throw new \RuntimeException($message);
        }

        return $decoded;
    }
}
