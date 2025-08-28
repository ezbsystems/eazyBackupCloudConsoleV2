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
    // Basic CSRF mitigation when token is provided
    $csrfToken = $_POST['token'] ?? $_GET['token'] ?? null;

use WHMCS\Module\Addon\CloudStorage\Admin\BucketSizeMonitor;
use WHMCS\Database\Capsule;

try {
    error_log("AJAX Debug: Starting request processing");
    
    $action = $_REQUEST['ajax_action'] ?? $_REQUEST['action'] ?? '';
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

        case 'collect_now':
            error_log("AJAX Debug: Processing collect_now request");
            
            // Multi-cluster mode: no single-cluster config required
            $result = BucketSizeMonitor::collectAllBucketSizes();
            echo json_encode($result);
            break;

        case 'get_stats':
            error_log("AJAX Debug: Processing get_stats request");
            
            $result = BucketSizeMonitor::getCollectionStats();
            echo json_encode($result);
            break;

        case 'add_cluster':
            // Optional: enforce POST token when provided
            if ($csrfToken !== null && $csrfToken === '') { echo json_encode(['status'=>'fail','message'=>'Invalid CSRF token']); break; }
            require_once __DIR__ . '/lib/Admin/ClusterManager.php';
            $name  = trim($_POST['cluster_name'] ?? '');
            $alias = trim($_POST['cluster_alias'] ?? '');
            $endpt = trim($_POST['s3_endpoint'] ?? '');
            $ak    = trim($_POST['admin_access_key'] ?? '');
            $sk    = trim($_POST['admin_secret_key'] ?? '');
            $isDef = isset($_POST['is_default']) ? 1 : 0;

            // Basic validation
            if (strlen($name) < 3 || strlen($alias) < 3) {
                echo json_encode(['status'=>'fail','message'=>'Name and alias must be at least 3 characters']);
                break;
            }
            if (!filter_var($endpt, FILTER_VALIDATE_URL)) {
                echo json_encode(['status'=>'fail','message'=>'S3 Endpoint must be a valid URL (e.g., https://s3.example.com)']);
                break;
            }
            if (strlen($ak) < 3 || strlen($sk) < 3) {
                echo json_encode(['status'=>'fail','message'=>'Access and Secret keys must be provided']);
                break;
            }
            if($name==''||$alias==''||$endpt==''||$ak==''||$sk==''){
                echo json_encode(['status'=>'fail','message'=>'All fields are required']);
                break;
            }
            $exists = Capsule::table('s3_clusters')->where('cluster_alias',$alias)->exists();
            if($exists){ echo json_encode(['status'=>'fail','message'=>'Alias already exists']); break; }
            if($isDef){ Capsule::table('s3_clusters')->update(['is_default'=>0]); }
            Capsule::table('s3_clusters')->insert([
                'cluster_name'=>$name,
                'cluster_alias'=>$alias,
                's3_endpoint'=>$endpt,
                'admin_access_key'=>$ak,
                'admin_secret_key'=>$sk,
                'is_default'=>$isDef,
                'created_at'=>date('Y-m-d H:i:s')
            ]);
            echo json_encode(['status'=>'success']);
            break;

        case 'edit_cluster':
            if ($csrfToken !== null && $csrfToken === '') { echo json_encode(['status'=>'fail','message'=>'Invalid CSRF token']); break; }
            require_once __DIR__ . '/lib/Admin/ClusterManager.php';
            $id = intval($_POST['cluster_id'] ?? 0);
            if(!$id){ echo json_encode(['status'=>'fail','message'=>'Invalid id']); break; }
            $fields = [
                'cluster_name'=>trim($_POST['cluster_name'] ?? ''),
                'cluster_alias'=>trim($_POST['cluster_alias'] ?? ''),
                's3_endpoint'=>trim($_POST['s3_endpoint'] ?? ''),
                'admin_access_key'=>trim($_POST['admin_access_key'] ?? ''),
                'admin_secret_key'=>trim($_POST['admin_secret_key'] ?? ''),
                'is_default'=>isset($_POST['is_default'])?1:0,
            ];
            if(in_array('', $fields, true)){
                echo json_encode(['status'=>'fail','message'=>'All fields are required']); break;
            }
            if (!filter_var($fields['s3_endpoint'], FILTER_VALIDATE_URL)){
                echo json_encode(['status'=>'fail','message'=>'S3 Endpoint must be a valid URL']); break;
            }
            if (strlen($fields['cluster_name']) < 3 || strlen($fields['cluster_alias']) < 3){
                echo json_encode(['status'=>'fail','message'=>'Name and alias must be at least 3 characters']); break;
            }
            if($fields['is_default']){ Capsule::table('s3_clusters')->update(['is_default'=>0]); }
            Capsule::table('s3_clusters')->where('id',$id)->update($fields);
            echo json_encode(['status'=>'success']);
            break;

        case 'delete_cluster':
            if ($csrfToken !== null && $csrfToken === '') { echo json_encode(['status'=>'fail','message'=>'Invalid CSRF token']); break; }
            require_once __DIR__ . '/lib/Admin/ClusterManager.php';
            $id = intval($_POST['cluster_id'] ?? 0);
            if(!$id){ echo json_encode(['status'=>'fail','message'=>'Invalid id']); break; }
            $row = Capsule::table('s3_clusters')->where('id',$id)->first();
            if(!$row){ echo json_encode(['status'=>'fail','message'=>'Not found']); break; }
            if($row->is_default){ echo json_encode(['status'=>'fail','message'=>'Cannot delete default cluster']); break; }
            Capsule::table('s3_clusters')->where('id',$id)->delete();
            echo json_encode(['status'=>'success']);
            break;

        case 'test_cluster':
            // Attempt to call a simple admin API endpoint with provided cluster alias
            $alias = trim($_POST['cluster_alias'] ?? '');
            if ($alias==='') { echo json_encode(['status'=>'fail','message'=>'Missing cluster alias']); break; }
            $cluster = Capsule::table('s3_clusters')->where('cluster_alias',$alias)->first();
            if (!$cluster) { echo json_encode(['status'=>'fail','message'=>'Cluster not found']); break; }
            require_once __DIR__ . '/lib/Admin/AdminOps.php';
            try {
                // Try a harmless call: list usage summary without uid
                $resp = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::getUsage($cluster->s3_endpoint, $cluster->admin_access_key, $cluster->admin_secret_key, ['show_summary'=>true]);
                if (($resp['status'] ?? 'fail') === 'success') {
                    echo json_encode(['status'=>'success','message'=>'Connection successful']);
                } else {
                    echo json_encode(['status'=>'fail','message'=>$resp['message'] ?? 'Unknown error']);
                }
            } catch (\Throwable $e) {
                echo json_encode(['status'=>'fail','message'=>$e->getMessage()]);
            }
            break;

        /* ------------------------- Migration Manager ---------------------- */
        case 'migrate_client':
            if ($csrfToken !== null && $csrfToken === '') { echo json_encode(['status'=>'fail','message'=>'Invalid CSRF token']); break; }
            $clientId = intval($_POST['client_id'] ?? 0);
            if ($clientId <= 0) {
                echo json_encode(['status' => 'fail', 'message' => 'Invalid client id']);
                break;
            }
            require_once __DIR__ . '/lib/MigrationController.php';
            $ok = \WHMCS\Module\Addon\CloudStorage\MigrationController::setClientAsMigrated($clientId);
            if ($ok) {
                echo json_encode(['status' => 'success', 'message' => 'Client marked as migrated']);
            } else {
                echo json_encode(['status' => 'fail', 'message' => 'Unable to update migration status']);
            }
            break;

        case 'set_client_cluster':
            if ($csrfToken !== null && $csrfToken === '') { echo json_encode(['status'=>'fail','message'=>'Invalid CSRF token']); break; }
            $clientId = intval($_POST['client_id'] ?? 0);
            $alias = trim($_POST['cluster_alias'] ?? '');
            if ($clientId <= 0 || $alias === '') {
                echo json_encode(['status' => 'fail', 'message' => 'Invalid parameters']);
                break;
            }
            $exists = Capsule::table('s3_clusters')->where('cluster_alias', $alias)->exists();
            if (!$exists) {
                echo json_encode(['status' => 'fail', 'message' => 'Unknown cluster alias']);
                break;
            }
            require_once __DIR__ . '/lib/Admin/ClusterManager.php';
            $ok = \WHMCS\Module\Addon\CloudStorage\Admin\ClusterManager::flipClientTo($clientId, $alias);
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'fail', 'message' => 'Unable to set backend']);
            break;

        case 'reset_client_cluster':
            if ($csrfToken !== null && $csrfToken === '') { echo json_encode(['status'=>'fail','message'=>'Invalid CSRF token']); break; }
            $clientId = intval($_POST['client_id'] ?? 0);
            if ($clientId <= 0) {
                echo json_encode(['status' => 'fail', 'message' => 'Invalid client id']);
                break;
            }
            require_once __DIR__ . '/lib/MigrationController.php';
            $ok = \WHMCS\Module\Addon\CloudStorage\MigrationController::resetClientBackend($clientId);
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'fail', 'message' => 'Unable to reset backend']);
            break;

        case 'freeze_client':
            if ($csrfToken !== null && $csrfToken === '') { echo json_encode(['status'=>'fail','message'=>'Invalid CSRF token']); break; }
            $clientId = intval($_POST['client_id'] ?? 0);
            if ($clientId <= 0) {
                echo json_encode(['status' => 'fail', 'message' => 'Invalid client id']);
                break;
            }
            require_once __DIR__ . '/lib/Admin/ClusterManager.php';
            $ok = \WHMCS\Module\Addon\CloudStorage\Admin\ClusterManager::setClientFrozen($clientId, true);
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'fail', 'message' => 'Unable to freeze client']);
            break;

        case 'unfreeze_client':
            if ($csrfToken !== null && $csrfToken === '') { echo json_encode(['status'=>'fail','message'=>'Invalid CSRF token']); break; }
            $clientId = intval($_POST['client_id'] ?? 0);
            if ($clientId <= 0) {
                echo json_encode(['status' => 'fail', 'message' => 'Invalid client id']);
                break;
            }
            require_once __DIR__ . '/lib/Admin/ClusterManager.php';
            $ok = \WHMCS\Module\Addon\CloudStorage\Admin\ClusterManager::setClientFrozen($clientId, false);
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'fail', 'message' => 'Unable to unfreeze client']);
            break;

        /* ------------------------- Existing debug bucket growth case ---------------------- */
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
