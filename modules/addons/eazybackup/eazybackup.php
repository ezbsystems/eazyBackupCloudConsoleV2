<?php

/**
 * eazyBackup Addon Module
 *
 * @copyright (c) 2019 eazyBackup Systems Ltd.
 */

use Comet\JobStatus;
use Comet\JobType;
use Carbon\Carbon;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Eazybackup\EazybackupObcMs365;
use WHMCS\Module\Addon\Eazybackup\Helper;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use eazyBackup\CometCompat\UserWebGetUserProfileAndHashRequest;
use eazyBackup\CometCompat\UserWebAccountRegenerateTotpRequest;
use eazyBackup\CometCompat\UserWebAccountValidateTotpRequest;


include_once 'config.php';

/** ------------------------------------------------------------------
 *  Autoload for eazyBackup\CometCompat shim classes
 *  ------------------------------------------------------------------ */

// Prefer Composer autoload if present
$__addonAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($__addonAutoload)) {
    require_once $__addonAutoload;
}

// Always register a tiny PSR-4 autoloader for our shim namespace
spl_autoload_register(function ($class) {
    $prefix  = 'eazyBackup\\CometCompat\\';
    $baseDir = __DIR__ . '/lib/CometCompat/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // not our namespace
    }
    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});


function eazybackup_activate()
{
    if (!Capsule::schema()->hasTable('comet_devices')) {
        Capsule::schema()->create('comet_devices', function ($table) {
            $table->string('id', 255);
            $table->integer('client_id')->nullable()->index();
            $table->string('username', 255)->nullable()->index();
            $table->string('hash', 255);
            $table->json('content')->nullable();
            $table->string('name', 255)->nullable()->index();
            $table->string('platform_os', 32)->default('');
            $table->string('platform_arch', 32)->default('');
            $table->boolean('is_active')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('revoked_at')->nullable();
            $table->primary('hash'); // match SQL definition
            $table->unique(['hash', 'client_id']); // keep this if required
        });
    }

    if (!Capsule::schema()->hasTable('comet_items')) {
        Capsule::schema()->create('comet_items', function ($table) {
            $table->uuid('id')->primary();
            $table->integer('client_id')->index();
            $table->string('username')->nullable()->index();
            $table->jsonb('content');
            $table->string('comet_device_id')->index();
            $table->string('owner_device')->nullable()->index();
            $table->string('name')->index();
            $table->string('type')->index();
            $table->bigInteger('total_bytes')->nullable();
            $table->bigInteger('total_files')->nullable();
            $table->bigInteger('total_directories')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    if (!Capsule::schema()->hasTable('comet_jobs')) {
        Capsule::schema()->create('comet_jobs', function ($table) {
            $table->uuid('id')->primary();
            $table->jsonb('content');
            $table->integer('client_id')->index();
            $table->string('username')->nullable()->index();
            $table->uuid('comet_vault_id')->index();
            $table->string('comet_device_id')->index();
            $table->uuid('comet_item_id')->index();
            $table->smallInteger('type');
            $table->smallInteger('status');
            $table->string('comet_snapshot_id', 100)->nullable();
            $table->string('comet_cancellation_id', 100);
            $table->bigInteger('total_bytes');
            $table->bigInteger('total_files');
            $table->bigInteger('total_directories');
            $table->bigInteger('upload_bytes');
            $table->bigInteger('download_bytes');
            $table->integer('total_ms_accounts')->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->timestamp('last_status_at')->nullable()->index();
            $table->index(['comet_device_id', 'last_status_at']);
        });
    }

    if (!Capsule::schema()->hasTable('comet_vaults')) {
        Capsule::schema()->create('comet_vaults', function ($table) {
            $table->uuid('id')->primary();
            $table->integer('client_id')->index();
            $table->string('username')->nullable()->index();
            $table->jsonb('content');
            $table->string('name')->index();
            $table->smallInteger('type')->index();
            $table->bigInteger('total_bytes');
            $table->string('bucket_server');
            $table->string('bucket_name');
            $table->string('bucket_key');
            $table->boolean('has_storage_limit');
            $table->bigInteger('storage_limit_bytes');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    // Create live jobs table for currently running jobs
    Capsule::statement("CREATE TABLE IF NOT EXISTS eb_jobs_live (\n  server_id        VARCHAR(64)   NOT NULL,\n  job_id           VARCHAR(128)  NOT NULL,\n  username         VARCHAR(255)  NOT NULL DEFAULT '',\n  device           VARCHAR(255)  NOT NULL DEFAULT '',\n  job_type         VARCHAR(80)   NOT NULL DEFAULT '',\n  started_at       INT UNSIGNED  NOT NULL,\n  bytes_done       BIGINT UNSIGNED NOT NULL DEFAULT 0,\n  throughput_bps   BIGINT UNSIGNED NOT NULL DEFAULT 0,\n  last_update      INT UNSIGNED    NOT NULL,\n  last_bytes       BIGINT UNSIGNED NOT NULL DEFAULT 0,\n  last_bytes_ts    INT UNSIGNED    NOT NULL DEFAULT 0,\n  cancel_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 0,\n  last_checked_ts  INT UNSIGNED    NOT NULL DEFAULT 0,\n  PRIMARY KEY (server_id, job_id),\n  KEY idx_started_at (started_at),\n  KEY idx_last_update (last_update)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Create recent finished jobs (24–48h) table
    Capsule::statement("CREATE TABLE IF NOT EXISTS eb_jobs_recent_24h (\n  server_id    VARCHAR(64)   NOT NULL,\n  job_id       VARCHAR(128)  NOT NULL,\n  username     VARCHAR(255)  NOT NULL DEFAULT '',\n  device       VARCHAR(255)  NOT NULL DEFAULT '',\n  job_type     VARCHAR(80)   NOT NULL DEFAULT '',\n  status       ENUM('success','error','warning','missed','skipped') NOT NULL,\n  bytes        BIGINT UNSIGNED NOT NULL DEFAULT 0,\n  duration_sec INT UNSIGNED    NOT NULL DEFAULT 0,\n  ended_at     INT UNSIGNED    NOT NULL,\n  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (server_id, job_id),\n  KEY idx_ended_at (ended_at),\n  KEY idx_status (status)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Backfill new columns for older installations (ignore errors if they already exist)
    try { Capsule::statement("ALTER TABLE comet_devices ADD COLUMN username VARCHAR(255) NULL"); } catch (\Throwable $e) { /* ignore */ }
    try { Capsule::statement("ALTER TABLE comet_devices ADD COLUMN client_id INT NULL"); } catch (\Throwable $e) { /* ignore */ }
    try { Capsule::statement("ALTER TABLE comet_devices ADD COLUMN platform_os VARCHAR(32) NOT NULL DEFAULT ''"); } catch (\Throwable $e) { /* ignore */ }
    try { Capsule::statement("ALTER TABLE comet_devices ADD COLUMN platform_arch VARCHAR(32) NOT NULL DEFAULT ''"); } catch (\Throwable $e) { /* ignore */ }
    try { Capsule::statement("ALTER TABLE comet_devices ADD COLUMN revoked_at TIMESTAMP NULL DEFAULT NULL"); } catch (\Throwable $e) { /* ignore */ }

    // Create per-server event cursor table
    Capsule::statement("CREATE TABLE IF NOT EXISTS eb_event_cursor (\n  source   VARCHAR(64)  PRIMARY KEY,\n  last_ts  INT UNSIGNED NOT NULL DEFAULT 0,\n  last_id  VARCHAR(128) NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Billing rollups: bundled storage billed TB per day
    Capsule::statement("CREATE TABLE IF NOT EXISTS eb_usage_bundled_daily (\n  d           DATE PRIMARY KEY,\n  billed_tb   DECIMAL(10,2) NOT NULL,\n  tier_crossing TINYINT(1) NOT NULL DEFAULT 0,\n  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  KEY idx_created_at (created_at)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Billing rollups: devices registered vs active-24h
    Capsule::statement("CREATE TABLE IF NOT EXISTS eb_devices_daily (\n  d           DATE PRIMARY KEY,\n  registered  INT NOT NULL,\n  active_24h  INT NOT NULL,\n  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Billing rollups: protected item mix snapshot
    Capsule::statement("CREATE TABLE IF NOT EXISTS eb_items_daily (\n  d           DATE PRIMARY KEY,\n  di_devices  INT NOT NULL,\n  hv_vms      INT NOT NULL,\n  vw_vms      INT NOT NULL,\n  m365_users  INT NOT NULL,\n  ff_items    INT NOT NULL,\n  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // (Removed) eazybackup_user_permissions schema creation
}

// Ensure schema upgrades are applied on runtime paths as well
function eazybackup_ensure_permissions_schema() { /* removed */ }

/**
 * Retrieve custom fields for a specific product.
 *
 * @param int $productId
 * @return \Illuminate\Support\Collection
 */
function getProductCustomFields($productId)
{
    return Capsule::table('tblcustomfields')
        ->where('type', 'product')
        ->where('relid', $productId)
        ->get();
}

// decrypt password function for the comet management console product
function decryptPassword($serviceId)
{
    $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    if ($service) {
        $decryptedPassword = decrypt($service->password);
        return ['success' => true, 'password' => $decryptedPassword];
    } else {
        return ['success' => false, 'message' => 'Service not found'];
    }
}

if ($_REQUEST['a'] == 'decryptpassword') {
    $serviceId = intval($_REQUEST['serviceid']);
    $result = decryptPassword($serviceId);
    echo json_encode($result);
    exit;
}

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/lib/Vault.php";
require_once __DIR__ . "/lib/Helper.php";
// Needed for comet_HumanFileSize and other helpers used in dashboard rendering
require_once __DIR__ . "/../../servers/comet/functions.php";



/**
 * Define addon module configuration parameters.
 *
 * @return array
 *
 */
function eazybackup_config()
{
    return [
        'name' => 'eazyBackup',
        'description' => 'WHMCS addon module for eazyBackup',
        'author' => 'eazyBackup Systems Ltd.',
        'language' => 'english',
        'version' => '1.0',
        'fields' => [
            "trialsignupgid" => [
                "FriendlyName" => "Trial Signup Product Group",
                "Type" => "dropdown",
                "Options" => eazybackup_ProductGroupsLoader(),
                "Description" => "Choose a product group for the trial signup page",
            ],
            "trialsignupemail" => [
                "FriendlyName" => "Trial Signup Email Address",
                "Type" => "text",
                "Description" => "Trial signup emails are sent to this email address",
            ],
            "resellersignupemailtemplate" => [
                "FriendlyName" => "Reseller Signup Email Template",
                "Type" => "dropdown",
                "Options" => eazybackup_EmailTemplatesLoader(),
                "Description" => "Choose an email template for the reseller signup email",
            ]
        ],
    ];
}

/**
 * Get a list of product groups from the WHMCS API.
 *
 * @return array
 * @throws Exception
 */
function eazybackup_ProductGroupsLoader()
{
    $options = [];
    foreach (Capsule::table("tblproductgroups")->get() as $group) {
        $options[$group->id] = $group->name;
    }

    return $options;
}

/**
 * Get a list of email templates from the WHMCS API.
 *
 * @return array
 */
function eazybackup_EmailTemplatesLoader()
{
    $results = localAPI("GetEmailTemplates", ["type" => "general"]);

    $templates = [];
    foreach ($results["emailtemplates"]["emailtemplate"] as $template) {
        $templates[$template["id"]] = $template["name"];
    }

    return $templates;
}


/**
 * Client Area Output.
 *
 * @param array $vars
 * @return mixed
 */
function eazybackup_clientarea(array $vars)
{

    if ($_REQUEST["a"] == "usagereport") {

        // 1) Get the serviceid from the URL
        $serviceid = isset($_REQUEST['serviceid']) ? (int) $_REQUEST['serviceid'] : 0;
        if (!$serviceid) {
            return [
                "pagetitle" => "Usage Report",
                "templatefile" => "templates/error",
                "vars" => ["error" => "No service ID was provided."]
            ];
        }

        // 2) Query the service row from tblhosting
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceid)
            ->where('userid', Auth::client()->id)  // ensure the logged-in user owns this service
            ->select('id', 'packageid', 'dedicatedip', 'username')
            ->first();

        if (!$service) {
            return [
                "pagetitle" => "Usage Report",
                "templatefile" => "templates/error",
                "vars" => ["error" => "No matching service found or you do not own this service."]
            ];
        }

        $organizationId = $service->dedicatedip;
        $params = comet_ProductParams($service->packageid);
        $reportData = myUsageReportLogic($params, $organizationId);

        return [
            "pagetitle" => "Server Usage Report",
            "templatefile" => "templates/usagereport",
            "vars" => array_merge($vars, $reportData)
        ];

    } else if ($_REQUEST["a"] == "api") {
        header('Content-Type: application/json');
        
        $postData = json_decode(file_get_contents('php://input'), true);
        $action = $postData['action'] ?? null;
        $serviceId = $postData['serviceId'] ?? null;
        $username = $postData['username'] ?? null;
        $vaultId = $postData['vaultId'] ?? null;

        if (!$action || !$serviceId || !$username) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
            exit;
        }

        $params = comet_ServiceParams($serviceId);
        $params['username'] = $username;

        $vault = new Vault($params);

        switch ($action) {
            case 'updateVault':
                if (!isset($vaultId) || $vaultId === '') {
                    echo json_encode(['status' => 'error', 'message' => 'Missing vaultId.']);
                    exit;
                }
                $vaultName = $postData['vaultName'] ?? null;
                $vaultQuota = $postData['vaultQuota'] ?? null;
                $retentionRules = $postData['retentionRules'] ?? null;
                $result = $vault->updateVault($vaultId, $vaultName, $vaultQuota, $retentionRules);
                echo json_encode($result);
                break;
            case 'deleteVault':
                if (!isset($vaultId) || $vaultId === '') {
                    echo json_encode(['status' => 'error', 'message' => 'Missing vaultId.']);
                    exit;
                }
                $result = $vault->deleteVault($vaultId);
                echo json_encode($result);
                break;
            case 'applyRetention':
                 if (!isset($vaultId) || $vaultId === '') {
                    echo json_encode(['status' => 'error', 'message' => 'Missing vaultId.']);
                    exit;
                }
                $retentionRules = $postData['retentionRules'] ?? null;
                $result = $vault->applyRetention($vaultId, $retentionRules);
                echo json_encode($result);
                break;
            default:
                echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
                break;
        }
        exit;
    } else if ($_REQUEST["a"] == "totp") {
        // Isolated TOTP AJAX endpoint
        require_once __DIR__ . "/pages/console/totp.php";
        exit; // script handles output
    } else if ($_REQUEST["a"] == "dashboard") {
        // Load the dashboard backend logic.
        $clientId = $_SESSION['uid'];
        $excludeProductgroupIds = [2, 11];
        $productIds = Capsule::table('tblproducts')
            ->select('id')
            ->whereNotIn('gid', $excludeProductgroupIds)
            ->pluck('id')
            ->toArray();

        $totalAccounts = Capsule::table('tblhosting')
            ->where('domainstatus', 'Active')
            ->where('userid', $clientId)
            ->whereIn('packageid', $productIds)
            ->count();

        // Get active usernames for this client
        $activeUsernames = Capsule::table('tblhosting')
            ->select('username')
            ->where('domainstatus', 'Active')
            ->where('userid', $clientId)
            ->whereIn('packageid', $productIds)
            ->pluck('username')
            ->toArray();

        // Count all devices for the client, but only from active WHMCS services
        $totalDevices = Capsule::table('comet_devices')
            ->where('client_id', $clientId)
            ->whereIn('username', $activeUsernames)
            ->count();

        // Count protected items for the client, but only from active WHMCS services
        $totalProtectedItems = Capsule::table('comet_items')
            ->where('client_id', $clientId)
            ->whereIn('username', $activeUsernames)
            ->count();

        // Sum storage used for the client, but only from active WHMCS services
        $totalStorageUsed = Capsule::table('comet_vaults')
            ->where('client_id', $clientId)
            ->whereIn('username', $activeUsernames)
            ->sum('total_bytes');

        // Get all devices for the client, but only from active WHMCS services
        $devices = Capsule::table('comet_devices')
            ->select('id', 'is_active', 'name', 'username', 'content')
            ->where('client_id', $clientId)
            ->whereIn('username', $activeUsernames)
            ->whereNull('revoked_at') // <-- ADD THIS LINE to hide revoked devices
            ->get();

        // Add version and platform information to each device
        foreach ($devices as $device) {
            $content = json_decode($device->content, true);
            $device->reported_version = $content['ClientVersion'] ?? null;
            $device->distribution = $content['PlatformVersion']['Distribution'] ?? null;
            
            // Debug: Log the first device to see what data we have
            if ($device === $devices->first()) {
                logModuleCall(
                    "eazybackup",
                    'dashboard_device_debug',
                    [
                        'device_name' => $device->name,
                        'reported_version' => $device->reported_version,
                        'distribution' => $device->distribution,
                        'content_keys' => array_keys($content),
                        'ClientVersion' => $content['ClientVersion'] ?? 'NOT_FOUND',
                        'PlatformVersion' => $content['PlatformVersion'] ?? 'NOT_FOUND'
                    ],
                    'First device data for debugging'
                );
            }
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-15 days'));
        foreach ($devices as $device) {
            // 1) grab the raw rows…
            $rawJobs = Capsule::table('comet_jobs')
                ->select(
                    'comet_jobs.status',
                    'comet_jobs.started_at',
                    'comet_jobs.ended_at',
                    'comet_jobs.total_bytes',
                    'comet_jobs.upload_bytes',
                    'comet_jobs.total_files',
                    'comet_jobs.total_directories',
                    'comet_items.name as protecteditem',
                    'comet_vaults.name as vaultname'
                )
                ->leftJoin('comet_items',  'comet_items.id',  '=', 'comet_jobs.comet_item_id')
                ->leftJoin('comet_vaults', 'comet_vaults.id', '=', 'comet_jobs.comet_vault_id')
                ->where('comet_jobs.comet_device_id', $device->id)
                ->whereDate('started_at', '>=', $startDate)
                ->whereDate('started_at', '<=', $endDate)
                ->orderBy('comet_jobs.started_at', 'desc')
                ->get();  // ← this gives you a Collection of StdClass
        
            // 2) map over them and overwrite the two fields your tooltip uses
            $device->jobs = $rawJobs->map(function($job) {
                // format with the Comet helper
                $job->Uploaded          = comet_HumanFileSize($job->upload_bytes);
                $job->SelectedDataSize  = comet_HumanFileSize($job->total_bytes);
                return $job;
            });        
          
        }

        $services = Capsule::table('tblhosting')
            ->select('username', 'id')
            ->where('domainstatus', 'Active')
            ->where('userid', $clientId)
            ->whereIn('packageid', $productIds)
            ->get();

        $accounts = [];
        foreach ($services as $service) {
            $total_devices = Capsule::table('comet_devices')
                ->where('client_id', $clientId)
                ->where('username', $service->username)
                ->count();

            $total_protected_items = Capsule::table('comet_items')
                ->where('client_id', $clientId)
                ->where('username', $service->username)
                ->count();

            $total_storage_vaults = Capsule::table('comet_vaults')
                ->where('client_id', $clientId)
                ->where('username', $service->username)
                ->count();

            // Get the full list of vaults for the current user
            $vaultsForUser = Capsule::table('comet_vaults')
                ->select('name', 'total_bytes')
                ->where('client_id', $clientId)
                ->where('username', $service->username)
                ->get()
                ->map(function ($vault) {
                    // Convert the vault object to an array and add the formatted size
                    $vaultArray = (array) $vault;
                    $vaultArray['size_formatted'] = Helper::humanFileSize($vaultArray['total_bytes']);
                    return $vaultArray;
                })
                ->toArray(); // This now returns an array of arrays


            $accounts[] = [
                'id' => $service->id,
                'username' => $service->username,
                'total_devices' => $total_devices,
                'total_protected_items' => $total_protected_items,
                'vaults' => $vaultsForUser, // Pass the detailed vault list
            ];
        }

        // Merge the dashboard-specific data into the existing $vars array.
        return [
            "pagetitle" => "Dashboard",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/clientarea/dashboard",
            "requirelogin" => true,  // if needed
            "forcessl" => true,  // if needed
            "vars" => [
                'modulelink' => $vars['modulelink'],
                'totalAccounts' => $totalAccounts,
                'totalDevices' => $totalDevices,
                'totalProtectedItems' => $totalProtectedItems,
                'totalStorageUsed' => Helper::humanFileSize($totalStorageUsed),
                'devices' => $devices,
                'accounts' => $accounts,
            ]
        ];

    } else if ($_REQUEST["a"] == "users") {
        // This action is now merged into the dashboard
        header("Location: {$vars['modulelink']}&a=dashboard");
        exit;



    } else if ($_REQUEST["a"] == "vaults") {
        // Load the dashboard backend logic.
        $clientId = $_SESSION['uid'];
        $excludeProductgroupIds = [2, 11];
        $productIds = Capsule::table('tblproducts')
            ->select('id')
            ->whereNotIn('gid', $excludeProductgroupIds)
            ->pluck('id')
            ->toArray();

        $accounts = Capsule::table('tblhosting')
            ->select('username', 'id')
            ->where('domainstatus', 'Active')
            ->where('userid', $clientId)
            ->whereIn('packageid', $productIds)
            ->pluck('username')
            ->toArray();


        $vaults = Capsule::table('comet_vaults')
            ->select('username', 'name', 'total_bytes')
            ->where('client_id', $clientId)
            ->whereIn('username', $accounts)
            ->get();

        return [
            "pagetitle" => "Dashboard",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/clientarea/vaults",
            "requirelogin" => true,  // if needed
            "forcessl" => true,  // if needed
            "vars" => [
                'modulelink' => $vars['modulelink'],
                'vaults' => $vaults
            ]
        ];

    } else if ($_REQUEST["a"] == "user-profile") {
        // Get the username and service ID from query parameters.
        $username = isset($_GET['username']) ? trim($_GET['username']) : '';
        $serviceid = isset($_GET['serviceid']) ? (int) $_GET['serviceid'] : 0;

        if (!$serviceid) {
            return [
                "pagetitle" => "User Profile",
                "templatefile" => "templates/error",
                "vars" => ["error" => "Service ID is required."]
            ];
        }

        // Query the account details by service id and current client only.
        $account = Capsule::table('tblhosting')
            ->where('id', $serviceid)
            ->where('userid', Auth::client()->id) // ensure the logged-in user owns this profile.
            ->first();

        if (!$account) {
            return [
                "pagetitle" => "User Profile",
                "templatefile" => "templates/error",
                "vars" => ["error" => "User not found or access denied."]
            ];
        }

        // Prefer the canonical username from the service record
        $resolvedUsername = $account->username;

        // Include the backend logic for user-profile.
        require_once __DIR__ . "/pages/console/user-profile.php";
        $routerVars = array_merge($vars, [
            'username'  => $resolvedUsername,
            'serviceid' => (int) $serviceid,
        ]);
        $userProfileData = eazybackup_user_profile($routerVars);

        // Check if backend logic returned an error.
        if (isset($userProfileData['error'])) {
            return [
                "pagetitle" => "User Profile",
                "templatefile" => "templates/error",
                "vars" => ["error" => $userProfileData['error']]
            ];
        }

        // Convert $account to an array before merging.
        $userProfileData['account'] = (array) $account;

        return [
            "pagetitle" => "User Profile: " . $resolvedUsername,
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/console/user-profile",
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => array_merge($vars, $userProfileData)
        ];
    } else if ($_REQUEST["a"] == "userdetails") {
        $serviceid = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
        if (!$serviceid) {
            return [
                "pagetitle" => "User Details",
                "templatefile" => "templates/error",
                "vars" => ["error" => "No service ID was provided."]
            ];
        }

        // 2) Query the service row from tblhosting
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceid)
            ->where('userid', Auth::client()->id)  // ensure the logged-in user owns this service
            ->select('id', 'packageid', 'dedicatedip', 'username')
            ->first();

        if (!$service) {
            return [
                "pagetitle" => "User Details",
                "templatefile" => "templates/error",
                "vars" => ["error" => "No matching service found or you do not own this service."]
            ];
        }

        // Optionally, fetch additional data needed by the 'userdetails.tpl' template
        // Since 'userdetails.tpl' loads data via AJAX, you may not need to pass much
        // However, passing service ID and username can be useful for initial setup

        return [
            "pagetitle" => "User Details",
            "templatefile" => "templates/userdetails",
            "vars" => array_merge($vars, [
                "serviceId" => $serviceid,
                "username" => $service->username,
                // Add other variables as needed
            ])
        ];

    } else if ($_REQUEST["a"] == "maintenance") {
        return [
            "pagetitle" => "Maintenance",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/maintenance",
            "requirelogin" => true,  // if needed
            "forcessl" => true,  // if needed
        ];

    } else if ($_REQUEST["a"] == "msp-welcome") {
        return [
            "pagetitle" => "MSP Welcome",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/msp-welcome",
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => array_merge($vars, [
            ]),
        ];

    } else if ($_REQUEST["a"] == "knowledgebase") {

        return [
            "pagetitle" => "eazyBackup Knowledgebase",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/knowledgebase",
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => array_merge($vars, [
                "welcomeMessage" => "Welcome aboard, ",
            ]),
        ];

    } else if ($_REQUEST["a"] == "whitelabel-signup") {
        return whitelabel_signup($vars);
    } else if ($_REQUEST["a"] == "createorder") {
        return eazybackup_createorder($vars);
    } else if ($_REQUEST["a"] == "signup") {
        return eazybackup_signup($vars);
    } else if ($_REQUEST["a"] == "obc-signup") {
        return obc_signup($vars);
    } else if ($_REQUEST["a"] == "download") {
        return [
            "pagetitle" => "Download eazyBackup",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/download",
            "requirelogin" => false,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
            ],
        ];

    } else if ($_REQUEST["a"] == "download-obc") {
        return [
            "pagetitle" => "Download OBC",
            "breadcrumb" => ["index.php?m=obc" => "OBC"],
            "templatefile" => "templates/download-obc",
            "requirelogin" => false,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
            ],
        ];
    } else if ($_REQUEST["a"] == "userdetails") {

        // 1) Get the serviceid from the URL
        $serviceid = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
        if (!$serviceid) {
            return [
                "pagetitle" => "User Details",
                "templatefile" => "templates/error",
                "vars" => ["error" => "No service ID was provided."]
            ];
        }

        $service = Capsule::table('tblhosting')
            ->where('id', $serviceid)
            ->where('userid', Auth::client()->id)  // ensure the logged-in user owns this service
            ->select('id', 'packageid', 'dedicatedip', 'username')
            ->first();

        if (!$service) {
            return [
                "pagetitle" => "User Details",
                "templatefile" => "templates/error",
                "vars" => ["error" => "No matching service found or you do not own this service."]
            ];
        }

        return [
            "pagetitle" => "User Details",
            "templatefile" => "templates/userdetails",
            "vars" => array_merge($vars, [
                "serviceId" => $serviceid,
                "username" => $service->username,
                // Add other variables as needed
            ])
        ];

    } else if ($_REQUEST["a"] == "ms365") {
        $serviceid = isset($_GET['serviceid']) ? intval($_GET['serviceid']) : 0;
        $errors = [];
        $username = '';

        $service = Capsule::table('tblhosting')
            ->where('id', $serviceid)
            ->first();

        if (!$service) {
            $errors['error'] = "Service not found.";
        } else if ($service->packageid != 52) {
            $errors['error'] = "Invalid service type.";
        } else {
            $username = htmlspecialchars($service->username, ENT_QUOTES, 'UTF-8');
        }

        return [
            "pagetitle" => "Microsoft 365 Cloud Backup Order Complete",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/ms365",
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
                "errors" => $errors,
                "username" => $username,
            ],
        ];
    } elseif ($_REQUEST["a"] == "clientarea_ms365") {
        // Handle the clientarea_ms365 action
        $serviceid = isset($_GET['serviceid']) ? intval($_GET['serviceid']) : 0;
        $errors = [];
        $templateFile = ""; // Default to empty

        if ($serviceid <= 0) {
            $errors['error'] = "Invalid or missing service ID.";
            $username = '';
            $templateFile = "templates/error"; // Use a generic error template
        } else {
            // Retrieve the service from the database
            $service = Capsule::table('tblhosting')
                ->where('id', $serviceid)
                ->first();

            if (!$service) {
                $errors['error'] = "Service not found.";
                $username = '';
                $templateFile = "templates/error"; // Use a generic error template
            } elseif (!in_array($service->packageid, [52, 57])) {
                // Ensure it's the correct product (either eazyBackup MS365 or OBC MS365)
                $errors['error'] = "Invalid service type.";
                $username = '';
                $templateFile = "templates/error"; // Use a generic error template
            } else {
                // Retrieve the username from the service
                $username = $service->username;

                // Determine the correct template based on the package ID
                $templateFile = $service->packageid == 57
                    ? "templates/success_obc-ms365"
                    : "templates/clientarea_ms365";
            }
        }
        return [
            "pagetitle" => $serviceid && $service->packageid == 57
                ? "OBC Microsoft 365 Cloud Backup Order Complete"
                : "Microsoft 365 Cloud Backup Order Complete",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => $templateFile,
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
                "errors" => $errors,
                "username" => htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'),
            ],
        ];
    } else if ($_REQUEST["a"] == "success-obc-ms365") {
        $serviceid = isset($_GET['serviceid']) ? intval($_GET['serviceid']) : 0;
        $errors = [];
        $username = '';

        $service = Capsule::table('tblhosting')
            ->where('id', $serviceid)
            ->first();

        if (!$service) {
            $errors['error'] = "Service not found.";
        } else if ($service->packageid != 57) {
            $errors['error'] = "Invalid service type.";
        } else {
            $username = htmlspecialchars($service->username, ENT_QUOTES, 'UTF-8');
        }

        return [
            "pagetitle" => "OBC Microsoft 365 Cloud Backup Order Complete",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/success-obc-ms365",
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
                "errors" => $errors,
                "username" => $username,
            ],
        ];
    } else if ($_REQUEST["a"] == "console") {
        require_once __DIR__ . "/pages/console/dashboard.php";
        $data = eazybackup_dashboard($vars);
        return [
            "pagetitle" => $data['pageTitle'],
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/console/dashboard",
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => array_merge($vars, $data),
        ];} else if ($_REQUEST["a"] == "complete") {

        // 1) Retrieve "serviceid" from the request
        $serviceid = isset($_REQUEST['serviceid']) ? (int) $_REQUEST['serviceid'] : 0;

        $serverHostname = ''; // Default empty or fallback
        if ($serviceid > 0) {
            // 2) Load the hosting record
            $hosting = Capsule::table('tblhosting')
                ->where('id', $serviceid)
                ->first();

            if ($hosting) {
                // 3) Check if there is a server assigned
                if (!empty($hosting->server)) {
                    $serverRow = Capsule::table('tblservers')
                        ->where('id', $hosting->server)
                        ->first();

                    // 4) Grab the hostname column
                    if ($serverRow) {
                        $serverHostname = $serverRow->hostname;
                    }
                }
            }
        }
        return [
            "pagetitle" => "Order Complete",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/complete-" . strtolower($_REQUEST["product"]),
            "requirelogin" => false,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
                "username" => urldecode($_REQUEST["username"] ?? ''),
                "serverHostname" => $serverHostname,
            ],
        ];
    } else if ($_REQUEST["a"] == "reseller") {
        if (!empty($_POST)) {
            $errors = eazybackup_validate_reseller($_POST);

            if (empty($errors)) {
                try {
                    $clients = localAPI("GetClientsDetails", ["email" => $_POST["email"]]);
                    $obcGroupId = eazybackup_getGroupId("OBC");

                    if ($clients["result"] == "success") {
                        $clientid = $clients["client"]["id"];
                        localAPI("UpdateClient", ["clientid" => $clientid, "groupid" => $obcGroupId]);
                    } else {
                        $clientData = [
                            "firstname" => $_POST["firstname"],
                            "lastname" => $_POST["lastname"],
                            "companyname" => $_POST["companyname"],
                            "email" => $_POST["email"],
                            "password2" => $_POST["password"],
                            "groupid" => $obcGroupId,
                            "phonenumber" => $_POST["phonenumber"],
                            "notes" => $notes,
                            "skipvalidation" => true,
                        ];

                        $client = localAPI("AddClient", $clientData);
                        $clientid = $client["clientid"];

                        if ($client["result"] !== "success") {
                            throw new \Exception("AddClient");
                        }
                    }

                    $messagename = Capsule::table("tblemailtemplates")
                        ->find($vars["resellersignupemailtemplate"])->name;

                    $emailData = [
                        "messagename" => $messagename,
                        "id" => $clientid,
                    ];

                    $email = localAPI("SendEmail", $emailData);

                    if ($email["result"] !== "success") {
                        throw new \Exception("SendEmail");
                    }

                    $note = localAPI("AddClientNote", ["userid" => $clientid, "notes" => $notes]);

                    $adminUser = 'API';

                    $ssoResult = localAPI('CreateSsoToken', [
                        'client_id' => $clientid,
                        'destination' => 'sso:custom_redirect',
                        // Adjust the sso_redirect_path as needed. Here we redirect to the download page.
                        'sso_redirect_path' => 'index.php?m=eazybackup&a=msp-welcome',
                    ], $adminUser);

                    // Log the SSO token response for debugging:
                    customFileLog("Reseller SSO Token Debug", $ssoResult);

                    if ($ssoResult['result'] === 'success') {
                        unset($_SESSION['old']);
                        $_SESSION['message'] = "Reseller account created, Welcome aboard!";
                        header("Location: {$ssoResult['redirect_url']}");
                        exit;
                    } else {
                        unset($_SESSION['old']);
                        $_SESSION['message'] = "Reseller account created but login failed: " . $ssoResult['message'];
                        header("Location: " . $vars["modulelink"] . "&a=download&product=eazybackup");
                        exit;
                    }
                } catch (\Exception $e) {
                    $errors["error"] = "There was an error creating your reseller account. Please contact support.";
                    logModuleCall(
                        "eazybackup",
                        __FUNCTION__,
                        $vars,
                        $e->getMessage(),
                        $e->getTraceAsString()
                    );
                }
            }
        }

        return [
            "pagetitle" => "Create a Reseller Account",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/reseller",
            "requirelogin" => false,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
                "errors" => $errors ?? [],
                "POST" => $_POST,
            ],
        ];
    } else if ($_REQUEST["a"] == "created") {
        return [
            "pagetitle" => "Continue to Client Area",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/created",
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
            ],
        ];
    } else if ($_REQUEST["a"] == "services") {
        $services = Capsule::table("tblhosting")
            ->where("tblhosting.userid", Auth::client()->id)
            ->where("tblhosting.packageid", 55) // PID = 55 for eazyBackup Management Console
            ->select(
                "tblhosting.id",
                "tblhosting.userid",
                "tblhosting.packageid",
                "tblhosting.domain",
                "tblhosting.username",
                "tblhosting.password",
                "tblhosting.dedicatedip",
                "tblhosting.regdate",
                "tblhosting.nextduedate",
                "tblhosting.amount",
                "tblhosting.domainstatus",
                "tblproducts.name as productname"
            )
            ->join("tblproducts", "tblhosting.packageid", "=", "tblproducts.id")
            ->get();

        // Debugging output
        if (empty($services)) {
            logModuleCall(
                "eazybackup",
                __FUNCTION__,
                $vars,
                "No PID=55 services found for user"
            );
        } else {
            logModuleCall(
                "eazybackup",
                __FUNCTION__,
                $vars,
                "PID=55 Services found",
                $services
            );
        }

        return [
            "pagetitle" => "My Services | Servers",
            "breadcrumb" => [
                "index.php?m=eazybackup" => "eazyBackup"
            ],
            "templatefile" => "templates/services",
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
                "services" => $services,
            ],
        ];
    } else if ($_REQUEST["a"] == "services-e3") {
        $services = Capsule::table("tblhosting")
            ->where("tblhosting.userid", Auth::client()->id)
            ->where("tblhosting.packageid", 48) // PID = 48 for e3 Cloud Storage
            ->select(
                "tblhosting.id",
                "tblhosting.userid",
                "tblhosting.packageid",
                "tblhosting.domain",
                "tblhosting.username",
                "tblhosting.password",
                "tblhosting.dedicatedip",
                "tblhosting.regdate",
                "tblhosting.nextduedate",
                "tblhosting.amount",
                "tblhosting.domainstatus",
                "tblproducts.name as productname"
            )
            ->join("tblproducts", "tblhosting.packageid", "=", "tblproducts.id")
            ->get();

        // Debugging output
        if (empty($services)) {
            logModuleCall(
                "eazybackup",
                __FUNCTION__,
                $vars,
                "No PID=48 services found for user"
            );
        } else {
            logModuleCall(
                "eazybackup",
                __FUNCTION__,
                $vars,
                "PID=48 Services found",
                $services
            );
        }

        return [
            "pagetitle" => "My Services | e3",
            "breadcrumb" => [
                "index.php?m=eazybackup" => "eazyBackup"
            ],
            "templatefile" => "templates/services-e3",
            "requirelogin" => true,
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
                "services" => $services,
            ],
        ];
    } elseif ($_REQUEST["a"] == "console_success") { // New action for eazyBackup Management Console
        return [
            "pagetitle" => "eazyBackup Management Console Setup Complete",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/console_success",
            "requirelogin" => true, // Assuming the user needs to be logged in
            "forcessl" => true,
            "vars" => [
                "modulelink" => $vars["modulelink"],
                // Add any additional variables needed by the console_success template
            ],
        ];
    }
}


