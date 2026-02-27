<?php
declare(strict_types=1);

// Minimal smoke runner:
// php accounts/modules/addons/eazybackup/bin/dev/dashboard-usage-smoke.php "https://dev.eazybackup.ca/index.php?m=eazybackup"

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Missing base URL argument\n");
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

echo "PASS\n";
