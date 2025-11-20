<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

class CloudBackupOAuthService
{
    public static function buildGoogleAuthUrl(string $clientId, string $redirectUri, string $scopes, string $state): string
    {
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => trim($scopes),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function exchangeCodeForTokens(string $clientId, string $clientSecret, string $code, string $redirectUri): array
    {
        $postFields = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ], '', '&', PHP_QUERY_RFC3986);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $tokenResp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || !$tokenResp) {
            throw new \RuntimeException('Token exchange failed');
        }
        $json = json_decode($tokenResp, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Invalid token response');
        }
        return $json;
    }

    public static function fetchGoogleUserInfo(string $accessToken): array
    {
        $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode < 200 || $httpCode >= 300 || !$resp) {
            return [];
        }
        $json = json_decode($resp, true);
        return is_array($json) ? $json : [];
    }
}