// function eazybackup_signup($vars)
// {
//     if (session_status() === PHP_SESSION_NONE) {
//         session_start();
//     }

//     if (!empty($_POST)) {
//         // Validate form data
//         $errors = eazybackup_validate($_POST);
//         if (!empty($errors)) {
//             return [
//                 "pagetitle" => "Sign Up",
//                 "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
//                 "templatefile" => "templates/trialsignup",
//                 "requirelogin" => false,
//                 "forcessl" => true,
//                 "vars" => [
//                     "modulelink" => $vars["modulelink"],
//                     "errors" => $errors,
//                     "POST" => $_POST,
//                 ],
//             ];
//         }

//         try {
//             // Create client data as before
//             $cardnotes = "\nNumber of accounts: " . $_POST["card"];
//             $clientData = [
//                 "firstname" => "eazyBackup User",
//                 "email" => $_POST["email"],
//                 "phonenumber" => $_POST["phonenumber"],
//                 "password2" => $_POST["password"],
//                 "notes" => $cardnotes,
//                 "skipvalidation" => true,
//             ];

//             $client = localAPI("AddClient", $clientData);
//             if ($client["result"] !== "success") {
//                 customFileLog("AddClient failed", $client);
//                 throw new \Exception("AddClient: " . $client['message']);
//             }

