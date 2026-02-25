<?php

/**
 * Admin Enqueue Repo Operation API
 *
 * Allows admins to manually enqueue repo operations (retention, maintenance).
 * Validates admin session, op_type, enqueues via KopiaRetentionOperationService.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionOperationService;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required'], 200))->send();
    exit;
}

if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')
    || !Capsule::schema()->hasTable('s3_kopia_repos')) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Repo operations not supported on this installation'], 200))->send();
    exit;
}

$repoId = isset($_POST['repo_id']) ? (int) $_POST['repo_id'] : 0;
$opType = isset($_POST['op_type']) ? strtolower(trim((string) $_POST['op_type'])) : '';
$operationToken = isset($_POST['operation_token']) ? trim((string) $_POST['operation_token']) : '';

$allowedOpTypes = ['retention_apply', 'maintenance_quick', 'maintenance_full'];
if (!in_array($opType, $allowedOpTypes, true)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'op_type must be one of: ' . implode(', ', $allowedOpTypes)], 200))->send();
    exit;
}

if ($repoId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'repo_id is required and must be positive'], 200))->send();
    exit;
}

$repoExists = Capsule::table('s3_kopia_repos')->where('id', $repoId)->exists();
if (!$repoExists) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Repository not found'], 200))->send();
    exit;
}

if ($operationToken === '') {
    $operationToken = 'admin-enqueue-' . (int) $_SESSION['adminid'] . '-' . $repoId . '-' . $opType . '-' . uniqid('', true);
}

if (strlen($operationToken) > 128) {
    (new JsonResponse(['status' => 'fail', 'message' => 'operation_token exceeds maximum length'], 200))->send();
    exit;
}

$payload = ['repo_id' => $repoId];

try {
    $result = KopiaRetentionOperationService::enqueue($repoId, $opType, $payload, $operationToken);
    (new JsonResponse([
        'status' => $result['status'] === 'success' ? 'success' : 'duplicate',
        'operation_id' => $result['operation_id'] ?? null,
    ], 200))->send();
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'admin_enqueue_repo_operation', ['repo_id' => $repoId, 'op_type' => $opType], $e->getMessage());
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to enqueue operation'], 200))->send();
}
exit;
