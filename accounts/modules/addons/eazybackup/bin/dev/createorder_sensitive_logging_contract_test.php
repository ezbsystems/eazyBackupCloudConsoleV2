<?php
declare(strict_types=1);

$sourceFile = dirname(__DIR__, 2) . '/eazybackup.php';
$source = @file_get_contents($sourceFile);
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read eazybackup addon source\n");
    exit(1);
}

if (strpos($source, 'print_r($_POST, true)') !== false) {
    fwrite(STDERR, "FAIL: create-order flow logs raw POST data\n");
    exit(1);
}

if (strpos($source, "print_r(\$vars['clientsdetails'], true)") !== false) {
    fwrite(STDERR, "FAIL: create-order flow logs raw client details\n");
    exit(1);
}

if (strpos($source, 'localAPI GetProducts =>') !== false) {
    fwrite(STDERR, "FAIL: create-order flow logs full GetProducts response\n");
    exit(1);
}

if (strpos($source, 'eazybackup: Form POST received') === false) {
    fwrite(STDERR, "FAIL: sanitized create-order audit marker is missing\n");
    exit(1);
}

fwrite(STDOUT, "createorder-sensitive-logging-ok\n");
