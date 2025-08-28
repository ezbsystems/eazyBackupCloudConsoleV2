<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function cloudstorage_config()
{
    return [
        'name' => 'Cloud Storage',
        'description' => 'This module show the usage of your buckets.',
        'author' => 'eazybackup',
        'language' => 'english',
        'version' => '1.0',
        'fields' => [
            'encryption_key' => [
                'FriendlyName' => 'Encryption Key',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Enter the encryption key.'
            ],
            'redis_host' => [
                'FriendlyName' => 'Redis Host',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Hostname or IP of Redis (for migration status)',
                'Default' => '127.0.0.1'
            ],
            'redis_port' => [
                'FriendlyName' => 'Redis Port',
                'Type' => 'text',
                'Size' => '6',
                'Description' => 'Redis port number',
                'Default' => '6379'
            ],
            'redis_hash' => [
                'FriendlyName' => 'Redis Hash Name',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Redis hash key used to store client→cluster mappings',
                'Default' => 'customer_migration_status'
            ],
            'default_backend_alias' => [
                'FriendlyName' => 'Default Backend Alias',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Cluster alias to use when a client has no explicit mapping',
                'Default' => 'old_ceph_cluster'
            ],
            'migrated_backend_alias' => [
                'FriendlyName' => 'Migrated Backend Alias',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Cluster alias set when using the one-click migration action',
                'Default' => 'new_ceph_cluster'
            ],
        ]
    ];
}

/**
 * Ensure required schema exists when module is already active (upgrade-safe).
 */
function cloudstorage_ensure_schema()
{
    try {
        if (Capsule::schema()->hasTable('s3_bucket_stats_summary')) {
            // Add usage_day if missing
            if (!Capsule::schema()->hasColumn('s3_bucket_stats_summary', 'usage_day')) {
                Capsule::schema()->table('s3_bucket_stats_summary', function ($table) {
                    $table->date('usage_day')->nullable();
                });
                try {
                    Capsule::statement("UPDATE s3_bucket_stats_summary SET usage_day = DATE(created_at) WHERE usage_day IS NULL OR usage_day = '0000-00-00'");
                } catch (\Throwable $e) { /* ignore backfill errors */ }
                try {
                    Capsule::statement("ALTER TABLE s3_bucket_stats_summary MODIFY usage_day DATE NOT NULL");
                } catch (\Throwable $e) { /* some MySQL modes may not allow; ignore */ }
            }
            // Ensure unique index exists (ignore if already there)
            try {
                Capsule::schema()->table('s3_bucket_stats_summary', function ($table) {
                    $table->unique(['user_id', 'bucket_id', 'usage_day'], 'uniq_user_bucket_day');
                });
            } catch (\Throwable $e) { /* index may exist; ignore */ }
        }
    } catch (\Throwable $e) { /* best-effort guard, never block page */ }
}

