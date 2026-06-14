<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\WorkerNodeRepository;

final class FleetAlertService
{
    private const CACHE_KEY = 'ms365_fleet_offline_alert_at';

    public static function checkOfflineNodes(): int
    {
        $minutes = FleetSettings::offlineAlertMinutes();
        $nodes = WorkerNodeRepository::offlineBeyond($minutes * 60);
        if ($nodes === []) {
            return 0;
        }
        $last = (int) (\WHMCS\Database\Capsule::table('tblconfiguration')
            ->where('setting', self::CACHE_KEY)
            ->value('value') ?? 0);
        if ($last > 0 && (time() - $last) < 3600) {
            return 0;
        }
        $names = array_map(static fn ($n) => (string) ($n['hostname'] ?? $n['node_id']), $nodes);
        $msg = 'MS365 worker fleet: ' . count($nodes) . ' node(s) offline beyond ' . $minutes . 'm: ' . implode(', ', $names);
        logActivity($msg);
        FleetAuditLog::write('offline_alert', $msg, 'fleet', '');

        return count($nodes);
    }
}
