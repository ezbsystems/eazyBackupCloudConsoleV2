<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\WorkerNodeRepository;
use Ms365Backup\JobQueueRepository;

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

    private const STALE_ALERT_KEY = 'ms365_fleet_stale_alert_at';

    public static function checkStaleRuns(): int
    {
        $stale = JobQueueRepository::countStaleRunning();
        $exhausted = JobQueueRepository::countExhaustedJobs();
        if ($stale <= 0 && $exhausted <= 0) {
            return 0;
        }
        $last = (int) (\WHMCS\Database\Capsule::table('tblconfiguration')
            ->where('setting', self::STALE_ALERT_KEY)
            ->value('value') ?? 0);
        if ($last > 0 && (time() - $last) < 3600) {
            return 0;
        }
        $msg = 'MS365 worker fleet: ' . $stale . ' stale running job(s), ' . $exhausted . ' exhausted job(s) pending cleanup';
        logActivity($msg);
        FleetAuditLog::write('stale_jobs_alert', $msg, 'fleet', '');
        \WHMCS\Database\Capsule::table('tblconfiguration')->updateOrInsert(
            ['setting' => self::STALE_ALERT_KEY],
            ['value' => (string) time()]
        );

        return $stale + $exhausted;
    }
}