//             // Use selected product and include promo code to trigger a free trial
//             $orderData = [
//                 "clientid" => $client["clientid"],
//                 "pid" => [$_POST["product"]],
//                 "promocode" => "trial",       // Apply the "trial" promo code
//                 "paymentmethod" => "stripe",
//                 "noinvoice" => true,          // Prevent invoice generation on signup
//                 "noemail" => true,          // Suppress invoice email (if desired)
//             ];

//             $order = localAPI("AddOrder", $orderData);
//             if ($order["result"] !== "success") {
//                 customFileLog("AddOrder failed", $order);
//                 throw new \Exception("AddOrder: " . $order['message']);
//             }

//             $acceptData = [
//                 "orderid" => $order["orderid"],
//                 "autosetup" => true,
//                 "sendemail" => true,
//                 "serviceusername" => $_POST["username"],
//                 "servicepassword" => $_POST["password"],
//             ];

//             $accept = localAPI("AcceptOrder", $acceptData);
//             if ($accept["result"] !== "success") {
//                 customFileLog("AcceptOrder failed", $accept);
//                 throw new \Exception("AcceptOrder: " . $accept['message']);
//             }

//             // Retrieve the created service record so we can pass its ID in the redirect URL
//             $service = Capsule::table('tblhosting')
//                 ->where('orderid', $order["orderid"])
//                 ->first();
//             if (!$service) {
//                 throw new \Exception("Service record not found after order acceptance.");
//             }

