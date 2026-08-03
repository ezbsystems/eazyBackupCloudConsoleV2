<?php
declare(strict_types=1);

/**
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_admin_users_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365AdminUserControlsRepository;
use Ms365Backup\Ms365AdminUsersRepository;

$failures = 0;
function assert_true(bool $cond, string $message): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

// --- Ms365AdminUserControlsRepository ---

$prior = Ms365AdminUserControlsRepository::decodePriorJobStatuses(
    json_encode(['a1111111-1111-4111-8111-111111111111' => 'active', 'b2222222-2222-4222-8222-222222222222' => 'paused'])
);
assert_true(count($prior) === 2, 'decodePriorJobStatuses parses job map');
assert_true(($prior['a1111111-1111-4111-8111-111111111111'] ?? '') === 'active', 'decodePriorJobStatuses keeps active status');

$emptyPrior = Ms365AdminUserControlsRepository::decodePriorJobStatuses('');
assert_true($emptyPrior === [], 'decodePriorJobStatuses empty string returns []');

$blocked = false;
try {
    Ms365AdminUserControlsRepository::assertCustomerAllowed(0);
} catch (\Throwable $e) {
    $blocked = true;
}
assert_true(!$blocked, 'assertCustomerAllowed(0) does not throw when not suspended');

assert_true(
    str_contains(Ms365AdminUserControlsRepository::CUSTOMER_BLOCKED_MESSAGE, 'administrator'),
    'customer blocked message mentions administrator'
);

// --- listUsers shape ---

$list = Ms365AdminUsersRepository::listUsers([], 1, 5);
assert_true(isset($list['rows'], $list['total'], $list['page'], $list['per_page']), 'listUsers returns pagination envelope');
assert_true(is_array($list['rows']), 'listUsers rows is array');

foreach ($list['rows'] as $row) {
    assert_true(isset($row['backup_user_id'], $row['client_name'], $row['username'], $row['status']), 'listUsers row has core columns');
    assert_true(isset($row['protected_users'], $row['onedrive_overage_gib'], $row['vaults'], $row['jobs']), 'listUsers row has billing/vault/job columns');
    assert_true(array_key_exists('whmcs_service_id', $row), 'listUsers row includes whmcs_service_id for service link');
    assert_true(array_key_exists('client_id', $row), 'listUsers row includes client_id for service link');
    assert_true(in_array($row['status'], ['Active', 'Suspended', 'Disabled'], true), 'listUsers status is valid badge');
    if (!empty($row['vaults'])) {
        $v = $row['vaults'][0];
        assert_true(isset($v['name'], $v['size_display']), 'vault entry has name and size_display for Stored column');
    }
    break;
}

// --- ensureTable idempotent ---

try {
    Ms365AdminUserControlsRepository::ensureTable();
    assert_true(
        \WHMCS\Database\Capsule::schema()->hasTable('ms365_admin_user_controls'),
        'ensureTable creates ms365_admin_user_controls'
    );
} catch (\Throwable $e) {
    assert_true(false, 'ensureTable: ' . $e->getMessage());
}

exit($failures > 0 ? 1 : 0);
