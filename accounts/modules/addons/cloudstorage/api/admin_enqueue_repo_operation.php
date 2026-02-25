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
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required'], 401))->send();
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

// Accept architecture command names; map to internal op types
$opTypeMap = [
    'kopia_retention_apply' => 'retention_apply',
    'retention_apply' => 'retention_apply',
    'kopia_maintenance_quick' => 'maintenance_quick',
    'maintenance_quick' => 'maintenance_quick',
    'kopia_maintenance_full' => 'maintenance_full',
    'maintenance_full' => 'maintenance_full',
];
$internalOpType = $opTypeMap[$opType] ?? null;
if ($internalOpType === null) {
    (new JsonResponse(['status' => 'fail', 'message' => 'op_type must be one of: retention_apply, maintenance_quick, maintenance_full, kopia_retention_apply, kopia_maintenance_quick, kopia_maintenance_full'], 200))->send();
    exit;
}

if ($repoId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'repo_id is required and must be positive'], 200))->send();
    exit;
}

$repoRow = Capsule::table('s3_kopia_repos')->where('id', $repoId)->first();
if (!$repoRow) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Repository not found'], 403))->send();
    exit;
}

// Enforce admin exists and is active (scope check)
$adminId = (int) $_SESSION['adminid'];
$adminRow = Capsule::table('tbladmins')->where('id', $adminId)->first();
if (!$adminRow || (int) ($adminRow->disabled ?? 0) === 1) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin access denied'], 403))->send();
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
    $result = KopiaRetentionOperationService::enqueue($repoId, $internalOpType, $payload, $operationToken);
    (new JsonResponse([
        'status' => $result['status'] === 'success' ? 'success' : 'duplicate',
        'operation_id' => $result['operation_id'] ?? null,
    ], 200))->send();
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'admin_enqueue_repo_operation', ['repo_id' => $repoId, 'op_type' => $internalOpType], $e->getMessage());
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to enqueue operation'], 200))->send();
}
exit;
