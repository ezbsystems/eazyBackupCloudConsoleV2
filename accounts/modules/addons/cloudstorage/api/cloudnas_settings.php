<?php
/**
 * Cloud NAS - Settings
 * Get or save Cloud NAS settings for the current client
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

// Default settings
$defaultSettings = [
    'cache_mode' => 'full',
    'cache_size_gb' => 10,
    'bandwidth_limit_enabled' => false,
    'bandwidth_download_kbps' => 0,
    'bandwidth_upload_kbps' => 0,
    'auto_mount' => true,
    'default_read_only' => false
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Save settings
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            (new JsonResponse(['status' => 'error', 'message' => 'Invalid request'], 200))->send();
            exit;
        }

        // Validate and sanitize settings
        $settings = [
            'cache_mode' => in_array($input['cache_mode'] ?? '', ['off', 'minimal', 'writes', 'full']) 
                ? $input['cache_mode'] 
                : 'full',
            'cache_size_gb' => max(1, min(500, intval($input['cache_size_gb'] ?? 10))),
            'bandwidth_limit_enabled' => !empty($input['bandwidth_limit_enabled']),
            'bandwidth_download_kbps' => max(0, intval($input['bandwidth_download_kbps'] ?? 0)),
            'bandwidth_upload_kbps' => max(0, intval($input['bandwidth_upload_kbps'] ?? 0)),
            'auto_mount' => isset($input['auto_mount']) ? !empty($input['auto_mount']) : true,
            'default_read_only' => !empty($input['default_read_only'])
        ];

        // Check if settings row exists
        $exists = Capsule::table('s3_cloudnas_settings')
            ->where('client_id', $clientId)
            ->exists();

        if ($exists) {
            Capsule::table('s3_cloudnas_settings')
                ->where('client_id', $clientId)
                ->update([
                    'settings_json' => json_encode($settings),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            Capsule::table('s3_cloudnas_settings')->insert([
                'client_id' => $clientId,
                'settings_json' => json_encode($settings),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        (new JsonResponse(['status' => 'success', 'message' => 'Settings saved'], 200))->send();
    } else {
        // Get settings
        $row = Capsule::table('s3_cloudnas_settings')
            ->where('client_id', $clientId)
            ->first();

        $settings = $defaultSettings;
        if ($row && !empty($row->settings_json)) {
            $saved = json_decode($row->settings_json, true);
            if (is_array($saved)) {
                $settings = array_merge($defaultSettings, $saved);
            }
        }

        (new JsonResponse(['status' => 'success', 'settings' => $settings], 200))->send();
    }
} catch (Exception $e) {
    error_log("cloudnas_settings error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to process settings'], 200))->send();
}
exit;

