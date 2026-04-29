<?php
/**
 * Admin POST endpoint for the per-client inbox messages UI.
 * Lives outside addonmodules.php to avoid the framework-level admin
 * CSRF gate (which our iframe action cannot reliably populate). Auth
 * is enforced via $_SESSION['adminid'] and a per-session CSRF token
 * that the iframe page injects into every form.
 */

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../eazybackup.php';

if (empty($_SESSION['adminid']) || (int)$_SESSION['adminid'] <= 0) {
    http_response_code(401);
    echo 'Not authorized';
    exit;
}
$adminId = (int)$_SESSION['adminid'];

$clientId = (int)($_POST['clientid'] ?? 0);
if ($clientId <= 0) {
    http_response_code(400);
    echo 'Missing client id';
    exit;
}

$expected = (string)($_SESSION['eb_admin_inbox_csrf'] ?? '');
$provided = (string)($_POST['eb_csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(400);
    echo 'Invalid CSRF token';
    exit;
}

$op = (string)($_POST['op'] ?? '');
$mid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$flash = ['type' => 'success', 'msg' => ''];

try {
    if ($op === 'save') {
        $title = trim((string)($_POST['title'] ?? ''));
        $body  = (string)($_POST['body'] ?? '');
        $expiresAtRaw = trim((string)($_POST['expires_at'] ?? ''));
        $expiresAt = null;
        if ($expiresAtRaw !== '') {
            $ts = strtotime($expiresAtRaw);
            if ($ts === false) throw new \RuntimeException('Invalid Expires at date/time.');
            $expiresAt = date('Y-m-d H:i:s', $ts);
        }
        if ($title === '') throw new \RuntimeException('Title is required.');
        if ($body === '')  throw new \RuntimeException('Body is required.');

        $now = date('Y-m-d H:i:s');
        if ($mid > 0) {
            Capsule::table('eb_client_messages')
                ->where('id', $mid)->where('client_id', $clientId)
                ->update([
                    'title' => $title,
                    'body'  => $body,
                    'expires_at' => $expiresAt,
                    'updated_at' => $now,
                ]);
        } else {
            Capsule::table('eb_client_messages')->insertGetId([
                'client_id'  => $clientId,
                'title'      => $title,
                'body'       => $body,
                'expires_at' => $expiresAt,
                'created_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $flash['msg'] = 'Message saved.';
    } elseif ($op === 'soft_delete') {
        if ($mid <= 0) throw new \RuntimeException('Invalid id.');
        Capsule::table('eb_client_messages')
            ->where('id', $mid)->where('client_id', $clientId)
            ->update(['deleted_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $flash['msg'] = 'Message archived (hidden from customer).';
    } elseif ($op === 'restore') {
        if ($mid <= 0) throw new \RuntimeException('Invalid id.');
        Capsule::table('eb_client_messages')
            ->where('id', $mid)->where('client_id', $clientId)
            ->update(['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        $flash['msg'] = 'Message restored.';
    } elseif ($op === 'hard_delete') {
        if ($mid <= 0) throw new \RuntimeException('Invalid id.');
        Capsule::table('eb_client_messages')
            ->where('id', $mid)->where('client_id', $clientId)
            ->delete();
        $flash['msg'] = 'Message permanently deleted.';
    } else {
        throw new \RuntimeException('Unknown operation.');
    }
} catch (\Throwable $ex) {
    $flash = ['type' => 'danger', 'msg' => 'Error: ' . $ex->getMessage()];
}

$_SESSION['eb_admin_inbox_flash'] = $flash;

// Prefer the referrer (the iframe URL) so we work regardless of the
// configured admin folder name; fall back to a sensible default.
$ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
$host = $_SERVER['HTTP_HOST'] ?? '';
$back = '';
if ($ref !== '' && $host !== '' && stripos($ref, '://' . $host) !== false) {
    $back = $ref;
} else {
    $webRoot = rtrim((string)\WHMCS\Config\Setting::getValue('SystemURL'), '/');
    $back = $webRoot . '/admin/addonmodules.php?module=eazybackup&action=client_notifications_panel&clientid=' . $clientId;
}
header('Location: ' . $back);
exit;
