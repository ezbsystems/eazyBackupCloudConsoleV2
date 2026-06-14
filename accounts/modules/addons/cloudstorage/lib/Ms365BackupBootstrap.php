<?php
declare(strict_types=1);

/**
 * Load ms365backup classes from the sibling addon (cloudstorage → ms365backup).
 */
function cloudstorage_load_ms365backup(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $autoload = dirname(__DIR__, 2) . '/ms365backup/ms365backup_autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Microsoft 365 backup engine is not installed.');
    }

    require_once $autoload;
    $loaded = true;
}
