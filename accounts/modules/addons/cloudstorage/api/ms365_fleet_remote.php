<?php
declare(strict_types=1);

/**
 * M2M fleet remote API (dev ↔ prod). Authenticated via X-MS365-Fleet-Deploy-Token.
 * Must live outside /admin/ so WHMCS does not require an admin session.
 */
require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__, 2) . '/ms365backup/ms365backup_autoload.php';
require dirname(__DIR__, 2) . '/ms365backup/pages/admin/fleet_remote.php';
