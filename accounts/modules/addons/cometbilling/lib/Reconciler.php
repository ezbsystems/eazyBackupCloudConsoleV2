<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Reconciler - Compare Comet Server usage against Portal billing.
 * Default mode uses aligned stored snapshots; live mode pulls from servers directly.
 */
class Reconciler
{
    private const ITEM_TYPES = [
        'devices' => 'Devices',
        'hyperv_vms' => 'Hyper-V VMs',
        'vmware_vms' => 'VMware VMs',
        'proxmox_vms' => 'Proxmox VMs',
        'disk_image' => 'Disk Image',
        'mssql' => 'MS SQL Server',
        'm365_accounts' => 'M365 Accounts',
    ];

    private const DEFAULT_TOLERANCE = 1;

    /**
     * Run reconciliation using stored snapshots (default).
     */
    public static function compare(?string $snapshotDate = null, int $tolerance = self::DEFAULT_TOLERANCE): array
    {
        return self::compareSnapshot($snapshotDate, $tolerance);
    }

    /**
     * Run reconciliation using live server API pulls.
     */
    public static function compareLive(int $tolerance = self::DEFAULT_TOLERANCE): array
    {
        $serverData = ServerUsageCollector::collectAll();
        $portalData = PortalUsageExtractor::getLatestSnapshot();

        $report = self::buildReport($serverData, $portalData, $tolerance);
        $report['mode'] = 'live';
        $report['snapshot_date'] = null;

        return $report;
    }

    /**
     * Run reconciliation using stored cb_server_usage_combined + aligned portal snapshot.
     */
    public static function compareSnapshot(?string $snapshotDate = null, int $tolerance = self::DEFAULT_TOLERANCE): array
    {
        if ($snapshotDate === null) {
            $snapshotDate = Capsule::table('cb_server_usage_combined')
                ->orderBy('snapshot_date', 'desc')
                ->value('snapshot_date');
        }

        if (!$snapshotDate) {
            throw new \RuntimeException('No server usage snapshots found. Run collect_usage first.');
        }

        $combined = Capsule::table('cb_server_usage_combined')
            ->where('snapshot_date', $snapshotDate)
            ->first();

        if (!$combined) {
            throw new \RuntimeException("No combined snapshot for date {$snapshotDate}");
        }

        $perServerRows = Capsule::table('cb_server_usage')
            ->where('snapshot_date', $snapshotDate)
            ->get();

        $serverData = self::snapshotRowToServerData($combined, $perServerRows);

        $portalPulledAt = PortalUsageExtractor::findSnapshotNear(
            $combined->created_at ?? ($snapshotDate . ' 00:00:00')
        );

        if (!$portalPulledAt) {
            $portalPulledAt = Capsule::table('cb_active_services')->max('pulled_at');
        }

        if (!$portalPulledAt) {
            throw new \RuntimeException('No portal active services snapshot found. Run portal pull first.');
        }

        $portalData = PortalUsageExtractor::getSnapshot($portalPulledAt);

        $report = self::buildReport($serverData, $portalData, $tolerance);
        $report['mode'] = 'snapshot';
        $report['snapshot_date'] = $snapshotDate;

        return $report;
    }

    /**
     * Convert stored snapshot rows to server data format expected by buildReport.
     */
    private static function snapshotRowToServerData(object $combined, $perServerRows): array
    {
        $servers = [];
        foreach ($perServerRows as $row) {
            $servers[$row->server_key] = [
                'server_key' => $row->server_key,
                'users' => (int) $row->total_users,
                'devices' => (int) $row->total_devices,
                'hyperv_vms' => (int) $row->hyperv_vms,
                'vmware_vms' => (int) $row->vmware_vms,
                'proxmox_vms' => (int) $row->proxmox_vms,
                'disk_image' => (int) $row->disk_image,
                'mssql' => (int) $row->mssql,
                'm365_accounts' => (int) $row->m365_accounts,
                'storage_bytes' => (int) $row->storage_bytes,
                'protected_items' => (int) $row->protected_items,
                'collected_at' => $row->created_at,
            ];
        }

        return [
            'collected_at' => $combined->created_at,
            'users' => (int) $combined->total_users,
            'devices' => (int) $combined->total_devices,
            'hyperv_vms' => (int) $combined->hyperv_vms,
            'vmware_vms' => (int) $combined->vmware_vms,
            'proxmox_vms' => (int) $combined->proxmox_vms,
            'disk_image' => (int) $combined->disk_image,
            'mssql' => (int) $combined->mssql,
            'm365_accounts' => (int) $combined->m365_accounts,
            'storage_bytes' => (int) $combined->storage_bytes,
            'protected_items' => (int) $combined->protected_items,
            'servers' => $servers,
            'errors' => [],
            'server_count' => (int) $combined->total_servers,
            'success_count' => count($servers),
        ];
    }

