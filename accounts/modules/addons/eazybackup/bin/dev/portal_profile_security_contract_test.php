<?php

declare(strict_types=1);

/**
 * Contract test: portal profile update + password change wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/portal_profile_security_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);

$indexFile = $repoRoot . '/accounts/portal/index.php';
$settingsTemplateFile = $repoRoot . '/accounts/portal/templates/settings.tpl';
$profileUpdateApiFile = $repoRoot . '/accounts/portal/api/profile_update.php';
$changePasswordApiFile = $repoRoot . '/accounts/portal/api/change_password.php';

$targets = [
    'portal index route file' => [
        'path' => $indexFile,
        'markers' => [
            'api query marker' => "\$api = \$_GET['api'] ?? '';",
            'profile update api route marker' => "'profile_update' => __DIR__ . '/api/profile_update.php'",
            'change password api route marker' => "'change_password' => __DIR__ . '/api/change_password.php'",
            'api include branch marker' => "if (\$api !== '' && isset(\$apiRoutes[\$api])) {",
            'api include marker' => "require_once \$apiRoutes[\$api];",
        ],
    ],
    'settings template file' => [
        'path' => $settingsTemplateFile,
        'markers' => [
            'profile form marker' => 'id="profile-form"',
            'password form marker' => 'id="password-form"',
            'profile endpoint marker' => "index.php?api=profile_update",
            'password endpoint marker' => "index.php?api=change_password",
            'csrf header marker' => "'X-CSRF-Token': csrfToken",
            'profile status marker' => 'id="profile-status"',
            'password status marker' => 'id="password-status"',
        ],
    ],
    'profile update api file' => [
        'path' => $profileUpdateApiFile,
        'markers' => [
            'auth include marker' => "require_once __DIR__ . '/../auth.php';",
            'auth required marker' => '$session = portal_require_auth();',
            'csrf validation marker' => '!portal_validate_csrf()',
            'method guard marker' => "\$_SERVER['REQUEST_METHOD'] !== 'POST'",
            'tenant user update marker' => "Capsule::table('s3_backup_tenant_users')",
            'session profile update marker' => "\$_SESSION['portal_user']['name'] = \$name;",
        ],
    ],
    'change password api file' => [
        'path' => $changePasswordApiFile,
        'markers' => [
            'auth include marker' => "require_once __DIR__ . '/../auth.php';",
            'auth required marker' => '$session = portal_require_auth();',
            'csrf validation marker' => '!portal_validate_csrf()',
            'method guard marker' => "\$_SERVER['REQUEST_METHOD'] !== 'POST'",
            'current password verify marker' => 'password_verify($currentPassword, (string) ($user->password_hash ?? \'\'))',
            'new password hash marker' => 'password_hash($newPassword, PASSWORD_DEFAULT)',
            'tenant user update marker' => "Capsule::table('s3_backup_tenant_users')",
        ],
    ],
];

$failures = [];
foreach ($targets as $targetName => $target) {
    $path = $target['path'];
    $source = @file_get_contents($path);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$targetName} at {$path}";
        continue;
    }

    foreach ($target['markers'] as $markerName => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: missing {$markerName}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "portal-profile-security-contract-ok\n";
exit(0);