//             // Update the product's next due date to 15 days in the future
//             $nextDueDate = date('Y-m-d H:i:s', strtotime('+15 days'));
//             Capsule::table('tblhosting')
//                 ->where('id', $service->id)
//                 ->update(['nextduedate' => $nextDueDate]);

//             // --- Begin Product-Specific Provisioning & SSO Auto-Login Sequence ---
//             $adminUser = 'API';
//             $product = $_POST["product"];

//             if ($product == "52") {
//                 // Run the container provisioning process for Microsoft 365 Backup
//                 $provisionResponse = EazybackupObcMs365::provisionLXDContainer($_POST["username"], $_POST["password"], $product);
//                 if (isset($provisionResponse['error'])) {
//                     customFileLog("Container provisioning failed", $provisionResponse);
//                     throw new Exception("Container provisioning failed: " . $provisionResponse['error']);
//                 }
//                 // Append the service id so the clientarea/ms365 action can retrieve the username
//                 $redirectPath = 'index.php?m=eazybackup&a=ms365&serviceid=' . $service->id;
//             } else {
//                 // For eazyBackup (pid 58) use existing behavior
//                 $redirectPath = 'index.php?m=eazybackup&a=download&product=eazybackup';
//             }

//             // Create SSO token with appropriate redirect path
//             $ssoResult = localAPI('CreateSsoToken', [
//                 'client_id' => $client["clientid"],
//                 'destination' => 'sso:custom_redirect',
//                 'sso_redirect_path' => $redirectPath,
//             ], $adminUser);