    public static function buildReport(array $serverData, array $portalData, int $tolerance = self::DEFAULT_TOLERANCE): array
    {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'server_collected_at' => $serverData['collected_at'] ?? null,
            'portal_snapshot_at' => $portalData['snapshot_time'] ?? null,
            'tolerance' => $tolerance,
            'items' => [],
            'per_server' => $serverData['servers'] ?? [],
            'other_boosters' => $portalData['other_boosters'] ?? ['count' => 0, 'amount' => 0.0, 'items' => []],
            'unknown' => $portalData['unknown'] ?? ['count' => 0, 'amount' => 0.0, 'types' => []],
            'summary' => [
                'total_items' => 0,
                'ok' => 0,
                'warning' => 0,
                'over_billed' => 0,
                'under_billed' => 0,
                'server_errors' => $serverData['errors'] ?? [],
            ],
            'server_raw' => self::summarizeServerData($serverData),
            'portal_raw' => self::summarizePortalData($portalData),
        ];

        foreach (self::ITEM_TYPES as $key => $label) {
            $serverCount = (float) ($serverData[$key] ?? 0);
            $portalCount = (float) ($portalData[$key]['count'] ?? 0);
            $portalAmount = (float) ($portalData[$key]['amount'] ?? 0.0);
            $variance = $portalCount - $serverCount;
            $status = self::getStatus($variance, $tolerance);

            $item = [
                'label' => $label,
                'server' => $serverCount,
                'portal' => $portalCount,
                'portal_amount' => round($portalAmount, 2),
                'variance' => $variance,
                'variance_pct' => $serverCount > 0 ? round(($variance / $serverCount) * 100, 1) : null,
                'status' => $status,
            ];

            if ($status !== 'ok' && !empty($portalData[$key]['items'])) {
                $item['portal_items'] = $portalData[$key]['items'];
            }

            $report['items'][$key] = $item;

            $report['summary']['total_items']++;
            $report['summary'][$status]++;
        }

        $report['storage'] = [
            'server_bytes' => $serverData['storage_bytes'] ?? 0,
            'server_human' => ServerUsageCollector::formatBytes($serverData['storage_bytes'] ?? 0),
        ];

        $report['overall_status'] = self::getOverallStatus($report['summary']);

