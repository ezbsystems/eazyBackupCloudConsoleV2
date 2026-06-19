<?php

/**
 * Queue MS365 vaults whose recycle grace period has expired for physical teardown.
 * Schedule daily, e.g.:
 * 15 3 * * * /usr/bin/php -q /var/www/eazybackup.ca/accounts/crons/ms365_vault_recycle_teardown.php
 */

require __DIR__ . '/../init.php';

require_once __DIR__ . '/../modules/addons/cloudstorage/lib/Client/Ms365VaultLifecycleService.php';

use WHMCS\Module\Addon\CloudStorage\Client\Ms365VaultLifecycleService;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

$queued = Ms365VaultLifecycleService::queueExpiredVaultsForTeardown();

echo '[ms365_vault_recycle_teardown] queued=' . (int) $queued . ' at ' . gmdate('c') . "\n";
