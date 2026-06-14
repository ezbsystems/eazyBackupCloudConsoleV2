<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use WHMCS\Database\Capsule;

final class FleetAuditLog
{
    public static function write(string $action, string $message, string $subjectType = '', string $subjectId = '', array $context = []): void
    {
        if (!Capsule::schema()->hasTable('ms365_worker_fleet_audit')) {
            return;
        }
        $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : null;
        Capsule::table('ms365_worker_fleet_audit')->insert([
            'admin_id' => $adminId > 0 ? $adminId : null,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'message' => mb_substr($message, 0, 500),
            'context_json' => $context !== [] ? json_encode($context, JSON_UNESCAPED_SLASHES) : null,
            'created_at' => time(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $limit = 50): array
    {
        if (!Capsule::schema()->hasTable('ms365_worker_fleet_audit')) {
            return [];
        }

        return Capsule::table('ms365_worker_fleet_audit')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }
}