//             customFileLog("SSO Token Debug", $ssoResult);

//             if ($ssoResult['result'] === 'success') {
//                 unset($_SESSION['old']);
//                 $_SESSION['message'] = "Account created, Welcome aboard!";
//                 header("Location: {$ssoResult['redirect_url']}");
//                 exit;
//             } else {
//                 unset($_SESSION['old']);
//                 $_SESSION['message'] = "Account created but login failed: " . $ssoResult['message'];
//                 header("Location: " . $vars["modulelink"] . "&a=download&product=eazybackup");
//                 exit;
//             }
//             // --- End SSO Auto-Login Sequence ---

//         } catch (\Exception $e) {
//             if (empty($errors["error"])) {
//                 $errors["error"] = "There was an error completing your sign up. Please contact support.";
//             }
//             customFileLog("Signup process failed", $e->getMessage() . ' - ' . $e->getTraceAsString());
//         }
//     }

//     return [
//         "pagetitle" => "Sign Up",
//         "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
//         "templatefile" => "templates/trialsignup",
//         "requirelogin" => false,
//         "forcessl" => true,
//         "vars" => [
//             "modulelink" => $vars["modulelink"],
//             "errors" => $errors ?? [],
//             "POST" => $_POST,
//         ],
//     ];
// }
function eazybackup_signup($vars)
{
    // Start session if not already
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // When form is submitted...
    if (!empty($_POST)) {
        // 1) Validate form data
        $errors = eazybackup_validate($_POST);
        if (!empty($errors)) {
            return [
                "pagetitle"    => "Sign Up",
                "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
                "templatefile" => "templates/trialsignup",
                "requirelogin" => false,
                "forcessl"     => true,
                "vars"         => [
                    "modulelink" => $vars["modulelink"],
                    "errors"     => $errors,
                    "POST"       => $_POST,
                ],
            ];
        }

        try {
            // 2) Create the client
            $cardnotes = "\nNumber of accounts: " . $_POST["card"];
            $clientData = [
                "firstname"     => "eazyBackup User",
                "email"         => $_POST["email"],
                "phonenumber"   => $_POST["phonenumber"],
                "password2"     => $_POST["password"],
                "notes"         => $cardnotes,
                "skipvalidation"=> true,
            ];
            $client = localAPI("AddClient", $clientData);
            if ($client["result"] !== "success") {
                customFileLog("AddClient failed", $client);
                throw new \Exception("AddClient: " . $client['message']);
            }

            // 3) Place the order with your "trial" promo code
            $orderData = [
                "clientid"     => $client["clientid"],
                "pid"          => [$_POST["product"]],
                "promocode"    => "trial",
                "paymentmethod"=> "stripe",
                "noinvoice"    => true,
                "noemail"      => true,
            ];
            $order = localAPI("AddOrder", $orderData);
            if ($order["result"] !== "success") {
                customFileLog("AddOrder failed", $order);
                throw new \Exception("AddOrder: " . $order['message']);
            }

            // 4) Accept the order (autosetup + email)
            $acceptData = [
                "orderid"        => $order["orderid"],
                "autosetup"      => true,
                "sendemail"      => true,
                "serviceusername"=> $_POST["username"],
                "servicepassword"=> $_POST["password"],
            ];
            $accept = localAPI("AcceptOrder", $acceptData);
            if ($accept["result"] !== "success") {
                customFileLog("AcceptOrder failed", $accept);
                throw new \Exception("AcceptOrder: " . $accept['message']);
            }

            // 5) Fetch the newly created service record
            $service = Capsule::table('tblhosting')
                ->where('orderid', $order["orderid"])
                ->first();
            if (!$service) {
                throw new \Exception("Service record not found after order acceptance.");
            }

            // 6) Override WHMCS dates to exactly 14 days from now
            $adminUser = 'API'; // must be a valid admin username
            $newDate   = date('Y-m-d', strtotime('+14 days'));
            $update    = localAPI('UpdateClientProduct', [
                'serviceid'       => $service->id,
                'nextduedate'     => $newDate,
                'nextinvoicedate' => $newDate,
            ], $adminUser);

            if ($update['result'] !== 'success') {
                customFileLog("UpdateClientProduct failed", $update);
                throw new \Exception("Could not set trial due date: " . $update['message']);
            }

            // 7) Product‑specific provisioning & build SSO redirect
            $product = $_POST["product"];
            if ($product == "52") {
                $provisionResponse = EazybackupObcMs365::provisionLXDContainer(
                    $_POST["username"],
                    $_POST["password"],
                    $product
                );
                if (isset($provisionResponse['error'])) {
                    customFileLog("Container provisioning failed", $provisionResponse);
                    throw new Exception("Container provisioning failed: " . $provisionResponse['error']);
                }
                $redirectPath = 'index.php?m=eazybackup&a=ms365&serviceid=' . $service->id;
            } else {
                $redirectPath = 'index.php?m=eazybackup&a=download&product=eazybackup';
            }

            // 8) Create SSO token and redirect
            $ssoResult = localAPI('CreateSsoToken', [
                'client_id'         => $client["clientid"],
                'destination'       => 'sso:custom_redirect',
                'sso_redirect_path' => $redirectPath,
            ], $adminUser);
            customFileLog("SSO Token Debug", $ssoResult);

            if ($ssoResult['result'] === 'success') {
                unset($_SESSION['old']);
                $_SESSION['message'] = "Account created, Welcome aboard!";
                header("Location: {$ssoResult['redirect_url']}");
                exit;
            } else {
                unset($_SESSION['old']);
                $_SESSION['message'] = "Account created but login failed: " . $ssoResult['message'];
                header("Location: " . $vars["modulelink"] . "&a=download&product=eazybackup");
                exit;
            }

        } catch (\Exception $e) {
            // Log and fall through to re‑show signup form with an error
            if (empty($errors["error"])) {
                $errors["error"] = "There was an error completing your sign up. Please contact support.";
            }
            customFileLog("Signup process failed", $e->getMessage() . ' - ' . $e->getTraceAsString());
        }
    }

    // 9) Initial form display or error redisplay
    return [
        "pagetitle"    => "Sign Up",
        "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
        "templatefile" => "templates/trialsignup",
        "requirelogin" => false,
        "forcessl"     => true,
        "vars"         => [
            "modulelink" => $vars["modulelink"],
            "errors"     => $errors ?? [],
            "POST"       => $_POST,
            "TURNSTILE_SITE_KEY"   => TURNSTILE_SITE_KEY,
        ],
    ];
}



function obc_signup($vars)
{
    // Ensure session is active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($_POST)) {
        $_POST["product"] = "60";

        $errors = eazybackup_validate($_POST);
        if (!empty($errors)) {
            return [
                "pagetitle" => "Sign Up",
                "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
                "templatefile" => "templates/trialsignup-obc",
                "requirelogin" => false,
                "forcessl" => true,
                "vars" => [
                    "modulelink" => $vars["modulelink"],
                    "errors" => $errors,
                    "POST" => $_POST,
                ],
            ];
        }

        try {
            $cardnotes = "\nNumber of accounts: " . $_POST["card"];
            $clientData = [
                "firstname" => "OBC User",
                "email" => $_POST["email"],
                "phonenumber" => $_POST["phonenumber"],
                "password2" => $_POST["password"],
                "notes" => $cardnotes,
                "skipvalidation" => true,
            ];

            $client = localAPI("AddClient", $clientData);
            if ($client["result"] !== "success") {
                customFileLog("AddClient failed", $client);
                throw new \Exception("AddClient: " . $client['message']);
            }

            $orderData = [
                "clientid" => $client["clientid"],
                "pid" => ["60"],
                "promocode" => "trial",
                "paymentmethod" => "stripe",
            ];

            $order = localAPI("AddOrder", $orderData);
            if ($order["result"] !== "success") {
                customFileLog("AddOrder failed", $order);
                throw new \Exception("AddOrder: " . $order['message']);
            }

            $acceptData = [
                "orderid" => $order["orderid"],
                "autosetup" => true,
                "sendemail" => true,
                "serviceusername" => $_POST["username"],
                "servicepassword" => $_POST["password"],
            ];

            $accept = localAPI("AcceptOrder", $acceptData);
            if ($accept["result"] !== "success") {
                customFileLog("AcceptOrder failed", $accept);
                throw new \Exception("AcceptOrder: " . $accept['message']);
            }

            // --- Begin SSO Auto-Login Sequence ---
            $adminUser = 'API';
            $ssoResult = localAPI('CreateSsoToken', [
                'client_id' => $client["clientid"],
                'destination' => 'sso:custom_redirect',
                // Consider updating this to a client area page that requires login
                'sso_redirect_path' => 'index.php?m=eazybackup&a=download-obc&product=obc',
            ], $adminUser);

            customFileLog("SSO Token Debug", $ssoResult);

            if ($ssoResult['result'] === 'success') {
                unset($_SESSION['old']);
                $_SESSION['message'] = "Account created, Welcome aboard!";
                header("Location: {$ssoResult['redirect_url']}");
                exit;
            } else {
                unset($_SESSION['old']);
                $_SESSION['message'] = "Account created but login failed: " . $ssoResult['message'];
                header("Location: " . $vars["modulelink"] . "&a=download-obc&product=obc");
                exit;
            }
            // --- End SSO Auto-Login Sequence ---

        } catch (\Exception $e) {
            if (empty($errors["error"])) {
                $errors["error"] = "There was an error completing your sign up. Please contact support.";
            }
            customFileLog("Signup process failed", $e->getMessage() . ' - ' . $e->getTraceAsString());
        }
    }

    return [
        "pagetitle" => "Sign Up for OBC",
        "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
        "templatefile" => "templates/trialsignup-obc",
        "requirelogin" => false,
        "forcessl" => true,
        "vars" => [
            "modulelink" => $vars["modulelink"],
            "errors" => $errors ?? [],
            "POST" => $_POST,
        ],
    ];
}

/**
 * Handles display and submission of the White Label Signup form.
 *
 * When a POST request is detected, the function:
 * - Retrieves submitted text fields and processes file uploads.
 * - Appends a note with all submitted details to the client's profile notes.
 * - Opens a support ticket using the local WHMCS API.
 * - (Then proceeds with additional operations such as product group creation, etc.)
 *
 * @param array $vars The module variables passed from WHMCS.
 * @return array The response array used by WHMCS to render the client area page.
 */
