<?php
/**
 * DEV ONLY — surfaces the client-area login password on the admin client Profile tab.
 *
 * WHMCS stores passwords as one-way hashes; plaintext cannot be recovered from the DB.
 * This hook captures passwords when they are set/changed and shows the latest capture here.
 *
 * Enable in configuration.php (development servers only):
 *   define('EB_DEV_PASSWORD_VIEW', true);
 *
 * Never enable on production.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

if (!function_exists('ebDevPasswordViewEnabled')) {
    function ebDevPasswordViewEnabled(): bool
    {
        return defined('EB_DEV_PASSWORD_VIEW') && EB_DEV_PASSWORD_VIEW === true;
    }
}

if (!function_exists('ebDevPasswordEnsureSchema')) {
    function ebDevPasswordEnsureSchema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;

        if (Capsule::schema()->hasTable('eb_dev_client_passwords')) {
            return;
        }

        Capsule::schema()->create('eb_dev_client_passwords', function ($table) {
            $table->unsignedInteger('client_id')->primary();
            $table->unsignedInteger('user_id')->nullable();
            $table->text('password_enc');
            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 64)->nullable();
        });
    }
}

if (!function_exists('ebDevPasswordOwnerUserId')) {
    function ebDevPasswordOwnerUserId(int $clientId): int
    {
        if ($clientId <= 0) {
            return 0;
        }

        $owner = Capsule::table('tblusers_clients')
            ->where('client_id', $clientId)
            ->where('owner', 1)
            ->value('auth_user_id');

        if ($owner) {
            return (int) $owner;
        }

        $any = Capsule::table('tblusers_clients')
            ->where('client_id', $clientId)
            ->orderByDesc('owner')
            ->value('auth_user_id');

        return (int) ($any ?? 0);
    }
}

if (!function_exists('ebDevPasswordClientIdsForUser')) {
    function ebDevPasswordClientIdsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return Capsule::table('tblusers_clients')
            ->where('auth_user_id', $userId)
            ->pluck('client_id')
            ->map(static function ($id): int {
                return (int) $id;
            })
            ->filter()
            ->values()
            ->all();
    }
}

if (!function_exists('ebDevPasswordAdminUsername')) {
    function ebDevPasswordAdminUsername(): string
    {
        if (empty($_SESSION['adminid'])) {
            return 'system';
        }

        return (string) (Capsule::table('tbladmins')
            ->where('id', (int) $_SESSION['adminid'])
            ->value('username') ?? 'system');
    }
}

if (!function_exists('ebDevPasswordStoreCapture')) {
    function ebDevPasswordStoreCapture(int $clientId, int $userId, string $password, string $updatedBy = 'hook'): void
    {
        if (!ebDevPasswordViewEnabled() || $clientId <= 0 || $password === '') {
            return;
        }

        ebDevPasswordEnsureSchema();

        $encrypted = encrypt($password);
        $now = date('Y-m-d H:i:s');

        Capsule::table('eb_dev_client_passwords')->updateOrInsert(
            ['client_id' => $clientId],
            [
                'user_id' => $userId > 0 ? $userId : null,
                'password_enc' => $encrypted,
                'updated_at' => $now,
                'updated_by' => substr($updatedBy, 0, 64),
            ],
        );
    }
}

if (!function_exists('ebDevPasswordLoadCapture')) {
    function ebDevPasswordLoadCapture(int $clientId): ?array
    {
        if (!ebDevPasswordViewEnabled() || $clientId <= 0) {
            return null;
        }

        ebDevPasswordEnsureSchema();

        $row = Capsule::table('eb_dev_client_passwords')
            ->where('client_id', $clientId)
            ->first();

        if (!$row || empty($row->password_enc)) {
            return null;
        }

        try {
            $plain = decrypt((string) $row->password_enc);
        } catch (\Throwable $e) {
            return null;
        }

        if ($plain === '') {
            return null;
        }

        return [
            'password' => $plain,
            'user_id' => (int) ($row->user_id ?? 0),
            'updated_at' => (string) ($row->updated_at ?? ''),
            'updated_by' => (string) ($row->updated_by ?? ''),
        ];
    }
}

if (!function_exists('ebDevPasswordApplyChange')) {
    function ebDevPasswordApplyChange(int $clientId, int $userId, string $newPassword): array
    {
        if ($clientId <= 0 || $newPassword === '') {
            return ['ok' => false, 'message' => 'Missing client or password.'];
        }

        $adminUser = ebDevPasswordAdminUsername();
        $errors = [];

        if ($userId > 0) {
            $resp = localAPI('UpdateUser', [
                'user_id' => $userId,
                'password2' => $newPassword,
            ], $adminUser);

            if (($resp['result'] ?? '') !== 'success') {
                $errors[] = 'UpdateUser: ' . ($resp['message'] ?? 'failed');
            }
        }

        $clientResp = localAPI('UpdateClient', [
            'clientid' => $clientId,
            'password2' => $newPassword,
        ], $adminUser);

        if (($clientResp['result'] ?? '') !== 'success') {
            $errors[] = 'UpdateClient: ' . ($clientResp['message'] ?? 'failed');
        }

        if (!empty($errors) && $userId <= 0) {
            return ['ok' => false, 'message' => implode(' ', $errors)];
        }

        ebDevPasswordStoreCapture($clientId, $userId, $newPassword, $adminUser);

        if (!empty($errors)) {
            return ['ok' => true, 'message' => 'Password saved locally; API reported: ' . implode(' ', $errors)];
        }

        return ['ok' => true, 'message' => 'Password updated.'];
    }
}

if (!ebDevPasswordViewEnabled()) {
    return;
}

add_hook('UserChangePassword', 1, function (array $vars) {
    $password = (string) ($vars['password'] ?? '');
    $userId = (int) ($vars['userId'] ?? $vars['userid'] ?? 0);
    if ($password === '' || $userId <= 0) {
        return;
    }

    foreach (ebDevPasswordClientIdsForUser($userId) as $clientId) {
        ebDevPasswordStoreCapture($clientId, $userId, $password, 'UserChangePassword');
    }
});

add_hook('ClientChangePassword', 1, function (array $vars) {
    $password = (string) ($vars['password'] ?? '');
    $clientId = (int) ($vars['userid'] ?? 0);
    if ($password === '' || $clientId <= 0) {
        return;
    }

    ebDevPasswordStoreCapture($clientId, ebDevPasswordOwnerUserId($clientId), $password, 'ClientChangePassword');
});

add_hook('AdminClientProfileTabFields', 1, function (array $vars) {
    $clientId = (int) ($vars['userid'] ?? $vars['id'] ?? 0);
    if ($clientId <= 0) {
        return [];
    }

    $userId = ebDevPasswordOwnerUserId($clientId);
    $userEmail = '';
    if ($userId > 0) {
        $userEmail = (string) (Capsule::table('tblusers')->where('id', $userId)->value('email') ?? '');
    }

    $capture = ebDevPasswordLoadCapture($clientId);
    $capturedPassword = $capture['password'] ?? '';
    $capturedAt = $capture['updated_at'] ?? '';
    $capturedBy = $capture['updated_by'] ?? '';

    $usersTabUrl = 'clientssummary.php?userid=' . $clientId . '#tab=2';
    $passwordValue = htmlspecialchars($capturedPassword, ENT_QUOTES, 'UTF-8');
    $emailValue = htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8');
    $capturedMeta = '';
    if ($capturedAt !== '') {
        $capturedMeta = '<div class="text-muted" style="margin-top:6px;font-size:12px;">Last captured '
            . htmlspecialchars($capturedAt, ENT_QUOTES, 'UTF-8')
            . ($capturedBy !== '' ? ' by ' . htmlspecialchars($capturedBy, ENT_QUOTES, 'UTF-8') : '')
            . '</div>';
    }

    $noCaptureNote = $capturedPassword === ''
        ? '<div class="alert alert-warning" style="margin-top:8px;margin-bottom:0;padding:8px 10px;">'
            . 'No password captured yet. Existing hashes cannot be reversed. Set a new password below or change it via the '
            . '<a href="' . htmlspecialchars($usersTabUrl, ENT_QUOTES, 'UTF-8') . '">Users tab</a> to record it here.'
            . '</div>'
        : '';

    $html = <<<HTML
<div class="eb-dev-password-panel">
    <div class="alert alert-info" style="margin-bottom:10px;padding:8px 10px;">
        <strong>Development only.</strong> Passwords are captured when set or changed while this hook is enabled.
    </div>
    <div class="form-group">
        <label>Owner user</label>
        <div class="form-control-static">{$emailValue}</div>
    </div>
    <div class="form-group">
        <label for="eb_dev_password_display">Captured client-area password</label>
        <div class="input-group">
            <input type="password" id="eb_dev_password_display" class="form-control" readonly value="{$passwordValue}" autocomplete="off">
            <span class="input-group-btn">
                <button type="button" class="btn btn-default" id="eb_dev_password_toggle" title="Show password">Show</button>
            </span>
        </div>
        {$capturedMeta}
        {$noCaptureNote}
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label for="eb_dev_new_password">Set new password</label>
        <input type="text" name="eb_dev_new_password" id="eb_dev_new_password" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep current password">
        <p class="help-block" style="margin-bottom:0;">Saved when you click <em>Save Changes</em> on this profile. Updates the owner user and records the plaintext here.</p>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('eb_dev_password_toggle');
    var input = document.getElementById('eb_dev_password_display');
    if (!btn || !input) { return; }
    btn.addEventListener('click', function () {
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.textContent = showing ? 'Show' : 'Hide';
    });
})();
</script>
HTML;

    return [
        'Client Area Password (Dev)' => $html,
    ];
});

add_hook('AdminClientProfileTabFieldsSave', 1, function (array $vars) {
    $clientId = (int) ($vars['userid'] ?? $vars['id'] ?? 0);
    $newPassword = trim((string) ($_REQUEST['eb_dev_new_password'] ?? ''));
    if ($clientId <= 0 || $newPassword === '') {
        return;
    }

    $userId = ebDevPasswordOwnerUserId($clientId);
    $result = ebDevPasswordApplyChange($clientId, $userId, $newPassword);

    if (!empty($result['message'])) {
        logActivity('Dev password view hook (client ' . $clientId . '): ' . $result['message']);
    }
});
