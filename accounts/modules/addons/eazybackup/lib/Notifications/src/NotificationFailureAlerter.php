<?php
declare(strict_types=1);

namespace EazyBackup\Notifications;

use PDO;
use WHMCS\Database\Capsule;

final class NotificationFailureAlerter
{
    public static function alert(int $notificationId, string $error, ?PDO $pdo = null): void
    {
        try {
            if (!Config::bool('ws_alert_enabled', true) || !function_exists('localAPI')) {
                return;
            }

            $row = self::notificationRow($notificationId, $pdo);
            if (!$row) {
                return;
            }

            $fingerprint = sha1(implode('|', [
                (string)($row['category'] ?? ''),
                (string)($row['template'] ?? ''),
                self::normalizeError($error),
            ]));

            if (!self::shouldSend($fingerprint)) {
                return;
            }

            $recipients = self::recipients($pdo);
            if ($recipients === []) {
                self::log('Notification failure alert suppressed: no admin recipients resolved');
                return;
            }

            $subject = '[EazyBackup] Notification delivery failed';
            $body = self::messageBody($notificationId, $row, $error);
            $sent = false;

            foreach ($recipients as $to) {
                $resp = @localAPI('SendAdminEmail', [
                    'customsubject' => $subject,
                    'custommessage' => $body,
                    'to' => $to,
                ]);
                if (!is_array($resp) || ($resp['result'] ?? '') !== 'success') {
                    $resp = @localAPI('SendEmail', [
                        'customtype' => 'general',
                        'customsubject' => $subject,
                        'custommessage' => $body,
                        'to' => $to,
                    ]);
                }

                if (is_array($resp) && ($resp['result'] ?? '') === 'success') {
                    $sent = true;
                } else {
                    self::log('Notification failure alert send failed to ' . $to . ': ' . json_encode($resp));
                }
            }

            if ($sent) {
                self::markSent($fingerprint);
                self::log('Notification failure alert sent for notification_id=' . $notificationId);
            }
        } catch (\Throwable $e) {
            self::log('Notification failure alert failed: ' . $e->getMessage());
        }
    }

    private static function notificationRow(int $notificationId, ?PDO $pdo = null): ?array
    {
        try {
            $row = Capsule::table('eb_notifications_sent')
                ->where('id', $notificationId)
                ->first(['id', 'service_id', 'client_id', 'username', 'category', 'threshold_key', 'template', 'subject', 'recipients', 'email_log_id', 'status', 'error', 'created_at', 'updated_at']);
            if ($row) {
                return (array)$row;
            }
        } catch (\Throwable $e) {
            // Fall back to PDO below.
        }

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT id, service_id, client_id, username, category, threshold_key, template, subject, recipients, email_log_id, status, error, created_at, updated_at FROM eb_notifications_sent WHERE id = ? LIMIT 1");
                $stmt->execute([$notificationId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return is_array($row) ? $row : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Use the existing websocket alert recipients so production does not need
     * another required addon setting. Falls back to active admin emails.
     *
     * @return string[]
     */
    private static function recipients(?PDO $pdo = null): array
    {
        $csv = trim((string)Config::get('ws_alert_admin_email', ''));
        $candidates = [];
        if ($csv !== '') {
            $candidates = preg_split('/[\s,;]+/', $csv) ?: [];
        } else {
            try {
                $candidates = Capsule::table('tbladmins')
                    ->where('disabled', 0)
                    ->whereNotNull('email')
                    ->whereRaw("TRIM(email) <> ''")
                    ->pluck('email')
                    ->all();
            } catch (\Throwable $e) {
                if ($pdo) {
                    try {
                        $rows = $pdo->query("SELECT email FROM tbladmins WHERE disabled = 0 AND email IS NOT NULL AND TRIM(email) <> ''")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                        $candidates = array_map('strval', $rows);
                    } catch (\Throwable $__) {
                        $candidates = [];
                    }
                }
            }
        }

        $valid = [];
        foreach ($candidates as $email) {
            $email = strtolower(trim((string)$email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[$email] = true;
            }
        }

        return array_keys($valid);
    }

    private static function shouldSend(string $fingerprint): bool
    {
        $path = self::stateFile($fingerprint);
        $cooldown = self::cooldownSeconds();
        $now = time();
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $ts = is_array($data) ? (int)($data['ts'] ?? 0) : 0;
            if ($ts > 0 && ($now - $ts) < $cooldown) {
                return false;
            }
        }

        return true;
    }

    private static function markSent(string $fingerprint): void
    {
        $payload = json_encode(['fingerprint' => $fingerprint, 'ts' => time()], JSON_UNESCAPED_SLASHES);
        if (is_string($payload)) {
            @file_put_contents(self::stateFile($fingerprint), $payload);
        }
    }

    private static function stateFile(string $fingerprint): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/eazybackup-notify-failure-alert-' . preg_replace('/[^a-f0-9]/', '', $fingerprint) . '.json';
    }

    private static function cooldownSeconds(): int
    {
        $mins = (int)trim((string)Config::get('ws_alert_cooldown_min', '30'));
        if ($mins < 1) {
            $mins = 30;
        }

        return $mins * 60;
    }

    private static function normalizeError(string $error): string
    {
        $error = preg_replace('/\s+/', ' ', trim($error)) ?? trim($error);
        return substr($error, 0, 240);
    }

    private static function messageBody(int $notificationId, array $row, string $error): string
    {
        $host = gethostname() ?: php_uname('n');

        return "An eazyBackup notification failed after being accepted for delivery by the websocket worker.\n\n"
            . "Host: {$host}\n"
            . "Notification row ID: {$notificationId}\n"
            . "Status: failed\n"
            . "Category: " . (string)($row['category'] ?? '') . "\n"
            . "Template: " . (string)($row['template'] ?? '') . "\n"
            . "Subject: " . (string)($row['subject'] ?? '') . "\n"
            . "Username: " . (string)($row['username'] ?? '') . "\n"
            . "Service ID: " . (string)($row['service_id'] ?? '') . "\n"
            . "Client ID: " . (string)($row['client_id'] ?? '') . "\n"
            . "Threshold key: " . (string)($row['threshold_key'] ?? '') . "\n"
            . "Intended recipients: " . (string)($row['recipients'] ?? '') . "\n"
            . "Time: " . gmdate('Y-m-d H:i:s') . " UTC\n\n"
            . "WHMCS error:\n" . $error . "\n";
    }

    private static function log(string $message): void
    {
        $message = '[notify-alert] ' . $message;
        if (function_exists('logActivity')) {
            try {
                logActivity($message);
                return;
            } catch (\Throwable $e) {
                $message .= ' (logActivity failed: ' . $e->getMessage() . ')';
            }
        }

        error_log($message);
    }
}

