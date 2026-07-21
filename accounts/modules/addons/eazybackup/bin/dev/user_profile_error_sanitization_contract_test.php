<?php
declare(strict_types=1);

$sourceFile = dirname(__DIR__, 2) . '/pages/console/user-profile.php';
$source = @file_get_contents($sourceFile);
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read user-profile controller\n");
    exit(1);
}

$required = [
    "first(['packageid', 'server'])",
    'comet_ProductParams($packageid, $serverid)',
    "We couldn't load this backup user right now.",
    "'classification' =>",
];
$forbidden = [
    '"Error fetching user data: " . $user',
    "'Error fetching user data: ' . \$user",
];

foreach ($required as $marker) {
    if (strpos($source, $marker) === false) {
        fwrite(STDERR, "FAIL: missing safe profile marker\n");
        exit(1);
    }
}
foreach ($forbidden as $marker) {
    if (strpos($source, $marker) !== false) {
        fwrite(STDERR, "FAIL: raw provider error remains customer-visible\n");
        exit(1);
    }
}

fwrite(STDOUT, "user-profile-error-sanitization-ok\n");
