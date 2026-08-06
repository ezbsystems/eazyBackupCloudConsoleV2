<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'auth'], 401))->send();
    exit;
}

$clientId = (int) $ca->getUserID();
$userIdRaw = trim((string) ($_GET['user_id'] ?? $_POST['user_id'] ?? ''));
$batchRunId = trim((string) ($_GET['batch_run_id'] ?? ''));
$manifestId = trim((string) ($_GET['manifest_id'] ?? ''));
// WHMCS init.php HTML-escapes $_GET (e.g. "&" → "&amp;"), which breaks Kopia path lookup.
$pathRaw = (string) ($_GET['path'] ?? '');
$path = html_entity_decode($pathRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$childRunId = trim((string) ($_GET['child_run_id'] ?? ''));
$limit = max(0, min(2000, (int) ($_GET['limit'] ?? 500)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));

if ($userIdRaw === '' || $batchRunId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id and batch_run_id are required'], 400))->send();
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// #region agent log
@file_put_contents(
    '/var/www/eazybackup.ca/.cursor/debug-dc344d.log',
    json_encode([
        'sessionId' => 'dc344d',
        'hypothesisId' => 'H1',
        'location' => 'ms365_restore_browse.php:path_decode',
        'message' => 'browse path after WHMCS GET sanitize',
        'data' => [
            'path_raw_tail' => substr($pathRaw, max(0, strlen($pathRaw) - 40)),
            'path_decoded_tail' => substr($path, max(0, strlen($path) - 40)),
            'raw_has_amp_entity' => str_contains($pathRaw, '&amp;'),
            'decoded_has_amp_entity' => str_contains($path, '&amp;'),
            'decoded_has_ampersand' => str_contains($path, '&'),
            'batch_run_id' => $batchRunId,
            'child_run_id' => $childRunId,
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_SLASHES) . "\n",
    FILE_APPEND | LOCK_EX
);
// #endregion

try {
    $user = Ms365E3Controller::resolveBackupUser($clientId, $userIdRaw);
    $page = Ms365E3Controller::browseRestoreSnapshot(
        $clientId,
        $user['id'],
        $batchRunId,
        $manifestId,
        $path,
        $childRunId,
        $limit,
        $offset,
    );
    (new JsonResponse([
        'status' => 'success',
        'entries' => $page['entries'] ?? [],
        'total_count' => (int) ($page['total_count'] ?? 0),
        'has_more' => (bool) ($page['has_more'] ?? false),
        'offset' => (int) ($page['offset'] ?? $offset),
        'limit' => (int) ($page['limit'] ?? $limit),
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_restore_browse')->send();
}
