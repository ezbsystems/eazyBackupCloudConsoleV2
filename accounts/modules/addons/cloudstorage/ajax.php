<?php
/**
 * AJAX handler for Cloud Storage Bucket Monitor
 * Handles all AJAX requests from the admin interface
 */

// Debug logging
error_log("AJAX Debug: Starting ajax.php");

try {
    require_once __DIR__ . '/../../../init.php';
    error_log("AJAX Debug: init.php loaded successfully");
} catch (Exception $e) {
    error_log("AJAX Debug: Failed to load init.php - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'message' => 'Failed to initialize WHMCS']);
    exit;
}

// Clean any output buffer to prevent corrupted JSON
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON content type
header('Content-Type: application/json');

use WHMCS\Module\Addon\CloudStorage\Admin\BucketSizeMonitor;
use WHMCS\Module\Addon\CloudStorage\Admin\CephPoolMonitor;
use WHMCS\Database\Capsule;

try {
    error_log("AJAX Debug: Starting request processing");
    
    $action = $_POST['action'] ?? '';
    error_log("AJAX Debug: Action = " . $action);

    // Get module configuration
    $config = [];
    $configQuery = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->get(['setting', 'value']);

    foreach ($configQuery as $item) {
        $config[$item->setting] = $item->value;
    }

    $s3Endpoint = $config['s3_endpoint'] ?? '';
    $cephAdminAccessKey = $config['ceph_access_key'] ?? '';
    $cephAdminSecretKey = $config['ceph_secret_key'] ?? '';

    error_log("AJAX Debug: Config loaded, endpoint = " . substr($s3Endpoint, 0, 20) . "...");

    switch ($action) {
        case 'get_bucket_data':
            error_log("AJAX Debug: Processing get_bucket_data request");
            
            $search = $_POST['search'] ?? '';
            $orderBy = $_POST['order_by'] ?? 'bucket_name';
            $orderDir = $_POST['order_dir'] ?? 'ASC';
            $filterType = $_POST['filter_type'] ?? 'all'; // New filter parameter

            error_log("AJAX Debug: Parameters - search={$search}, orderBy={$orderBy}, orderDir={$orderDir}, filterType={$filterType}");

            $result = BucketSizeMonitor::getCurrentBucketSizes($search, $orderBy, $orderDir, $filterType);
            
            error_log("AJAX Debug: BucketSizeMonitor returned status: " . ($result['status'] ?? 'unknown'));
            error_log("AJAX Debug: Bucket count: " . count($result['buckets'] ?? []));
            
            echo json_encode($result);
            break;

        case 'get_chart_data':
            error_log("AJAX Debug: Processing get_chart_data request");
            
            $days = intval($_POST['days'] ?? 30);
            $bucketName = $_POST['bucket_name'] ?? '';
            $bucketOwner = $_POST['bucket_owner'] ?? '';

            $result = BucketSizeMonitor::getHistoricalBucketData($days, $bucketName, $bucketOwner);

            // Safety check: Limit chart data to prevent browser memory issues
            if (isset($result['total_size_chart']) && count($result['total_size_chart']) > 365) {
                $result['total_size_chart'] = array_slice($result['total_size_chart'], -365);
                $result['data_points_limited'] = true;
                error_log("AJAX Chart data limited to prevent memory issues: " . count($result['total_size_chart']) . " points");
            }

            echo json_encode($result);
            break;

        case 'get_pool_forecast':
            error_log("AJAX Debug: Processing get_pool_forecast request");

            $poolName = trim((string)($_POST['pool_name'] ?? ''));
            if ($poolName === '') {
                // Fallback to addon setting
                $poolName = trim((string)($config['ceph_pool_monitor_pool_name'] ?? 'default.rgw.buckets.data'));
            }

            $days = intval($_POST['days'] ?? 90);
            $forecastDays = intval($_POST['forecast_days'] ?? 180);
            $targetPercent = floatval($_POST['target_percent'] ?? 80);

            $result = CephPoolMonitor::getForecast($poolName, $days, $forecastDays, $targetPercent);
            echo json_encode($result);
            break;

        case 'collect_now':
            error_log("AJAX Debug: Processing collect_now request");
            
            if (empty($s3Endpoint) || empty($cephAdminAccessKey) || empty($cephAdminSecretKey)) {
                echo json_encode([
                    'status' => 'fail',
                    'message' => 'Module configuration is incomplete'
                ]);
                break;
            }

            $result = BucketSizeMonitor::collectAllBucketSizes($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey);
            echo json_encode($result);
            break;

        case 'get_stats':
            error_log("AJAX Debug: Processing get_stats request");
            
            $result = BucketSizeMonitor::getCollectionStats();
            echo json_encode($result);
            break;

        case 'debug_bucket_growth':
            error_log("AJAX Debug: Processing debug_bucket_growth request");
            
            $bucketName = $_POST['bucket_name'] ?? '';
            $bucketOwner = $_POST['bucket_owner'] ?? '';

            if (empty($bucketName) || empty($bucketOwner)) {
                echo json_encode([
                    'status' => 'fail',
                    'message' => 'Bucket name and owner are required'
                ]);
                break;
            }

            $result = BucketSizeMonitor::debugBucketGrowth($bucketName, $bucketOwner);
            echo json_encode($result);
            break;

        default:
            error_log("AJAX Debug: Unknown action: " . $action);
            echo json_encode([
                'status' => 'fail',
                'message' => 'Unknown action'
            ]);
            break;
    }

    error_log("AJAX Debug: Request completed successfully");
    
} catch (Exception $e) {
    error_log("AJAX Debug: Exception occurred - " . $e->getMessage());
    error_log("AJAX Debug: Stack trace - " . $e->getTraceAsString());
    
    echo json_encode([
        'status' => 'fail',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("AJAX Debug: Fatal error occurred - " . $e->getMessage());
    error_log("AJAX Debug: Stack trace - " . $e->getTraceAsString());
    
    echo json_encode([
        'status' => 'fail',
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
} 