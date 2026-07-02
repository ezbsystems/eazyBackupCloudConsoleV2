<?php
declare(strict_types=1);

/**
 * Notification policy + settings validation tests.
 *
 * Run: php accounts/modules/addons/cloudstorage/tests/backup_user_notification_policy_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__) . '/lib/Client/CloudBackupNotificationPolicy.php';
require_once dirname(__DIR__) . '/lib/Client/BackupUserNotificationSettingsService.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\BackupUserNotificationSettingsService;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupNotificationPolicy;

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

function assert_same($expected, $actual, string $message): void
{
    assert_true($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

// parseEmailList
assert_same(
    ['a@example.com', 'b@example.com'],
    CloudBackupNotificationPolicy::parseEmailList('a@example.com,b@example.com'),
    'parseEmailList comma-separated'
);
assert_same(
    ['ops@example.com'],
    CloudBackupNotificationPolicy::parseEmailList('["ops@example.com"]'),
    'parseEmailList JSON array'
);

// validatePayload
$invalid = BackupUserNotificationSettingsService::validatePayload([
    'notify_emails' => ['not-an-email'],
]);
assert_true(!empty($invalid['errors']['notify_emails']), 'invalid email rejected by service validation');

$duplicate = BackupUserNotificationSettingsService::validatePayload([
    'notify_emails' => ['dup@example.com', 'dup@example.com'],
]);
assert_true(!empty($duplicate['errors']['notify_emails']), 'duplicate email rejected');

$entityEncoded = BackupUserNotificationSettingsService::validatePayload([
    'notify_emails' => '[&quot;support@eazybackup.ca&quot;]',
]);
assert_true(empty($entityEncoded['errors']), 'WHMCS entity-encoded JSON notify_emails accepted');
assert_same(['support@eazybackup.ca'], $entityEncoded['dto']['notify_emails'], 'entity-encoded JSON parsed');

$jsonInArray = BackupUserNotificationSettingsService::validatePayload([
    'notify_emails' => ['["support@eazybackup.ca"]'],
]);
assert_true(empty($jsonInArray['errors']), 'JSON string inside notify_emails array accepted');
assert_same(['support@eazybackup.ca'], $jsonInArray['dto']['notify_emails'], 'JSON string inside array parsed');

$schema = Capsule::schema();
$hasNotifyCols = $schema->hasTable('s3_backup_users')
    && $schema->hasColumn('s3_backup_users', 'notifications_enabled');

if ($hasNotifyCols) {
    $client = Capsule::table('tblclients')->orderBy('id', 'asc')->first(['id', 'email']);
    assert_true($client !== null, 'client row available for integration checks');

    if ($client !== null) {
        $clientId = (int) $client->id;
        $clientEmail = strtolower(trim((string) ($client->email ?? '')));
        $backupUserId = 0;

        try {
            $backupUserId = (int) Capsule::table('s3_backup_users')->insertGetId([
                'client_id' => $clientId,
                'tenant_id' => null,
                'username' => 'notify_test_' . bin2hex(random_bytes(4)),
                'password_hash' => password_hash('test', PASSWORD_DEFAULT),
                'email' => 'backup-user@example.com',
                'status' => 'active',
                'backup_type' => 'both',
                'notifications_enabled' => 0,
                'notify_emails' => json_encode(['user-list@example.com']),
                'notify_on_success' => 0,
                'notify_on_warning' => 1,
                'notify_on_failure' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $disabledDecision = CloudBackupNotificationPolicy::resolve([
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
            ], 'failed', $client);
            assert_true($disabledDecision['send'] === false, 'master disabled → send false');
            assert_same('notifications_disabled', $disabledDecision['reason'], 'master disabled reason');

            Capsule::table('s3_backup_users')->where('id', $backupUserId)->update([
                'notifications_enabled' => 1,
                'notify_emails' => json_encode(['user-list@example.com']),
                'notify_on_success' => 0,
                'notify_on_warning' => 0,
                'notify_on_failure' => 1,
            ]);

            $userRecipients = CloudBackupNotificationPolicy::resolve([
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
            ], 'failed', $client);
            assert_same(['user-list@example.com'], $userRecipients['recipients'], 'backup user emails used when job has no override');

            $overrideRecipients = CloudBackupNotificationPolicy::resolve([
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
                'notify_override_email' => 'override@example.com',
            ], 'failed', $client);
            assert_same(['override@example.com'], $overrideRecipients['recipients'], 'job notify_override_email wins');

            $warningBlocked = CloudBackupNotificationPolicy::resolve([
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
                'notify_on_warning' => 1,
            ], 'partial_success', $client);
            assert_true($warningBlocked['send'] === true, 'partial_success allowed when warning toggle on');

            Capsule::table('s3_backup_users')->where('id', $backupUserId)->update([
                'notify_on_warning' => 0,
            ]);
            $warningSkipped = CloudBackupNotificationPolicy::resolve([
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
                'notify_on_warning' => null,
            ], 'warning', $client);
            assert_true($warningSkipped['send'] === false, 'partial_success/warning respects warning toggle');

            Capsule::table('s3_backup_users')->where('id', $backupUserId)->update([
                'notify_on_success' => 1,
                'notify_on_warning' => 1,
                'notify_on_failure' => 0,
            ]);
            $jobOverridesUser = CloudBackupNotificationPolicy::resolve([
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
                'notify_on_failure' => 1,
            ], 'failed', $client);
            assert_true($jobOverridesUser['send'] === true, 'outcome toggle precedence: job over backup user');

            $defaultJobTogglesInheritUser = CloudBackupNotificationPolicy::resolve([
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
                'notify_on_success' => 0,
                'notify_on_warning' => 1,
                'notify_on_failure' => 1,
            ], 'success', $client);
            assert_true($defaultJobTogglesInheritUser['send'] === true, 'schema-default job toggles inherit backup user success setting');
            assert_true(
                in_array('user-list@example.com', $defaultJobTogglesInheritUser['recipients'], true),
                'schema-default job toggles still resolve backup user recipients'
            );

            if ($clientEmail !== '') {
                Capsule::table('s3_backup_users')->where('id', $backupUserId)->update([
                    'notify_emails' => null,
                ]);
                $fallbackRecipients = CloudBackupNotificationPolicy::resolve([
                    'client_id' => $clientId,
                    'backup_user_id' => $backupUserId,
                ], 'failed', $client);
                assert_true(in_array($clientEmail, array_map('strtolower', $fallbackRecipients['recipients']), true)
                    || in_array('backup-user@example.com', $fallbackRecipients['recipients'], true),
                    'recipients fall back to backup user or client email');
            }
        } finally {
            if ($backupUserId > 0) {
                Capsule::table('s3_backup_users')->where('id', $backupUserId)->delete();
            }
        }
    }
} else {
    echo "SKIP: s3_backup_users notification columns not present (run module upgrade first)\n";
}

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