function whitelabel_signup(array $vars)
{
    // ---------- GET Request Branch ----------
    // If the request method is not POST, generate a custom domain and pass it to the template.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $custom_domain = substr(uniqid(), 0, 8) . '.obcbackup.com';
        return [
            "pagetitle" => "White Label Signup",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/whitelabel-signup",
            "requirelogin" => false,
            "forcessl" => true,
            "vars" => array_merge($vars, [
                "custom_domain" => $custom_domain,
            ]),
        ];
    }

    // ---------- POST Request Branch ----------
    // Retrieve text fields from POST data.
    $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
    $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
    $help_url = isset($_POST['help_url']) ? trim($_POST['help_url']) : '';
    $eula = isset($_POST['eula']) ? trim($_POST['eula']) : '';
    $header_color = isset($_POST['header_color']) ? trim($_POST['header_color']) : '';
    $accent_color = isset($_POST['accent_color']) ? trim($_POST['accent_color']) : '';
    $tile_background = isset($_POST['tile_background']) ? trim($_POST['tile_background']) : '';
    $custom_domain = isset($_POST['custom_domain']) ? trim($_POST['custom_domain']) : '';

    // If custom_domain is empty, generate one.
    if (empty($custom_domain)) {
        $custom_domain = substr(uniqid(), 0, 8) . '.obcbackup.com';
    }

    // Retrieve Custom SMTP Server fields (if provided).
    $smtp_sendas_name = isset($_POST['smtp_sendas_name']) ? trim($_POST['smtp_sendas_name']) : '';
    $smtp_sendas_email = isset($_POST['smtp_sendas_email']) ? trim($_POST['smtp_sendas_email']) : '';
    $smtp_server = isset($_POST['smtp_server']) ? trim($_POST['smtp_server']) : '';
    $smtp_port = isset($_POST['smtp_port']) ? trim($_POST['smtp_port']) : '';
    $smtp_username = isset($_POST['smtp_username']) ? trim($_POST['smtp_username']) : '';
    $smtp_password = isset($_POST['smtp_password']) ? trim($_POST['smtp_password']) : '';
    $smtp_security = isset($_POST['smtp_security']) ? trim($_POST['smtp_security']) : '';

    // Build a note string with the submitted details.
    $note = "White Label Signup Details:\n";
    $note .= "Product Name: " . $product_name . "\n";
    $note .= "Company Name: " . $company_name . "\n";
    $note .= "Help URL: " . $help_url . "\n";
    $note .= "EULA: " . $eula . "\n";
    $note .= "Header Color: " . $header_color . "\n";
    $note .= "Accent Color: " . $accent_color . "\n";
    $note .= "Tile Background: " . $tile_background . "\n";
    $note .= "Custom Control Panel Domain: " . $custom_domain . "\n";
    $note .= "Custom SMTP Server Details:\n";
    $note .= "  Send as (display name): " . $smtp_sendas_name . "\n";
    $note .= "  Send as (email): " . $smtp_sendas_email . "\n";
    $note .= "  SMTP Server: " . $smtp_server . "\n";
    $note .= "  Port: " . $smtp_port . "\n";
    $note .= "  SMTP Username: " . $smtp_username . "\n";
    $note .= "  SMTP Password: " . $smtp_password . "\n";
    $note .= "  Security: " . $smtp_security . "\n";

    // Retrieve client details (ensure the user is logged in).
    $client = Auth::client();
    $clientId = $client->id;
    // Use the client's identifier (e.g. username) for folder creation.
    $userIdentifier = $client->username;

    // Set up the directory for file uploads.
    $uploadBase = '/var/www/eazybackup.ca/accounts/assets/';
    $userUploadDir = $uploadBase . $userIdentifier . '/';
    if (!is_dir($userUploadDir)) {
        mkdir($userUploadDir, 0755, true);
    }

    // Allowed file extensions.
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'svg', 'ico'];
    // List of file fields to process.
    $fileFields = [
        'icon_windows',
        'icon_macos',
        'menu_bar_icon_macos',
        'logo_image',
        'tile_image',
        'background_logo',
        'app_icon_image',
        'header',
        'tab_icon'
    ];

    // Process file uploads.
    foreach ($fileFields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES[$field]['tmp_name'];
            $originalFileName = $_FILES[$field]['name'];
            $fileNameParts = explode(".", $originalFileName);
            $fileExtension = strtolower(end($fileNameParts));

            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = $field . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $destPath = $userUploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $note .= ucfirst(str_replace('_', ' ', $field)) . ": " . $destPath . "\n";
                } else {
                    $note .= ucfirst(str_replace('_', ' ', $field)) . ": File upload failed.\n";
                }
            } else {
                $note .= ucfirst(str_replace('_', ' ', $field)) . ": Invalid file extension ($fileExtension).\n";
            }
        } else {
            $note .= ucfirst(str_replace('_', ' ', $field)) . ": No file uploaded.\n";
        }
    }

    // Append the new details to the client's existing notes.
    $currentNotes = Capsule::table('tblclients')->where('id', $clientId)->value('notes');
    $updatedNotes = $currentNotes . "\n" . $note;

    // Update the client's notes via the local API.
    $updateResponse = localAPI("UpdateClient", [
        "clientid" => $clientId,
        "notes" => $updatedNotes,
    ]);

    if (isset($updateResponse['result']) && $updateResponse['result'] == "success") {
        // Open a support ticket using the local API.
        $ticketSubject = "White Label for " . $product_name;
        $ticketMessage = "Thank you for submitting your White Label product request. Our team has received your request and is currently processing it. You can expect an update within the next 24 to 48 hours.\n\nYour White Label products will appear in the \"Order New Services\" menu. Please refrain from placing an order for your White Label products until our team has provisioned your products.\n\nIf you have any questions in the meantime, feel free to reply to this ticket.";
        $ticketData = [
            'deptid' => '2', // Adjust the department ID as needed.
            'subject' => $ticketSubject,
            'message' => $ticketMessage,
            'clientid' => $clientId,
            'priority' => 'Medium',
            'markdown' => true,
            'preventClientClosure' => true,
            'responsetype' => 'json',
        ];
        $adminUsername = 'API'; // Adjust this to your admin username if needed.

        $ticketResponse = localAPI("OpenTicket", $ticketData, $adminUsername);
        logActivity("eazybackup: OpenTicket Response => " . json_encode($ticketResponse));

        /* ------------------------------------------------------------------
         *  Product‑Group creation (if it doesn’t exist yet)
         * ------------------------------------------------------------------*/
        $groupId = Capsule::table('tblproductgroups')
            ->where('name', $product_name)           // “Acme Backup”, etc.
            ->value('id');

        if (!$groupId) {
            $groupId = Capsule::table('tblproductgroups')->insertGetId([
                'name' => $product_name,
                'headline' => $company_name . ' Cloud Backup',
                'created_at'       => Carbon::now()->format('Y-m-d H:i:s'),
                'orderfrmtpl' => '',       // use default order‑form template
            ]);
        }

        /* ------------------------------------------------------------------
         * Upsert the client ➜ group mapping row
         * ------------------------------------------------------------------*/
        Capsule::table('tbl_client_productgroup_map')
            ->updateOrInsert(
                ['client_id' => $clientId],
                [
                    'product_group_id' => $groupId,
                    'created_at'       => Carbon::now()->format('Y-m-d H:i:s'),
                ]
            );


        // Return success with the custom domain included.
        return [
            "pagetitle" => "White Label Signup",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/whitelabel-signup",
            "requirelogin" => false,
            "forcessl" => true,
            "vars" => array_merge($vars, [
                "custom_domain" => $custom_domain,
                "successMessage" => "Your white label details have been submitted successfully! A support ticket has been opened for your request.",
            ]),
        ];
    } else {
        $vars['errors']["error"] = "Failed to update your profile. Please try again later.";
    }

    // If an error occurred, generate a new custom domain for display.
    $custom_domain = substr(uniqid(), 0, 8) . '.obcbackup.com';
    return [
        "pagetitle" => "White Label Signup",
        "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
        "templatefile" => "templates/whitelabel-signup",
        "requirelogin" => false,
        "forcessl" => true,
        "vars" => array_merge($vars, [
            "custom_domain" => $custom_domain,
        ]),
    ];
}


/**
 * Deep‑clone a WHMCS product into a target group.
 * – Copies pricing, module settings, configurable options, custom fields, etc.
 *
 * @param int    $templatePid  Source product ID (e.g. 60 for OBC template)
 * @param int    $targetGroup  Destination product‑group ID
 * @param string $newName      New product name shown to the reseller
 * @return int|false           New product ID or false on failure
 */
function cloneProduct(int $templatePid, int $targetGroup, string $newName)
{
    Capsule::connection()->beginTransaction();
    try {
        // 1. Clone main product row
        $template = Capsule::table('tblproducts')->where('id', $templatePid)->first();
        if (!$template) {
            throw new Exception("Template product $templatePid not found");
        }

        $productRow           = (array) $template;
        unset($productRow['id']);
        $productRow['gid']    = $targetGroup;
        $productRow['name']   = $newName;
        $productRow['created_at'] = Carbon::now()->toDateTimeString();

        $newPid = Capsule::table('tblproducts')->insertGetId($productRow);

        /* 2. Clone pricing (tblpricing). One row per currency */
        $prices = Capsule::table('tblpricing')
                  ->where('relid', $templatePid)
                  ->where('type', 'product')
                  ->get();

        foreach ($prices as $price) {
            $newPrice         = (array) $price;
            unset($newPrice['id']);
            $newPrice['relid'] = $newPid;          // point to new product
            Capsule::table('tblpricing')->insert($newPrice);
        }

        /* 3. Clone custom fields (tblcustomfields) */
        $fields = Capsule::table('tblcustomfields')
                 ->where('relid', $templatePid)
                 ->where('type', 'product')
                 ->get();

        foreach ($fields as $field) {
            $newField            = (array) $field;
            unset($newField['id']);
            $newField['relid']   = $newPid;
            Capsule::table('tblcustomfields')->insert($newField);
        }

        Capsule::connection()->commit();
        return $newPid;

    } catch (\Throwable $e) {
        Capsule::connection()->rollBack();
        logActivity("cloneProduct failed: " . $e->getMessage());
        return false;
    }
}


