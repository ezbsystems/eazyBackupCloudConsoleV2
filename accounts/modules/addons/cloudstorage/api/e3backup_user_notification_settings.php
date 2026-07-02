<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\BackupUserNotificationSettingsService;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function notificationSettingsFail(string $message, int $httpCode = 400, array $errors = []): void
{
    $payload = ['status' => 'fail', 'message' => $message];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    (new JsonResponse($payload, $httpCode))->send();
    exit;
}

/**
 * @return array{userId: int, currentUser: object}|null
 */
function notificationSettingsResolveUser(int $clientId, bool $isMsp, string $tenantTable, int $tenantOwnerId, string $tenantOwnerSelect, string $userIdRaw)
{
    $hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
    if ($userIdRaw === '') {
        notificationSettingsFail('Invalid user ID.', 400, ['user_id' => 'Invalid user ID']);
    }

    $lookup = Capsule::table('s3_backup_users as u')
        ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
        ->where('u.client_id', $clientId);
    if ($hasPublicIdCol && !ctype_digit($userIdRaw)) {
        $lookup->where('u.public_id', $userIdRaw);
    } else {
        $lookup->where('u.id', (int) $userIdRaw);
    }

    $currentUser = $lookup->select([
        'u.id',
        'u.client_id',
        'u.tenant_id',
        Capsule::raw($tenantOwnerSelect),
        't.status as tenant_status',
    ])->first();

    if (!$currentUser) {
        notificationSettingsFail('User not found.', 404);
    }

    if (!$isMsp && !empty($currentUser->tenant_id)) {
        notificationSettingsFail('User not found.', 404);
    }

    $tenantClientId = (int) ($currentUser->tenant_owner_id ?? 0);
    $tenantStatus = strtolower((string) ($currentUser->tenant_status ?? ''));
    if ($isMsp && !empty($currentUser->tenant_id) && ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted')) {
        notificationSettingsFail('User not found.', 404);
    }

    return [
        'userId' => (int) $currentUser->id,
        'currentUser' => $currentUser,
    ];
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    notificationSettingsFail('Session timeout', 200);
}

$clientId = (int) $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$mspId = MspController::getMspIdForClient($clientId);
$tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int) ($mspId ?? 0) : $clientId;
$tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$userIdRaw = trim((string) ($method === 'GET' ? ($_GET['user_id'] ?? '') : ($_POST['user_id'] ?? '')));

$resolved = notificationSettingsResolveUser($clientId, $isMsp, $tenantTable, $tenantOwnerId, $tenantOwnerSelect, $userIdRaw);
$userId = (int) $resolved['userId'];

if ($method === 'GET') {
    if (function_exists('session_write_close')) {
        session_write_close();
    }
    $settings = BackupUserNotificationSettingsService::getForBackupUser($clientId, $userId);
    (new JsonResponse([
        'status' => 'success',
        'notification_settings' => $settings,
    ], 200))->send();
    exit;
}

if ($method !== 'POST') {
    notificationSettingsFail('Method not allowed.', 405);
}

$token = (string) ($_POST['token'] ?? '');
if ($token === '' || !function_exists('check_token')) {
    notificationSettingsFail('CSRF validation failed.', 400);
}
try {
    if (!check_token('plain', $token)) {
        notificationSettingsFail('CSRF validation failed.', 400);
    }
} catch (\Throwable $e) {
    notificationSettingsFail('CSRF validation failed.', 400);
}

if (function_exists('session_write_close')) {
    session_write_close();
}

$payload = [
    'notifications_enabled' => $_POST['notifications_enabled'] ?? null,
    'notify_on_success' => $_POST['notify_on_success'] ?? null,
    'notify_on_warning' => $_POST['notify_on_warning'] ?? null,
    'notify_on_failure' => $_POST['notify_on_failure'] ?? null,
    'notify_emails' => $_POST['notify_emails'] ?? [],
];

$result = BackupUserNotificationSettingsService::saveForBackupUser($clientId, $userId, $payload);
if (empty($result['ok'])) {
    notificationSettingsFail(
        (string) ($result['message'] ?? 'Failed to save notification settings.'),
        400,
        $result['errors'] ?? []
    );
}

(new JsonResponse([
    'status' => 'success',
    'message' => 'Notification settings saved.',
    'notification_settings' => $result['settings'] ?? BackupUserNotificationSettingsService::getForBackupUser($clientId, $userId),
], 200))->send();
exit;
