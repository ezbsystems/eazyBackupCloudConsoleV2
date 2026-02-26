<?php
/**
 * Unit tests for UuidBinary helper (cloud backup UUID DB boundary conversions).
 * Run: php accounts/modules/addons/cloudstorage/tests/cloudbackup_uuid_helper_test.php
 */

require_once __DIR__ . '/../lib/Client/UuidBinary.php';

use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

$fail = 0;

// isUuid
if (!UuidBinary::isUuid('018f1234-5678-7abc-def0-123456789abc')) {
    echo "FAIL: isUuid valid UUID v7\n";
    $fail++;
}
if (!UuidBinary::isUuid('550e8400-e29b-41d4-a716-446655440000')) {
    echo "FAIL: isUuid valid UUID v4\n";
    $fail++;
}
if (UuidBinary::isUuid('123')) {
    echo "FAIL: isUuid numeric should be false\n";
    $fail++;
}
if (UuidBinary::isUuid('not-a-uuid')) {
    echo "FAIL: isUuid invalid string should be false\n";
    $fail++;
}
if (UuidBinary::isUuid('')) {
    echo "FAIL: isUuid empty should be false\n";
    $fail++;
}

// normalize
$norm = UuidBinary::normalize('018F1234-5678-7ABC-DEF0-123456789ABC');
if ($norm !== '018f1234-5678-7abc-def0-123456789abc') {
    echo "FAIL: normalize should lowercase: got '$norm'\n";
    $fail++;
}
try {
    UuidBinary::normalize('invalid');
    echo "FAIL: normalize invalid should throw\n";
    $fail++;
} catch (\InvalidArgumentException $e) {
    // expected
}

// toDbExpr
$expr = UuidBinary::toDbExpr('018f1234-5678-7abc-def0-123456789abc');
if ($expr !== "UUID_TO_BIN('018f1234-5678-7abc-def0-123456789abc')") {
    echo "FAIL: toDbExpr: got '$expr'\n";
    $fail++;
}

// fromDbExpr
$sel = UuidBinary::fromDbExpr('run_id', 'id');
if ($sel !== "BIN_TO_UUID(run_id) AS id") {
    echo "FAIL: fromDbExpr(column, alias): got '$sel'\n";
    $fail++;
}
$sel2 = UuidBinary::fromDbExpr('job_id');
if ($sel2 !== "BIN_TO_UUID(job_id) AS id") {
    echo "FAIL: fromDbExpr(column) default alias: got '$sel2'\n";
    $fail++;
}

if ($fail > 0) {
    echo "cloudbackup_uuid_helper_test: $fail assertion(s) failed\n";
    exit(1);
}

echo "cloudbackup_uuid_helper_test: all pass\n";
exit(0);
