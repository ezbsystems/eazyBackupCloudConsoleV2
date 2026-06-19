<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Ms365BackupBootstrap.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'auth'], 401))->send();
    exit;
}

$clientId = (int) $ca->getUserID();
$backupUserRaw = trim((string) ($_GET['backup_user_id'] ?? $_POST['backup_user_id'] ?? ''));

if ($backupUserRaw === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'backup_user_id required'], 400))->send();
    exit;
}

try {
    cloudstorage_load_ms365backup();

    $backupUserId = 0;
    if (Capsule::schema()->hasColumn('s3_backup_users', 'public_id') && !ctype_digit($backupUserRaw)) {
        $backupUserId = (int) Capsule::table('s3_backup_users')
            ->where('client_id', $clientId)
            ->where('public_id', $backupUserRaw)
            ->value('id');
    }
    if ($backupUserId <= 0 && ctype_digit($backupUserRaw)) {
        $backupUserId = (int) $backupUserRaw;
    }
    if ($backupUserId <= 0) {
        (new JsonResponse(['status' => 'fail', 'message' => 'backup user not found'], 404))->send();
        exit;
    }

    $owns = Capsule::table('s3_backup_users')
        ->where('id', $backupUserId)
        ->where('client_id', $clientId)
        ->exists();
    if (!$owns) {
        (new JsonResponse(['status' => 'fail', 'message' => 'forbidden'], 403))->send();
        exit;
    }

    $usage = \Ms365Backup\Ms365BillingService::usageSummaryForBackupUser($clientId, $backupUserId);

    (new JsonResponse([
        'status' => 'success',
        'usage' => $usage,
    ]))->send();
} catch (\Throwable $e) {
    (new JsonResponse([
        'status' => 'fail',
        'message' => Ms365E3Controller::customerErrorMessage($e, 'ms365_usage'),
    ], 500))->send();
}
