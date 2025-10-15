<?php
declare(strict_types=1);

use WHMCS\Database\Capsule;

// Lightweight function shims called from the WS worker.
// They delegate to the Notification Service while keeping the worker lean.

// Lazy-load the service classes only when first invoked
if (!function_exists('eb_notifications_service')) {
    function eb_notifications_service() {
        static $svc = null;
        if ($svc) return $svc;
        // Load minimal set of classes (no composer PSR-4 here)
        require_once __DIR__ . '/src/Config.php';
        require_once __DIR__ . '/src/RecipientResolver.php';
        require_once __DIR__ . '/src/StorageThresholds.php';
        require_once __DIR__ . '/src/IdempotencyStore.php';
        require_once __DIR__ . '/src/PricingCalculator.php';
        require_once __DIR__ . '/src/TemplateRenderer.php';
        require_once __DIR__ . '/src/NotificationService.php';
        $svc = new \EazyBackup\Notifications\NotificationService();
        return $svc;
    }
}

if (!function_exists('eb_notify_device_registered')) {
    function eb_notify_device_registered(PDO $pdo, string $profile, string $username, string $deviceId, array $payload): void {
        try { eb_notifications_service()->onDeviceRegistered($pdo, $profile, $username, $deviceId, $payload); } catch (\Throwable $e) { /* ignore */ }
    }
}

if (!function_exists('eb_notify_account_updated')) {
    function eb_notify_account_updated(PDO $pdo, string $profile, string $username): void {
        try { eb_notifications_service()->onAccountProfileUpdated($pdo, $profile, $username); } catch (\Throwable $e) { /* ignore */ }
    }
}

if (!function_exists('eb_notify_backup_completed')) {
    function eb_notify_backup_completed(PDO $pdo, string $profile, string $username): void {
        try { eb_notifications_service()->onBackupCompleted($pdo, $profile, $username); } catch (\Throwable $e) { /* ignore */ }
    }
}


