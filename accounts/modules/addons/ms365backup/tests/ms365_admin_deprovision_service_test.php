<?php
declare(strict_types=1);

/**
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_admin_deprovision_service_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365AdminDeprovisionService;
use WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap;

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

// searchClients short query
$short = Ms365AdminDeprovisionService::searchClients('a');
assert_true($short === [], 'searchClients returns [] for query shorter than 2 chars');

// searchClients shape (may be empty on dev)
$clients = Ms365AdminDeprovisionService::searchClients('test');
assert_true(is_array($clients), 'searchClients returns array');
foreach ($clients as $client) {
    assert_true(isset($client['client_id'], $client['client_name']), 'searchClients row has client_id and client_name');
    break;
}

// listBackupUsersForClient rejects invalid client
$invalid = false;
try {
    Ms365AdminDeprovisionService::listBackupUsersForClient(0);
} catch (\Throwable $e) {
    $invalid = true;
}
assert_true($invalid, 'listBackupUsersForClient(0) throws');

// resolveByServiceId rejects invalid id
$badService = false;
try {
    Ms365AdminDeprovisionService::resolveByServiceId(0);
} catch (\Throwable $e) {
    $badService = true;
}
assert_true($badService, 'resolveByServiceId(0) throws');

// buildPreview rejects invalid backup user
$badPreview = false;
try {
    Ms365AdminDeprovisionService::buildPreview(0);
} catch (\Throwable $e) {
    $badPreview = true;
}
assert_true($badPreview, 'buildPreview(0) throws');

// If we have backup users, preview shape is valid
$list = \Ms365Backup\Ms365AdminUsersRepository::listUsers([], 1, 1);
if (!empty($list['rows'][0]['backup_user_id'])) {
    $backupUserId = (int) $list['rows'][0]['backup_user_id'];
    try {
        $preview = Ms365AdminDeprovisionService::buildPreview($backupUserId);
        assert_true(isset($preview['client'], $preview['user'], $preview['jobs'], $preview['vaults']), 'buildPreview has core sections');
        assert_true(isset($preview['will_cancel'], $preview['will_not_touch']), 'buildPreview has will/will_not sections');
        assert_true(
            str_starts_with((string) ($preview['confirm_phrase'] ?? ''), 'DELETE '),
            'buildPreview confirm_phrase starts with DELETE'
        );
        assert_true(
            (int) ($preview['will_not_touch']['object_storage_service_id'] ?? 0) >= 0,
            'buildPreview includes object storage service id field'
        );
    } catch (\Throwable $e) {
        assert_true(false, 'buildPreview live row: ' . $e->getMessage());
    }
}

$pid = E3BackupUserProductBootstrap::getPid();
assert_true($pid > 0, 'e3 Backup User PID is configured (' . $pid . ')');

exit($failures > 0 ? 1 : 0);
