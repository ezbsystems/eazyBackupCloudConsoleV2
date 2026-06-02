<?php
declare(strict_types=1);

if (!class_exists('Ms365Backup\\GraphClient', false)) {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Ms365Backup\\';
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/lib/Ms365Backup/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}
