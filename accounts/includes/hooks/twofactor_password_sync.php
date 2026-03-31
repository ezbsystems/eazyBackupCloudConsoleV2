<?php

use WHMCS\Authentication\CurrentUser;
use WHMCS\Database\Capsule;
use WHMCS\User\User;

if (!function_exists('ebSyncOwnerClientPasswordHashes')) {
    function ebSyncOwnerClientPasswordHashes(int $userId = 0, int $clientId = 0): void
    {
        if ($userId > 0) {
            $userHash = (string) (Capsule::table('tblusers')->where('id', $userId)->value('password') ?? '');
            if ($userHash === '') {
                return;
            }

            $ownerClientIds = Capsule::table('tblusers_clients')
                ->where('auth_user_id', $userId)
                ->where('owner', 1)
                ->pluck('client_id')
                ->map(static function ($value): int {
                    return (int) $value;
                })
                ->filter()
                ->values()
                ->all();

            if (!empty($ownerClientIds)) {
                Capsule::table('tblclients')
                    ->whereIn('id', $ownerClientIds)
                    ->update(['password' => $userHash]);
            }

            return;
        }

        if ($clientId > 0) {
            $clientHash = (string) (Capsule::table('tblclients')->where('id', $clientId)->value('password') ?? '');
            if ($clientHash === '') {
                return;
            }

            $ownerUserIds = Capsule::table('tblusers_clients')
                ->where('client_id', $clientId)
                ->where('owner', 1)
                ->pluck('auth_user_id')
                ->map(static function ($value): int {
                    return (int) $value;
                })
                ->filter()
                ->values()
                ->all();

            if (!empty($ownerUserIds)) {
                Capsule::table('tblusers')
                    ->whereIn('id', $ownerUserIds)
                    ->update(['password' => $clientHash]);
            }
        }
    }
}

if (!function_exists('ebSyncCurrentClientPasswordForTwoFactorDisableConfirm')) {
    function ebSyncCurrentClientPasswordForTwoFactorDisableConfirm(): void
    {
        if (PHP_SAPI === 'cli' || (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')) {
            return;
        }

        $requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        if (!preg_match('~/index\.php/account/security/two-factor/disable/confirm$~', $requestPath)) {
            return;
        }

        $password = (string) ($_POST['pwverify'] ?? '');
        if ($password === '') {
            return;
        }

        try {
            $currentUser = new CurrentUser();
            $user = $currentUser->user();
            $client = $currentUser->client();

            if (!($user instanceof User) || !$client || !$user->isOwner($client)) {
                return;
            }

            if (!$user->verifyPassword($password)) {
                return;
            }

            $clientId = (int) ($client->id ?? 0);
            $userHash = (string) (Capsule::table('tblusers')->where('id', (int) $user->id)->value('password') ?? '');
            $clientHash = (string) (Capsule::table('tblclients')->where('id', $clientId)->value('password') ?? '');

            if ($clientId <= 0 || $userHash === '' || hash_equals($userHash, $clientHash)) {
                return;
            }

            Capsule::table('tblclients')
                ->where('id', $clientId)
                ->update(['password' => $userHash]);
        } catch (\Throwable $e) {
            logActivity('2FA disable password sync hook failed: ' . $e->getMessage());
        }
    }
}

ebSyncCurrentClientPasswordForTwoFactorDisableConfirm();

add_hook('UserChangePassword', 1, function (array $vars) {
    ebSyncOwnerClientPasswordHashes((int) ($vars['userId'] ?? 0), 0);
});

add_hook('ClientChangePassword', 1, function (array $vars) {
    ebSyncOwnerClientPasswordHashes(0, (int) ($vars['userid'] ?? 0));
});
