<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use WHMCS\Database\Capsule;

class CephPoolMonitor
{
    private static $module = 'cloudstorage';

    /**
     * Collect pool usage using the Ceph CLI (recommended for on-box cron execution).
     *
     * Expects the local host to have working Ceph CLI auth/config (e.g., /etc/ceph/ceph.conf + keyring).
     *
     * @param string $cephCliPath
     * @param array $extraArgs
     * @param string $poolName
     * @return array
     */
    public static function collectPoolUsageFromCephCli($cephCliPath, array $extraArgs, $poolName)
    {
        $collectedAt = date('Y-m-d H:i:s');

        if (!Capsule::schema()->hasTable('ceph_pool_usage_history')) {
            return [
                'status' => 'fail',
                'message' => 'Missing DB table ceph_pool_usage_history (run addon module upgrade/activation)',
                'collected_at' => $collectedAt,
            ];
        }

        $cephCliPath = trim((string)$cephCliPath);
        $poolName = trim((string)$poolName);

        if ($cephCliPath === '') {
            return ['status' => 'fail', 'message' => 'Missing ceph CLI path', 'collected_at' => $collectedAt];
        }
        if ($poolName === '') {
            return ['status' => 'fail', 'message' => 'Missing pool name', 'collected_at' => $collectedAt];
        }

        $cmd = array_values(array_filter(array_merge(
            [$cephCliPath],
            $extraArgs,
            ['df', 'detail', '-f', 'json']
        ), function ($v) {
            return $v !== null && $v !== '';
        }));

        $stdout = '';
        $stderr = '';
        $exitCode = 0;

        try {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $proc = proc_open($cmd, $descriptors, $pipes, null, null, [
                'bypass_shell' => true,
            ]);

            if (!is_resource($proc)) {
                return ['status' => 'fail', 'message' => 'Failed to start ceph CLI process', 'collected_at' => $collectedAt];
            }

            // No stdin input expected
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($proc);
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['cmd' => $cmd, 'pool' => $poolName], $e->getMessage());
            return [
                'status' => 'fail',
                'message' => 'Exception running ceph CLI: ' . $e->getMessage(),
                'collected_at' => $collectedAt,
            ];
        }

        if ((int)$exitCode !== 0) {
            $msg = 'ceph CLI failed (exit ' . (int)$exitCode . ')';
            if (trim($stderr) !== '') {
                $msg .= ': ' . trim($stderr);
            }
            logModuleCall(self::$module, __FUNCTION__, ['cmd' => $cmd, 'pool' => $poolName], $msg);
            return [
                'status' => 'fail',
                'message' => $msg,
                'collected_at' => $collectedAt,
            ];
        }

        $json = json_decode($stdout, true);
        if (!is_array($json)) {
            $msg = 'Invalid JSON from ceph CLI';
            logModuleCall(self::$module, __FUNCTION__, ['cmd' => $cmd, 'pool' => $poolName], $msg . ' stderr=' . trim($stderr));
            return [
                'status' => 'fail',
                'message' => $msg,
                'collected_at' => $collectedAt,
            ];
        }

        $parsed = self::extractPoolStatsFromCephDfDetail($json, $poolName);
        if ($parsed['status'] !== 'success') {
            logModuleCall(self::$module, __FUNCTION__, ['pool' => $poolName], $parsed['message'] ?? 'Failed to parse pool stats');
            $parsed['collected_at'] = $collectedAt;
            return $parsed;
        }

        $insert = [
            'pool_name' => $poolName,
            'used_bytes' => (int)$parsed['used_bytes'],
            'max_avail_bytes' => (int)$parsed['max_avail_bytes'],
            'capacity_bytes' => (int)$parsed['capacity_bytes'],
            'percent_used' => (float)$parsed['percent_used'],
            'collected_at' => $collectedAt,
            'created_at' => $collectedAt,
        ];

        Capsule::table('ceph_pool_usage_history')->insert($insert);

