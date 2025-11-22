<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../lib/Whitelabel/MailService.php';

class EmailTriggers
{
    const WELCOME_ON_SIGNUP = 'welcome';

    public static function trigger(int $tenantId, string $key, string $toEmail, array $vars = []): void
    {
        try {
            $ms = new \EazyBackup\Whitelabel\MailService([]);
            $ms->sendTemplate($tenantId, $key, $toEmail, $vars);
        } catch (\Throwable $__) { /* swallow trigger errors */ }
    }
}


