<?php
declare(strict_types=1);

require_once dirname(__DIR__, 5) . '/init.php';
require_once dirname(__DIR__, 5) . '/modules/servers/comet/functions.php';

$addonDir = dirname(__DIR__, 2);
$previousDirectory = getcwd();
chdir($addonDir);
require_once $addonDir . '/eazybackup.php';

$_POST = [
    'product' => '',
    'username' => 'cursor-test-user',
    'password' => 'Password1!',
    'confirmpassword' => 'Password1!',
];

try {
    $errors = eazybackup_validate_order($_POST);
} catch (\Throwable $e) {
    fwrite(STDERR, 'FAIL: invalid product caused ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($previousDirectory !== false) {
        chdir($previousDirectory);
    }
}

if (($errors['product'] ?? '') !== 'You must choose a valid plan.') {
    fwrite(STDERR, "FAIL: invalid product was not returned as a validation error\n");
    exit(1);
}

fwrite(STDOUT, "createorder-invalid-product-validation-ok\n");
