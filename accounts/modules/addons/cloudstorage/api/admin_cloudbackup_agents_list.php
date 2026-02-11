<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/CloudBackupAdminController.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\CloudBackupAdminController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required'], 200))->send();
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'created_at');
$dir = strtolower((string) ($_GET['dir'] ?? 'desc'));
$page = (int) ($_GET['page'] ?? 1);
$perPage = (int) ($_GET['per_page'] ?? 50);

if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}
if ($page < 1) {
    $page = 1;
}
if ($perPage < 1) {
    $perPage = 50;
}
if ($perPage > 200) {
    $perPage = 200;
}

$filters = [
    'q' => $q,
    'client_id' => $_GET['client_id'] ?? null,
    'status' => $_GET['status'] ?? null,
    'agent_type' => $_GET['agent_type'] ?? null,
    'tenant_id' => $_GET['tenant_id'] ?? null,
    'online_status' => $_GET['online_status'] ?? null,
];

$offset = ($page - 1) * $perPage;
$rows = CloudBackupAdminController::getAllAgents($filters, ['field' => $sort, 'dir' => $dir], $perPage, $offset);
$total = CloudBackupAdminController::countAllAgents($filters);
$pages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
if ($pages < 1) {
    $pages = 1;
}

(new JsonResponse([
    'status' => 'success',
    'rows' => $rows,
    'meta' => [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'sort' => $sort,
        'dir' => $dir,
    ],
], 200))->send();
exit;