/**
 * Activate.
 *
 * Called upon activation of the module for the first time.
 * Use this function to perform any database and schema modifications
 * required by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function cloudstorage_activate() {
    if (!Capsule::schema()->hasTable('s3_users')) {
        Capsule::schema()->create('s3_users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('username');
            $table->unsignedInteger('parent_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    if (!Capsule::schema()->hasTable('s3_prices')) {
        Capsule::schema()->create('s3_prices', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
    }

    if (!Capsule::schema()->hasTable('s3_user_access_keys')) {
        Capsule::schema()->create('s3_user_access_keys', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('access_key', 255)->nullable();
            $table->string('secret_key')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
    }
    if (!Capsule::schema()->hasTable('s3_buckets')) {
        Capsule::schema()->create('s3_buckets', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('name');
            $table->string('s3_id');
            $table->enum('versioning', ['off', 'enabled'])->default('off');
            $table->boolean('object_lock_enabled')->default(0);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
    }

    if (!Capsule::schema()->hasTable('s3_bucket_stats')) {
        Capsule::schema()->create('s3_bucket_stats', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('bucket_id');
            $table->unsignedInteger('user_id');
            $table->integer('num_objects')->default(0)->nullable();
            $table->bigInteger('size')->default(0)->nullable();
            $table->bigInteger('size_actual')->default(0)->nullable();
            $table->bigInteger('size_utilized')->default(0)->nullable();
            $table->bigInteger('size_kb')->default(0)->nullable();
            $table->bigInteger('size_kb_actual')->default(0)->nullable();
            $table->bigInteger('size_kb_utilized')->default(0)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
    }

    if (!Capsule::schema()->hasTable('s3_bucket_stats_summary')) {
        Capsule::schema()->create('s3_bucket_stats_summary', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('bucket_id');
            $table->unsignedInteger('user_id');
            $table->bigInteger('total_usage')->default(0)->nullable();
            // Day this usage applies to (facilitates stable daily upserts)
            $table->date('usage_day')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
    }

    // Ensure new column and unique index exist for daily usage summaries
    if (Capsule::schema()->hasTable('s3_bucket_stats_summary')) {
        if (!Capsule::schema()->hasColumn('s3_bucket_stats_summary', 'usage_day')) {
            Capsule::schema()->table('s3_bucket_stats_summary', function ($table) {
                $table->date('usage_day')->nullable();
            });
            // Backfill usage_day from created_at for existing rows
            try {
                Capsule::statement("UPDATE s3_bucket_stats_summary SET usage_day = DATE(created_at) WHERE usage_day IS NULL OR usage_day = '0000-00-00'");
            } catch (\Throwable $e) { /* ignore */ }
        }
        try {
            Capsule::schema()->table('s3_bucket_stats_summary', function ($table) {
                $table->unique(['user_id', 'bucket_id', 'usage_day'], 'uniq_user_bucket_day');
            });
        } catch (\Throwable $e) {
            // Index may already exist; ignore
        }
        // Attempt to enforce NOT NULL on usage_day after backfill
        try {
            Capsule::statement("ALTER TABLE s3_bucket_stats_summary MODIFY usage_day DATE NOT NULL");
        } catch (\Throwable $e) { /* some environments may not permit; ignore */ }
    }

    if (!Capsule::schema()->hasTable('s3_transfer_stats')) {
        Capsule::schema()->create('s3_transfer_stats', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('bucket_id');
            $table->unsignedInteger('user_id');
            $table->bigInteger('bytes_sent')->default(0)->nullable();
            $table->bigInteger('bytes_received')->default(0)->nullable();
            $table->bigInteger('ops')->default(0)->nullable();
            $table->bigInteger('successful_ops')->default(0)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
    }

    if (!Capsule::schema()->hasTable('s3_transfer_stats_summary')) {
        Capsule::schema()->create('s3_transfer_stats_summary', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('bucket_id');
            $table->unsignedInteger('user_id');
            $table->bigInteger('bytes_sent')->default(0)->nullable();
            $table->bigInteger('bytes_received')->default(0)->nullable();
            $table->bigInteger('ops')->default(0)->nullable();
            $table->bigInteger('successful_ops')->default(0)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
    }

    if (!Capsule::schema()->hasTable('s3_delete_buckets')) {
        Capsule::schema()->create('s3_delete_buckets', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('bucket_name');
            $table->tinyInteger('attempt_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
    }

    if (!Capsule::schema()->hasTable('s3_subusers')) {
        Capsule::schema()->create('s3_subusers', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('subuser');
            $table->enum('permission', ['read', 'write', 'readwrite', 'full'])->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
    }

    if (!Capsule::schema()->hasTable('s3_subusers_keys')) {
        Capsule::schema()->create('s3_subusers_keys', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('sub_user_id');
            $table->string('access_key');
            $table->string('secret_key');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('sub_user_id')->references('id')->on('s3_subusers')->onDelete('cascade');
        });
    }

    if (!Capsule::schema()->hasTable('s3_bucket_sizes_history')) {
        Capsule::schema()->create('s3_bucket_sizes_history', function ($table) {
            $table->increments('id');
            $table->string('bucket_name');
            $table->string('bucket_owner');
            $table->bigInteger('bucket_size_bytes')->default(0);
            $table->integer('bucket_object_count')->default(0);
            $table->timestamp('collected_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['bucket_name', 'collected_at']);
            $table->index(['bucket_owner', 'collected_at']);
        });
    }
    // Ensure unique key to prevent duplicates at the same timestamp for same bucket/owner
    if (Capsule::schema()->hasTable('s3_bucket_sizes_history')) {
        try {
            // Add unique index if it does not exist yet
            Capsule::schema()->table('s3_bucket_sizes_history', function ($table) {
                // Some schema managers may not support conditional add; wrap in try/catch at runtime
                $table->unique(['bucket_name', 'bucket_owner', 'collected_at'], 'uniq_bucket_owner_collected');
            });
        } catch (\Throwable $e) {
            // Ignore if already exists
        }
    }

    if (!Capsule::schema()->hasTable('s3_historical_stats')) {
        Capsule::schema()->create('s3_historical_stats', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->date('date'); // Daily date
            $table->bigInteger('total_storage')->default(0)->nullable(); // Current storage snapshot
            $table->bigInteger('bytes_sent')->default(0)->nullable(); // Daily increment
            $table->bigInteger('bytes_received')->default(0)->nullable(); // Daily increment
            $table->bigInteger('operations')->default(0)->nullable(); // Daily increment
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            $table->unique(['user_id', 'date']); // One record per user per day
            $table->index(['date']);
        });
    }

    // ---------------------------------------------------------------------
    // MULTI-CLUSTER SUPPORT: s3_clusters table
    // ---------------------------------------------------------------------
    if (!Capsule::schema()->hasTable('s3_clusters')) {
        Capsule::schema()->create('s3_clusters', function ($table) {
            $table->increments('id');
            $table->string('cluster_name');
            $table->string('cluster_alias')->unique();
            $table->string('s3_endpoint');
            $table->string('admin_access_key');
            $table->string('admin_secret_key');
            $table->boolean('is_default')->default(false);
            // Ensure created_at column exists with default CURRENT_TIMESTAMP
            $table->timestamp('created_at')->useCurrent();
        });
    }

    // Ensure created_at exists on older installs where the column may be missing
    if (Capsule::schema()->hasTable('s3_clusters') && !Capsule::schema()->hasColumn('s3_clusters', 'created_at')) {
        Capsule::schema()->table('s3_clusters', function ($table) {
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    // Application-level enforcement: ensure only one default cluster exists
    try {
        if (Capsule::schema()->hasTable('s3_clusters')) {
            $defaults = Capsule::table('s3_clusters')->where('is_default', 1)->orderBy('created_at', 'desc')->get(['id']);
            if ($defaults && count($defaults) > 1) {
                // Keep the most recent as default, unset others
                $keepId = $defaults->first()->id;
                Capsule::table('s3_clusters')->where('is_default', 1)->where('id', '!=', $keepId)->update(['is_default' => 0]);
            }
        }
    } catch (\Throwable $e) { /* best effort */ }

    // ---------------------------------------------------------------------
    // Migration events audit log
    // ---------------------------------------------------------------------
    if (!Capsule::schema()->hasTable('s3_migration_events')) {
        Capsule::schema()->create('s3_migration_events', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('actor_admin_id')->nullable();
            $table->enum('action', ['freeze','sync','verify','flip','unfreeze','rollback']);
            $table->string('from_alias')->nullable();
            $table->string('to_alias')->nullable();
            // Use TEXT to remain compatible across MySQL versions; store JSON string
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['client_id', 'created_at']);
        });
    }

    // ---------------------------------------------------------------------
    // Access keys state tracking (per client/tenant)
    // ---------------------------------------------------------------------
    if (!Capsule::schema()->hasTable('s3_access_keys')) {
        Capsule::schema()->create('s3_access_keys', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id'); // references s3_users.id
            $table->string('access_key', 255)->unique();
            $table->string('secret_hash')->nullable();
            $table->enum('state', ['active','frozen','revoked'])->default('active');
            $table->string('migrated_to_alias')->nullable();
            $table->timestamp('flipped_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            $table->index(['user_id']);
        });
    }
    // Ensure secret_hash can be NULL in case RGW does not expose secrets when listing keys
    try {
        Capsule::statement("ALTER TABLE s3_access_keys MODIFY secret_hash VARCHAR(255) NULL");
    } catch (\Throwable $e) { /* ignore if not supported or already NULL */ }

    return [
        'status' => 'success'
    ];
}

/**
 * Deactivate.
 *
 * Called upon deactivation of the module.
 * Use this function to undo any database and schema modifications
 * performed by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function cloudstorage_deactivate() {
    return [
        'status' => 'success'
    ];
}

function cloudstorage_clientarea($vars) {
    // Ensure schema migration if module was activated before this column was introduced
    cloudstorage_ensure_schema();

    $page = $_GET['page'];
    $encryptionKey = $vars['encryption_key'];
    $s3Endpoint = $vars['s3_endpoint'];
    $cephAdminUser = $vars['ceph_admin_user'];
    $cephAdminAccessKey = $vars['ceph_access_key'];
    $cephAdminSecretKey = $vars['ceph_secret_key'];

    switch ($page) {
        case 'signup':
            $pagetitle = 'e3 Storage Signup';
            $templatefile = 'templates/signup';
            $vars = [];
            break;

        case 'handlesignup':
            $pagetitle = 'e3 Storage Signup';
            $templatefile = 'signup';
            $vars = require 'pages/handlesignup.php';
            break;

        case 'test':
            $pagetitle = 'Testing';
            $templatefile = 'templates/dashboard';
            $vars = require 'pages/test.php';
            // $vars = require 'storeexistingaccount.php';
            die('wait test');
            break;

        case 'dashboard':
            $pagetitle = 'Storage Dashboard';
            $templatefile = 'templates/dashboard';
            $vars = require 'pages/dashboard.php';
            break;

        case 'denied':
            $pagetitle = 'Access Denied';
            $templatefile = 'templates/denied';
            $vars = [
                'error' => isset($_GET['msg']) ? (string)$_GET['msg'] : 'You do not have permission to access e3 Object Storage.',
            ];
            break;

        case 'access_keys':
            $pagetitle = 'Access Keys';
            $templatefile = 'templates/access_keys';
            $vars = require 'pages/access_keys.php';
            break;

        case 'buckets':
            $pagetitle = 'Buckets';
            $templatefile = 'templates/buckets';
            $vars = require 'pages/buckets.php';
            break;

        case 'billing':
            $pagetitle = "Billing";
            $templatefile = 'templates/billing';
            $vars = require 'pages/billing.php';
            break;

        case 'history':
            $pagetitle = "Usage History";
            $templatefile = 'templates/history';
            $vars = require 'pages/history.php';
            break;

        case 'browse':
            $pagetitle = "Browse Bucket";
            $templatefile = 'templates/browse';
            $vars = require 'pages/browse.php';
            break;

        case 'subusers':
            $pagetitle = "Sub Users";
            $templatefile = 'templates/subuser';
            $vars = require 'pages/subusers.php';
            break;

        case 'users':
            $pagetitle = "Users";
            $templatefile = 'templates/users';
            $vars = require 'pages/users.php';
            break;

        case 'savebucket':
            require 'pages/savebucket.php';
            break;

        case 'services':
            $pagetitle = "e3 Cloud Storage";
            $templatefile = 'templates/services';
            $vars = require 'pages/services.php';
            break;

        case 'deletebucket':
            require 'pages/deletebucket.php';
            break;

        default:
            $pagetitle = 'S3 Storage';
            $templatefile = 'templates/s3storage';
            $vars = require 'pages/s3storage.php';
            break;
    }

    if (isset($_SESSION['message'])) {
        $vars['message'] = $_SESSION['message'];
        unset($_SESSION['message']);
    }

    return [
        'pagetitle' => $pagetitle,
        'templatefile' => $templatefile,
        'vars' => $vars,
    ];
}

/**
 * Admin Area Output.
 *
 * Called when the addon module is accessed via the admin area.
 * Should return HTML output for display to the admin user.
 *
 * @param array $vars Common variables
 *
 * @return string
 */
function cloudstorage_output($vars)
{
    $action = $_REQUEST['action'] ?? 'bucket_monitor';

    // Handle the AJAX request
    if ($action === 'ajax') {
        require_once __DIR__ . '/ajax.php';
        exit; // Stop execution to prevent rendering the admin page
    }
    
    switch ($action) {
        case 'migration_events':
            require_once __DIR__ . '/pages/admin/migration_events.php';
            echo cloudstorage_admin_migration_events($vars);
            break;
        case 'cluster_manager':
            require_once __DIR__ . '/pages/admin/cluster_manager.php';
            echo cloudstorage_admin_cluster_manager($vars);
            break;

        case 'migration_manager':
            require_once __DIR__ . '/pages/admin/migration_manager.php';
            echo cloudstorage_admin_migration_manager($vars);
            break;

        case 'bucket_monitor':
        default:
            require_once __DIR__ . '/pages/admin/bucket_monitor.php';
            echo cloudstorage_admin_bucket_monitor($vars);
            break;
    }
}

/**
 * Admin Area Sidebar.
 *
 * @param array $vars Common variables
 *
 * @return string
 */
function cloudstorage_sidebar($vars)
{
    $sidebar = '<div class="list-group">'
        . '<a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=bucket_monitor" class="list-group-item">'
            . '<i class="fa fa-database"></i> Bucket Monitor'
        . '</a>'
        . '<a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=cluster_manager" class="list-group-item">'
            . '<i class="fa fa-server"></i> Cluster Manager'
        . '</a>'
        . '<a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=migration_manager" class="list-group-item">'
            . '<i class="fa fa-exchange-alt"></i> Migration Manager'
        . '</a>'
        . '<a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=migration_events" class="list-group-item">'
            . '<i class="fa fa-history"></i> Migration Events'
        . '</a>'
        . '</div>';
    
    return $sidebar;
}