        return $report;
    }

    private static function getStatus(float $variance, int $tolerance): string
    {
        if (abs($variance) < 0.0001) {
            return 'ok';
        }
        if (abs($variance) <= $tolerance) {
            return 'warning';
        }
        if ($variance > 0) {
            return 'over_billed';
        }
        return 'under_billed';
    }

    private static function getOverallStatus(array $summary): string
    {
        if (!empty($summary['server_errors'])) {
            return 'incomplete';
        }
        if ($summary['over_billed'] > 0 || $summary['under_billed'] > 0) {
            return 'variance_detected';
        }
        return 'ok';
    }

    private static function summarizeServerData(array $data): array
    {
        return [
            'server_count' => $data['server_count'] ?? 0,
            'success_count' => $data['success_count'] ?? 0,
            'total_users' => $data['users'] ?? 0,
            'total_devices' => $data['devices'] ?? 0,
            'total_protected_items' => $data['protected_items'] ?? 0,
            'storage_bytes' => $data['storage_bytes'] ?? 0,
            'storage_human' => ServerUsageCollector::formatBytes($data['storage_bytes'] ?? 0),
            'servers' => array_keys($data['servers'] ?? []),
            'errors' => $data['errors'] ?? [],
        ];
    }

    private static function summarizePortalData(array $data): array
    {
        return [
            'snapshot_time' => $data['snapshot_time'] ?? null,
            'raw_rows' => $data['raw_rows'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0.0,
            'account_fees' => $data['account_fees']['amount'] ?? 0.0,
            'server_licenses' => $data['server_licenses']['amount'] ?? 0.0,
            'other_boosters_count' => $data['other_boosters']['count'] ?? 0,
            'other_boosters_amount' => $data['other_boosters']['amount'] ?? 0.0,
            'unknown_count' => $data['unknown']['count'] ?? 0,
            'unknown_amount' => $data['unknown']['amount'] ?? 0.0,
        ];
    }

    public static function saveReport(array $report): int
    {
        self::ensureTable();

        $id = Capsule::table('cb_reconciliation_reports')->insertGetId([
            'report_date' => date('Y-m-d H:i:s'),
            'server_collected_at' => $report['server_collected_at'],
            'portal_snapshot_at' => $report['portal_snapshot_at'],
            'overall_status' => $report['overall_status'],
            'summary' => json_encode($report['summary']),
            'items' => json_encode($report['items']),
            'server_data' => json_encode($report['server_raw']),
            'portal_data' => json_encode(array_merge($report['portal_raw'], [
                'mode' => $report['mode'] ?? 'snapshot',
                'snapshot_date' => $report['snapshot_date'] ?? null,
                'tolerance' => $report['tolerance'] ?? self::DEFAULT_TOLERANCE,
                'per_server' => $report['per_server'] ?? [],
                'other_boosters' => $report['other_boosters'] ?? null,
                'unknown' => $report['unknown'] ?? null,
            ])),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public static function getReports(int $limit = 20): array
    {
        self::ensureTable();

        return Capsule::table('cb_reconciliation_reports')
            ->orderBy('report_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->summary = json_decode($row->summary, true);
                $row->items = json_decode($row->items, true);
                return $row;
            })
            ->toArray();
    }

    public static function getReport(int $id): ?object
    {
        self::ensureTable();

        $row = Capsule::table('cb_reconciliation_reports')
            ->where('id', $id)
            ->first();

        if ($row) {
            $row->summary = json_decode($row->summary, true);
            $row->items = json_decode($row->items, true);
            $row->server_data = json_decode($row->server_data, true);
            $row->portal_data = json_decode($row->portal_data, true);
        }

        return $row;
    }

    /**
     * Reconstruct a display report array from a saved DB row.
     */
    public static function reportFromSaved(object $row): array
    {
        $portalData = is_array($row->portal_data) ? $row->portal_data : [];

        return [
            'generated_at' => $row->report_date,
            'server_collected_at' => $row->server_collected_at,
            'portal_snapshot_at' => $row->portal_snapshot_at,
            'overall_status' => $row->overall_status,
            'mode' => $portalData['mode'] ?? 'snapshot',
            'snapshot_date' => $portalData['snapshot_date'] ?? null,
            'tolerance' => $portalData['tolerance'] ?? self::DEFAULT_TOLERANCE,
            'items' => $row->items ?? [],
            'per_server' => $portalData['per_server'] ?? [],
            'other_boosters' => $portalData['other_boosters'] ?? ['count' => 0, 'amount' => 0.0],
            'unknown' => $portalData['unknown'] ?? ['count' => 0, 'amount' => 0.0, 'types' => []],
            'summary' => $row->summary ?? [],
            'server_raw' => $row->server_data ?? [],
            'portal_raw' => $portalData,
            'storage' => [
                'server_bytes' => ($row->server_data['storage_bytes'] ?? 0),
                'server_human' => ServerUsageCollector::formatBytes((int) ($row->server_data['storage_bytes'] ?? 0)),
            ],
        ];
    }

    private static function ensureTable(): void
    {
        if (!Capsule::schema()->hasTable('cb_reconciliation_reports')) {
            Capsule::schema()->create('cb_reconciliation_reports', function ($table) {
                $table->bigIncrements('id');
                $table->dateTime('report_date');
                $table->dateTime('server_collected_at')->nullable();
                $table->dateTime('portal_snapshot_at')->nullable();
                $table->enum('overall_status', ['ok', 'variance_detected', 'incomplete'])->default('ok');
                $table->json('summary')->nullable();
                $table->json('items')->nullable();
                $table->json('server_data')->nullable();
                $table->json('portal_data')->nullable();
                $table->dateTime('created_at');
                $table->index('report_date');
                $table->index('overall_status');
            });
        }
    }

    public static function getVarianceSummary(array $report): string
    {
        $lines = [];

        foreach ($report['items'] as $item) {
            if ($item['status'] !== 'ok') {
                $sign = $item['variance'] > 0 ? '+' : '';
                $lines[] = "{$item['label']}: Server={$item['server']}, Portal={$item['portal']} ({$sign}{$item['variance']})";
            }
        }

        if (empty($lines)) {
            return 'All items match - no variances detected.';
        }

        return implode("\n", $lines);
    }
}
