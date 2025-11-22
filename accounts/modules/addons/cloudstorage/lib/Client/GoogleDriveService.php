<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

class GoogleDriveService
{
    /**
     * Exchange a Google OAuth refresh token for a short-lived access token.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $refreshToken
     * @return array [status, access_token, expires_in, token_type, expiry, message]
     */
    public static function exchangeRefreshToken($clientId, $clientSecret, $refreshToken)
    {
        $url = 'https://oauth2.googleapis.com/token';
        $postFields = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        $resp = self::httpRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded'
        ], $postFields, 15);

        if ($resp['status'] !== 'success') {
            return $resp;
        }

        $data = json_decode($resp['body'], true);
        if (!is_array($data) || empty($data['access_token'])) {
            return ['status' => 'fail', 'message' => 'Invalid token response'];
        }

        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 0;
        $expiry = $expiresIn > 0 ? date('c', time() + $expiresIn - 30) : null; // safety skew

        return [
            'status' => 'success',
            'access_token' => $data['access_token'],
            'expires_in' => $expiresIn,
            'token_type' => $data['token_type'] ?? 'Bearer',
            'expiry' => $expiry,
        ];
    }

    /**
     * List Shared Drives.
     *
     * @param string $accessToken
     * @param string|null $pageToken
     * @param int $pageSize
     * @return array [status, items, nextPageToken, message]
     */
    public static function listSharedDrives($accessToken, $pageToken = null, $pageSize = 100)
    {
        $params = [
            'pageSize' => max(1, min(200, (int)$pageSize)),
            'fields' => 'nextPageToken,drives(id,name)',
        ];
        if (!empty($pageToken)) {
            $params['pageToken'] = $pageToken;
        }
        $url = 'https://www.googleapis.com/drive/v3/drives?' . http_build_query($params);
        $resp = self::httpRequest('GET', $url, [
            'Authorization: Bearer ' . $accessToken,
        ], null, 15);
        if ($resp['status'] !== 'success') {
            return $resp;
        }
        $data = json_decode($resp['body'], true);
        if (!is_array($data)) {
            return ['status' => 'fail', 'message' => 'Invalid response'];
        }
        $items = [];
        foreach ($data['drives'] ?? [] as $d) {
            $items[] = [
                'id' => $d['id'] ?? '',
                'name' => $d['name'] ?? '',
            ];
        }
        return [
            'status' => 'success',
            'items' => $items,
            'nextPageToken' => $data['nextPageToken'] ?? null,
        ];
    }

    /**
     * List child folders under a parent for My Drive or a Shared Drive.
     *
     * @param string $accessToken
     * @param string $parentId 'root' for My Drive
     * @param array $opts ['driveId' => string|null, 'q' => string|null, 'pageToken' => string|null, 'pageSize' => int]
     * @return array [status, items, nextPageToken, message]
     */
    public static function listChildFolders($accessToken, $parentId, array $opts = [])
    {
        $driveId = $opts['driveId'] ?? null;
        $pageToken = $opts['pageToken'] ?? null;
        $pageSize = isset($opts['pageSize']) ? (int)$opts['pageSize'] : 100;
        $pageSize = max(1, min(200, $pageSize));
        $search = isset($opts['q']) ? trim((string)$opts['q']) : '';

        $q = sprintf("mimeType='application/vnd.google-apps.folder' and '%s' in parents and trashed=false", self::escapeQueryLiteral($parentId));
        if ($search !== '') {
            $q .= " and name contains '" . self::escapeQueryLiteral($search) . "'";
        }

        $params = [
            'q' => $q,
            'fields' => 'nextPageToken,files(id,name,parents,driveId,mimeType)',
            'pageSize' => $pageSize,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
            // Use a supported orderBy; 'name' is valid for Drive v3
            'orderBy' => 'name',
        ];
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }
        if ($driveId) {
            $params['corpora'] = 'drive';
            $params['driveId'] = $driveId;
        } else {
            $params['corpora'] = 'user';
        }

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);
        $resp = self::httpRequest('GET', $url, [
            'Authorization: Bearer ' . $accessToken,
        ], null, 20);
        if ($resp['status'] !== 'success') {
            return $resp;
        }
        $data = json_decode($resp['body'], true);
        if (!is_array($data)) {
            return ['status' => 'fail', 'message' => 'Invalid response'];
        }
        $items = [];
        foreach ($data['files'] ?? [] as $f) {
            if (($f['mimeType'] ?? '') !== 'application/vnd.google-apps.folder') {
                continue;
            }
            $items[] = [
                'id' => $f['id'] ?? '',
                'name' => $f['name'] ?? '',
                'parents' => $f['parents'] ?? [],
                'driveId' => $f['driveId'] ?? null,
            ];
        }
        return [
            'status' => 'success',
            'items' => $items,
            'nextPageToken' => $data['nextPageToken'] ?? null,
        ];
    }

    private static function httpRequest($method, $url, array $headers = [], $body = null, $timeout = 15)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $err) {
            return ['status' => 'fail', 'message' => 'HTTP error: ' . ($err ?: 'Unknown error')];
        }
        if ($httpCode >= 400) {
            return ['status' => 'fail', 'message' => 'HTTP ' . $httpCode, 'body' => $responseBody];
        }
        return ['status' => 'success', 'body' => $responseBody, 'httpCode' => $httpCode];
    }

    /**
     * Escape a literal for use inside single-quoted Drive query.
     */
    private static function escapeQueryLiteral($literal)
    {
        return str_replace("'", "\\'", (string)$literal);
    }
}


