<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * S3-compatible object storage (AWS S3, MinIO, etc.) using path-style keys mirroring local layout.
 */
final class S3CompatibleBackupStorage implements BackupStorageInterface
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $region = 'us-east-1',
    ) {
    }

    public function writeJson(string $absolutePath, array $data): void
    {
        $body = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->putObject($this->keyFromPath($absolutePath), $body, 'application/json');
    }

    public function readJson(string $absolutePath): ?array
    {
        $body = $this->getObject($this->keyFromPath($absolutePath));
        if ($body === null) {
            return null;
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function exists(string $absolutePath): bool
    {
        return $this->headObject($this->keyFromPath($absolutePath));
    }

    public function ensureDir(string $absolutePath): void
    {
        // Object stores have no directories; keys are created on put.
    }

    public function writeStream(string $absolutePath, $stream): void
    {
        $contents = stream_get_contents($stream);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read stream for S3 upload');
        }
        $this->putObject($this->keyFromPath($absolutePath), $contents, 'application/octet-stream');
    }

    private function keyFromPath(string $absolutePath): string
    {
        $base = rtrim(StorageLayout::BASE_PATH, '/');
        if (str_starts_with($absolutePath, $base . '/')) {
            return ltrim(substr($absolutePath, strlen($base)), '/');
        }

        return ltrim($absolutePath, '/');
    }

    private function putObject(string $key, string $body, string $contentType): void
    {
        $url = $this->objectUrl($key);
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $stringToSign = "PUT\n\n{$contentType}\n{$date}\n/{$this->bucket}/{$key}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));
        $auth = 'AWS ' . $this->accessKey . ':' . $signature;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Date: ' . $date,
                'Content-Type: ' . $contentType,
                'Authorization: ' . $auth,
            ],
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException("S3 PUT failed for {$key} (HTTP {$code})");
        }
    }

    private function getObject(string $key): ?string
    {
        $url = $this->objectUrl($key);
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $stringToSign = "GET\n\n\n{$date}\n/{$this->bucket}/{$key}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));
        $auth = 'AWS ' . $this->accessKey . ':' . $signature;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Date: ' . $date,
                'Authorization: ' . $auth,
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 404) {
            return null;
        }
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException("S3 GET failed for {$key} (HTTP {$code})");
        }

        return is_string($body) ? $body : null;
    }

    private function headObject(string $key): bool
    {
        $url = $this->objectUrl($key);
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $stringToSign = "HEAD\n\n\n{$date}\n/{$this->bucket}/{$key}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));
        $auth = 'AWS ' . $this->accessKey . ':' . $signature;

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Date: ' . $date,
                'Authorization: ' . $auth,
            ],
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code >= 200 && $code < 300;
    }

    private function objectUrl(string $key): string
    {
        $endpoint = rtrim($this->endpoint, '/');

        return $endpoint . '/' . $this->bucket . '/' . ltrim($key, '/');
    }
}
