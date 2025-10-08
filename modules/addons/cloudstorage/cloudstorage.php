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
            's3_region' => [
                'FriendlyName' => 'S3 Region',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Enter the AWS region string to use for signing (e.g., ca-central-1). Defaults to us-east-1 if empty.'
            ],
            'encryption_key' => [
                'FriendlyName' => 'Encryption Key',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Enter the encryption key.'
            ],
            's3_endpoint' => [
                'FriendlyName' => 'S3 Endpoint',
                'Type' => 'text',
                'Size' => '250',
                'Description' => 'Enter the S3 endpoint.'
            ],
            'ceph_server_ip' => [
                'FriendlyName' => 'Ceph Server Ip',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Enter the Ceph server ip.'
            ],
            'ceph_admin_user' => [
                'FriendlyName' => 'Ceph Admin User',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Enter the Ceph admin user.'
            ],
            'ceph_access_key' => [
                'FriendlyName' => 'Ceph Access Key',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Enter the Ceph access key.'
            ],
            'ceph_secret_key' => [
                'FriendlyName' => 'Ceph Secret Key',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Enter the Ceph secret key.'
            ]
        ]
    ];
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
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
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
    // Clean any output buffer to prevent language files from outputting
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $action = $_REQUEST['action'] ?? 'bucket_monitor';
    
    switch ($action) {
        case 'bucket_monitor':
        default:
            require_once __DIR__ . '/pages/admin/bucket_monitor.php';
            cloudstorage_admin_bucket_monitor($vars);
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
    $sidebar = '<div class="list-group">
        <a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=bucket_monitor" class="list-group-item">
            <i class="fa fa-database"></i> Bucket Monitor
        </a>
    </div>';
    
    return $sidebar;
}

