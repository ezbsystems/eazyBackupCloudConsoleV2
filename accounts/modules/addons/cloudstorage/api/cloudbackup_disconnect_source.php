<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200);
    $response->send();
    exit;
}

try {
    $clientId = (int) $ca->getUserID();
    $sourceId = isset($_POST['source_id']) ? (int) $_POST['source_id'] : 0;
    $revokeRemote = isset($_POST['revoke_remote']) ? (int) $_POST['revoke_remote'] : 0;

    if ($sourceId <= 0) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'source_id is required'], 200);
        $response->send();
        exit;
    }

    $source = Capsule::table('s3_cloudbackup_sources')
        ->where('id', $sourceId)
        ->where('client_id', $clientId)
        ->first();

    if (!$source) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Source not found'], 200);
        $response->send();
        exit;
    }

    // Optionally revoke at provider
    if ($revokeRemote === 1 && $source->provider === 'google_drive' && !empty($source->refresh_token_enc)) {
        // Load encryption key
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->pluck('value', 'setting');
        $encryptionKey = $settings['cloudbackup_encryption_key'] ?? ($settings['encryption_key'] ?? '');
        if ($encryptionKey) {
            try {
                $refreshToken = HelperController::decryptKey($source->refresh_token_enc, $encryptionKey);
                if (!empty($refreshToken)) {
                    $revokeUrl = 'https://oauth2.googleapis.com/revoke?token=' . urlencode($refreshToken);
                    $ch = curl_init($revokeUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($ch);
                    curl_close($ch);
                }
            } catch (\Exception $e) {
                // Ignore revoke errors, proceed to local revoke
            }
        }
    }

    Capsule::table('s3_cloudbackup_sources')
        ->where('id', $sourceId)
        ->update([
            'status' => 'revoked',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    $response = new JsonResponse(['status' => 'success'], 200);
    $response->send();
    exit;
} catch (\Exception $e) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Failed to disconnect source'], 200);
    $response->send();
    exit;
}


