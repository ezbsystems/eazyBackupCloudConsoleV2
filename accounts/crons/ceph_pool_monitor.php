<?php
/**
 * Ceph Pool Usage History Collection Cron
 *
 * Collects `ceph df detail` pool stats for a configured pool and stores them for forecasting.
 *
 * Suggested schedule: hourly or every 30 minutes
 *   0,30 * * * * /usr/bin/php -q /var/www/eazybackup.ca/accounts/crons/ceph_pool_monitor.php >/dev/null 2>&1
 */

require_once __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\CephPoolMonitor;

try {
    // Load addon settings
    $rows = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->get(['setting', 'value']);

    $config = [];
    foreach ($rows as $row) {
        $config[(string)$row->setting] = (string)$row->value;
    }

    $enabled = !empty($config['ceph_pool_monitor_enabled']) && ($config['ceph_pool_monitor_enabled'] === 'on' || $config['ceph_pool_monitor_enabled'] === '1');
    if (!$enabled) {
        $msg = '[Ceph Pool Monitor Cron] Disabled (ceph_pool_monitor_enabled is off)';
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
        exit(0);
    }

    $poolName = $config['ceph_pool_monitor_pool_name'] ?? 'default.rgw.buckets.data';
    $source = strtolower(trim((string)($config['ceph_pool_monitor_source'] ?? 'cli')));

    $result = null;
    if ($source === 'prometheus') {
        $baseUrl = (string)($config['prometheus_base_url'] ?? '');
        $bearer = (string)($config['prometheus_bearer_token'] ?? '');
        $basicUser = (string)($config['prometheus_basic_auth_user'] ?? '');
        $basicPass = (string)($config['prometheus_basic_auth_pass'] ?? '');
        $verifyTls = !empty($config['prometheus_verify_tls']) && ($config['prometheus_verify_tls'] === 'on' || $config['prometheus_verify_tls'] === '1');
        $usedTpl = (string)($config['prometheus_pool_used_query'] ?? 'ceph_pool_stored{pool="{{pool}}"}');
        $maxAvailTpl = (string)($config['prometheus_pool_max_avail_query'] ?? 'ceph_pool_max_avail{pool="{{pool}}"}');
        $backfillDays = (int)($config['prometheus_backfill_days'] ?? 90);
        $backfillStep = (int)($config['prometheus_backfill_step_seconds'] ?? 3600);

        // Backfill only if this pool has no history yet (best-effort).
        $bf = CephPoolMonitor::backfillPoolUsageFromPrometheus($baseUrl, $poolName, $backfillDays, $backfillStep, [
            'bearer_token' => $bearer,
            'basic_user' => $basicUser,
            'basic_pass' => $basicPass,
            'verify_tls' => $verifyTls,
            'used_query_tpl' => $usedTpl,
            'max_avail_query_tpl' => $maxAvailTpl,
        ]);
        if (($bf['status'] ?? '') !== 'success') {
            $result = $bf; // fail early if backfill can't even query
        } else {
            $result = CephPoolMonitor::collectPoolUsageFromPrometheus($baseUrl, $poolName, [
                'bearer_token' => $bearer,
                'basic_user' => $basicUser,
                'basic_pass' => $basicPass,
                'verify_tls' => $verifyTls,
                'used_query_tpl' => $usedTpl,
                'max_avail_query_tpl' => $maxAvailTpl,
            ]);
        }
    } else {
        $cephCliPath = $config['ceph_cli_path'] ?? '/usr/bin/ceph';
        $cephCliArgs = $config['ceph_cli_args'] ?? '';
        $extraArgs = CephPoolMonitor::parseCephCliArgs($cephCliArgs);
        $result = CephPoolMonitor::collectPoolUsageFromCephCli($cephCliPath, $extraArgs, $poolName);
    }

    if (($result['status'] ?? '') === 'success') {
        $msg = '[Ceph Pool Monitor Cron] Collected ' . $poolName . ' used=' . ($result['used_bytes'] ?? 0) .
            ' capacity=' . ($result['capacity_bytes'] ?? 0) .
            ' percent=' . round((float)($result['percent_used'] ?? 0), 2) . '%';
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
        exit(0);
    }

    $msg = '[Ceph Pool Monitor Cron] FAILED: ' . ($result['message'] ?? 'Unknown error');
    error_log($msg);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
    }
    exit(1);

} catch (\Throwable $e) {
    $msg = '[Ceph Pool Monitor Cron] Exception: ' . $e->getMessage();
    error_log($msg);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
    }
    exit(1);
}


