<?php
declare(strict_types=1);

$hookFile = dirname(__DIR__, 5) . '/includes/hooks/cometUsage_ClientServices.php';
$source = @file_get_contents($hookFile);
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read Comet usage hook\n");
    exit(1);
}

$required = [
    "servertype ?? '') !== 'comet'",
    'catch (\\Throwable $e)',
    'return $return;',
];

foreach ($required as $marker) {
    if (strpos($source, $marker) === false) {
        fwrite(STDERR, "FAIL: Comet usage hook is not safely scoped\n");
        exit(1);
    }
}

fwrite(STDOUT, "comet-usage-hook-scope-ok\n");
