<?php
declare(strict_types=1);

namespace EazyBackup\Notifications;

use WHMCS\Database\Capsule;

final class IdempotencyStore
{
    /**
     * Attempt to reserve a (username, category, threshold_key).
     * - Inserts a new row with status='pending' if not exists.
     * - If a row exists with status in ('pending','failed'), reuses it (updates details, keeps status='pending').
     * - If a row exists with status='sent', returns null (skip send).
     * Returns row id when a send should proceed; null to skip.
     */
    public static function reserve(string $username, string $category, string $key, array $baseRow, ?\PDO $pdo = null): ?int
    {
        $now = date('Y-m-d H:i:s');
        // First try optimistic insert with 'pending'
        try {
            $id = Capsule::table('eb_notifications_sent')->insertGetId(array_merge($baseRow, [
                'username' => $username,
                'category' => $category,
                'threshold_key' => $key,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            return (int)$id;
        } catch (\Throwable $e) {
            // Likely duplicate; look up existing row
            try {
                $row = Capsule::table('eb_notifications_sent')
                    ->where('username', $username)
                    ->where('category', $category)
                    ->where('threshold_key', $key)
                    ->first(['id','status']);
                if ($row) {
                    $id = (int)$row->id;
                    $status = (string)$row->status;
                    if ($status === 'sent') {
                        return null;
                    }
                    // Reuse pending/failed: refresh details and set pending
                    Capsule::table('eb_notifications_sent')->where('id', $id)->update([
                        'template' => (string)$baseRow['template'],
                        'subject' => (string)$baseRow['subject'],
                        'recipients' => (string)$baseRow['recipients'],
                        'merge_json' => (string)$baseRow['merge_json'],
                        'status' => 'pending',
                        'updated_at' => $now,
                    ]);
                    return $id;
                }
            } catch (\Throwable $_) { /* fall through */ }

            // Fallback to PDO path for both insert and reuse flows
            if ($pdo) {
                try {
                    // Try insert
                    $sql = "INSERT INTO eb_notifications_sent
                            (service_id, client_id, username, category, threshold_key, template, subject, recipients, merge_json, email_log_id, status, error, created_at, updated_at)
                            VALUES (:service_id,:client_id,:username,:category,:threshold_key,:template,:subject,:recipients,:merge_json,NULL,'pending',NULL,:created_at,:updated_at)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':service_id' => (int)$baseRow['service_id'],
                        ':client_id' => (int)$baseRow['client_id'],
                        ':username' => $username,
                        ':category' => $category,
                        ':threshold_key' => $key,
                        ':template' => (string)$baseRow['template'],
                        ':subject' => (string)$baseRow['subject'],
                        ':recipients' => (string)$baseRow['recipients'],
                        ':merge_json' => (string)$baseRow['merge_json'],
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]);
                    return (int)$pdo->lastInsertId();
                } catch (\Throwable $_) {
                    // Duplicate: fetch and reuse
                    try {
                        $sel = $pdo->prepare("SELECT id, status FROM eb_notifications_sent WHERE username=? AND category=? AND threshold_key=? LIMIT 1");
                        $sel->execute([$username, $category, $key]);
                        $r = $sel->fetch(\PDO::FETCH_ASSOC);
                        if ($r) {
                            $id = (int)$r['id'];
                            $status = (string)$r['status'];
                            if ($status === 'sent') { return null; }
                            $upd = $pdo->prepare("UPDATE eb_notifications_sent SET template=?, subject=?, recipients=?, merge_json=?, status='pending', updated_at=? WHERE id=?");
                            $upd->execute([(string)$baseRow['template'], (string)$baseRow['subject'], (string)$baseRow['recipients'], (string)$baseRow['merge_json'], $now, $id]);
                            return $id;
                        }
                    } catch (\Throwable $__) { return null; }
                }
            }
            return null;
        }
    }

    public static function markFailed(int $id, string $error, ?\PDO $pdo = null): void
    {
        try {
            Capsule::table('eb_notifications_sent')->where('id', $id)->update([
                'status' => 'failed',
                'error' => $error,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE eb_notifications_sent SET status='failed', error=:err, updated_at=:u WHERE id=:id");
                    $stmt->execute([':err'=>$error, ':u'=>date('Y-m-d H:i:s'), ':id'=>$id]);
                } catch (\Throwable $_) { /* ignore */ }
            }
        }
    }

    public static function attachEmailLog(int $id, int $emailLogId, ?\PDO $pdo = null): void
    {
        try {
            Capsule::table('eb_notifications_sent')->where('id', $id)->update([
                'email_log_id' => $emailLogId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE eb_notifications_sent SET email_log_id=:lid, updated_at=:u WHERE id=:id");
                    $stmt->execute([':lid'=>$emailLogId, ':u'=>date('Y-m-d H:i:s'), ':id'=>$id]);
                } catch (\Throwable $_) { /* ignore */ }
            }
        }
    }

    public static function markSent(int $id, ?int $emailLogId = null, ?\PDO $pdo = null): void
    {
        $now = date('Y-m-d H:i:s');
        try {
            $data = ['status' => 'sent', 'updated_at' => $now];
            if ($emailLogId && $emailLogId > 0) { $data['email_log_id'] = $emailLogId; }
            Capsule::table('eb_notifications_sent')->where('id', $id)->update($data);
        } catch (\Throwable $e) {
            if ($pdo) {
                try {
                    if ($emailLogId && $emailLogId > 0) {
                        $stmt = $pdo->prepare("UPDATE eb_notifications_sent SET status='sent', email_log_id=:lid, updated_at=:u WHERE id=:id");
                        $stmt->execute([':lid'=>$emailLogId, ':u'=>$now, ':id'=>$id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE eb_notifications_sent SET status='sent', updated_at=:u WHERE id=:id");
                        $stmt->execute([':u'=>$now, ':id'=>$id]);
                    }
                } catch (\Throwable $_) { /* ignore */ }
            }
        }
    }
}


