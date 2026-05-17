<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'worker applies CLI context before WHMCS init' => [
        $root . '/bin/comet_ws_worker.php',
        "eb_apply_whmcs_cli_server_context();\n\n// Load ONLY the addon vendor autoloader",
    ],
    'status enum includes pending on create' => [
        $root . '/eazybackup.php',
        "\$t->enum('status', ['pending','sent','failed'])->default('pending');",
    ],
    'status enum includes pending on upgrade' => [
        $root . '/eazybackup.php',
        "ALTER TABLE eb_notifications_sent MODIFY COLUMN status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending'",
    ],
    'renderer throws on SendEmail failure' => [
        $root . '/lib/Notifications/src/TemplateRenderer.php',
        "throw new \\RuntimeException(\$message);",
    ],
    'systemd clean env includes WHMCS server name' => [
        $root . '/Docs/EAZYBACKUP_README.md',
        'env -i COMET_PROFILE=%i WHMCS_SERVER_NAME=accounts.eazybackup.ca SERVER_NAME=accounts.eazybackup.ca HTTP_HOST=accounts.eazybackup.ca HTTPS=on SERVER_PORT=443',
    ],
];

foreach ($checks as $label => [$file, $needle]) {
    $contents = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($contents) || !str_contains($contents, $needle)) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

echo "notification_cli_context_contract_test: OK\n";

