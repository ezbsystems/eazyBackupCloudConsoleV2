<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Reconciler - Compare Comet Server actual usage against Portal billing.
 * Generates variance reports to identify discrepancies.
 */
class Reconciler
{
    /**
     * Billable item categories to compare.
     */
    private const ITEM_TYPES = [
        'devices' => 'Devices',
        'hyperv_vms' => 'Hyper-V VMs',
        'vmware_vms' => 'VMware VMs',
        'proxmox_vms' => 'Proxmox VMs',
        'disk_image' => 'Disk Image',
        'mssql' => 'MS SQL Server',
        'm365_accounts' => 'M365 Accounts',
    ];

    /**
     * Run a full reconciliation comparison.
     * Collects current server usage and compares against latest portal snapshot.
     * 
     * @return array Reconciliation report with variances
     */
    public static function compare(): array
    {
        // Collect server usage
        $serverData = ServerUsageCollector::collectAll();
        
        // Get portal billing data
        $portalData = PortalUsageExtractor::getLatestSnapshot();
        
        return self::buildReport($serverData, $portalData);
    }

    /**
     * Build reconciliation report from server and portal data.
     * 
     * @param array $serverData
     * @param array $portalData
     * @return array
     */
    public static function buildReport(array $serverData, array $portalData): array
    {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'server_collected_at' => $serverData['collected_at'] ?? null,
            'portal_snapshot_at' => $portalData['snapshot_time'] ?? null,
            'items' => [],
            'summary' => [
                'total_items' => 0,
                'ok' => 0,
                'over_billed' => 0,
                'under_billed' => 0,
                'server_errors' => $serverData['errors'] ?? [],
            ],
            'server_raw' => self::summarizeServerData($serverData),
            'portal_raw' => self::summarizePortalData($portalData),
        ];

        // Compare each item type
        foreach (self::ITEM_TYPES as $key => $label) {
            $serverCount = $serverData[$key] ?? 0;
            
            // Portal data structure: ['count' => N, 'amount' => X]
            $portalCount = $portalData[$key]['count'] ?? 0;
            $portalAmount = $portalData[$key]['amount'] ?? 0.0;
            
            $variance = $portalCount - $serverCount;
            $status = self::getStatus($variance);
            
            $report['items'][$key] = [
                'label' => $label,
                'server' => $serverCount,
                'portal' => $portalCount,
                'portal_amount' => round($portalAmount, 2),
                'variance' => $variance,
                'variance_pct' => $serverCount > 0 ? round(($variance / $serverCount) * 100, 1) : null,
                'status' => $status,
            ];
            
            $report['summary']['total_items']++;
            $report['summary'][$status]++;
        }

        // Add storage comparison if available
        $report['storage'] = [
            'server_bytes' => $serverData['storage_bytes'] ?? 0,
            'server_human' => ServerUsageCollector::formatBytes($serverData['storage_bytes'] ?? 0),
        ];

        // Overall status
        $report['overall_status'] = self::getOverallStatus($report['summary']);
        
        return $report;
    }

    /**
     * Get status based on variance.
     * 
     * @param float $variance
     * @return string
     */
    private static function getStatus(float $variance): string
    {
        if ($variance == 0) return 'ok';
        if ($variance > 0) return 'over_billed'; // Portal shows more than server
        return 'under_billed'; // Portal shows less than server (rare)
    }

    /**
     * Get overall reconciliation status.
     * 
     * @param array $summary
     * @return string
     */
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

    /**
     * Summarize server data for report display.
     * 
     * @param array $data
     * @return array
     */
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

    /**
     * Summarize portal data for report display.
     * 
     * @param array $data
     * @return array
     */
    private static function summarizePortalData(array $data): array
    {
        return [
            'snapshot_time' => $data['snapshot_time'] ?? null,
            'raw_rows' => $data['raw_rows'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0.0,
            'account_fees' => $data['account_fees']['amount'] ?? 0.0,
            'server_licenses' => $data['server_licenses']['amount'] ?? 0.0,
        ];
    }

    /**
     * Save reconciliation report to database.
     * 
     * @param array $report
     * @return int The inserted report ID
     */
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
            'portal_data' => json_encode($report['portal_raw']),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    /**
     * Get saved reconciliation reports.
     * 
     * @param int $limit
     * @return array
     */
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

    /**
     * Get a specific reconciliation report.
     * 
     * @param int $id
     * @return object|null
     */
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
     * Ensure reconciliation reports table exists.
     */
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

    /**
     * Generate a text summary of variances for display.
     * 
     * @param array $report
     * @return string
     */
    public static function getVarianceSummary(array $report): string
    {
        $lines = [];
        
        foreach ($report['items'] as $key => $item) {
            if ($item['status'] !== 'ok') {
                $sign = $item['variance'] > 0 ? '+' : '';
                $lines[] = "{$item['label']}: Server={$item['server']}, Portal={$item['portal']} ({$sign}{$item['variance']})";
            }
        }
        
        if (empty($lines)) {
            return "All items match - no variances detected.";
        }
        
        return implode("\n", $lines);
    }
}