function eazybackup_createorder($vars)
{
    // Debug: confirm function invocation
    logActivity("eazybackup: ENTER eazybackup_createorder function.");

    // -----------------------------
    // 1) Handle Form Submission
    // -----------------------------
    if (!empty($_POST)) {
        logActivity("eazybackup: Form POST => " . print_r($_POST, true));

        $errors = eazybackup_validate_order($_POST);
        // Capture the original product selection from the form
        $selectedPid = $_POST["product"] ?? null;

        $productGroupId = Capsule::table('tblproducts')
        ->where('id', $selectedPid)
        ->value('gid');
        $publicGroupIds = [6, 7];
        $isWhiteLabel = ! in_array($productGroupId, $publicGroupIds, true);

        logActivity("eazybackup: Selected PID {$selectedPid} has group {$productGroupId}; isWhiteLabel=" . ($isWhiteLabel ? 'yes':'no'));

        if (empty($errors)) {
            try {
                // Check for Client ID in session
                $clientid = $_SESSION['uid'] ?? null;
                if (!$clientid) {
                    throw new \Exception("Client ID is missing from session.");
                }
                logActivity("eazybackup: Client ID => " . $clientid);

                // --- Begin Order Creation ---
                $orderData = [
                    "clientid" => $clientid,
                    "pid" => [$selectedPid],
                    "promocode" => "trial",   
                    "paymentmethod" => "stripe",  
                    "noinvoice"     => $isWhiteLabel ? true : false,
                ];
                logActivity("eazybackup: AddOrder => " . json_encode($orderData));

                $order = localAPI("AddOrder", $orderData);
                logActivity("eazybackup: AddOrder Response => " . json_encode($order));

                if ($order["result"] !== "success") {
                    throw new \Exception("AddOrder Failed: " . $order['message']);
                }

                // Accept the order
                $acceptData = [
                    "orderid" => $order["orderid"],
                    "autosetup" => true,
                    "sendemail" => true,
                    "serviceusername" => $_POST["username"] ?? "",
                    "servicepassword" => $_POST["password"] ?? "",
                ];
                logActivity("eazybackup: AcceptOrder => " . json_encode($acceptData));

                $accept = localAPI("AcceptOrder", $acceptData);
                logActivity("eazybackup: AcceptOrder Response => " . json_encode($accept));

                if ($accept["result"] !== "success") {
                    throw new \Exception("AcceptOrder Failed: " . $accept['message']);
                }

                // Retrieve the created service record
                $service = Capsule::table('tblhosting')
                    ->where('orderid', $order["orderid"])
                    ->first();
                logActivity("eazybackup: Created Service => " . json_encode($service));

                if ($isWhiteLabel) {
                    // Grab user’s choice from the form; default to monthly
                    $billingTerm = ($_POST['billingterm'] ?? 'monthly') === 'annual' ? 'annual' : 'monthly';
                
                    // Map to WHMCS billing cycle names
                    $billingCycle = $billingTerm === 'annual' ? 'Annually' : 'Monthly';
                
                    // Set next due/invoice date to exactly one month from today
                    $nextDate = date('Y-m-d', strtotime('+1 month'));
                
                    $updateData = [
                        'serviceid'       => $service->id,
                        'billingcycle'    => $billingCycle,
                        'nextduedate'     => $nextDate,
                        'nextinvoicedate' => $nextDate,
                    ];
                    logActivity("eazybackup: UpdateClientProduct => " . json_encode($updateData));
                    $updateResult = localAPI('UpdateClientProduct', $updateData);
                    logActivity("eazybackup: UpdateClientProduct Response => " . json_encode($updateResult));
                }                

                // --- End Order Creation ---

                // --- Begin LXD Provisioning for MS 365 Products ---
                if ($selectedPid == 52 || $selectedPid == 57) {
                    logActivity("eazybackup: Initiating LXD provisioning for product {$selectedPid}");
                    $provisionResponse = EazybackupObcMs365::provisionLXDContainer($_POST["username"], $_POST["password"], $selectedPid);
                    if (isset($provisionResponse['error'])) {
                        logActivity("eazybackup: Container provisioning failed: " . json_encode($provisionResponse));
                        throw new \Exception("Container provisioning failed: " . $provisionResponse['error']);
                    }
                }
                // --- End LXD Provisioning ---

                $service = Capsule::table('tblhosting')
                    ->where('orderid', $order["orderid"])
                    ->first();

                // 1) Build the “module parameters” that comet_UpdateUser() expects
                $params = comet_ServiceParams($service->id);

                // overwrite/add the two things we care about:
                $params['username']         = $_POST['username'];
                $params['clientsdetails']   = [
                    // comet_UpdateUser() does $params["clientsdetails"]["email"]
                    'email' => $_POST['reportemail'] ?? ''
                ];

                // 2) Call the Comet helper to update that user’s notification email
                try {
                    logActivity("eazybackup: Updating Comet user email to {$params['clientsdetails']['email']}");
                    comet_UpdateUser($params);
                } catch (\Exception $e) {
                    // if you want to fail the order on error, throw; otherwise just log
                    logActivity("eazybackup: comet_UpdateUser failed: " . $e->getMessage());
                }

                // --- Begin Redirect Logic ---
                // Default settings
                $redirectProductParam = "eazybackup";  // default product param
                $template = "complete";                // default template action

                // 1) Check user’s client group ID (legacy check)
                $clientGroupId = Capsule::table('tblclients')
                    ->where('id', $clientid)
                    ->value('groupid');
                logActivity("eazybackup: DB clientGroupId => " . ($clientGroupId ?? 0));

                if (!empty($clientGroupId)) {
                    $expectedCustomField = "gid" . $clientGroupId;
                    logActivity("eazybackup: Checking product {$selectedPid} for {$expectedCustomField}");

                    $hasGroupField = Capsule::table('tblcustomfields')
                        ->where('relid', $selectedPid)
                        ->where('type', 'product')
                        ->where('fieldname', $expectedCustomField)
                        ->exists();

                    if ($hasGroupField) {
                        $redirectProductParam = "whitelabel";
                        logActivity("eazybackup: Found {$expectedCustomField}, using whitelabel.");
                    } else {
                        logActivity("eazybackup: Did NOT find {$expectedCustomField}, using eazybackup.");
                    }
                } else {
                    logActivity("eazybackup: No group or group=0, using eazybackup.");
                }

                // Override the redirect template for MS 365 products based on the original selection
                if ($selectedPid == 52) {
                    $template = "ms365";
                } elseif ($selectedPid == 57) {
                    $template = "success-obc-ms365";
                }

                $PUBLIC_GROUP_IDS = [6, 7];

                $productGroupId = Capsule::table('tblproducts')
                                ->where('id', $selectedPid)
                                ->value('gid');

                if (!in_array($productGroupId, $PUBLIC_GROUP_IDS, true)) {
                    // Anything outside groups 6 & 7 is a white‑label product
                    $redirectProductParam = 'whitelabel';
                } elseif ($productGroupId == 7) {
                    $redirectProductParam = 'obc';        // optional: treat OBC separately
                } else {
                    $redirectProductParam = 'eazybackup'; // group 6
                }

                // 3) Final redirect using determined template and product parameter
                $redirectUrl = $vars["modulelink"]
                    . '&a=' . $template
                    . '&product=' . $redirectProductParam
                    . '&serviceid=' . urlencode($service->id)
                    . '&username=' . urlencode($_POST["username"] ?? "");
                logActivity("eazybackup: Redirect => " . $redirectUrl);
                header("Location: {$redirectUrl}");
                exit;

            } catch (\Exception $e) {
                $errors["error"] = "There was an error completing your sign up. Please contact support.";
                logActivity("eazybackup: Signup process failed: " . $e->getMessage() . " - " . $e->getTraceAsString());
            }
        }
    }

    // -----------------------------
    // 2) Build Category Arrays
    // -----------------------------
    logActivity("eazybackup: Checking \$vars['clientsdetails'] => " . print_r($vars['clientsdetails'], true));

    // Determine the current client id
    $clientid = $_SESSION['uid'] ?? ($vars['clientsdetails']['id'] ?? null);

    // Query the custom mapping to retrieve the product group for this client
    $mapping = Capsule::table('tbl_client_productgroup_map')
        ->where('client_id', $clientid)
        ->first();
    $whitelabel_product_name = "White Label";
    $customGroupId = null;
    if ($mapping) {
        $customGroupId = $mapping->product_group_id;
        $group = Capsule::table('tblproductgroups')->where('id', $customGroupId)->first();
        if ($group) {
            $whitelabel_product_name = $group->name;
        }
    }

    // Fetch All Products via localAPI
    $apiResponse = localAPI("GetProducts", []);
    logActivity("eazybackup: localAPI GetProducts => " . json_encode($apiResponse));
    $allProducts = $apiResponse["products"]["product"] ?? [];
    logActivity("eazybackup: Total products => " . count($allProducts));

    // Define category arrays
    $categories = [
        'whitelabel' => [],
        'eazybackup' => [],
        'obc' => [],
    ];

    // a) If user has a custom mapping, filter products with gid equal to client's custom group id.
    if (!empty($customGroupId)) {
        foreach ($allProducts as $p) {
            if ($p['gid'] == $customGroupId) {
                $categories['whitelabel'][] = $p;
            }
        }
    }

    // b) Now, iterate over all products and include products with package IDs 52 and 57.
    foreach ($allProducts as $p) {
        if ($p['pid'] == 52) {
            $categories['eazybackup'][] = $p;
            logActivity("eazybackup: Added product with PID 52 to eazybackup category");
        }
        if ($p['pid'] == 57) {
            $categories['obc'][] = $p;
            logActivity("eazybackup: Added product with PID 57 to obc category");
        }
    }

    logActivity("eazybackup: Final categories => " . print_r($categories, true));

    // -----------------------------
    // 3) Return Data to Template
    // -----------------------------
    return [
        "pagetitle" => "Create Order",
        "breadcrumb" => ["index.php?m=eazybackup" => "createorder"],
        "templatefile" => "templates/createorder",
        "requirelogin" => true,
        "forcessl" => true,
        "vars" => [
            "modulelink" => $vars["modulelink"],
            "errors" => $errors ?? [],
            "POST" => $_POST,
            "categories" => $categories,
            "whitelabel_product_name" => $whitelabel_product_name,
        ],
    ];
}


function eazybackup_getGroupId($name)
{
    return Capsule::table("tblclientgroups")->where("groupname", $name)->first()->id;
}

function isValidPassword($password)
{
    return preg_match('/[A-Z]/', $password) &&        // At least one uppercase letter
        preg_match('/[a-z]/', $password) &&        // At least one lowercase letter
        preg_match('/\d/', $password) &&           // At least one number
        preg_match('/[^a-zA-Z\d]/', $password) &&  // At least one special character
        strlen($password) >= 8;                    // Minimum length of 8 characters
}