        return [
            'status' => 'success',
            'pool_name' => $poolName,
            'used_bytes' => (int)$insert['used_bytes'],
            'max_avail_bytes' => (int)$insert['max_avail_bytes'],
            'capacity_bytes' => (int)$insert['capacity_bytes'],
            'percent_used' => (float)$insert['percent_used'],
            'collected_at' => $collectedAt,
        ];
    }

    /**
     * Extract pool stats from `ceph df detail -f json`.
     *
     * This supports common variations in ceph output field names across versions.
     *
     * @param array $json
     * @param string $poolName
     * @return array
     */
    private static function extractPoolStatsFromCephDfDetail(array $json, $poolName)
    {
        $pools = $json['pools'] ?? null;
        if (!is_array($pools)) {
            return ['status' => 'fail', 'message' => 'ceph df detail output missing pools[]'];
        }

        $target = null;
        foreach ($pools as $pool) {
            if (!is_array($pool)) {
                continue;
            }
            if (($pool['name'] ?? '') === $poolName) {
                $target = $pool;
                break;
            }
        }

        if (!$target) {
            return ['status' => 'fail', 'message' => 'Pool not found in ceph df output: ' . $poolName];
        }

        $stats = $target['stats'] ?? [];
        if (!is_array($stats)) {
            $stats = [];
        }

        $usedBytes = null;
        foreach (['bytes_used', 'stored', 'stored_bytes'] as $k) {
            if (isset($stats[$k]) && is_numeric($stats[$k])) {
                $usedBytes = (int)$stats[$k];
                break;
            }
        }
        if ($usedBytes === null && isset($stats['kb_used']) && is_numeric($stats['kb_used'])) {
            $usedBytes = (int)$stats['kb_used'] * 1024;
        }

        $maxAvailBytes = null;
        foreach (['max_avail', 'max_avail_bytes'] as $k) {
            if (isset($stats[$k]) && is_numeric($stats[$k])) {
                $maxAvailBytes = (int)$stats[$k];
                break;
            }
        }
        if ($maxAvailBytes === null && isset($stats['kb_avail']) && is_numeric($stats['kb_avail'])) {
            $maxAvailBytes = (int)$stats['kb_avail'] * 1024;
        }

        if ($usedBytes === null || $maxAvailBytes === null) {
            return [
                'status' => 'fail',
                'message' => 'Unable to determine used/max_avail from ceph df output for pool',
            ];
        }

        $capacityBytes = $usedBytes + $maxAvailBytes;
        $percentUsed = 0.0;
        if ($capacityBytes > 0) {
            $percentUsed = ($usedBytes / $capacityBytes) * 100.0;
        } elseif (isset($stats['percent_used']) && is_numeric($stats['percent_used'])) {
            $percentUsed = (float)$stats['percent_used'];
        }

        return [
            'status' => 'success',
            'used_bytes' => $usedBytes,
            'max_avail_bytes' => $maxAvailBytes,
            'capacity_bytes' => $capacityBytes,
            'percent_used' => $percentUsed,
        ];
    }

    /**
     * Get daily (per-day max) pool usage history.
     *
     * @param string $poolName
     * @param int $days
     * @return array
     */
    public static function getDailyHistory($poolName, $days)
    {
        $poolName = trim((string)$poolName);
        $days = max(1, (int)$days);
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        if (!Capsule::schema()->hasTable('ceph_pool_usage_history')) {
            return [
                'status' => 'fail',
                'message' => 'Missing DB table ceph_pool_usage_history (run addon module upgrade/activation)',
                'pool_name' => $poolName,
                'used_bytes_series' => [],
                'data_points' => 0,
                'latest' => null,
            ];
        }

        $rows = Capsule::table('ceph_pool_usage_history')
            ->select([
                Capsule::raw('DATE(collected_at) as date'),
                Capsule::raw('MAX(used_bytes) as used_bytes'),
                Capsule::raw('MAX(capacity_bytes) as capacity_bytes'),
                Capsule::raw('MAX(percent_used) as percent_used'),
            ])
            ->where('pool_name', $poolName)
            ->where('collected_at', '>=', $startDate)
            ->groupBy(Capsule::raw('DATE(collected_at)'))
            ->orderBy('date', 'ASC')
            ->get();

        $series = [];
        foreach ($rows as $row) {
            $ts = strtotime($row->date . ' 12:00:00') * 1000;
            $series[] = ['x' => $ts, 'y' => (int)$row->used_bytes];
        }

        $latest = Capsule::table('ceph_pool_usage_history')
            ->where('pool_name', $poolName)
            ->orderBy('collected_at', 'DESC')
            ->first();

        return [
            'status' => 'success',
            'pool_name' => $poolName,
            'used_bytes_series' => $series,
            'data_points' => count($series),
            'latest' => $latest ? [
                'used_bytes' => (int)$latest->used_bytes,
                'capacity_bytes' => (int)$latest->capacity_bytes,
                'percent_used' => (float)$latest->percent_used,
                'collected_at' => (string)$latest->collected_at,
            ] : null,
        ];
    }

    /**
     * Forecast when the pool hits a target percent (default 80%) using the last N days.
     *
     * The model is a robust linear trend on daily-max used bytes:
     *   1. Theil–Sen median slope across all pairwise points (resistant to outliers).
     *   2. Intercept set to median(y - slope*x) so the line is the Theil–Sen estimator.
     *
     * Improvements applied to keep the chart and ETA consistent and actionable:
     *   - Forecast is anchored at the latest observed sample, so the forecast line
     *     starts from the actual current value (no visual gap when the fit doesn't
     *     pass exactly through the last point).
     *   - ETA is derived from the same anchor: days_to_target = (threshold - last) / slope.
     *   - A 95% prediction band is returned as upper/lower series, derived from
     *     the residual MAD (robust noise estimate, scaled to a Gaussian-equivalent
     *     standard deviation) and growing modestly over the forecast horizon.
     *   - R² on the historical fit is returned so the UI can show forecast confidence.
     *
     * @param string $poolName
     * @param int    $historyDays
     * @param int    $forecastDays
     * @param float  $targetPercent
     * @return array
     */
    public static function getForecast($poolName, $historyDays = 90, $forecastDays = 180, $targetPercent = 80.0)
    {
        $poolName = trim((string)$poolName);
        $historyDays = max(7, (int)$historyDays);
        $forecastDays = max(30, (int)$forecastDays);
        $targetPercent = (float)$targetPercent;
        if ($targetPercent <= 0 || $targetPercent >= 100) {
            $targetPercent = 80.0;
        }

        $history = self::getDailyHistory($poolName, $historyDays);
        if (($history['status'] ?? '') !== 'success') {
            return $history;
        }

        $actual = $history['used_bytes_series'] ?? [];
        $latest = $history['latest'] ?? null;
        $n = count($actual);

        if (!$latest || $n < 7) {
            return [
                'status' => 'success',
                'pool_name' => $poolName,
                'used_bytes_series' => $actual,
                'forecast_series' => [],
                'forecast_lower_series' => [],
                'forecast_upper_series' => [],
                'threshold_bytes' => null,
                'target_percent' => $targetPercent,
                'eta_to_target' => null,
                'message' => 'Not enough historical data to forecast (need at least 7 daily points).',
                'latest' => $latest,
            ];
        }

        $capacityBytes = (int)($latest['capacity_bytes'] ?? 0);
        $thresholdBytes = $capacityBytes > 0 ? (int)round(($targetPercent / 100.0) * $capacityBytes) : null;

        $y = array_map(function ($pt) { return (int)($pt['y'] ?? 0); }, $actual);
        $x = range(0, $n - 1);

        // Theil–Sen median slope
        $slopes = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $dx = $x[$j] - $x[$i];
                if ($dx <= 0) { continue; }
                $slopes[] = ($y[$j] - $y[$i]) / $dx;
            }
        }
        $slope = self::medianFloat($slopes);

        // Theil–Sen intercept = median(y_i - slope*x_i)
        $resOffsets = [];
        for ($i = 0; $i < $n; $i++) {
            $resOffsets[] = $y[$i] - ($slope * $x[$i]);
        }
        $intercept = self::medianFloat($resOffsets);
        if ($intercept === null) {
            $intercept = (float)$y[$n - 1];
        }

        // Residuals + goodness-of-fit
        $residuals = [];
        $absResiduals = [];
        $ssRes = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $pred = $intercept + ($slope * $x[$i]);
            $r = (float)$y[$i] - $pred;
            $residuals[] = $r;
            $absResiduals[] = abs($r);
            $ssRes += $r * $r;
        }
        $yMean = array_sum($y) / $n;
        $ssTot = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $diff = (float)$y[$i] - $yMean;
            $ssTot += $diff * $diff;
        }
        $rSquared = $ssTot > 0 ? max(0.0, min(1.0, 1.0 - ($ssRes / $ssTot))) : 0.0;

        // Robust noise estimate: MAD scaled to a Gaussian-equivalent std-dev.
        $mad = self::medianFloat($absResiduals) ?: 0.0;
        $sigmaRobust = 1.4826 * $mad;

        // Anchor the forecast at the latest observed sample so the chart is
        // continuous and the ETA is computed from the actual current state,
        // not from where the fit happens to land at day n-1.
        $lastTs = (int)($actual[$n - 1]['x'] ?? 0);
        $yLast  = (int)($actual[$n - 1]['y'] ?? 0);
        $dayMs  = 86400 * 1000;

        // ~95% prediction band (1.96 sigma). Width grows mildly with horizon
        // (sqrt(k)) to convey rising uncertainty further into the future.
        $bandFactor = 1.96;

        $forecast = [];
        $forecastLower = [];
        $forecastUpper = [];
        for ($k = 0; $k <= $forecastDays; $k++) {
            $ts = $lastTs + ($k * $dayMs);
            $predY = $yLast + ($slope * $k);
            $predClamped = max(0, (int)round($predY));

            $bandWidth = $bandFactor * $sigmaRobust * sqrt(max(1, $k));
            $forecast[]      = ['x' => $ts, 'y' => $predClamped];
            $forecastLower[] = ['x' => $ts, 'y' => max(0, (int)round($predY - $bandWidth))];
            $forecastUpper[] = ['x' => $ts, 'y' => max(0, (int)round($predY + $bandWidth))];
        }

        // ETA anchored at last actual sample
        $eta = null;
        if ($thresholdBytes !== null) {
            if ($yLast >= $thresholdBytes) {
                $eta = [
                    'status' => 'already_reached',
                    'date'   => date('Y-m-d', (int)($lastTs / 1000)),
                    'days'   => 0,
                ];
            } elseif ($slope > 0) {
                $daysAhead = (int)ceil(($thresholdBytes - $yLast) / $slope);
                if ($daysAhead < 1) { $daysAhead = 1; }
                $etaTs = $lastTs + ($daysAhead * $dayMs);
                $eta = [
                    'status' => 'forecast',
                    'date'   => date('Y-m-d', (int)($etaTs / 1000)),
                    'days'   => $daysAhead,
                ];
            }
        }

        // Heuristic fit-quality label. R² is a useful first-order indicator on
        // a single-bucket time-series; we expose both raw R² and the label.
        $quality = 'poor';
        if ($rSquared >= 0.85) {
            $quality = 'good';
        } elseif ($rSquared >= 0.6) {
            $quality = 'fair';
        }

        return [
            'status' => 'success',
            'pool_name' => $poolName,
            'used_bytes_series' => $actual,
            'forecast_series' => $forecast,
            'forecast_lower_series' => $forecastLower,
            'forecast_upper_series' => $forecastUpper,
            'threshold_bytes' => $thresholdBytes,
            'target_percent' => $targetPercent,
            'eta_to_target' => $eta,
            'model' => [
                'method' => 'theil_sen_anchored',
                'slope_bytes_per_day' => $slope,
                'intercept_bytes' => $intercept,
                'history_points' => $n,
                'capacity_bytes' => $capacityBytes,
                'r_squared' => $rSquared,
                'mad_bytes' => $mad,
                'sigma_robust_bytes' => $sigmaRobust,
                'fit_quality' => $quality,
            ],
            'latest' => $latest,
        ];
    }

    /**
     * Numerically stable median (average of the two middle values for even-length arrays).
     * Returns null when the array is empty.
     *
     * @param array<int|float> $values
     * @return float|null
     */
    private static function medianFloat(array $values)
    {
        $cnt = count($values);
        if ($cnt === 0) {
            return null;
        }
        sort($values);
        $mid = (int)floor($cnt / 2);
        if ($cnt % 2 === 1) {
            return (float)$values[$mid];
        }
        return ((float)$values[$mid - 1] + (float)$values[$mid]) / 2.0;
    }

    /**
     * Parse a safe extra-args string for the ceph CLI.
     * Allows typical ceph flags like: --conf=/etc/ceph/ceph.conf --name=client.admin --keyring=/etc/ceph/ceph.client.admin.keyring
     *
     * @param string $args
     * @return array
     */
    public static function parseCephCliArgs($args)
    {
        $args = trim((string)$args);
        if ($args === '') {
            return [];
        }
        $parts = preg_split('/\s+/', $args);
        $safe = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') { continue; }
            // Very small allowlist for safety (no shell metacharacters).
            if (!preg_match('/^[A-Za-z0-9._\\-\\/=:@,]+$/', $p)) {
                continue;
            }
            $safe[] = $p;
        }
        return $safe;
    }

    /**
     * Collect pool usage using Prometheus instant queries (api/v1/query) and store one point.
     *
     * @param string $baseUrl
     * @param string $poolName
     * @param array $opts
     * @return array
     */
    public static function collectPoolUsageFromPrometheus($baseUrl, $poolName, array $opts = [])
    {
        $collectedAt = date('Y-m-d H:i:s');

        if (!Capsule::schema()->hasTable('ceph_pool_usage_history')) {
            return [
                'status' => 'fail',
                'message' => 'Missing DB table ceph_pool_usage_history (run addon module upgrade/activation)',
                'collected_at' => $collectedAt,
            ];
        }

        $baseUrl = trim((string)$baseUrl);
        $poolName = trim((string)$poolName);
        if ($baseUrl === '') {
            return ['status' => 'fail', 'message' => 'Missing Prometheus base URL', 'collected_at' => $collectedAt];
        }
        if ($poolName === '') {
            return ['status' => 'fail', 'message' => 'Missing pool name', 'collected_at' => $collectedAt];
        }

        $usedTpl = (string)($opts['used_query_tpl'] ?? 'ceph_pool_stored{pool="{{pool}}"}');
        $maxAvailTpl = (string)($opts['max_avail_query_tpl'] ?? 'ceph_pool_max_avail{pool="{{pool}}"}');

        $usedQuery = self::renderPromQueryTemplate($usedTpl, $poolName);
        $maxAvailQuery = self::renderPromQueryTemplate($maxAvailTpl, $poolName);

        $verify = array_key_exists('verify_tls', $opts) ? (bool)$opts['verify_tls'] : true;

        $authHeaders = [];
        $bearer = trim((string)($opts['bearer_token'] ?? ''));
        if ($bearer !== '') {
            $authHeaders['Authorization'] = 'Bearer ' . $bearer;
        }

        $basicUser = trim((string)($opts['basic_user'] ?? ''));
        $basicPass = (string)($opts['basic_pass'] ?? '');

        $client = new Client([
            'timeout' => 15,
            'connect_timeout' => 10,
            'verify' => $verify,
        ]);

        $ts = time();
        $used = self::prometheusInstantQuery($client, $baseUrl, $usedQuery, $ts, $authHeaders, $basicUser, $basicPass);
        if ($used['status'] !== 'success') {
            // Fallback for common Ceph exporter shape: pool metrics keyed by pool_id, mapping provided by ceph_pool_metadata{name="..."}.
            if (($used['message'] ?? '') === 'No data returned for query') {
                $alt = self::buildPoolMetricByNameViaMetadata('ceph_pool_stored', $poolName);
                $used = self::prometheusInstantQuery($client, $baseUrl, $alt, $ts, $authHeaders, $basicUser, $basicPass);
                if ($used['status'] === 'success') {
                    $usedQuery = $alt;
                }
            }
            if ($used['status'] !== 'success') {
                return ['status' => 'fail', 'message' => 'Prometheus used query failed: ' . ($used['message'] ?? 'Unknown') . ' | query=' . $usedQuery, 'collected_at' => $collectedAt];
            }
        }

        $maxAvail = self::prometheusInstantQuery($client, $baseUrl, $maxAvailQuery, $ts, $authHeaders, $basicUser, $basicPass);
        if ($maxAvail['status'] !== 'success') {
            if (($maxAvail['message'] ?? '') === 'No data returned for query') {
                $alt = self::buildPoolMetricByNameViaMetadata('ceph_pool_max_avail', $poolName);
                $maxAvail = self::prometheusInstantQuery($client, $baseUrl, $alt, $ts, $authHeaders, $basicUser, $basicPass);
                if ($maxAvail['status'] === 'success') {
                    $maxAvailQuery = $alt;
                }
            }
            if ($maxAvail['status'] !== 'success') {
                return ['status' => 'fail', 'message' => 'Prometheus max_avail query failed: ' . ($maxAvail['message'] ?? 'Unknown') . ' | query=' . $maxAvailQuery, 'collected_at' => $collectedAt];
            }
        }

        $usedBytes = (int)round((float)$used['value']);
        $maxAvailBytes = (int)round((float)$maxAvail['value']);
        $capacityBytes = $usedBytes + $maxAvailBytes;
        $percentUsed = $capacityBytes > 0 ? (($usedBytes / $capacityBytes) * 100.0) : 0.0;

        Capsule::table('ceph_pool_usage_history')->insert([
            'pool_name' => $poolName,
            'used_bytes' => $usedBytes,
            'max_avail_bytes' => $maxAvailBytes,
            'capacity_bytes' => $capacityBytes,
            'percent_used' => $percentUsed,
            'collected_at' => $collectedAt,
            'created_at' => $collectedAt,
        ]);

        return [
            'status' => 'success',
            'pool_name' => $poolName,
            'used_bytes' => $usedBytes,
            'max_avail_bytes' => $maxAvailBytes,
            'capacity_bytes' => $capacityBytes,
            'percent_used' => $percentUsed,
            'collected_at' => $collectedAt,
            'source' => 'prometheus',
            'queries' => [
                'used' => $usedQuery,
                'max_avail' => $maxAvailQuery,
            ],
        ];
    }

    /**
     * Backfill pool usage history from Prometheus query_range for the last N days.
     * Only intended to run when the table is empty for that pool.
     *
     * @param string $baseUrl
     * @param string $poolName
     * @param int $days
     * @param int $stepSeconds
     * @param array $opts
     * @return array
     */
    public static function backfillPoolUsageFromPrometheus($baseUrl, $poolName, $days = 90, $stepSeconds = 3600, array $opts = [])
    {
        $collectedAt = date('Y-m-d H:i:s');

        if (!Capsule::schema()->hasTable('ceph_pool_usage_history')) {
            return [
                'status' => 'fail',
                'message' => 'Missing DB table ceph_pool_usage_history (run addon module upgrade/activation)',
                'collected_at' => $collectedAt,
            ];
        }

        $baseUrl = trim((string)$baseUrl);
        $poolName = trim((string)$poolName);
        $days = max(1, (int)$days);
        $stepSeconds = max(60, (int)$stepSeconds);

        if ($baseUrl === '' || $poolName === '') {
            return ['status' => 'fail', 'message' => 'Missing Prometheus base URL or pool name', 'collected_at' => $collectedAt];
        }

        $existing = (int)Capsule::table('ceph_pool_usage_history')->where('pool_name', $poolName)->count();
        if ($existing > 0) {
            return [
                'status' => 'success',
                'message' => 'Backfill skipped (history already exists for pool)',
                'pool_name' => $poolName,
                'inserted_points' => 0,
                'collected_at' => $collectedAt,
                'skipped' => true,
            ];
        }

        $usedTpl = (string)($opts['used_query_tpl'] ?? 'ceph_pool_stored{pool="{{pool}}"}');
        $maxAvailTpl = (string)($opts['max_avail_query_tpl'] ?? 'ceph_pool_max_avail{pool="{{pool}}"}');

        $usedQuery = self::renderPromQueryTemplate($usedTpl, $poolName);
        $maxAvailQuery = self::renderPromQueryTemplate($maxAvailTpl, $poolName);

        $verify = array_key_exists('verify_tls', $opts) ? (bool)$opts['verify_tls'] : true;

        $authHeaders = [];
        $bearer = trim((string)($opts['bearer_token'] ?? ''));
        if ($bearer !== '') {
            $authHeaders['Authorization'] = 'Bearer ' . $bearer;
        }

        $basicUser = trim((string)($opts['basic_user'] ?? ''));
        $basicPass = (string)($opts['basic_pass'] ?? '');

        $client = new Client([
            'timeout' => 60,
            'connect_timeout' => 10,
            'verify' => $verify,
        ]);

        $end = time();
        $start = $end - ($days * 86400);

        $usedRange = self::prometheusRangeQuery($client, $baseUrl, $usedQuery, $start, $end, $stepSeconds, $authHeaders, $basicUser, $basicPass);
        if ($usedRange['status'] !== 'success') {
            if (($usedRange['message'] ?? '') === 'No data returned for query_range') {
                $alt = self::buildPoolMetricByNameViaMetadata('ceph_pool_stored', $poolName);
                $usedRange = self::prometheusRangeQuery($client, $baseUrl, $alt, $start, $end, $stepSeconds, $authHeaders, $basicUser, $basicPass);
                if ($usedRange['status'] === 'success') {
                    $usedQuery = $alt;
                }
            }
            if ($usedRange['status'] !== 'success') {
                return ['status' => 'fail', 'message' => 'Prometheus used range query failed: ' . ($usedRange['message'] ?? 'Unknown') . ' | query=' . $usedQuery, 'collected_at' => $collectedAt];
            }
        }

        $maxAvailRange = self::prometheusRangeQuery($client, $baseUrl, $maxAvailQuery, $start, $end, $stepSeconds, $authHeaders, $basicUser, $basicPass);
        if ($maxAvailRange['status'] !== 'success') {
            if (($maxAvailRange['message'] ?? '') === 'No data returned for query_range') {
                $alt = self::buildPoolMetricByNameViaMetadata('ceph_pool_max_avail', $poolName);
                $maxAvailRange = self::prometheusRangeQuery($client, $baseUrl, $alt, $start, $end, $stepSeconds, $authHeaders, $basicUser, $basicPass);
                if ($maxAvailRange['status'] === 'success') {
                    $maxAvailQuery = $alt;
                }
            }
            if ($maxAvailRange['status'] !== 'success') {
                return ['status' => 'fail', 'message' => 'Prometheus max_avail range query failed: ' . ($maxAvailRange['message'] ?? 'Unknown') . ' | query=' . $maxAvailQuery, 'collected_at' => $collectedAt];
            }
        }

        // Align timestamps that exist in both series
        $usedPoints = $usedRange['points'] ?? [];
        $maxAvailPoints = $maxAvailRange['points'] ?? [];

        $common = array_intersect(array_keys($usedPoints), array_keys($maxAvailPoints));
        sort($common);

        $batch = [];
        foreach ($common as $ts) {
            $usedBytes = (int)round((float)$usedPoints[$ts]);
            $maxAvailBytes = (int)round((float)$maxAvailPoints[$ts]);
            $capacityBytes = $usedBytes + $maxAvailBytes;
            $percentUsed = $capacityBytes > 0 ? (($usedBytes / $capacityBytes) * 100.0) : 0.0;

            $dt = date('Y-m-d H:i:s', (int)$ts);
            $batch[] = [
                'pool_name' => $poolName,
                'used_bytes' => $usedBytes,
                'max_avail_bytes' => $maxAvailBytes,
                'capacity_bytes' => $capacityBytes,
                'percent_used' => $percentUsed,
                'collected_at' => $dt,
                'created_at' => $dt,
            ];
        }

        $inserted = 0;
        if ($batch) {
            // Chunk inserts to be safe
            $chunkSize = 500;
            for ($i = 0; $i < count($batch); $i += $chunkSize) {
                $chunk = array_slice($batch, $i, $chunkSize);
                Capsule::table('ceph_pool_usage_history')->insert($chunk);
                $inserted += count($chunk);
            }
        }

        return [
            'status' => 'success',
            'pool_name' => $poolName,
            'inserted_points' => $inserted,
            'days' => $days,
            'step_seconds' => $stepSeconds,
            'collected_at' => $collectedAt,
            'source' => 'prometheus',
            'queries' => [
                'used' => $usedQuery,
                'max_avail' => $maxAvailQuery,
            ],
        ];
    }

    private static function normalizePrometheusBaseUrl($baseUrl)
    {
        $baseUrl = trim((string)$baseUrl);
        $baseUrl = rtrim($baseUrl, '/');
        return $baseUrl;
    }

    private static function renderPromQueryTemplate($tpl, $poolName)
    {
        $tpl = (string)$tpl;
        $poolName = (string)$poolName;
        return trim(str_replace(['{{pool}}', '${pool}'], [$poolName, $poolName], $tpl));
    }

    private static function escapePromLabelValue($value)
    {
        $value = (string)$value;
        $value = str_replace("\\", "\\\\", $value);
        $value = str_replace("\"", "\\\"", $value);
        return $value;
    }

    /**
     * Build a PromQL expression to select a pool metric by pool *name* using ceph_pool_metadata mapping.
     *
     * Many Ceph Prometheus exporters expose pool gauges keyed by pool_id, not name.
     * This join converts a name match into a pool_id match.
     *
     * Example:
     *   ceph_pool_stored * on(pool_id) group_left(name) ceph_pool_metadata{name="default.rgw.buckets.data"}
     */
    private static function buildPoolMetricByNameViaMetadata($metricName, $poolName)
    {
        $metricName = trim((string)$metricName);
        $poolName = self::escapePromLabelValue($poolName);
        return $metricName . ' * on(pool_id) group_left(name) ceph_pool_metadata{name="' . $poolName . '"}';
    }

    private static function prometheusInstantQuery(Client $client, $baseUrl, $query, $unixTime, array $headers, $basicUser, $basicPass)
    {
        $baseUrl = self::normalizePrometheusBaseUrl($baseUrl);
        $url = $baseUrl . '/api/v1/query';

        try {
            $options = [
                'headers' => $headers,
                'query' => [
                    'query' => (string)$query,
                    'time' => (string)$unixTime,
                ],
            ];
            if ($basicUser !== '') {
                $options['auth'] = [$basicUser, $basicPass];
            }

            $resp = $client->get($url, $options);
            $json = json_decode((string)$resp->getBody(), true);
            if (!is_array($json) || ($json['status'] ?? '') !== 'success') {
                return ['status' => 'fail', 'message' => 'Prometheus returned non-success'];
            }
            $result = $json['data']['result'] ?? [];
            if (!is_array($result) || count($result) === 0) {
                return ['status' => 'fail', 'message' => 'No data returned for query'];
            }

            // Sum across series if multiple are returned
            $sum = 0.0;
            foreach ($result as $series) {
                $val = $series['value'] ?? null;
                if (!is_array($val) || count($val) < 2) { continue; }
                $sum += (float)$val[1];
            }
            return ['status' => 'success', 'value' => $sum];
        } catch (RequestException $e) {
            $msg = $e->getMessage();
            if ($e->hasResponse()) {
                $code = $e->getResponse()->getStatusCode();
                if ((int)$code === 404) {
                    $msg .= ' (Prometheus API endpoint not found at ' . $baseUrl . '. This usually means the URL points to an exporter /metrics endpoint, not a Prometheus server. Use the Prometheus server base URL (often :9090) or a compatible API.)';
                }
            }
            return ['status' => 'fail', 'message' => $msg];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    private static function prometheusRangeQuery(Client $client, $baseUrl, $query, $start, $end, $stepSeconds, array $headers, $basicUser, $basicPass)
    {
        $baseUrl = self::normalizePrometheusBaseUrl($baseUrl);
        $url = $baseUrl . '/api/v1/query_range';

        try {
            $options = [
                'headers' => $headers,
                'query' => [
                    'query' => (string)$query,
                    'start' => (string)$start,
                    'end' => (string)$end,
                    'step' => (string)$stepSeconds,
                ],
            ];
            if ($basicUser !== '') {
                $options['auth'] = [$basicUser, $basicPass];
            }

            $resp = $client->get($url, $options);
            $json = json_decode((string)$resp->getBody(), true);
            if (!is_array($json) || ($json['status'] ?? '') !== 'success') {
                return ['status' => 'fail', 'message' => 'Prometheus returned non-success'];
            }

            $result = $json['data']['result'] ?? [];
            if (!is_array($result) || count($result) === 0) {
                return ['status' => 'fail', 'message' => 'No data returned for query_range'];
            }

            // Sum across series; align by timestamp seconds
            $points = [];
            foreach ($result as $series) {
                $values = $series['values'] ?? [];
                if (!is_array($values)) { continue; }
                foreach ($values as $pair) {
                    if (!is_array($pair) || count($pair) < 2) { continue; }
                    $ts = (int)round((float)$pair[0]);
                    $val = (float)$pair[1];
                    if (!isset($points[$ts])) { $points[$ts] = 0.0; }
                    $points[$ts] += $val;
                }
            }

            ksort($points);
            return ['status' => 'success', 'points' => $points];
        } catch (RequestException $e) {
            $msg = $e->getMessage();
            if ($e->hasResponse()) {
                $code = $e->getResponse()->getStatusCode();
                if ((int)$code === 404) {
                    $msg .= ' (Prometheus query_range API not found at ' . $baseUrl . '. This usually means the URL points to an exporter /metrics endpoint, not a Prometheus server. Use the Prometheus server base URL (often :9090) or a compatible API.)';
                }
            }
            return ['status' => 'fail', 'message' => $msg];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }
}


