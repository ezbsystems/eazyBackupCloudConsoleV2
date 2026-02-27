<?php
declare(strict_types=1);

// Usage examples:
// php accounts/modules/addons/eazybackup/bin/dev/dashboard-usage-smoke.php --schema
// php accounts/modules/addons/eazybackup/bin/dev/dashboard-usage-smoke.php --endpoint "https://dev.eazybackup.ca/index.php?m=eazybackup"
// php accounts/modules/addons/eazybackup/bin/dev/dashboard-usage-smoke.php --schema --endpoint "https://dev.eazybackup.ca/index.php?m=eazybackup"

$args = $argv;
array_shift($args);

$runSchema = false;
$runEndpoint = false;
$base = '';

foreach ($args as $arg) {
    if ($arg === '--schema') {
        $runSchema = true;
        continue;
    }
    if ($arg === '--endpoint') {
        $runEndpoint = true;
        continue;
    }
    if ($arg !== '' && $arg[0] !== '-') {
        $base = $arg;
    }
}

if (!$runSchema && !$runEndpoint) {
    $runEndpoint = true; // Backward compatible default behavior
}

if ($runSchema) {
    require_once __DIR__ . '/../bootstrap.php';
    $pdo = db();
    $required = ['d', 'client_id', 'registered', 'online', 'updated_at'];

    try {
        $rows = $pdo->query("SHOW COLUMNS FROM eb_devices_client_daily")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        fwrite(STDERR, "Schema check failed: eb_devices_client_daily missing\n");
        exit(1);
    }

    $cols = [];
    foreach ($rows as $r) {
        $cols[] = (string)($r['Field'] ?? '');
    }

    foreach ($required as $col) {
        if (!in_array($col, $cols, true)) {
            fwrite(STDERR, "Schema check failed: missing column {$col}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "Schema PASS\n");
}

if ($runEndpoint) {
    if ($base === '') {
        fwrite(STDERR, "Missing base URL argument for --endpoint\n");
        exit(2);
    }

    $url = rtrim($base, '&') . '&a=dashboard-usage-metrics';
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        fwrite(STDERR, "HTTP request failed: {$url}\n");
        exit(1);
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        fwrite(STDERR, "Response is not valid JSON\n");
        exit(1);
    }

    if (($data['status'] ?? '') !== 'success') {
        fwrite(STDERR, "status != success\n");
        exit(1);
    }

    foreach (['devices30d', 'storage30d', 'status24h'] as $key) {
        if (!array_key_exists($key, $data)) {
            fwrite(STDERR, "missing key: {$key}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "Endpoint PASS\n");
}

fwrite(STDOUT, "PASS\n");
