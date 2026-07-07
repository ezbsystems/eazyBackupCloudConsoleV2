<?php
declare(strict_types=1);

/**
 * Unit tests for Ms365AdminTenantExportService payload and export gates.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_admin_tenant_export_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365AdminTenantExportService;
use Ms365Backup\TenantRecordRepository;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

function assert_eq(mixed $expected, mixed $actual, string $message): void
{
    global $failures;
    if ($expected !== $actual) {
        echo "FAIL: {$message}\n";
        echo '  expected: ' . var_export($expected, true) . "\n";
        echo '  actual:   ' . var_export($actual, true) . "\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$tenant = [
    'id' => 5,
    'connection_status' => 'connected',
    'connection_auth_mode' => TenantRecordRepository::AUTH_MODE_PLATFORM,
    'azure_tenant_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
];

$creds = [
    'region' => 'GlobalPublicCloud',
    'tenant_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
    'client_id' => 'platform-client-id',
    'client_secret' => 'platform-secret-value',
];

$payload = Ms365AdminTenantExportService::buildExportPayload(
    42,
    20,
    'acme-backup',
    $tenant,
    $creds,
    TenantRecordRepository::AUTH_MODE_PLATFORM
);

assert_eq(42, $payload['backup_user_id'], 'payload backup_user_id');
assert_eq(20, $payload['whmcs_client_id'], 'payload whmcs_client_id');
assert_eq('acme-backup', $payload['backup_username'], 'payload backup_username');
assert_eq(5, $payload['tenant_record_id'], 'payload tenant_record_id');
assert_eq(TenantRecordRepository::AUTH_MODE_PLATFORM, $payload['connection_auth_mode'], 'payload auth mode');
assert_eq('connected', $payload['connection_status'], 'payload connection status');
assert_eq('GlobalPublicCloud', $payload['manual_connect']['region'], 'manual_connect region');
assert_eq('platform-client-id', $payload['manual_connect']['client_id'], 'manual_connect client_id');
assert_eq('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $payload['manual_connect']['tenant_id'], 'manual_connect tenant_id');
assert_eq('platform-secret-value', $payload['manual_connect']['app_secret'], 'manual_connect app_secret maps client_secret');
assert_true(
    str_contains((string) $payload['notes'], 'customer_app'),
    'platform_consent notes mention customer_app after manual save'
);

$customerTenant = $tenant;
$customerTenant['connection_auth_mode'] = TenantRecordRepository::AUTH_MODE_CUSTOMER;
$customerCreds = $creds;
$customerCreds['client_id'] = 'customer-client-id';
$customerCreds['client_secret'] = 'customer-secret';

$customerPayload = Ms365AdminTenantExportService::buildExportPayload(
    7,
    3,
    'msp-user',
    $customerTenant,
    $customerCreds,
    TenantRecordRepository::AUTH_MODE_CUSTOMER
);

assert_eq(TenantRecordRepository::AUTH_MODE_CUSTOMER, $customerPayload['connection_auth_mode'], 'customer_app auth mode preserved');
assert_eq('customer-client-id', $customerPayload['manual_connect']['client_id'], 'customer_app client_id');
assert_true(
    !str_contains((string) $customerPayload['notes'], 'platform app'),
    'customer_app notes do not mention platform app'
);

assert_true(Ms365AdminTenantExportService::canExportTenant($tenant), 'connected tenant with azure id can export');

$disconnected = $tenant;
$disconnected['connection_status'] = 'disconnected';
assert_true(!Ms365AdminTenantExportService::canExportTenant($disconnected), 'disconnected tenant blocked');

$noAzure = $tenant;
$noAzure['azure_tenant_id'] = '';
assert_true(!Ms365AdminTenantExportService::canExportTenant($noAzure), 'missing azure tenant id blocked');
assert_true(!Ms365AdminTenantExportService::canExportTenant(null), 'null tenant blocked');

assert_true(
    str_contains(Ms365AdminTenantExportService::exportBlockReason($disconnected), 'not connected'),
    'disconnected block reason mentions status'
);
assert_true(
    str_contains(Ms365AdminTenantExportService::exportBlockReason(null), 'No MS365 tenant record'),
    'null tenant block reason'
);

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
