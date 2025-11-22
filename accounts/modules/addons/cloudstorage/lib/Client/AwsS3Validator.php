<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

class AwsS3Validator
{
    /**
     * Validate that an S3/AWS or S3-compatible bucket exists and credentials are valid.
     * Performs a signed GET ListObjectsV2 with max-keys=0.
     *
     * @param array $cfg ['endpoint' => string|null, 'region' => string, 'bucket' => string, 'access_key' => string, 'secret_key' => string]
     * @return array ['status' => 'success'] or ['status' => 'fail', 'code' => 'auth|not_found|network|other', 'message' => string]
     */
    public static function validateBucketExists(array $cfg): array
    {
        $endpoint = trim((string)($cfg['endpoint'] ?? ''));
        $region = trim((string)($cfg['region'] ?? 'us-east-1'));
        $bucket = trim((string)($cfg['bucket'] ?? ''));
        $accessKey = (string)($cfg['access_key'] ?? '');
        $secretKey = (string)($cfg['secret_key'] ?? '');

        if ($bucket === '' || $accessKey === '' || $secretKey === '') {
            return ['status' => 'fail', 'code' => 'other', 'message' => 'Missing bucket or credentials'];
        }

        $isAws = ($endpoint === '' || stripos($endpoint, 'amazonaws.com') !== false);
        $scheme = 'https';
        $host = '';
        $path = '';
        if ($isAws) {
            // Virtual-host style for AWS
            $awsHost = "s3.{$region}.amazonaws.com";
            $host = "{$bucket}.{$awsHost}";
            $path = '/';
        } else {
            $parts = parse_url($endpoint);
            if (!is_array($parts) || !isset($parts['host'])) {
                return ['status' => 'fail', 'code' => 'other', 'message' => 'Invalid endpoint'];
            }
            $host = $parts['host'];
            if (isset($parts['scheme']) && $parts['scheme'] !== '') {
                $scheme = $parts['scheme'];
            }
            $path = '/' . $bucket;
        }

        $query = [
            'list-type' => '2',
            'max-keys' => '0',
        ];
        $queryStr = self::canonicalQueryString($query);
        $url = "{$scheme}://{$host}{$path}" . ($queryStr !== '' ? "?{$queryStr}" : '');

        // SigV4
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', '');
        $canonicalURI = $path;
        $canonicalQuery = $queryStr;
        $canonicalHeaders = "host:{$host}\n" . "x-amz-content-sha256:{$payloadHash}\n" . "x-amz-date:{$amzDate}\n";
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";
        $canonicalRequest = "GET\n{$canonicalURI}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$dateStamp}/{$region}/s3/aws4_request";
        $stringToSign = $algorithm . "\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signature = self::sign($secretKey, $dateStamp, $region, 's3', $stringToSign);
        $authorization = "{$algorithm} Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $headers = [
            "Authorization: {$authorization}",
            "x-amz-date: {$amzDate}",
            "x-amz-content-sha256: {$payloadHash}",
            "Host: {$host}",
            "Accept: */*",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['status' => 'fail', 'code' => 'network', 'message' => "Network error: {$err}"];
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr($resp, 0, $headerSize);
        $body = substr($resp, $headerSize);
        curl_close($ch);

        $lowBody = strtolower($body);
        if ($status === 200 || $status === 204) {
            return ['status' => 'success'];
        }
        if ($status === 404 || strpos($lowBody, 'nosuchbucket') !== false) {
            return ['status' => 'fail', 'code' => 'not_found', 'message' => 'Bucket not found'];
        }
        if (in_array($status, [301, 400, 401, 403], true)) {
            // detect region mismatch
            if (preg_match('/^x-amz-bucket-region:\s*([^\r\n]+)/mi', $rawHeaders, $m)) {
                $hdrRegion = trim($m[1]);
                if (strcasecmp($hdrRegion, $region) !== 0) {
                    return ['status' => 'fail', 'code' => 'auth', 'message' => 'Region mismatch'];
                }
            }
            if (strpos($lowBody, 'signaturedoesnotmatch') !== false) {
                return ['status' => 'fail', 'code' => 'auth', 'message' => 'Signature mismatch'];
            }
            if (strpos($lowBody, 'invalidaccesskeyid') !== false) {
                return ['status' => 'fail', 'code' => 'auth', 'message' => 'Invalid access key'];
            }
            if (strpos($lowBody, 'accessdenied') !== false) {
                return ['status' => 'fail', 'code' => 'auth', 'message' => 'Access denied'];
            }
            return ['status' => 'fail', 'code' => 'auth', 'message' => 'Authentication failed'];
        }

        return ['status' => 'fail', 'code' => 'other', 'message' => "Unexpected status {$status}"];
    }

    private static function canonicalQueryString(array $query): string
    {
        ksort($query);
        $parts = [];
        foreach ($query as $k => $v) {
            $parts[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
        }
        return implode('&', $parts);
    }

    private static function hmacSHA256(string $key, string $data): string
    {
        return hash_hmac('sha256', $data, $key, true);
    }

    private static function getSignatureKey(string $secret, string $dateStamp, string $region, string $service): string
    {
        $kDate = self::hmacSHA256('AWS4' . $secret, $dateStamp);
        $kRegion = self::hmacSHA256($kDate, $region);
        $kService = self::hmacSHA256($kRegion, $service);
        $kSigning = self::hmacSHA256($kService, 'aws4_request');
        return $kSigning;
    }

    private static function sign(string $secret, string $dateStamp, string $region, string $service, string $stringToSign): string
    {
        $kSigning = self::getSignatureKey($secret, $dateStamp, $region, $service);
        return strtolower(bin2hex(hash_hmac('sha256', $stringToSign, $kSigning, true)));
    }
}


