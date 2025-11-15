<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupLogFormatter;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Session timeout.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$loggedInUserId = $ca->getUserID();

$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Product not found.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$runId = $_GET['run_id'] ?? null;
if (!$runId) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Run ID is required.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$run = CloudBackupController::getRun($runId, $loggedInUserId);
if (!$run) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Run not found or access denied.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

// Parse log excerpt into lines for live display
$logLines = [];
if (!empty($run['log_excerpt'])) {
    // Try to parse as JSON array first
    $logData = json_decode($run['log_excerpt'], true);
    
    // Handle case where log is stored as JSON array of JSON strings (double-encoded)
    if (is_array($logData) && !empty($logData) && is_string($logData[0])) {
        foreach ($logData as $line) {
            if (is_string($line)) {
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    $logLines[] = $decoded;
                }
            } elseif (is_array($line)) {
                $logLines[] = $line;
            }
        }
    } elseif (is_array($logData)) {
        $logLines = $logData;
    } else {
        // If not a JSON array, try parsing line by line
        $lines = explode("\n", trim($run['log_excerpt']));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $decoded = json_decode($line, true);
            if ($decoded !== null) {
                $logLines[] = $decoded;
            }
        }
    }
}

// Build formatted log excerpt (server-side formatting) and a simple hash for caching
$formattedLog = null;
$logHash = null;
if (!empty($run['log_excerpt'])) {
    $logHash = md5($run['log_excerpt']);
    // Light caching: avoid formatting if client already has same hash (client passes ?log_hash=...)
    $clientHash = isset($_GET['log_hash']) ? (string)$_GET['log_hash'] : null;
    if ($clientHash !== $logHash) {
        $formattedLog = CloudBackupLogFormatter::formatRcloneLogs($run['log_excerpt']);
    }
}

$jsonData = [
    'status' => 'success',
    'run' => [
        'status' => $run['status'],
        'progress_pct' => $run['progress_pct'],
        'bytes_total' => $run['bytes_total'],
        'bytes_transferred' => $run['bytes_transferred'],
        'objects_total' => $run['objects_total'],
        'objects_transferred' => $run['objects_transferred'],
        'speed_bytes_per_sec' => $run['speed_bytes_per_sec'],
        'eta_seconds' => $run['eta_seconds'],
        'current_item' => $run['current_item'],
        'started_at' => $run['started_at'],
        'finished_at' => $run['finished_at'],
        'log_excerpt' => $run['log_excerpt'] ?? '',
        'log_lines' => $logLines, // Raw lines for fallback
        'formatted_log_excerpt' => $formattedLog, // Formatted full excerpt (null if unchanged by hash)
        'log_excerpt_hash' => $logHash,
    ]
];

$response = new JsonResponse($jsonData, 200);
$response->send();
exit();

