<?php
declare(strict_types=1);

/**
 * Source-contract tests for managed-only e3 Backup User creation.
 *
 * Run:
 *   php modules/addons/cloudstorage/tests/e3backup_add_user_managed_contract_test.php
 */

$moduleRoot = dirname(__DIR__);
$files = [
    'modal' => $moduleRoot . '/templates/partials/e3backup_create_user_modal.tpl',
    'users' => $moduleRoot . '/templates/e3backup_users.tpl',
    'onboarding' => $moduleRoot . '/templates/partials/e3_onboarding_drawers.tpl',
    'welcome' => $moduleRoot . '/templates/welcome.tpl',
    'create_api' => $moduleRoot . '/api/e3backup_user_create.php',
    'update_api' => $moduleRoot . '/api/e3backup_user_update.php',
    'welcome_api' => $moduleRoot . '/api/setpassword_and_provision.php',
    'provisioner' => $moduleRoot . '/lib/Provision/Provisioner.php',
];

$sources = [];
$failures = [];
foreach ($files as $key => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        $failures[] = "could not read {$path}";
        $source = '';
    }
    $sources[$key] = $source;
}

function contract_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function contract_contains(string $source, string $needle, string $message): void
{
    contract_assert(strpos($source, $needle) !== false, $message);
}

function contract_not_contains(string $source, string $needle, string $message): void
{
    contract_assert(stripos($source, $needle) === false, $message);
}

function contract_order(string $source, array $needles, string $message): void
{
    $last = -1;
    foreach ($needles as $needle) {
        $position = strpos($source, $needle);
        if ($position === false || $position <= $last) {
            contract_assert(false, $message . " (marker: {$needle})");
            return;
        }
        $last = $position;
    }
}

$modal = $sources['modal'];
contract_order($modal, [
    'e3-create-user-username',
    'Managed Recovery',
    'e3-create-user-password',
    'Email backup reports',
    'e3-create-report-settings',
    'e3-create-user-tenant',
], 'modal fields follow the required progressive-disclosure order');
contract_contains($modal, '<div class="eb-subpanel !p-4">', 'managed recovery panel preserves parent vertical rhythm');
contract_contains($modal, 'role="dialog"', 'create modal has dialog role');
contract_contains($modal, 'aria-modal="true"', 'create modal is marked modal');
contract_contains($modal, 'aria-labelledby="e3-create-user-title"', 'create modal references its title');
contract_contains($modal, '@keydown.escape.window="closeCreateModal()"', 'Escape dismisses create modal');
contract_contains($modal, ':aria-expanded="notificationForm.enabled ? \'true\' : \'false\'"', 'report toggle exposes expanded state');
contract_contains($modal, 'aria-controls="e3-create-report-settings"', 'report toggle controls report disclosure');
contract_contains($modal, 'x-show="notificationForm.enabled"', 'report settings are conditionally disclosed');
contract_contains($modal, 'aria-controls="e3-create-mobile-pricing"', 'mobile pricing toggle identifies its disclosure');
contract_contains($modal, 'id="e3-create-mobile-pricing"', 'mobile pricing disclosure has an id');
contract_contains($modal, 'role="region"', 'mobile pricing disclosure has region semantics');
contract_not_contains($modal, 'Strict Customer-Managed', 'create modal hides strict selection');
contract_not_contains($modal, 'Zero-Knowledge', 'create modal has no zero-knowledge claim');
contract_not_contains($modal, 'recovery key', 'create modal has no cosmetic recovery-key flow');
contract_not_contains($modal, 'managed_acknowledged', 'create modal has no managed acknowledgement');

$users = $sources['users'];
contract_contains($users, 'enabled: false', 'new-user report emails default off');
contract_contains($users, "notifications_enabled: this.notificationForm.enabled ? '1' : '0'", 'create request submits report master state');
contract_contains($users, 'createRequestToken: 0', 'create modal tracks async request identity');
contract_contains($users, 'openCreateModal() {' . "\n" . '            if (this.saving)', 'create modal cannot reopen while saving');
contract_contains($users, 'closeCreateModal(force = false)', 'create modal supports an explicit programmatic close');
contract_contains($users, 'if (this.saving && !force)', 'interactive close is blocked while saving');
contract_contains($users, 'if (this.saving) {' . "\n" . '                return;' . "\n" . '            }', 'duplicate create submissions are blocked');
contract_contains($users, 'requestToken !== this.createRequestToken', 'stale create responses are ignored');
contract_contains($users, 'this.closeCreateModal(true)', 'successful create can close while saving');
contract_not_contains($users, 'generateRecoveryKey()', 'client recovery-key generator is removed');
contract_not_contains($users, 'recovery_key_downloaded', 'client recovery-key state is removed');
contract_not_contains($users, 'strict_acknowledged', 'client strict acknowledgement state is removed');
contract_not_contains($users, 'managed_acknowledged', 'client managed acknowledgement state is removed');

foreach (['onboarding', 'welcome'] as $key) {
    contract_not_contains($sources[$key], 'Zero-Knowledge', "{$key} has no zero-knowledge creation claim");
    contract_not_contains($sources[$key], 'recovery key', "{$key} has no recovery-key creation claim");
    contract_not_contains($sources[$key], 'strict_acknowledged', "{$key} has no strict acknowledgement payload");
    contract_not_contains($sources[$key], 'eb_e3_encryption_mode', "{$key} has no encryption chooser");
}

contract_contains($sources['create_api'], '$encryptionMode = \'managed\';', 'create API enforces managed mode');
contract_contains($sources['create_api'], "'notifications_enabled' => \$_POST['notifications_enabled'] ?? false", 'create API defaults reports off');
$provisionerStart = strpos($sources['provisioner'], 'public static function provisionE3BackupUser');
$provisionerEnd = strpos($sources['provisioner'], 'private static function ensureMs365DefaultBackupUser', (int) $provisionerStart);
$provisionE3BackupUserSource = ($provisionerStart !== false && $provisionerEnd !== false)
    ? substr($sources['provisioner'], $provisionerStart, $provisionerEnd - $provisionerStart)
    : $sources['provisioner'];
$profileEmailWriteSources = [
    'create_api' => $sources['create_api'],
    'update_api' => $sources['update_api'],
    'provisioner' => $provisionE3BackupUserSource,
];
foreach ($profileEmailWriteSources as $key => $source) {
    contract_not_contains(
        $source,
        "Capsule::table('tblclients')->where('id', \$clientId)->value('email')",
        "{$key} does not pin the current account-owner email into the backup user profile"
    );
}
contract_contains($sources['welcome_api'], "'encryption_mode' => 'managed'", 'welcome API provisions managed mode');
contract_not_contains($sources['welcome_api'], "\$_POST['encryption_mode']", 'welcome API ignores client encryption mode');
contract_contains($sources['welcome_api'], "'notifications_enabled' => false", 'welcome API defaults new-user reports off');
contract_contains($sources['provisioner'], '$encryptionMode = \'managed\';', 'provisioner enforces managed mode for new rows');
contract_contains($sources['provisioner'], "? (bool) \$spec['notifications_enabled'] : false", 'provisioner defaults new-user reports off');

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo "FAIL: {$failure}\n";
    }
    echo "\n" . count($failures) . " contract failure(s).\n";
    exit(1);
}

echo "e3backup-add-user-managed-contract-test-ok\n";