function eazybackup_validate(array $vars)
{
    // Log the POST data at the start of the validation function
    error_log("Form submission data: " . print_r($vars, true));

    $errors = [];

    // Validate Cloudflare Turnstile
    if (empty($vars["cf-turnstile-response"])
        || !validateTurnstile($vars["cf-turnstile-response"])
    ) {
        $errors["turnstile"] = "Please complete the CAPTCHA verification.";
        logModuleCall(
            'eazybackup',
            'ValidateTurnstile',
            ['responseToken' => $vars['cf-turnstile-response']],
            ['success' => false]
        );
    }


    // Validate username
    if (empty($vars["username"]) || !preg_match('/^[a-zA-Z0-9._-]{6,}$/', $vars["username"])) {
        $errors["username"] = "Username must be at least 6 characters and may contain only letters, numbers, periods, underscores, or hyphens.";
    } else {
        try {
            // Check if the username is already taken
            comet_Server(["pid" => $vars["product"]])->AdminGetUserProfile($vars["username"]);
            $errors["username"] = "That username is not available, please try another";
        } catch (\Exception $e) {
            // Username is available; do nothing
        }
    }

    // Enhanced password validation
    if (!isValidPassword($vars["password"])) {
        $errors["password"] = "Password must be at least 8 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character.";
    }

    // Validate password confirmation matches
    if (empty($vars["confirmpassword"])) {
        $errors["confirmpassword"] = "You must confirm your password";
    } else if ($vars["confirmpassword"] !== $vars["password"]) {
        $errors["confirmpassword"] = "Passwords do not match";
    }

    // Validate email
    if (empty($vars["email"])) {
        $errors["email"] = "You must provide an email address";
    } else {
        // Split the email to get the domain part
        $emailParts = explode('@', $vars["email"]);
        $domain = strtolower(array_pop($emailParts));

        // Check if domain is in blocked list
        if (in_array($domain, BLOCKED_EMAIL_DOMAINS)) {
            $errors["email"] = "Please signup with your business email address";
        } else {
            // Validate email doesn't exist in WHMCS
            $clients = localAPI("GetClientsDetails", ["email" => $vars["email"]]);
            error_log("WHMCS API response for email validation: " . print_r($clients, true));
            if ($clients["result"] == "success") {
                $errors["email"] = "This Email address is already in use. <a href=\"clientarea.php\" target=\"_top\">Login to Client Area.</a>";
            }
        }
    }

    // if (!in_array($vars["product"], comet_GetPids())) {
    //     $errors["product"] = "Please select a backup plan";
    // }

    return $errors;
}

function validateTurnstile($cfToken)
{
    $secretKey = TURNSTILE_SECRET_KEY;
    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

    // Prepare POST data
    $data = [
        'secret' => $secretKey,
        'response' => $cfToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] 
    ];

    // Using cURL for a POST request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $result = curl_exec($ch);
    if ($result === false) {
        error_log("cURL error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    // Decode the JSON response from Cloudflare
    $responseData = json_decode($result, true);
    logModuleCall(
        'eazybackup',
        'TurnstileSiteVerify',
        ['secret' => $secretKey, 'response' => $cfToken],
        $responseData
    );


    return isset($responseData['success']) && $responseData['success'] === true;
}

function validateRecaptcha($recaptchaResponse)
{
    $secretKey = RECAPTCHA_SECRET_KEY;
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $response = file_get_contents($url . "?secret=" . $secretKey . "&response=" . $recaptchaResponse);
    $responseKeys = json_decode($response, true);

    error_log("reCAPTCHA response from Google: " . print_r($responseKeys, true));

    return intval($responseKeys["success"]) === 1;
}

function eazybackup_validate_reseller(array $vars)
{
    $errors = [];

    // Validate first name, last name, company name
    if (empty($vars["firstname"])) {
        $errors["firstname"] = "You must provide your first name";
    } elseif (!preg_match('/^[a-zA-Z]+$/', $vars["firstname"])) {
        $errors["firstname"] = "First name must contain only letters";
    }

    if (empty($vars["lastname"])) {
        $errors["lastname"] = "You must provide your last name";
    } elseif (!preg_match('/^[a-zA-Z]+$/', $vars["lastname"])) {
        $errors["lastname"] = "Last name must contain only letters";
    }

    if (empty($vars["email"])) {
        $errors["email"] = "You must provide an email address";
    } else {
        // Validate email isn't already a reseller
        $clients = localAPI("GetClientsDetails", ["email" => $vars["email"]]);
        $obcGroupId = eazybackup_getGroupId("OBC");

        if ($clients["result"] == "success") {
            // Verify client is not already a reseller
            if ($clients["client"]["groupid"] == $obcGroupId) {
                $errors["email"] = "This email already belongs to a reseller. <a href=\"clientarea.php\" target=\"_top\">Login to Client Area.</a>";
            } else {
                // Make sure the user owns the account
                $login = localAPI("ValidateLogin", ["email" => $vars["email"], "password2" => $vars["password"]]);
                if ($login["result"] !== "success") {
                    $errors["email"] = "We couldn't match your email and password to an account. If you already have an account, use that email and password.";
                }
            }
        }
    }

    if (empty($vars["phonenumber"])) {
        $errors["phonenumber"] = "Please provide your phone number";
    } else if (!preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/', $vars["phonenumber"])) {
        $errors["phonenumber"] = "Phone number must be in the format: 123-456-7890";
    }


    // Validate password
    if (!comet_ValidateBackupPassword($vars["password"])) {
        $errors["password"] = "Password must be at least 8 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character.";
    }

    // Validate password confirmation matches
    if (empty($vars["confirmpassword"])) {
        $errors["confirmpassword"] = "You must confirm your password";
    } else if ($vars["confirmpassword"] !== $vars["password"]) {
        $errors["confirmpassword"] = "Passwords do not match";
    }

    return $errors;
}

function eazybackup_validate_order(array $vars)
{
    $errors = [];

    // Retrieve the selected product ID
    $product = isset($_POST["product"]) ? $_POST["product"] : null;

    if ($product == "55") {
        // Validation for eazyBackup Management Console (PID = 55)

        // 1. Validate Username
        if (!comet_ValidateBackupUsername($vars["username"])) {
            $errors["username"] = "Username must be at least 6 characters and may contain only a-z, A-Z, 0-9, _, ., -";
        }

        if (!empty($vars["username"])) {
            try {
                comet_Server(["pid" => $vars["product"]])->AdminGetUserProfile($vars["username"]);
                $errors["username"] = "That username is taken, try another";
            } catch (\Exception $e) {
                // Username is available; no action needed
            }
        }

        // 2. Validate Password
        if (!comet_ValidateBackupPassword($vars["password"])) {
            $errors["password"] = "Password must be at least 8 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character.";
        }

        // 3. Validate Password Confirmation
        if (empty($vars["confirmpassword"])) {
            $errors["confirmpassword"] = "You must confirm your password";
        } elseif ($vars["confirmpassword"] !== $vars["password"]) {
            $errors["confirmpassword"] = "Passwords do not match";
        }

        // 4. Validate Backup Location
        if (empty($_POST['company_name'])) {
            $errors['company_name'] = "Backup Location is required.";
        } else {
            $backupLocation = trim($_POST['company_name']);
            // Example: Allow letters, numbers, spaces, hyphens, and underscores
            if (!preg_match('/^[a-zA-Z0-9\s\-_]{3,50}$/', $backupLocation)) {
                $errors['company_name'] = "Backup Location must be between 3 and 50 characters and contain only letters, numbers, spaces, hyphens, and underscores.";
            }
        }

        // 6. Validate Product Name
        if (empty($_POST['product_name'])) {
            $errors['product_name'] = "Product Name is required.";
        } else {
            $productName = trim($_POST['product_name']);
            // Example: Allow letters, numbers, spaces, hyphens, and underscores
            if (!preg_match('/^[a-zA-Z0-9\s\-_]{3,100}$/', $productName)) {
                $errors['product_name'] = "Product Name must be between 3 and 100 characters and contain only letters, numbers, spaces, hyphens, and underscores.";
            }
        }

        // 7. Validate Subdomain (Default Server URL)
        if (empty($_POST['subdomain'])) {
            $errors['subdomain'] = "Default Server URL is required.";
        } else {
            $subdomain = trim($_POST['subdomain']);
            // Subdomain rules: 3-30 characters, letters, numbers, hyphens only
            if (!preg_match('/^[a-zA-Z0-9\-]{3,30}$/', $subdomain)) {
                $errors['subdomain'] = "Subdomain must be between 3 and 30 characters and contain only letters, numbers, and hyphens.";
            }
        }

        // Add any additional validation specific to PID=55 here

    } else {
        // Validation for other products

        // 1. Validate Product Selection
        if (!in_array($_POST["product"], comet_GetPids())) {
            $errors["product"] = "You must choose a valid plan.";
        }

        // 2. Validate Username
        if (!comet_ValidateBackupUsername($vars["username"])) {
            $errors["username"] = "Username must be at least 6 characters and may contain only a-z, A-Z, 0-9, _, ., -";
        }

        if (!empty($vars["username"])) {
            try {
                comet_Server(["pid" => $vars["product"]])->AdminGetUserProfile($vars["username"]);
                $errors["username"] = "That username is taken, try another";
            } catch (\Exception $e) {
                // Username is available; no action needed
            }
        }

        // 3. Validate Password
        if (!comet_ValidateBackupPassword($vars["password"])) {
            $errors["password"] = "Password must be at least 8 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character.";
        }

        // 4. Validate Password Confirmation
        if (empty($vars["confirmpassword"])) {
            $errors["confirmpassword"] = "You must confirm your password";
        } elseif ($vars["confirmpassword"] !== $vars["password"]) {
            $errors["confirmpassword"] = "Passwords do not match";
        }

        // Add any additional validation specific to other products here
    }

    return $errors;
}

function customFileLog($message, $data = null)
{
    $logFilePath = '/var/www/eazybackup.ca/signupform.log';
    $timeStamp = date('Y-m-d H:i:s');
    $logEntry = "{$timeStamp} - {$message}";

    if ($data !== null) {
        if (is_array($data)) {
            $data = json_encode($data);
        }
        $logEntry .= " - Additional Data: {$data}";
    }

    // Append to the log file
    file_put_contents($logFilePath, $logEntry . PHP_EOL, FILE_APPEND);
}

function logErrorDetails($functionName, $vars, $errorMessage, $additionalDetails = [])
{
    // Ensure sensitive information is not logged
    $safeVars = array_filter($vars, function ($key) {
        return !in_array($key, ['password', 'password2', 'servicepassword']); // Exclude sensitive keys
    }, ARRAY_FILTER_USE_KEY);

    // Convert details array to a string if it's not already one
    $detailsString = is_array($additionalDetails) ? json_encode($additionalDetails) : $additionalDetails;

    // Log the error
    logModuleCall(
        "eazybackup",
        $functionName,
        $safeVars, // Pass sanitized vars
        $errorMessage,
        $detailsString
    );
}
