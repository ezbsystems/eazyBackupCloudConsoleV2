<?php
declare(strict_types=1);

/**
 * Smoke test for WHMCS notification bootstrapping from a stripped CLI context.
 *
 * Default mode does not send email:
 *   php bin/dev/notification_cli_context_smoke.php
 *
 * Optional controlled send:
 *   php bin/dev/notification_cli_context_smoke.php --send --template=tpl_device_added --client-id=123
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This smoke test must run from CLI.\n");
    exit(2);
}

$opts = getopt('', ['send', 'template::', 'client-id::', 'host::']);
$send = array_key_exists('send', $opts);
$templateKey = (string)($opts['template'] ?? 'tpl_device_added');
$clientId = (int)($opts['client-id'] ?? 0);
$host = (string)($opts['host'] ?? 'accounts.eazybackup.ca');

$addonRoot = dirname(__DIR__, 2);
$repoRoot = dirname($addonRoot, 4);

foreach (['WHMCS_SERVER_NAME', 'SERVER_NAME', 'HTTP_HOST', 'HTTPS', 'SERVER_PORT'] as $key) {
    unset($_SERVER[$key], $_ENV[$key]);
    putenv($key);
}

require_once $addonRoot . '/bin/whmcs_cli_context.php';
eb_apply_whmcs_cli_server_context($host);

$expected = [
    'WHMCS_SERVER_NAME' => $host,
    'SERVER_NAME' => $host,
    'HTTP_HOST' => $host,
    'HTTPS' => 'on',
    'SERVER_PORT' => '443',
];

foreach ($expected as $key => $value) {
    if ((string)($_SERVER[$key] ?? '') !== $value) {
        fwrite(STDERR, "Context assertion failed for \$_SERVER[{$key}]\n");
        exit(1);
    }
}

$autoload = $addonRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing addon autoload at {$autoload}\n");
    exit(2);
}
require_once $autoload;

$whmcsInit = $repoRoot . '/accounts/init.php';
if (!is_file($whmcsInit)) {
    fwrite(STDERR, "Missing WHMCS init at {$whmcsInit}\n");
    exit(2);
}
require_once $whmcsInit;

if (!function_exists('localAPI')) {
    fwrite(STDERR, "localAPI unavailable after WHMCS bootstrap.\n");
    exit(1);
}

if (!$send) {
    echo json_encode([
        'result' => 'success',
        'mode' => 'bootstrap',
        'server_name' => $_SERVER['SERVER_NAME'] ?? '',
        'http_host' => $_SERVER['HTTP_HOST'] ?? '',
        'https' => $_SERVER['HTTPS'] ?? '',
        'server_port' => $_SERVER['SERVER_PORT'] ?? '',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

require_once $addonRoot . '/lib/Notifications/bootstrap.php';
eb_notifications_service();

$username = 'cli-smoke';
$subject = 'CLI notification smoke: ' . gmdate('Y-m-d H:i:s') . ' UTC';
$key = 'smoke:cli_context:' . gmdate('YmdHis');
$templateName = \EazyBackup\Notifications\Config::templateName($templateKey);
$rowId = \EazyBackup\Notifications\IdempotencyStore::reserve($username, 'device', $key, [
    'service_id' => 0,
    'client_id' => $clientId,
    'template' => $templateName,
    'subject' => $subject,
    'recipients' => 'smoke',
    'merge_json' => json_encode([
        'username' => $username,
        'subject' => $subject,
        'client_id' => $clientId,
    ], JSON_UNESCAPED_SLASHES),
]);

if ($rowId === null) {
    fwrite(STDERR, "Unable to reserve notification smoke row.\n");
    exit(1);
}

try {
    $resp = \EazyBackup\Notifications\TemplateRenderer::send($templateKey, [
        'subject' => $subject,
        'username' => $username,
        'service_id' => 0,
        'client_id' => $clientId,
        'device_id' => 'CLI-SMOKE',
        'device_name' => 'CLI Smoke',
        'recipients' => 'smoke',
    ]);

    $emailLogId = (int)($resp['id'] ?? 0);
    \EazyBackup\Notifications\IdempotencyStore::markSent($rowId, $emailLogId > 0 ? $emailLogId : null);
    echo json_encode(['result' => 'success', 'row_id' => $rowId, 'email_log_id' => $emailLogId, 'response' => $resp], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    \EazyBackup\Notifications\IdempotencyStore::markFailed($rowId, $e->getMessage());
    fwrite(STDERR, json_encode(['result' => 'failed', 'row_id' => $rowId, 'message' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

