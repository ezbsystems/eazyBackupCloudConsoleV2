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
use Illuminate\Database\Schema\Blueprint;
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

// Prefer Composer autoload if present, then fall back to Comet server module vendor, then WHMCS root
$__addonAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($__addonAutoload)) {
    require_once $__addonAutoload;
    // Ensure Composer class maps are up to date (for PartnerHub namespace)
    if (class_exists('Composer\\Autoload\\ClassLoader')) {
        try { $loader = require __DIR__ . '/vendor/autoload.php'; if ($loader instanceof Composer\Autoload\ClassLoader) { $loader->register(true); } } catch (\Throwable $__) { /* ignore */ }
    }
}
// If CometAPI isn't available yet, try the Comet server module's vendor autoloader
if (!class_exists('CometAPI')) {
    $__cometServerAutoload = dirname(__DIR__, 2) . '/servers/comet/vendor/autoload.php';
    if (is_file($__cometServerAutoload)) { require_once $__cometServerAutoload; }
}
// As a last resort, try the WHMCS root vendor
if (!class_exists('CometAPI')) {
    $__whmcsRootAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
    if (is_file($__whmcsRootAutoload)) { require_once $__whmcsRootAutoload; }
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

// PSR-4 autoload for EazyBackup\Whitelabel services
spl_autoload_register(function ($class) {
    $prefix  = 'EazyBackup\\Whitelabel\\';
    $baseDir = __DIR__ . '/lib/Whitelabel/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) { return; }
    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) { require_once $file; }
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

    // Track client -> product group mapping for white-label products
    if (!Capsule::schema()->hasTable('tbl_client_productgroup_map')) {
        Capsule::schema()->create('tbl_client_productgroup_map', function ($table) {
            $table->increments('id');
            $table->integer('client_id')->index();
            $table->integer('product_group_id')->index();
            $table->dateTime('created_at')->nullable();
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
            $table->bigInteger('total_bytes')->nullable();
            $table->string('bucket_server');
            $table->string('bucket_name');
            $table->string('bucket_key');
            $table->boolean('has_storage_limit');
            $table->bigInteger('storage_limit_bytes');
            $table->boolean('is_active')->default(1)->index();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('removed_at')->nullable()->index();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unique('id'); // enables ON DUPLICATE KEY UPDATE
            $table->index(['username', 'id'], 'idx_user_vault');
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

    // Dashboard rollups: per-client devices trend (registered + online)
    Capsule::statement("CREATE TABLE IF NOT EXISTS eb_devices_client_daily (\n  d           DATE NOT NULL,\n  client_id   INT NOT NULL,\n  registered  INT NOT NULL DEFAULT 0,\n  online      INT NOT NULL DEFAULT 0,\n  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  PRIMARY KEY (d, client_id),\n  KEY idx_client (client_id),\n  KEY idx_d (d)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Billing rollups: protected item mix snapshot
    Capsule::statement("CREATE TABLE IF NOT EXISTS eb_items_daily (\n  d           DATE PRIMARY KEY,\n  di_devices  INT NOT NULL,\n  hv_vms      INT NOT NULL,\n  vw_vms      INT NOT NULL,\n  m365_users  INT NOT NULL,\n  ff_items    INT NOT NULL,\n  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Device grouping (Phase 1): client-scoped groups + per-device single assignment (Ungrouped is implicit)
    try {
        if (!Capsule::schema()->hasTable('eb_device_groups')) {
            Capsule::schema()->create('eb_device_groups', function ($table) {
                $table->bigIncrements('id');
                $table->integer('client_id')->index();
                $table->string('name', 191);
                $table->string('name_norm', 191);
                $table->string('color', 32)->nullable();
                $table->string('icon', 32)->nullable();
                $table->integer('sort_order')->default(0)->index();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->unique(['client_id', 'name_norm'], 'uniq_client_group_name');
            });
        }
    } catch (\Throwable $e) { /* ignore */ }

    try {
        if (!Capsule::schema()->hasTable('eb_device_group_assignments')) {
            Capsule::schema()->create('eb_device_group_assignments', function ($table) {
                $table->integer('client_id')->index();
                $table->string('device_id', 255);
                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->primary(['client_id', 'device_id'], 'pk_client_device');
                $table->index(['client_id', 'group_id'], 'idx_client_group');
            });
        }
    } catch (\Throwable $e) { /* ignore */ }

        // Create announcement dismissals table for client/user scoped dismissals
        try {
        Capsule::statement("CREATE TABLE IF NOT EXISTS mod_eazybackup_dismissals (\n  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n  user_id INT UNSIGNED NULL,\n  client_id INT UNSIGNED NULL,\n  announcement_key VARCHAR(191) NOT NULL,\n  dismissed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  UNIQUE KEY uniq_user_announcement (user_id, announcement_key),\n  UNIQUE KEY uniq_client_announcement (client_id, announcement_key),\n  KEY idx_announcement_key (announcement_key)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (\Throwable $e) { /* ignore */ }

        // Consolidated billing preference table (client-scoped)
        try {
        Capsule::statement("CREATE TABLE IF NOT EXISTS mod_eazy_consolidated_billing (\n  clientid INT UNSIGNED NOT NULL PRIMARY KEY,\n  enabled TINYINT(1) NOT NULL DEFAULT 0,\n  dom TINYINT UNSIGNED NOT NULL DEFAULT 1,\n  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Toronto',\n  effective_from DATE NULL,\n  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,\n  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  KEY idx_enabled (enabled)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (\Throwable $e) { /* ignore */ }

        // Ensure per-config option discount table exists and is up to date
        try {
            if (!Capsule::schema()->hasTable('mod_rd_discountConfigOptions')) {
                Capsule::schema()->create('mod_rd_discountConfigOptions', function ($table) {
                    $table->increments('id');
                    $table->integer('serviceid');
                    $table->integer('configoptionid');
                    $table->double('discount_price', 8, 2)->default(0);
                    $table->double('price', 8, 2)->default(0);
                    $table->timestamp('created_at')->default(Capsule::raw("CURRENT_TIMESTAMP"));
                    $table->timestamp('updated_at')->default(Capsule::raw("CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"));
                    $table->unique(['serviceid', 'configoptionid'], 'uniq_service_config');
                });
            } else {
                // Migrate legacy column name if present
                if (Capsule::schema()->hasColumn('mod_rd_discountConfigOptions', 'discount_per')) {
                    try {
                        Capsule::statement("ALTER TABLE mod_rd_discountConfigOptions CHANGE COLUMN discount_per discount_price DOUBLE(8,2)");
                    } catch (\Throwable $e) { /* ignore */ }
                }
                // Add missing columns
                Capsule::schema()->table('mod_rd_discountConfigOptions', function ($table) {
                    if (!Capsule::schema()->hasColumn('mod_rd_discountConfigOptions', 'discount_price')) {
                        $table->double('discount_price', 8, 2)->default(0);
                    }
                    if (!Capsule::schema()->hasColumn('mod_rd_discountConfigOptions', 'price')) {
                        $table->double('price', 8, 2)->default(0);
                    }
                    if (!Capsule::schema()->hasColumn('mod_rd_discountConfigOptions', 'created_at')) {
                        $table->timestamp('created_at')->default(Capsule::raw("CURRENT_TIMESTAMP"));
                    }
                    if (!Capsule::schema()->hasColumn('mod_rd_discountConfigOptions', 'updated_at')) {
                        $table->timestamp('updated_at')->default(Capsule::raw("CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"));
                    }
                });
                // Add unique index if missing
                $hasIndex = Capsule::selectOne("
                    SELECT 1 FROM information_schema.STATISTICS
                    WHERE table_schema = DATABASE()
                      AND table_name = 'mod_rd_discountConfigOptions'
                      AND index_name = 'uniq_service_config'
                    LIMIT 1
                ");
                if (!$hasIndex) {
                    try {
                        Capsule::schema()->table('mod_rd_discountConfigOptions', function ($table) {
                            $table->unique(['serviceid', 'configoptionid'], 'uniq_service_config');
                        });
                    } catch (\Throwable $e) { /* ignore */ }
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal: log and continue
            try { logActivity('eazybackup_activate: mod_rd_discountConfigOptions ensure failed: ' . $e->getMessage()); } catch (\Throwable $__) {}
        }

        // Keep your original create logic for brand-new installs…
        eazybackup_migrate_schema(); // …and make sure existing installs get patched too.
        // Attempt to restore saved addon settings (if any) so config survives deactivate/activate cycles
        try { eb_restore_settings(); } catch (\Throwable $e) { /* ignore */ }
        return ['status' => 'success'];
}

/**
 * Module upgrade handler: add minimal indexes used by admin power panel queries.
 */
function eazybackup_upgrade($vars = [])
{
    
    eazybackup_migrate_schema();

   
    try { Capsule::statement("ALTER TABLE comet_vaults ADD INDEX idx_bucket_server (bucket_server)"); } catch (\Throwable $e) {}
    try { Capsule::statement("ALTER TABLE comet_vaults ADD INDEX idx_username (username)"); } catch (\Throwable $e) {}
    try { Capsule::statement("ALTER TABLE comet_vaults ADD INDEX idx_type (type)"); } catch (\Throwable $e) {}
    try { Capsule::statement("ALTER TABLE comet_vaults ADD INDEX idx_user_server_type (username, bucket_server, type)"); } catch (\Throwable $e) {}

    try {
        Capsule::statement("CREATE TABLE IF NOT EXISTS comet_server_aliases (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            server_id INT UNSIGNED NOT NULL,
            alias_host VARCHAR(255) NOT NULL,
            UNIQUE KEY uniq_server_alias (server_id, alias_host),
            KEY idx_server (server_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\Throwable $e) {}

    // Ensure announcement dismissals table and indexes exist on upgrade
    try {
        Capsule::statement("CREATE TABLE IF NOT EXISTS mod_eazybackup_dismissals (\n  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n  user_id INT UNSIGNED NULL,\n  client_id INT UNSIGNED NULL,\n  announcement_key VARCHAR(191) NOT NULL,\n  dismissed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  UNIQUE KEY uniq_user_announcement (user_id, announcement_key),\n  UNIQUE KEY uniq_client_announcement (client_id, announcement_key),\n  KEY idx_announcement_key (announcement_key)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (\Throwable $e) {}
    try { Capsule::statement("CREATE UNIQUE INDEX uniq_user_announcement ON mod_eazybackup_dismissals (user_id, announcement_key)"); } catch (\Throwable $e) {}
    try { Capsule::statement("CREATE UNIQUE INDEX uniq_client_announcement ON mod_eazybackup_dismissals (client_id, announcement_key)"); } catch (\Throwable $e) {}
    try { Capsule::statement("CREATE INDEX idx_announcement_key ON mod_eazybackup_dismissals (announcement_key)"); } catch (\Throwable $e) {}
}

/**
 * Module deactivate handler: back up current addon settings so they persist across deactivation.
 */
function eazybackup_deactivate()
{
    try { eb_backup_settings(); } catch (\Throwable $e) { /* ignore */ }
    return ['status' => 'success'];
}

function eb_add_column_if_missing(string $table, string $column, callable $definition): void {
    $schema = Capsule::schema();
    if (!$schema->hasTable($table)) return; // creator handles create path
    if ($schema->hasColumn($table, $column)) return;
    $schema->table($table, function (Blueprint $t) use ($column, $definition) {
        $definition($t); 
    });
}

function eb_add_index_if_missing(string $table, string $indexSql): void {
    try {
        Capsule::connection()->statement($indexSql);
    } catch (\Throwable $e) {
        
    }
}

/** Create or patch all addon tables */
function eazybackup_migrate_schema(): void {
    $schema = Capsule::schema();

    // --- Device grouping (Phase 1) ---
    try {
        if (!$schema->hasTable('eb_device_groups')) {
            $schema->create('eb_device_groups', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->integer('client_id')->index();
                $t->string('name', 191);
                $t->string('name_norm', 191);
                $t->string('color', 32)->nullable();
                $t->string('icon', 32)->nullable();
                $t->integer('sort_order')->default(0)->index();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $t->unique(['client_id', 'name_norm'], 'uniq_client_group_name');
            });
        }
    } catch (\Throwable $e) { /* ignore */ }

    try {
        if (!$schema->hasTable('eb_device_group_assignments')) {
            $schema->create('eb_device_group_assignments', function (Blueprint $t) {
                $t->integer('client_id')->index();
                $t->string('device_id', 255);
                $t->unsignedBigInteger('group_id')->nullable()->index();
                $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $t->primary(['client_id', 'device_id'], 'pk_client_device');
                $t->index(['client_id', 'group_id'], 'idx_client_group');
            });
        }
    } catch (\Throwable $e) { /* ignore */ }

    // --- comet_devices ---
    if (!$schema->hasTable('comet_devices')) {
        $schema->create('comet_devices', function (Blueprint $t) {
            $t->string('id', 255);
            $t->integer('client_id')->nullable()->index();
            $t->string('username', 255)->nullable()->index();
            $t->string('hash', 255);
            $t->json('content')->nullable();
            $t->string('name', 255)->nullable()->index();
            $t->string('platform_os', 32)->default('');
            $t->string('platform_arch', 32)->default('');
            $t->boolean('is_active')->default(0);
            $t->timestamp('created_at')->nullable();
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $t->timestamp('revoked_at')->nullable();
            $t->primary('hash');
            $t->unique(['hash','client_id']);
        });
    } else {
        eb_add_column_if_missing('comet_devices','platform_os', fn(Blueprint $t)=>$t->string('platform_os',32)->default(''));
        eb_add_column_if_missing('comet_devices','platform_arch',fn(Blueprint $t)=>$t->string('platform_arch',32)->default(''));
        eb_add_column_if_missing('comet_devices','is_active',   fn(Blueprint $t)=>$t->boolean('is_active')->default(0));
        eb_add_column_if_missing('comet_devices','revoked_at',  fn(Blueprint $t)=>$t->timestamp('revoked_at')->nullable());
        eb_add_column_if_missing('comet_devices','content',     fn(Blueprint $t)=>$t->json('content')->nullable());
        eb_add_index_if_missing('comet_devices', "CREATE INDEX IF NOT EXISTS idx_devices_client ON comet_devices (client_id)");
        eb_add_index_if_missing('comet_devices', "CREATE INDEX IF NOT EXISTS idx_devices_user   ON comet_devices (username)");
    }

    // --- comet_items ---
    if (!$schema->hasTable('comet_items')) {
        $schema->create('comet_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->integer('client_id')->index();
            $t->string('username')->nullable()->index();
            $t->json('content')->nullable();                 // <-- json, not jsonb
            $t->string('owner_device',128)->nullable()->index();   // raw Comet DeviceID
            $t->string('comet_device_id',64)->default('')->index(); // derived SHA-256 hex
            $t->string('name')->index();
            $t->string('type')->index();
            $t->bigInteger('total_bytes')->nullable();
            $t->bigInteger('total_files')->nullable();
            $t->bigInteger('total_directories')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
    } else {
        eb_add_column_if_missing('comet_items','content',         fn(Blueprint $t)=>$t->json('content')->nullable());
        eb_add_column_if_missing('comet_items','owner_device',    fn(Blueprint $t)=>$t->string('owner_device',128)->nullable()->index());
        eb_add_column_if_missing('comet_items','comet_device_id', fn(Blueprint $t)=>$t->string('comet_device_id',64)->default('')->index());
        eb_add_column_if_missing('comet_items','total_bytes',     fn(Blueprint $t)=>$t->bigInteger('total_bytes')->nullable());
        eb_add_column_if_missing('comet_items','total_files',     fn(Blueprint $t)=>$t->bigInteger('total_files')->nullable());
        eb_add_column_if_missing('comet_items','total_directories',fn(Blueprint $t)=>$t->bigInteger('total_directories')->nullable());
        eb_add_index_if_missing('comet_items',"CREATE INDEX IF NOT EXISTS idx_client_user ON comet_items (client_id, username)");
    }

    // --- comet_vaults ---
    if (!$schema->hasTable('comet_vaults')) {
        $schema->create('comet_vaults', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->integer('client_id')->index();
            $t->string('username')->nullable()->index();
            $t->json('content')->nullable();
            $t->string('name')->index();
            $t->smallInteger('type')->index();
            $t->bigInteger('total_bytes')->nullable();
            $t->string('bucket_server');
            $t->string('bucket_name');
            $t->string('bucket_key');
            $t->boolean('has_storage_limit');
            $t->bigInteger('storage_limit_bytes');
            $t->boolean('is_active')->default(1)->index();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('updated_at')->nullable();
            $t->timestamp('removed_at')->nullable()->index();
            $t->timestamp('last_success_at')->nullable();
            $t->text('last_error')->nullable();
            $t->unique('id');
            $t->index(['username','id'], 'idx_user_vault');
        });
    } else {
        eb_add_column_if_missing('comet_vaults','is_active',  fn(Blueprint $t)=>$t->boolean('is_active')->default(1));
        eb_add_column_if_missing('comet_vaults','removed_at', fn(Blueprint $t)=>$t->timestamp('removed_at')->nullable());
        // Make total_bytes nullable on existing installs
        try { Capsule::statement("ALTER TABLE comet_vaults MODIFY total_bytes BIGINT NULL"); } catch (\Throwable $e) { /* ignore */ }
        // Freshness columns
        eb_add_column_if_missing('comet_vaults','last_success_at', fn(Blueprint $t)=>$t->timestamp('last_success_at')->nullable());
        eb_add_column_if_missing('comet_vaults','last_error', fn(Blueprint $t)=>$t->text('last_error')->nullable());
        eb_add_index_if_missing('comet_vaults', "CREATE INDEX IF NOT EXISTS idx_user_vault ON comet_vaults (username, id)");
        eb_add_index_if_missing('comet_vaults', "CREATE INDEX IF NOT EXISTS idx_vault_active ON comet_vaults (is_active)");
    }

    // --- eb_event_cursor ---
    if (!$schema->hasTable('eb_event_cursor')) {
        $schema->create('eb_event_cursor', function (Blueprint $t) {
            $t->string('source',128)->primary();
            $t->unsignedInteger('last_ts');
            $t->string('last_id',128)->nullable();
        });
    }

    // --- eb_nfr --- (applications + grants)
    if (!$schema->hasTable('eb_nfr')) {
        $schema->create('eb_nfr', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('client_id');
            $t->unsignedInteger('product_id')->nullable();
            $t->unsignedInteger('service_id')->nullable();
            $t->string('service_username', 255)->nullable();
            $t->string('requested_username', 255)->nullable();
            $t->string('requested_password', 255)->nullable();
            $t->enum('status', ['pending','approved','provisioned','rejected','suspended','expired','converted','cancelled'])->default('pending');
            $t->string('company_name', 255);
            $t->string('contact_name', 255)->nullable();
            $t->string('job_title', 255)->nullable();
            $t->string('work_email', 255);
            $t->string('phone', 64)->nullable();
            $t->text('markets')->nullable();
            $t->text('use_cases')->nullable();
            $t->text('platforms')->nullable();
            $t->text('virtualization')->nullable();
            $t->boolean('disk_image')->default(0);
            $t->unsignedInteger('requested_quota_gib')->nullable();
            $t->unsignedInteger('approved_quota_gib')->nullable();
            $t->enum('overage', ['block','allow_notice'])->default('block');
            $t->unsignedInteger('device_cap')->nullable();
            $t->unsignedInteger('duration_days')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamp('end_reminder_sent_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $t->index('client_id', 'idx_nfr_client');
            $t->index('status', 'idx_nfr_status');
            $t->index('service_id', 'idx_nfr_service');
        });
    } else {
        // Ensure key columns and indexes exist if upgrading from early draft
        try { Capsule::statement("ALTER TABLE eb_nfr ADD INDEX idx_nfr_client (client_id)"); } catch (\Throwable $e) {}
        try { Capsule::statement("ALTER TABLE eb_nfr ADD INDEX idx_nfr_status (status)"); } catch (\Throwable $e) {}
        try { Capsule::statement("ALTER TABLE eb_nfr ADD INDEX idx_nfr_service (service_id)"); } catch (\Throwable $e) {}
        try { Capsule::statement("ALTER TABLE eb_nfr ADD COLUMN requested_username VARCHAR(255) NULL"); } catch (\Throwable $e) {}
        try { Capsule::statement("ALTER TABLE eb_nfr ADD COLUMN requested_password VARCHAR(255) NULL"); } catch (\Throwable $e) {}
        try { Capsule::statement("ALTER TABLE eb_nfr ADD COLUMN end_reminder_sent_at TIMESTAMP NULL"); } catch (\Throwable $e) {}
    }

    // --- eb_jobs_live ---
    if (!$schema->hasTable('eb_jobs_live')) {
        $schema->create('eb_jobs_live', function (Blueprint $t) {
            $t->string('server_id',64);
            $t->string('job_id',64);
            $t->string('username',255)->default('');
            $t->string('device',255)->default('');
            $t->string('job_type',64)->default('');
            $t->unsignedInteger('started_at');
            $t->unsignedBigInteger('bytes_done')->default(0);
            $t->unsignedBigInteger('throughput_bps')->default(0);
            $t->unsignedInteger('last_update');
            $t->bigInteger('last_bytes')->default(0);
            $t->integer('last_bytes_ts')->default(0);
            $t->tinyInteger('cancel_attempts')->default(0);
            $t->integer('last_checked_ts')->default(0);
            $t->primary(['server_id','job_id']);
            $t->index('last_update','idx_last_update');
        });
    } else {
        eb_add_column_if_missing('eb_jobs_live','last_bytes',       fn(Blueprint $t)=>$t->bigInteger('last_bytes')->default(0));
        eb_add_column_if_missing('eb_jobs_live','last_bytes_ts',    fn(Blueprint $t)=>$t->integer('last_bytes_ts')->default(0));
        eb_add_column_if_missing('eb_jobs_live','cancel_attempts',  fn(Blueprint $t)=>$t->tinyInteger('cancel_attempts')->default(0));
        eb_add_column_if_missing('eb_jobs_live','last_checked_ts',  fn(Blueprint $t)=>$t->integer('last_checked_ts')->default(0));
    }

    // --- eb_jobs_recent_24h ---
    if (!$schema->hasTable('eb_jobs_recent_24h')) {
        $schema->create('eb_jobs_recent_24h', function (Blueprint $t) {
            $t->string('server_id',64);
            $t->string('job_id',64);
            $t->string('username',255)->default('');
            $t->string('device',255)->default('');
            $t->string('job_type',64)->default('');
            $t->string('status',16)->default('success'); // success|warning|error|missed|abandoned|unknown
            $t->unsignedBigInteger('bytes')->default(0);
            $t->unsignedInteger('duration_sec')->default(0);
            $t->unsignedInteger('ended_at')->default(0);
            $t->primary(['server_id','job_id']);
        });
    }

    // --- eb_devices_registry (if you use it) ---
    if (!$schema->hasTable('eb_devices_registry')) {
        $schema->create('eb_devices_registry', function (Blueprint $t) {
            $t->string('server_id',64);
            $t->string('device_id',128);
            $t->string('username',255)->default('');
            $t->string('friendly_name',255)->default('');
            $t->string('platform_os',32)->default('');
            $t->string('platform_arch',32)->default('');
            $t->unsignedInteger('registered_at');
            $t->unsignedInteger('last_seen')->default(0);
            $t->string('status',16)->default('active');
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $t->primary(['server_id','device_id']);
            $t->index('username','idx_username');
            $t->index('status','idx_status');
            $t->index('last_seen','idx_last_seen');
        });
    }

    // ---------- eb_items_daily (optional nightly rollup) ----------
    if (!$schema->hasTable('eb_items_daily')) {
        $schema->create('eb_items_daily', function (Blueprint $t) {
            $t->date('d')->primary();
            $t->unsignedInteger('di_devices')->default(0);
            $t->unsignedInteger('hv_vms')->default(0);
            $t->unsignedInteger('vw_vms')->default(0);
            $t->unsignedInteger('m365_users')->default(0);
            $t->unsignedInteger('ff_items')->default(0);
        });
    }
    // Used by rollup_items_daily.php as per README. :contentReference[oaicite:8]{index=8}

    // ---------- eb_devices_client_daily (per-client devices trend rollup) ----------
    if (!$schema->hasTable('eb_devices_client_daily')) {
        $schema->create('eb_devices_client_daily', function (Blueprint $t) {
            $t->date('d');
            $t->integer('client_id');
            $t->unsignedInteger('registered')->default(0);
            $t->unsignedInteger('online')->default(0);
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $t->primary(['d', 'client_id']);
            $t->index('client_id', 'idx_client');
            $t->index('d', 'idx_d');
        });
    }

    // ---------- eb_storage_daily (hourly rollup of per-user vault usage) ----------
    if (!$schema->hasTable('eb_storage_daily')) {
        $schema->create('eb_storage_daily', function (Blueprint $t) {
            $t->date('d');
            $t->integer('client_id')->default(0);
            $t->string('username', 255);
            $t->unsignedBigInteger('bytes_total')->default(0);
            $t->unsignedBigInteger('bytes_t1000')->default(0);
            $t->unsignedBigInteger('bytes_t1003')->default(0);
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $t->primary(['d','client_id','username']);
            $t->index('client_id', 'idx_client');
            $t->index('username', 'idx_username');
            $t->index('d', 'idx_date');
        });
    }

    // --- mod_eazy_consolidated_billing ---
    if (!$schema->hasTable('mod_eazy_consolidated_billing')) {
        $schema->create('mod_eazy_consolidated_billing', function (Blueprint $t) {
            $t->integer('clientid')->unsigned();
            $t->tinyInteger('enabled')->default(0);
            $t->tinyInteger('dom')->unsigned()->default(1);
            $t->string('timezone', 64)->default('America/Toronto');
            $t->date('effective_from')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->primary('clientid');
            $t->index('enabled', 'idx_enabled');
        });
    } else {
        eb_add_column_if_missing('mod_eazy_consolidated_billing','enabled',       fn(Blueprint $t)=>$t->tinyInteger('enabled')->default(0));
        eb_add_column_if_missing('mod_eazy_consolidated_billing','dom',           fn(Blueprint $t)=>$t->tinyInteger('dom')->unsigned()->default(1));
        eb_add_column_if_missing('mod_eazy_consolidated_billing','timezone',      fn(Blueprint $t)=>$t->string('timezone',64)->default('America/Toronto'));
        eb_add_column_if_missing('mod_eazy_consolidated_billing','effective_from',fn(Blueprint $t)=>$t->date('effective_from')->nullable());
        eb_add_column_if_missing('mod_eazy_consolidated_billing','created_at',    fn(Blueprint $t)=>$t->timestamp('created_at')->nullable()->useCurrent());
        eb_add_column_if_missing('mod_eazy_consolidated_billing','updated_at',    fn(Blueprint $t)=>$t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate());
        eb_add_index_if_missing('mod_eazy_consolidated_billing', "CREATE INDEX IF NOT EXISTS idx_enabled ON mod_eazy_consolidated_billing (enabled)");
    }

    // --- eazybackup_trial_verifications (trial email verification tokens) ---
    if (!$schema->hasTable('eazybackup_trial_verifications')) {
        $schema->create('eazybackup_trial_verifications', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('client_id')->index();
            $t->string('email', 255)->index();
            $t->string('token', 191)->unique('uq_trial_token');
            $t->json('meta')->nullable(); // username, productId, phone, etc.
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamp('consumed_at')->nullable()->index();
        });
    } else {
        // Ensure important indexes exist on upgrade
        eb_add_index_if_missing('eazybackup_trial_verifications', "CREATE UNIQUE INDEX IF NOT EXISTS uq_trial_token ON eazybackup_trial_verifications (token)");
        eb_add_index_if_missing('eazybackup_trial_verifications', "CREATE INDEX IF NOT EXISTS idx_trial_client ON eazybackup_trial_verifications (client_id)");
        eb_add_index_if_missing('eazybackup_trial_verifications', "CREATE INDEX IF NOT EXISTS idx_trial_email ON eazybackup_trial_verifications (email)");
        eb_add_index_if_missing('eazybackup_trial_verifications', "CREATE INDEX IF NOT EXISTS idx_trial_expires ON eazybackup_trial_verifications (expires_at)");
        eb_add_index_if_missing('eazybackup_trial_verifications', "CREATE INDEX IF NOT EXISTS idx_trial_consumed ON eazybackup_trial_verifications (consumed_at)");
    }

    // --- eb_client_notify_prefs (per-client notification preferences) ---
    if (!$schema->hasTable('eb_client_notify_prefs')) {
        $schema->create('eb_client_notify_prefs', function (Blueprint $t) {
            $t->integer('client_id')->unsigned();
            $t->tinyInteger('notify_storage')->default(1);
            $t->tinyInteger('notify_devices')->default(1);
            $t->tinyInteger('notify_addons')->default(1);
            $t->string('routing_policy', 16)->default('billing'); // primary|billing|technical|custom
            $t->text('custom_recipients')->nullable();
            $t->tinyInteger('show_upcoming_charges')->default(1);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->primary('client_id');
            $t->index('routing_policy','idx_notify_route');
        });
    } else {
        eb_add_column_if_missing('eb_client_notify_prefs','notify_storage',         fn(Blueprint $t)=>$t->tinyInteger('notify_storage')->default(1));
        eb_add_column_if_missing('eb_client_notify_prefs','notify_devices',         fn(Blueprint $t)=>$t->tinyInteger('notify_devices')->default(1));
        eb_add_column_if_missing('eb_client_notify_prefs','notify_addons',          fn(Blueprint $t)=>$t->tinyInteger('notify_addons')->default(1));
        eb_add_column_if_missing('eb_client_notify_prefs','routing_policy',         fn(Blueprint $t)=>$t->string('routing_policy',16)->default('billing'));
        eb_add_column_if_missing('eb_client_notify_prefs','custom_recipients',      fn(Blueprint $t)=>$t->text('custom_recipients')->nullable());
        eb_add_column_if_missing('eb_client_notify_prefs','show_upcoming_charges',  fn(Blueprint $t)=>$t->tinyInteger('show_upcoming_charges')->default(1));
        eb_add_index_if_missing('eb_client_notify_prefs', "CREATE INDEX IF NOT EXISTS idx_notify_route ON eb_client_notify_prefs (routing_policy)");
    }

    // --- eb_notifications_sent ---
    if (!$schema->hasTable('eb_notifications_sent')) {
        $schema->create('eb_notifications_sent', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('service_id');
            $t->unsignedInteger('client_id');
            $t->string('username', 191);
            $t->enum('category', ['storage','device','addon']);
            $t->string('threshold_key', 191);
            $t->string('template', 191);
            $t->string('subject', 255);
            $t->text('recipients');
            $t->json('merge_json');
            $t->unsignedInteger('email_log_id')->nullable();
            $t->enum('status', ['sent','failed'])->default('sent');
            $t->dateTime('acknowledged_at')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $t->unique(['username','category','threshold_key'], 'uq_user_cat_key');
            $t->index(['service_id','created_at'], 'idx_service_created');
            $t->index('acknowledged_at', 'idx_notifications_ack');
        });
    } else {
        eb_add_index_if_missing('eb_notifications_sent', "CREATE UNIQUE INDEX IF NOT EXISTS uq_user_cat_key ON eb_notifications_sent (username, category, threshold_key)");
        eb_add_index_if_missing('eb_notifications_sent', "CREATE INDEX IF NOT EXISTS idx_service_created ON eb_notifications_sent (service_id, created_at)");
        eb_add_column_if_missing('eb_notifications_sent','acknowledged_at', fn(Blueprint $t)=>$t->dateTime('acknowledged_at')->nullable());
        eb_add_index_if_missing('eb_notifications_sent', "CREATE INDEX IF NOT EXISTS idx_notifications_ack ON eb_notifications_sent (acknowledged_at)");
    }

    // --- eb_module_settings (addon config backup store) ---
    if (!$schema->hasTable('eb_module_settings')) {
        $schema->create('eb_module_settings', function (Blueprint $t) {
            $t->string('setting', 191)->primary();
            $t->text('value');
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }

    // --- eb_tos_versions / eb_tos_client_acceptances / eb_tos_user_acceptances ---
    if (!$schema->hasTable('eb_tos_versions')) {
        $schema->create('eb_tos_versions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('version', 32); // e.g., 2025-03-01
            $t->string('title', 191)->default('Terms of Service');
            $t->text('summary')->nullable();
            $t->longText('content_html')->nullable();
            $t->tinyInteger('is_active')->default(0);
            $t->tinyInteger('require_acceptance')->default(0);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('published_at')->nullable();
            $t->integer('created_by')->nullable();
            $t->unique('version', 'uq_tos_version');
            $t->index(['is_active'], 'idx_tos_active');
            $t->index(['require_acceptance'], 'idx_tos_require');
        });
    } else {
        eb_add_column_if_missing('eb_tos_versions','summary',             fn(Blueprint $t)=>$t->text('summary')->nullable());
        eb_add_column_if_missing('eb_tos_versions','content_html',        fn(Blueprint $t)=>$t->longText('content_html')->nullable());
        eb_add_column_if_missing('eb_tos_versions','is_active',           fn(Blueprint $t)=>$t->tinyInteger('is_active')->default(0));
        eb_add_column_if_missing('eb_tos_versions','require_acceptance',  fn(Blueprint $t)=>$t->tinyInteger('require_acceptance')->default(0));
        eb_add_column_if_missing('eb_tos_versions','published_at',        fn(Blueprint $t)=>$t->timestamp('published_at')->nullable());
        eb_add_column_if_missing('eb_tos_versions','created_by',          fn(Blueprint $t)=>$t->integer('created_by')->nullable());
        eb_add_index_if_missing('eb_tos_versions', "CREATE UNIQUE INDEX IF NOT EXISTS uq_tos_version ON eb_tos_versions (version)");
        eb_add_index_if_missing('eb_tos_versions', "CREATE INDEX IF NOT EXISTS idx_tos_active ON eb_tos_versions (is_active)");
        eb_add_index_if_missing('eb_tos_versions', "CREATE INDEX IF NOT EXISTS idx_tos_require ON eb_tos_versions (require_acceptance)");
    }

    if (!$schema->hasTable('eb_tos_client_acceptances')) {
        $schema->create('eb_tos_client_acceptances', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('client_id')->unsigned();
            $t->string('tos_version', 32);
            $t->timestamp('accepted_at')->nullable()->useCurrent();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->unique(['client_id','tos_version'], 'uq_client_tos_version');
            $t->index(['client_id'], 'idx_tos_client');
        });
    } else {
        eb_add_column_if_missing('eb_tos_client_acceptances','ip_address', fn(Blueprint $t)=>$t->string('ip_address',45)->nullable());
        eb_add_column_if_missing('eb_tos_client_acceptances','user_agent', fn(Blueprint $t)=>$t->text('user_agent')->nullable());
        eb_add_index_if_missing('eb_tos_client_acceptances', "CREATE UNIQUE INDEX IF NOT EXISTS uq_client_tos_version ON eb_tos_client_acceptances (client_id, tos_version)");
        eb_add_index_if_missing('eb_tos_client_acceptances', "CREATE INDEX IF NOT EXISTS idx_tos_client ON eb_tos_client_acceptances (client_id)");
    }

    if (!$schema->hasTable('eb_tos_user_acceptances')) {
        $schema->create('eb_tos_user_acceptances', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('client_id')->unsigned();
            $t->integer('user_id')->nullable();    // WHMCS 8 Users model
            $t->integer('contact_id')->nullable(); // Legacy sub-account contact id
            $t->string('tos_version', 32);
            $t->timestamp('accepted_at')->nullable()->useCurrent();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->unique(['client_id','user_id','contact_id','tos_version'], 'uq_user_tos_version');
            $t->index(['client_id'], 'idx_tos_user_client');
            $t->index(['user_id'], 'idx_tos_user_user');
            $t->index(['contact_id'], 'idx_tos_user_contact');
        });
    } else {
        eb_add_column_if_missing('eb_tos_user_acceptances','user_id',     fn(Blueprint $t)=>$t->integer('user_id')->nullable());
        eb_add_column_if_missing('eb_tos_user_acceptances','contact_id',  fn(Blueprint $t)=>$t->integer('contact_id')->nullable());
        eb_add_column_if_missing('eb_tos_user_acceptances','ip_address',  fn(Blueprint $t)=>$t->string('ip_address',45)->nullable());
        eb_add_column_if_missing('eb_tos_user_acceptances','user_agent',  fn(Blueprint $t)=>$t->text('user_agent')->nullable());
        eb_add_index_if_missing('eb_tos_user_acceptances', "CREATE UNIQUE INDEX IF NOT EXISTS uq_user_tos_version ON eb_tos_user_acceptances (client_id, user_id, contact_id, tos_version)");
        eb_add_index_if_missing('eb_tos_user_acceptances', "CREATE INDEX IF NOT EXISTS idx_tos_user_client ON eb_tos_user_acceptances (client_id)");
        eb_add_index_if_missing('eb_tos_user_acceptances', "CREATE INDEX IF NOT EXISTS idx_tos_user_user ON eb_tos_user_acceptances (user_id)");
        eb_add_index_if_missing('eb_tos_user_acceptances', "CREATE INDEX IF NOT EXISTS idx_tos_user_contact ON eb_tos_user_acceptances (contact_id)");
    }

    // --- eb_privacy_versions / eb_privacy_client_acceptances / eb_privacy_user_acceptances ---
    if (!$schema->hasTable('eb_privacy_versions')) {
        $schema->create('eb_privacy_versions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('version', 32); // e.g., 2026-01-13
            $t->string('title', 191)->default('Privacy Policy');
            $t->text('summary')->nullable();
            $t->longText('content_html')->nullable();
            $t->tinyInteger('is_active')->default(0);
            $t->tinyInteger('require_acceptance')->default(0);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('published_at')->nullable();
            $t->integer('created_by')->nullable();
            $t->unique('version', 'uq_privacy_version');
            $t->index(['is_active'], 'idx_privacy_active');
            $t->index(['require_acceptance'], 'idx_privacy_require');
        });
    } else {
        eb_add_column_if_missing('eb_privacy_versions','summary',            fn(Blueprint $t)=>$t->text('summary')->nullable());
        eb_add_column_if_missing('eb_privacy_versions','content_html',       fn(Blueprint $t)=>$t->longText('content_html')->nullable());
        eb_add_column_if_missing('eb_privacy_versions','is_active',          fn(Blueprint $t)=>$t->tinyInteger('is_active')->default(0));
        eb_add_column_if_missing('eb_privacy_versions','require_acceptance', fn(Blueprint $t)=>$t->tinyInteger('require_acceptance')->default(0));
        eb_add_column_if_missing('eb_privacy_versions','published_at',       fn(Blueprint $t)=>$t->timestamp('published_at')->nullable());
        eb_add_column_if_missing('eb_privacy_versions','created_by',         fn(Blueprint $t)=>$t->integer('created_by')->nullable());
        eb_add_index_if_missing('eb_privacy_versions', "CREATE UNIQUE INDEX IF NOT EXISTS uq_privacy_version ON eb_privacy_versions (version)");
        eb_add_index_if_missing('eb_privacy_versions', "CREATE INDEX IF NOT EXISTS idx_privacy_active ON eb_privacy_versions (is_active)");
        eb_add_index_if_missing('eb_privacy_versions', "CREATE INDEX IF NOT EXISTS idx_privacy_require ON eb_privacy_versions (require_acceptance)");
    }

    if (!$schema->hasTable('eb_privacy_client_acceptances')) {
        $schema->create('eb_privacy_client_acceptances', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('client_id')->unsigned();
            $t->string('privacy_version', 32);
            $t->timestamp('accepted_at')->nullable()->useCurrent();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->unique(['client_id','privacy_version'], 'uq_client_privacy_version');
            $t->index(['client_id'], 'idx_privacy_client');
        });
    } else {
        eb_add_column_if_missing('eb_privacy_client_acceptances','ip_address', fn(Blueprint $t)=>$t->string('ip_address',45)->nullable());
        eb_add_column_if_missing('eb_privacy_client_acceptances','user_agent', fn(Blueprint $t)=>$t->text('user_agent')->nullable());
        eb_add_index_if_missing('eb_privacy_client_acceptances', "CREATE UNIQUE INDEX IF NOT EXISTS uq_client_privacy_version ON eb_privacy_client_acceptances (client_id, privacy_version)");
        eb_add_index_if_missing('eb_privacy_client_acceptances', "CREATE INDEX IF NOT EXISTS idx_privacy_client ON eb_privacy_client_acceptances (client_id)");
    }

    if (!$schema->hasTable('eb_privacy_user_acceptances')) {
        $schema->create('eb_privacy_user_acceptances', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('client_id')->unsigned();
            $t->integer('user_id')->nullable();    // WHMCS 8 Users model
            $t->integer('contact_id')->nullable(); // Legacy sub-account contact id
            $t->string('privacy_version', 32);
            $t->timestamp('accepted_at')->nullable()->useCurrent();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->unique(['client_id','user_id','contact_id','privacy_version'], 'uq_user_privacy_version');
            $t->index(['client_id'], 'idx_privacy_user_client');
            $t->index(['user_id'], 'idx_privacy_user_user');
            $t->index(['contact_id'], 'idx_privacy_user_contact');
        });
    } else {
        eb_add_column_if_missing('eb_privacy_user_acceptances','user_id',     fn(Blueprint $t)=>$t->integer('user_id')->nullable());
        eb_add_column_if_missing('eb_privacy_user_acceptances','contact_id',  fn(Blueprint $t)=>$t->integer('contact_id')->nullable());
        eb_add_column_if_missing('eb_privacy_user_acceptances','ip_address',  fn(Blueprint $t)=>$t->string('ip_address',45)->nullable());
        eb_add_column_if_missing('eb_privacy_user_acceptances','user_agent',  fn(Blueprint $t)=>$t->text('user_agent')->nullable());
        eb_add_index_if_missing('eb_privacy_user_acceptances', "CREATE UNIQUE INDEX IF NOT EXISTS uq_user_privacy_version ON eb_privacy_user_acceptances (client_id, user_id, contact_id, privacy_version)");
        eb_add_index_if_missing('eb_privacy_user_acceptances', "CREATE INDEX IF NOT EXISTS idx_privacy_user_client ON eb_privacy_user_acceptances (client_id)");
        eb_add_index_if_missing('eb_privacy_user_acceptances', "CREATE INDEX IF NOT EXISTS idx_privacy_user_user ON eb_privacy_user_acceptances (user_id)");
        eb_add_index_if_missing('eb_privacy_user_acceptances', "CREATE INDEX IF NOT EXISTS idx_privacy_user_contact ON eb_privacy_user_acceptances (contact_id)");
    }

    // --- eb_password_onboarding (per-client first-login password flow) ---
    if (!$schema->hasTable('eb_password_onboarding')) {
        $schema->create('eb_password_onboarding', function (Blueprint $t) {
            $t->integer('client_id')->unsigned();
            $t->tinyInteger('must_set')->default(1);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('completed_at')->nullable();
            $t->primary('client_id');
        });
    } else {
        eb_add_column_if_missing('eb_password_onboarding','must_set',     fn(Blueprint $t)=>$t->tinyInteger('must_set')->default(1));
        eb_add_column_if_missing('eb_password_onboarding','created_at',   fn(Blueprint $t)=>$t->timestamp('created_at')->nullable()->useCurrent());
        eb_add_column_if_missing('eb_password_onboarding','completed_at', fn(Blueprint $t)=>$t->timestamp('completed_at')->nullable());
    }

    // --- eb_billing_grace ---
    if (!$schema->hasTable('eb_billing_grace')) {
        $schema->create('eb_billing_grace', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('service_id')->default(0)->index();
            $t->unsignedInteger('client_id')->default(0);
            $t->string('username', 191);
            $t->enum('category', ['device','addon']);
            $t->string('entity_key', 191);
            $t->integer('quantity')->nullable();
            $t->timestamp('first_seen_at');
            $t->integer('grace_days')->default(0);
            $t->timestamp('grace_expires_at')->nullable();
            $t->string('source', 32)->default('ws');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $t->unique(['username','category','entity_key'], 'uq_grace');
            $t->index(['username','category'], 'idx_user_cat');
        });
    } else {
        // Backfill columns if upgrading
        eb_add_column_if_missing('eb_billing_grace','quantity',         fn(Blueprint $t)=>$t->integer('quantity')->nullable());
        eb_add_column_if_missing('eb_billing_grace','grace_days',       fn(Blueprint $t)=>$t->integer('grace_days')->default(0));
        eb_add_column_if_missing('eb_billing_grace','grace_expires_at', fn(Blueprint $t)=>$t->timestamp('grace_expires_at')->nullable());
        eb_add_column_if_missing('eb_billing_grace','source',           fn(Blueprint $t)=>$t->string('source',32)->default('ws'));
        eb_add_index_if_missing('eb_billing_grace', "CREATE UNIQUE INDEX IF NOT EXISTS uq_grace ON eb_billing_grace (username, category, entity_key)");
        eb_add_index_if_missing('eb_billing_grace', "CREATE INDEX IF NOT EXISTS idx_service ON eb_billing_grace (service_id)");
        eb_add_index_if_missing('eb_billing_grace', "CREATE INDEX IF NOT EXISTS idx_user_cat ON eb_billing_grace (username, category)");
    }

    // --- eb_whitelabel_tenants ---
    if (!$schema->hasTable('eb_whitelabel_tenants')) {
        $schema->create('eb_whitelabel_tenants', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('client_id');
            $t->enum('status', ['queued','building','active','failed','suspended','removing'])->default('queued');
            $t->string('org_id',191)->nullable();
            $t->string('subdomain',191);
            $t->string('fqdn',255);
            $t->string('custom_domain',255)->nullable();
            $t->string('custom_domain_status', 32)->nullable();
            $t->integer('product_id')->nullable();
            $t->integer('server_id')->nullable();
            $t->integer('servergroup_id')->nullable();
            $t->string('comet_admin_user',191)->nullable();
            $t->text('comet_admin_pass_enc')->nullable();
            $t->json('brand_json')->nullable();
            $t->json('email_json')->nullable();
            $t->json('policy_ids_json')->nullable();
            $t->json('storage_template_json')->nullable();
            $t->string('idempotency_key',191);
            $t->bigInteger('last_build_id')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index(['client_id','status'],'idx_wl_client_status');
            $t->unique(['fqdn'],'uq_wl_fqdn');
        });
    } else {
        // Ensure columns exist on upgrade
        eb_add_column_if_missing('eb_whitelabel_tenants','custom_domain', fn(Blueprint $t)=>$t->string('custom_domain',255)->nullable());
        eb_add_column_if_missing('eb_whitelabel_tenants','custom_domain_status', fn(Blueprint $t)=>$t->string('custom_domain_status',32)->nullable());
        // Public ULID used for customer-facing URLs
        eb_add_column_if_missing('eb_whitelabel_tenants','public_id', fn(Blueprint $t)=>$t->char('public_id',26)->nullable());
        eb_add_index_if_missing('eb_whitelabel_tenants', "CREATE UNIQUE INDEX IF NOT EXISTS idx_eb_wl_tenants_public_id ON eb_whitelabel_tenants (public_id)");
    }

    // --- eb_whitelabel_builds ---
    if (!$schema->hasTable('eb_whitelabel_builds')) {
        $schema->create('eb_whitelabel_builds', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('tenant_id');
            $t->enum('step', ['dns','nginx','cert','org','admin','branding','email','storage','whmcs','verify']);
            $t->enum('status', ['queued','running','success','failed'])->default('queued');
            $t->json('log_json')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->string('idempotency_key',191);
            $t->index(['tenant_id','step'],'idx_wlb_tenant_step');
        });
    }
    // --- eb_whitelabel_assets ---
    if (!$schema->hasTable('eb_whitelabel_assets')) {
        $schema->create('eb_whitelabel_assets', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('tenant_id');
            $t->enum('asset_type', ['logo','header','icon','tile','app_icon']);
            $t->string('filename',255);
            $t->string('comet_resource_hash',191)->nullable();
            $t->string('mime',64)->nullable();
            $t->integer('size')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->index(['tenant_id','asset_type'],'idx_wla_tenant_type');
        });
    }

    // --- eb_whitelabel_custom_domains ---
    if (!$schema->hasTable('eb_whitelabel_custom_domains')) {
        $schema->create('eb_whitelabel_custom_domains', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('tenant_id')->index();
            $t->string('hostname', 255);
            $t->enum('status', ['pending_dns','dns_ok','cert_ok','org_updated','verified','failed'])->default('pending_dns');
            $t->text('last_error')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->timestamp('cert_expires_at')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->unique(['tenant_id','hostname'], 'uq_wlcd_tenant_host');
        });
    } else {
        eb_add_column_if_missing('eb_whitelabel_custom_domains','status', fn(Blueprint $t)=>$t->enum('status', ['pending_dns','dns_ok','cert_ok','org_updated','verified','failed'])->default('pending_dns'));
        eb_add_column_if_missing('eb_whitelabel_custom_domains','last_error', fn(Blueprint $t)=>$t->text('last_error')->nullable());
        eb_add_column_if_missing('eb_whitelabel_custom_domains','checked_at', fn(Blueprint $t)=>$t->timestamp('checked_at')->nullable());
        eb_add_column_if_missing('eb_whitelabel_custom_domains','cert_expires_at', fn(Blueprint $t)=>$t->timestamp('cert_expires_at')->nullable());
        eb_add_index_if_missing('eb_whitelabel_custom_domains', "CREATE UNIQUE INDEX IF NOT EXISTS uq_wlcd_tenant_host ON eb_whitelabel_custom_domains (tenant_id, hostname)");
    }

    // --- eb_whitelabel_tenant_mail (per-tenant SMTP settings) ---
    if (!$schema->hasTable('eb_whitelabel_tenant_mail')) {
        $schema->create('eb_whitelabel_tenant_mail', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('tenant_id')->unique();
            $t->string('mode', 16)->default('builtin'); // builtin|smtp|smtp-ssl
            $t->string('host', 255)->default('');
            $t->unsignedInteger('port')->default(0);
            $t->string('username', 255)->default('');
            $t->text('password_enc')->nullable();
            $t->string('from_name', 255)->default('');
            $t->string('from_email', 255)->default('');
            $t->tinyInteger('allow_unencrypted')->default(0);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index('tenant_id','idx_wl_mail_tenant');
        });
    } else {
        eb_add_column_if_missing('eb_whitelabel_tenant_mail','allow_unencrypted', fn(Blueprint $t)=>$t->tinyInteger('allow_unencrypted')->default(0));
        eb_add_index_if_missing('eb_whitelabel_tenant_mail', "CREATE INDEX IF NOT EXISTS idx_wl_mail_tenant ON eb_whitelabel_tenant_mail (tenant_id)");
    }

    // --- eb_whitelabel_email_templates ---
    if (!$schema->hasTable('eb_whitelabel_email_templates')) {
        $schema->create('eb_whitelabel_email_templates', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('tenant_id');
            $t->string('key', 64); // e.g., welcome
            $t->string('name', 191);
            $t->string('subject', 255);
            $t->longText('body_html')->nullable();
            $t->longText('body_text')->nullable();
            $t->tinyInteger('is_active')->default(1);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->unique(['tenant_id','key'], 'uq_tenant_key');
            $t->index(['tenant_id','is_active'],'idx_tenant_active');
        });
    } else {
        eb_add_column_if_missing('eb_whitelabel_email_templates','is_active', fn(Blueprint $t)=>$t->tinyInteger('is_active')->default(1));
        eb_add_index_if_missing('eb_whitelabel_email_templates', "CREATE UNIQUE INDEX IF NOT EXISTS uq_tenant_key ON eb_whitelabel_email_templates (tenant_id, key)");
        eb_add_index_if_missing('eb_whitelabel_email_templates', "CREATE INDEX IF NOT EXISTS idx_tenant_active ON eb_whitelabel_email_templates (tenant_id, is_active)");
    }

    // --- eb_whitelabel_email_log ---
    if (!$schema->hasTable('eb_whitelabel_email_log')) {
        $schema->create('eb_whitelabel_email_log', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('tenant_id')->index();
            $t->bigInteger('template_id')->nullable()->index();
            $t->string('to_email', 255);
            $t->enum('status', ['queued','sent','failed'])->default('queued');
            $t->text('provider_resp')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    // ========= Partner Hub: MSP + Customers + Billing (Phase 1 schema) =========
    // eb_msp_accounts
    if (!$schema->hasTable('eb_msp_accounts')) {
        $schema->create('eb_msp_accounts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('whmcs_client_id')->unique();
            $t->string('name', 191)->default('');
            $t->enum('status', ['active','suspended','pending'])->default('active');
            $t->json('branding_json')->nullable();
            $t->enum('billing_mode', ['whmcs','stripe_connect'])->default('stripe_connect');
            $t->string('stripe_connect_id', 255)->nullable();
            $t->tinyInteger('charges_enabled')->default(0);
            $t->tinyInteger('payouts_enabled')->default(0);
            $t->json('connect_capabilities')->nullable();
            $t->json('connect_requirements')->nullable();
            $t->timestamp('onboarded_at')->nullable();
            $t->timestamp('last_verification_check')->nullable();
            $t->decimal('default_fee_percent', 5, 2)->nullable();
            $t->json('invoice_branding_json')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index(['status'], 'idx_msp_status');
        });
    }
    // Backfill Connect mirrors on upgrade
    else {
        eb_add_column_if_missing('eb_msp_accounts','charges_enabled', fn(Blueprint $t)=>$t->tinyInteger('charges_enabled')->default(0));
        eb_add_column_if_missing('eb_msp_accounts','payouts_enabled', fn(Blueprint $t)=>$t->tinyInteger('payouts_enabled')->default(0));
        eb_add_column_if_missing('eb_msp_accounts','connect_capabilities', fn(Blueprint $t)=>$t->json('connect_capabilities')->nullable());
        eb_add_column_if_missing('eb_msp_accounts','connect_requirements', fn(Blueprint $t)=>$t->json('connect_requirements')->nullable());
        eb_add_column_if_missing('eb_msp_accounts','onboarded_at', fn(Blueprint $t)=>$t->timestamp('onboarded_at')->nullable());
        eb_add_column_if_missing('eb_msp_accounts','last_verification_check', fn(Blueprint $t)=>$t->timestamp('last_verification_check')->nullable());
        eb_add_column_if_missing('eb_msp_accounts','default_fee_percent', fn(Blueprint $t)=>$t->decimal('default_fee_percent',5,2)->nullable());
    }

    // eb_customers
    if (!$schema->hasTable('eb_customers')) {
        $schema->create('eb_customers', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->integer('whmcs_client_id')->unique();
            $t->string('name', 191)->default('');
            $t->string('external_ref', 191)->nullable();
            $t->enum('status', ['active','inactive'])->default('active');
            $t->text('notes')->nullable();
            $t->string('stripe_customer_id', 255)->nullable()->index();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index(['msp_id','status'], 'idx_customer_msp_status');
        });
    }

    // eb_customer_user_links
    if (!$schema->hasTable('eb_customer_user_links')) {
        $schema->create('eb_customer_user_links', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('customer_id');
            $t->integer('whmcs_user_id');
            $t->enum('role', ['Owner','Viewer'])->default('Owner');
            $t->unique(['customer_id','whmcs_user_id'], 'uq_customer_user');
            $t->index('customer_id', 'idx_cul_customer');
            $t->index('whmcs_user_id', 'idx_cul_user');
        });
    }

    // eb_customer_comet_accounts (pivot)
    if (!$schema->hasTable('eb_customer_comet_accounts')) {
        $schema->create('eb_customer_comet_accounts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('customer_id');
            $t->string('comet_user_id', 191);
            $t->unique(['customer_id','comet_user_id'], 'uq_customer_comet');
            $t->index('customer_id', 'idx_cca_customer');
            $t->index('comet_user_id', 'idx_cca_comet');
        });
    }

    // eb_service_links (WHMCS service → Comet user association)
    if (!$schema->hasTable('eb_service_links')) {
        $schema->create('eb_service_links', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('whmcs_service_id');
            $t->bigInteger('msp_id')->nullable();
            $t->bigInteger('customer_id')->nullable();
            $t->string('comet_user_id', 191)->nullable();
            $t->unique('whmcs_service_id', 'uq_service');
            $t->index(['msp_id','customer_id'], 'idx_service_msp_customer');
        });
    }

    // Add nullable MSP/Customer scoping columns to Comet mirrors if present
    foreach (['comet_users','comet_devices','comet_items','comet_jobs'] as $mirror) {
        if ($schema->hasTable($mirror)) {
            eb_add_column_if_missing($mirror, 'msp_id', fn(Blueprint $t)=>$t->bigInteger('msp_id')->nullable()->index());
            eb_add_column_if_missing($mirror, 'customer_id', fn(Blueprint $t)=>$t->bigInteger('customer_id')->nullable()->index());
        }
    }

    // Plans & billing primitives (created in Phase 1 for forward-compat)
    if (!$schema->hasTable('eb_plans')) {
        $schema->create('eb_plans', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->string('name', 191);
            $t->string('currency', 8)->default('USD');
            $t->string('stripe_product_id', 255)->nullable()->index();
            $t->enum('status', ['active','archived'])->default('active');
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }

    if (!$schema->hasTable('eb_plan_prices')) {
        $schema->create('eb_plan_prices', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('plan_id')->index();
            $t->string('nickname', 191)->nullable();
            $t->enum('billing_cycle', ['month','year'])->default('month');
            $t->string('stripe_price_id', 255)->nullable()->unique();
            $t->tinyInteger('is_metered')->default(0);
            $t->string('metric_code', 64)->nullable();
            $t->decimal('application_fee_percent', 5, 2)->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }
    else {
        eb_add_column_if_missing('eb_plan_prices','application_fee_percent', fn(Blueprint $t)=>$t->decimal('application_fee_percent',5,2)->nullable());
    }

    if (!$schema->hasTable('eb_subscriptions')) {
        $schema->create('eb_subscriptions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->bigInteger('customer_id')->index();
            $t->bigInteger('plan_id')->nullable()->index();
            $t->string('stripe_subscription_id', 255)->nullable()->unique();
            $t->string('stripe_status', 32)->default('active');
            $t->bigInteger('current_price_id')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('cancel_at')->nullable();
            $t->tinyInteger('cancel_at_period_end')->default(0);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index(['customer_id','stripe_status'], 'idx_sub_customer_status');
        });
    }

    if (!$schema->hasTable('eb_usage_ledger')) {
        $schema->create('eb_usage_ledger', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('customer_id')->index();
            $t->string('metric', 64);
            $t->bigInteger('qty')->default(0);
            $t->timestamp('period_start');
            $t->timestamp('period_end');
            $t->string('source', 32)->default('manual');
            $t->string('idempotency_key', 191)->nullable()->unique();
            $t->timestamp('pushed_to_stripe_at')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    if (!$schema->hasTable('eb_invoice_cache')) {
        $schema->create('eb_invoice_cache', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('customer_id')->index();
            $t->string('stripe_invoice_id', 255)->unique();
            $t->bigInteger('amount_total')->default(0);
            $t->bigInteger('amount_tax')->default(0);
            $t->string('status', 32)->default('draft');
            $t->string('hosted_invoice_url', 255)->nullable();
            $t->unsignedInteger('created')->default(0); // epoch seconds
            $t->string('currency', 8)->default('USD');
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }

    if (!$schema->hasTable('eb_payment_cache')) {
        $schema->create('eb_payment_cache', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('customer_id')->index();
            $t->string('stripe_payment_intent_id', 255)->unique();
            $t->bigInteger('amount')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->string('status', 32)->default('requires_payment_method');
            $t->unsignedInteger('created')->default(0); // epoch seconds
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }

    // --- Partner Hub Catalog: Products ---
    if (!$schema->hasTable('eb_catalog_products')) {
        $schema->create('eb_catalog_products', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->string('name', 160);
            $t->text('description')->nullable();
            $t->enum('category', ['Backup','Services','Other'])->default('Backup');
            $t->string('stripe_product_id', 64)->nullable();
            $t->tinyInteger('active')->default(1);
            $t->tinyInteger('is_published')->default(0);
            $t->dateTime('published_at')->nullable();
            $t->char('default_currency',3)->nullable();
            $t->bigInteger('created_by')->nullable();
            $t->bigInteger('updated_by')->nullable();
            $t->text('features_json')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index('active');
        });
    } else {
        eb_add_column_if_missing('eb_catalog_products','is_published', fn(Blueprint $t)=>$t->tinyInteger('is_published')->default(0));
        eb_add_column_if_missing('eb_catalog_products','published_at', fn(Blueprint $t)=>$t->dateTime('published_at')->nullable());
        eb_add_column_if_missing('eb_catalog_products','default_currency', fn(Blueprint $t)=>$t->char('default_currency',3)->nullable());
        eb_add_column_if_missing('eb_catalog_products','created_by', fn(Blueprint $t)=>$t->bigInteger('created_by')->nullable());
        eb_add_column_if_missing('eb_catalog_products','updated_by', fn(Blueprint $t)=>$t->bigInteger('updated_by')->nullable());
        eb_add_column_if_missing('eb_catalog_products','base_metric_code', fn(Blueprint $t)=>$t->enum('base_metric_code',[ 'STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC' ])->nullable());
        eb_add_column_if_missing('eb_catalog_products','features_json', fn(Blueprint $t)=>$t->text('features_json')->nullable());
    }
    // --- Partner Hub Catalog: Prices ---
    if (!$schema->hasTable('eb_catalog_prices')) {
        $schema->create('eb_catalog_prices', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('product_id')->index();
            $t->string('name', 160);
            $t->enum('kind', ['recurring','metered','one_time']);
            $t->char('currency', 3)->default('CAD');
            $t->string('unit_label', 32)->nullable();
            $t->integer('unit_amount'); // cents
            $t->enum('interval', ['month','year','none'])->default('month');
            $t->enum('aggregate_usage', ['sum','last_during_period','max','avg'])->nullable();
            $t->enum('metric_code', ['STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC'])->default('GENERIC');
            $t->string('stripe_price_id', 64)->nullable()->unique();
            $t->tinyInteger('active')->default(1);
            $t->enum('billing_type',['per_unit','metered','one_time'])->default('per_unit');
            $t->integer('version')->default(1);
            $t->bigInteger('supersedes_price_id')->nullable();
            $t->tinyInteger('is_published')->default(0);
            $t->dateTime('published_at')->nullable();
            $t->string('last_publish_request_id',64)->nullable();
            $t->integer('amount_per_gb_cents')->nullable();
            $t->integer('display_per_tb_money')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index(['kind']);
            $t->index(['metric_code']);
            $t->index(['active']);
            $t->index(['product_id','version']);
            $t->index(['is_published']);
        });
    } else {
        eb_add_column_if_missing('eb_catalog_prices','billing_type', fn(Blueprint $t)=>$t->enum('billing_type',['per_unit','metered','one_time'])->default('per_unit'));
        eb_add_column_if_missing('eb_catalog_prices','version', fn(Blueprint $t)=>$t->integer('version')->default(1));
        eb_add_column_if_missing('eb_catalog_prices','supersedes_price_id', fn(Blueprint $t)=>$t->bigInteger('supersedes_price_id')->nullable());
        eb_add_column_if_missing('eb_catalog_prices','is_published', fn(Blueprint $t)=>$t->tinyInteger('is_published')->default(0));
        eb_add_column_if_missing('eb_catalog_prices','published_at', fn(Blueprint $t)=>$t->dateTime('published_at')->nullable());
        eb_add_column_if_missing('eb_catalog_prices','last_publish_request_id', fn(Blueprint $t)=>$t->string('last_publish_request_id',64)->nullable());
        eb_add_column_if_missing('eb_catalog_prices','amount_per_gb_cents', fn(Blueprint $t)=>$t->integer('amount_per_gb_cents')->nullable());
        eb_add_column_if_missing('eb_catalog_prices','display_per_tb_money', fn(Blueprint $t)=>$t->integer('display_per_tb_money')->nullable());
    }

    // MSP default currency
    if ($schema->hasTable('eb_msp_accounts')) {
        eb_add_column_if_missing('eb_msp_accounts','default_currency', fn(Blueprint $t)=>$t->char('default_currency',3)->nullable());
    }

    // MSP settings (Checkout & Dunning JSON)
    if (!$schema->hasTable('eb_msp_settings')) {
        $schema->create('eb_msp_settings', function (Blueprint $t) {
            $t->bigInteger('msp_id')->primary();
            $t->json('checkout_json');
            $t->json('tax_json')->nullable();
            $t->json('email_json')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    } else {
        eb_add_column_if_missing('eb_msp_settings','checkout_json', fn(Blueprint $t)=>$t->json('checkout_json'));
        eb_add_column_if_missing('eb_msp_settings','tax_json', fn(Blueprint $t)=>$t->json('tax_json')->nullable());
        eb_add_column_if_missing('eb_msp_settings','email_json', fn(Blueprint $t)=>$t->json('email_json')->nullable());
        eb_add_column_if_missing('eb_msp_settings','created_at', fn(Blueprint $t)=>$t->timestamp('created_at')->nullable()->useCurrent());
        eb_add_column_if_missing('eb_msp_settings','updated_at', fn(Blueprint $t)=>$t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate());
    }

    // MSP Tax Registrations (local mirror)
    if (!$schema->hasTable('eb_msp_tax_regs')) {
        $schema->create('eb_msp_tax_regs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->char('country', 2);
            $t->string('region', 8)->nullable();
            $t->string('registration_number', 191);
            $t->string('legal_name', 191)->nullable();
            $t->string('stripe_registration_id', 191)->nullable()->index();
            $t->enum('source', ['stripe','local'])->default('local');
            $t->tinyInteger('is_active')->default(1);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index(['msp_id','country','region']);
        });
    }

    // MSP Tax Audit trail
    if (!$schema->hasTable('eb_msp_tax_audit')) {
        $schema->create('eb_msp_tax_audit', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->integer('user_id')->nullable();
            $t->enum('action', ['create','update','delete','sync']);
            $t->json('before_json')->nullable();
            $t->json('after_json')->nullable();
            $t->json('meta_json')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    // --- Partner Hub Catalog: Plan templates ---
    if (!$schema->hasTable('eb_plan_templates')) {
        $schema->create('eb_plan_templates', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->string('name', 160);
            $t->text('description')->nullable();
            $t->integer('trial_days')->default(0);
            $t->integer('version')->default(1);
            $t->tinyInteger('active')->default(1);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index('active');
        });
    }

    // --- Partner Hub Catalog: Plan components ---
    if (!$schema->hasTable('eb_plan_components')) {
        $schema->create('eb_plan_components', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('plan_id')->index();
            $t->bigInteger('price_id')->index();
            $t->enum('metric_code', ['STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC']);
            $t->integer('default_qty')->default(0);
            $t->enum('overage_mode', ['bill_all','cap_at_default'])->default('bill_all');
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index(['plan_id']);
            $t->index(['price_id']);
            $t->index(['metric_code']);
        });
    }

    // --- Partner Hub Catalog: Plan instances ---
    if (!$schema->hasTable('eb_plan_instances')) {
        $schema->create('eb_plan_instances', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->bigInteger('customer_id')->index();
            $t->string('comet_user_id', 128)->index();
            $t->bigInteger('plan_id')->index();
            $t->integer('plan_version');
            $t->string('stripe_account_id', 64);
            $t->string('stripe_customer_id', 64);
            $t->string('stripe_subscription_id', 64);
            $t->date('anchor_date');
            $t->enum('status', ['active','trialing','past_due','canceled','paused']);
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index(['stripe_subscription_id']);
        });
    }

    // --- Partner Hub Catalog: Plan instance items ---
    if (!$schema->hasTable('eb_plan_instance_items')) {
        $schema->create('eb_plan_instance_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('plan_instance_id')->index();
            $t->bigInteger('plan_component_id')->index();
            $t->string('stripe_subscription_item_id', 64);
            $t->enum('metric_code', ['STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC']);
            $t->integer('last_qty')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $t->index(['metric_code']);
        });
    }

    // Webhook idempotency store (processed Stripe event ids)
    if (!$schema->hasTable('eb_stripe_events')) {
        $schema->create('eb_stripe_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('event_id', 255)->unique();
            $t->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    // Payouts cache (per MSP)
    if (!$schema->hasTable('eb_payouts')) {
        $schema->create('eb_payouts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->string('stripe_payout_id', 255)->unique();
            $t->bigInteger('amount')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->string('status', 32)->default('pending');
            $t->unsignedInteger('arrival_date')->default(0);
            $t->unsignedInteger('created')->default(0);
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }

    // Disputes cache (per MSP)
    if (!$schema->hasTable('eb_disputes')) {
        $schema->create('eb_disputes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('msp_id')->index();
            $t->string('stripe_dispute_id', 255)->unique();
            $t->bigInteger('amount')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->string('reason', 64)->nullable();
            $t->string('status', 32)->default('warning_needs_response');
            $t->unsignedInteger('evidence_due_by')->default(0);
            $t->string('charge_id', 255)->nullable();
            $t->unsignedInteger('created')->default(0);
            $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }

    // --- eb_annual_entitlement_ledger (per-service cycle entitlement tracking) ---
    try {
        if (!$schema->hasTable('eb_annual_entitlement_ledger')) {
            $schema->create('eb_annual_entitlement_ledger', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('service_id')->index();
                $t->unsignedInteger('client_id')->index();
                $t->string('username', 191)->index();
                $t->unsignedInteger('config_id')->index();
                $t->date('cycle_start')->index();
                $t->date('cycle_end')->index();
                $t->unsignedInteger('current_usage_qty')->default(0);
                $t->unsignedInteger('current_config_qty')->default(0);
                $t->unsignedInteger('max_paid_qty')->default(0);
                $t->string('status', 32)->default('active')->index();
                $t->integer('recommended_delta')->nullable();
                $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                $t->unique(['service_id', 'config_id', 'cycle_start'], 'uniq_service_config_cycle');
                $t->index(['client_id', 'cycle_start'], 'idx_client_cycle');
            });
        }
    } catch (\Throwable $e) { /* ignore */ }

    // --- eb_annual_entitlement_events (audit log for entitlement changes) ---
    try {
        if (!$schema->hasTable('eb_annual_entitlement_events')) {
            $schema->create('eb_annual_entitlement_events', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('service_id')->index();
                $t->unsignedInteger('config_id')->index();
                $t->date('cycle_start')->index();
                $t->string('event_type', 64)->index();
                $t->unsignedInteger('old_max_paid_qty')->nullable();
                $t->unsignedInteger('new_max_paid_qty')->nullable();
                $t->text('note')->nullable();
                $t->unsignedInteger('admin_id')->nullable()->index();
                $t->timestamp('created_at')->nullable()->useCurrent();
                $t->index(['service_id', 'config_id', 'cycle_start'], 'idx_event_service_config_cycle');
            });
        }
    } catch (\Throwable $e) { /* ignore */ }
}

// ULID generator for public tenant IDs (Crockford Base32, 26 chars)
if (!function_exists('eazybackup_generate_ulid')) {
    function eazybackup_generate_ulid(): string {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        try {
            $time = (int) floor(microtime(true) * 1000);
        } catch (\Throwable $__) {
            $time = (int) (time() * 1000);
        }
        // 48 bits time, 80 bits randomness -> 128 bits total
        // Build 16-byte binary string: 6 bytes time (big-endian) + 10 bytes random
        $timeBytes = '';
        for ($i = 5; $i >= 0; $i--) { $timeBytes .= chr(($time >> ($i * 8)) & 0xFF); }
        try { $rand = random_bytes(10); } catch (\Throwable $__) { $rand = substr(hash('sha256', uniqid('', true), true), 0, 10); }
        $bin = $timeBytes . $rand; // 16 bytes
        // Encode to base32 (26 chars)
        $bits = '';
        for ($i = 0; $i < 16; $i++) { $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT); }
        $out = '';
        for ($i = 0; $i < 26; $i++) {
            $chunk = substr($bits, $i * 5, 5);
            if ($chunk === '') { $chunk = '00000'; }
            $idx = bindec(str_pad($chunk, 5, '0'));
            $out .= $alphabet[$idx % 32];
        }
        return $out;
    }
}

/**
 * Backup all current addon settings from tbladdonmodules(module='eazybackup') to eb_module_settings.
 */
function eb_backup_settings(): void
{
    $rows = Capsule::table('tbladdonmodules')
        ->where('module', 'eazybackup')
        ->get(['setting','value']);
    foreach ($rows as $r) {
        $setting = (string)($r->setting ?? ''); if ($setting === '') continue;
        $value = (string)($r->value ?? '');
        try {
            // Try insert, then update on duplicate
            Capsule::table('eb_module_settings')->insert(['setting'=>$setting,'value'=>$value,'updated_at'=>date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            try {
                Capsule::table('eb_module_settings')->where('setting',$setting)->update(['value'=>$value,'updated_at'=>date('Y-m-d H:i:s')]);
            } catch (\Throwable $e2) { /* ignore */ }
        }
    }
}

/**
 * Restore addon settings from eb_module_settings back into tbladdonmodules(module='eazybackup').
 */
function eb_restore_settings(): void
{
    $rows = Capsule::table('eb_module_settings')->get(['setting','value']);
    foreach ($rows as $r) {
        $setting = (string)($r->setting ?? ''); if ($setting === '') continue;
        $value = (string)($r->value ?? '');
        // Upsert into tbladdonmodules
        $exists = Capsule::table('tbladdonmodules')
            ->where('module','eazybackup')
            ->where('setting',$setting)
            ->exists();
        if ($exists) {
            try { Capsule::table('tbladdonmodules')->where('module','eazybackup')->where('setting',$setting)->update(['value'=>$value]); } catch (\Throwable $e) { /* ignore */ }
        } else {
            try { Capsule::table('tbladdonmodules')->insert(['module'=>'eazybackup','setting'=>$setting,'value'=>$value]); } catch (\Throwable $e) { /* ignore */ }
        }
    }
}

// Ensure schema upgrades are applied on runtime paths as well
function eazybackup_ensure_permissions_schema() { /* removed */ }

/**
 * Clamp a desired day-of-month to that month's last day in a timezone-safe way.
 */
function eb_clamp_day(int $year, int $month, int $dom): int {
    $last = (int) Carbon::create($year, $month, 1)->endOfMonth()->day;
    if ($dom < 1) { $dom = 1; }
    if ($dom > $last) { $dom = $last; }
    return $dom;
}

/**
 * Compute consolidated next due date per rules.
 * $cycle: 'monthly' | 'annual'
 */
function eb_computeConsolidatedDueDate(Carbon $baseLocal, int $dom, string $cycle, string $tz): Carbon {
    $y = (int)$baseLocal->year;
    $m = (int)$baseLocal->month;
    $d = (int)$baseLocal->day;
    $targetDay = eb_clamp_day($y, $m, $dom);
    $candidate = Carbon::create($y, $m, $targetDay, 12, 0, 0, $tz)->startOfDay();

    // Initial next due date should never exceed one month in the future for either cycle.
    // Use monthly logic for both monthly and annual on creation.
    if ($d > $dom) {
        $next = $baseLocal->copy()->addMonthNoOverflow();
        $y2 = (int)$next->year; $m2 = (int)$next->month;
        $targetDay = eb_clamp_day($y2, $m2, $dom);
        return Carbon::create($y2, $m2, $targetDay, 12, 0, 0, $tz)->startOfDay();
    }
    return $candidate;
}

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

if (!function_exists('fetchDistinctDiskImageDevicesMaps')) {
    /**
     * Returns two maps:
     *  - byCU: [client_id][username] => distinct devices with ≥1 windisk item (active devices only)
     *  - byC:  [client_id]           => distinct devices with ≥1 windisk item (active devices only)
     */
    function fetchDistinctDiskImageDevicesMaps(array $rows): array {
        if (empty($rows)) return ['byCU' => [], 'byC' => []];

        $clientIds = [];
        $usernames = [];
        foreach ($rows as $r) {
            if (isset($r['user_id']))   { $clientIds[(int)$r['user_id']] = true; }
            if (isset($r['username']))  { $usernames[(string)$r['username']] = true; }
        }
        $clientIds = array_keys($clientIds);
        $usernames = array_keys($usernames);

        // Base builder with left join to items
        $base = Capsule::table('comet_devices as cd')
            ->leftJoin('comet_items as ci', 'ci.comet_device_id', '=', 'cd.id')
            ->where('cd.is_active', 1);

        // 1) Map by (client_id, username)
        $q1 = (clone $base)
            ->whereIn('cd.client_id', $clientIds)
            ->whereIn('cd.username', $usernames)
            ->selectRaw("
                cd.client_id,
                cd.username,
                COUNT(DISTINCT CASE
                    WHEN (ci.id IS NOT NULL AND (ci.type = 'engine1/windisk'
                         OR JSON_EXTRACT(ci.content, '$.Engine') = 'engine1/windisk'))
                    THEN cd.id END
                ) AS di_devices_distinct
            ")
            ->groupBy('cd.client_id', 'cd.username')
            ->get();

        $byCU = [];
        foreach ($q1 as $row) {
            $byCU[(int)$row->client_id][(string)$row->username] = (int)$row->di_devices_distinct;
        }

        // 2) Map by client_id only (fallback when username key misses)
        $q2 = (clone $base)
            ->whereIn('cd.client_id', $clientIds)
            ->selectRaw("
                cd.client_id,
                COUNT(DISTINCT CASE
                    WHEN (ci.id IS NOT NULL AND (ci.type = 'engine1/windisk'
                         OR JSON_EXTRACT(ci.content, '$.Engine') = 'engine1/windisk'))
                    THEN cd.id END
                ) AS di_devices_distinct
            ")
            ->groupBy('cd.client_id')
            ->get();

        $byC = [];
        foreach ($q2 as $row) {
            $byC[(int)$row->client_id] = (int)$row->di_devices_distinct;
        }

        return ['byCU' => $byCU, 'byC' => $byC];
    }
}
// Backward-compatible wrapper: older call sites referenced the singular name
if (!function_exists('fetchDistinctDiskImageDevicesMap')) {
    /**
     * Legacy shim returning the by-(client_id, username) map
     * @param array $rows
     * @return array [client_id][username] => distinct DI devices
     */
    function fetchDistinctDiskImageDevicesMap(array $rows): array {
        $maps = fetchDistinctDiskImageDevicesMaps($rows);
        return $maps['byCU'] ?? [];
    }
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
// Shared constants (e.g., ANNOUNCEMENT_KEY)
require_once __DIR__ . '/lib/constants.php';



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
        'author'      => 'eazyBackup Systems Ltd.',
        'language'    => 'english',
        'version'     => '1.5.1', // Password on signup
        'fields'      => [
            'trialsignupgid' => [
                'FriendlyName' => 'Trial Signup Product Group',
                'Type'         => 'dropdown',
                'Options'      => eazybackup_ProductGroupsLoader(),
                'Description'  => 'Choose a product group for the trial signup page',
            ],
            'trialsignupemail' => [
                'FriendlyName' => 'Trial Signup Email Address',
                'Type'         => 'text',
                'Description'  => 'Trial signup emails are sent to this email address',
            ],
            'resellersignupemailtemplate' => [
                'FriendlyName' => 'Reseller Signup Email Template',
                'Type'         => 'dropdown',
                'Options'      => eazybackup_EmailTemplatesLoader(),
                'Description'  => 'Choose an email template for the reseller signup email',
            ],
            'trial_verification_email_template' => [
                'FriendlyName' => 'Trial Verification Email Template',
                'Type'         => 'dropdown',
                'Options'      => eazybackup_EmailTemplatesLoader(),
                'Description'  => 'General email template used for eazyBackup trial verification (merge field: {$trial_verification_link})',
            ],
            'turnstilesitekey' => [
                'FriendlyName' => 'Turnstile Site Key',
                'Type'         => 'text',
                'Size'         => '60',
                'Description'  => 'Public site key from Cloudflare Turnstile',
            ],
            'turnstilesecret' => [
                'FriendlyName' => 'Turnstile Secret Key',
                'Type'         => 'password',
                'Size'         => '60',
                'Description'  => 'Secret key for server-side verification',
            ],
            'resellergroups' => [
                'FriendlyName' => 'Reseller Client Groups',
                'Type'         => 'textarea',
                'Rows'         => '3',
                'Cols'         => '60',
                'Description'  => eazybackup_AdminGroupsDescription(),
            ],

            // ---- Notifications: module-level defaults ----
            'notify_storage' => [
                'FriendlyName' => 'Notify: Storage',
                'Type' => 'yesno',
                'Description' => 'Send storage threshold/overage notifications',
            ],
            'notify_devices' => [
                'FriendlyName' => 'Notify: Devices',
                'Type' => 'yesno',
                'Description' => 'Send device added notifications',
            ],
            'notify_addons' => [
                'FriendlyName' => 'Notify: Add-ons',
                'Type' => 'yesno',
                'Description' => 'Send add-on enabled notifications',
            ],
            'notify_threshold_percent' => [
                'FriendlyName' => 'Storage Threshold %',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '90',
                'Description' => 'Trigger at this % of each TiB milestone (e.g., 90)',
            ],
            'notify_suppress_new_service_days' => [
                'FriendlyName' => 'Suppress new-service billing notifications (days)',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '15',
                'Description' => 'Do not send Storage/Add-on billing notifications for services created within this many days (0 disables).',
            ],
            'notify_routing' => [
                'FriendlyName' => 'Recipient Routing',
                'Type' => 'dropdown',
                'Options' => [
                    'primary' => 'Primary contact',
                    'billing' => 'Billing contacts',
                    'technical' => 'Technical contacts',
                    'custom' => 'Custom list',
                ],
                'Default' => 'billing',
                'Description' => 'Default recipient policy (per-user overrides may apply later)',
            ],
            'notify_custom_emails' => [
                'FriendlyName' => 'Custom Recipients',
                'Type' => 'text',
                'Size' => '200',
                'Description' => 'Comma/semicolon separated list when routing=custom',
            ],
            'notify_test_mode' => [
                'FriendlyName' => 'Notifications Test Mode',
                'Type' => 'yesno',
                'Description' => 'Route all notifications ONLY to the test recipient(s); customers will NOT receive copies',
            ],
            'notify_test_recipient' => [
                'FriendlyName' => 'Test Recipient(s)',
                'Type' => 'text',
                'Size' => '200',
                'Description' => 'CSV/SSV emails to receive test-mode notifications',
            ],
            'notify_test_client_id' => [
                'FriendlyName' => 'Test Client ID (optional)',
                'Type' => 'text',
                'Size' => '10',
                'Description' => 'Client ID to associate custom test emails with WHMCS Email Log',
            ],
            // Template selectors
            'tpl_storage_warning' => [
                'FriendlyName' => 'Template: Storage Warning',
                'Type'         => 'dropdown',
                'Options'      => eazybackup_EmailTemplatesLoader(),
            ],
            'tpl_storage_overage' => [
                'FriendlyName' => 'Template: Storage Overage',
                'Type'         => 'dropdown',
                'Options'      => eazybackup_EmailTemplatesLoader(),
            ],
            'tpl_device_added' => [
                'FriendlyName' => 'Template: Device Added',
                'Type'         => 'dropdown',
                'Options'      => eazybackup_EmailTemplatesLoader(),
            ],
            'tpl_addon_enabled' => [
                'FriendlyName' => 'Template: Add-on Enabled',
                'Type'         => 'dropdown',
                'Options'      => eazybackup_EmailTemplatesLoader(),
            ],
            // Partner Hub defaults
            'partnerhub_default_fee_percent' => [
                'FriendlyName' => 'Partner Hub: Default Application Fee %',
                'Type' => 'text',
                'Size' => '6',
                'Default' => '0',
                'Description' => 'Default fee percent for MSP subscriptions when a price has no explicit default. Range 0–100 (max two decimals).',
            ],
            // Partner Hub navigation visibility (client-area sidebar) — all default to enabled
            'partnerhub_nav_enabled' => [
                'FriendlyName' => 'Partner Hub: Show group',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Show Partner Hub group in the client sidebar',
            ],
            'partnerhub_show_overview' => [
                'FriendlyName' => 'Partner Hub: Show Overview',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Show the Overview link in Partner Hub',
            ],
            'partnerhub_show_clients' => [
                'FriendlyName' => 'Partner Hub: Show Clients',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Show the Clients link in Partner Hub',
            ],
            'partnerhub_show_catalog' => [
                'FriendlyName' => 'Partner Hub: Show Catalog',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Show the Catalog section in Partner Hub',
            ],
            'partnerhub_show_billing' => [
                'FriendlyName' => 'Partner Hub: Show Billing',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Show the Billing section in Partner Hub',
            ],
            'partnerhub_show_money' => [
                'FriendlyName' => 'Partner Hub: Show Money',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Show the Money section in Partner Hub',
            ],
            'partnerhub_show_stripe' => [
                'FriendlyName' => 'Partner Hub: Show Stripe Account',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Show the Stripe Account section in Partner Hub',
            ],
            'partnerhub_show_settings' => [
                'FriendlyName' => 'Partner Hub: Show Settings',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Show the Settings section in Partner Hub',
            ],
            // Grace periods
            'grace_days_devices' => [
                'FriendlyName' => 'Grace Period (days) — Devices',
                'Type' => 'text',
                'Size' => '6',
                'Default' => '0',
                'Description' => 'Days before device billing starts (0 to disable)'
            ],
            'grace_days_addons' => [
                'FriendlyName' => 'Grace Period (days) — Add-ons',
                'Type' => 'text',
                'Size' => '6',
                'Default' => '0',
                'Description' => 'Days before add-on billing starts (0 to disable)'
            ],
            // White-Label Automation settings
            'whitelabel_enabled' => [
                'FriendlyName' => 'White-Label: Enable',
                'Type' => 'yesno',
                'Description' => 'Enable White-Label Tenant automation and UI',
            ],
            // AWS Route 53
            'aws_access_key_id' => [
                'FriendlyName' => 'AWS Access Key ID',
                'Type' => 'password',
                'Size' => '60',
                'Description' => 'Used for Route 53 DNS record management',
            ],
            'aws_secret_access_key' => [
                'FriendlyName' => 'AWS Secret Access Key',
                'Type' => 'password',
                'Size' => '60',
                'Description' => 'Stored encrypted by WHMCS',
            ],
            'aws_session_token' => [
                'FriendlyName' => 'AWS Session Token (optional)',
                'Type' => 'password',
                'Size' => '120',
                'Description' => 'If using temporary credentials, provide the session token',
            ],
            'aws_region' => [
                'FriendlyName' => 'AWS Region',
                'Type' => 'text',
                'Size' => '32',
                'Default' => 'us-east-1',
            ],
            'route53_hosted_zone_id' => [
                'FriendlyName' => 'Route 53 Hosted Zone ID',
                'Type' => 'text',
                'Size' => '48',
                'Description' => 'HostedZoneId for your base domain',
            ],
            'whitelabel_dns_target' => [
                'FriendlyName' => 'DNS Target (CNAME)',
                'Type' => 'text',
                'Size' => '120',
                'Description' => 'Target hostname for tenant CNAME records (e.g., proxy1.example.com)',
            ],
            'whitelabel_base_domain' => [
                'FriendlyName' => 'Base Domain for Tenants',
                'Type' => 'text',
                'Size' => '60',
                'Description' => 'e.g., examplebrand.tld',
            ],
            // Comet root admin
            'comet_server_id' => [
                'FriendlyName' => 'Comet Server (from WHMCS Servers)',
                'Type' => 'dropdown',
                'Options' => eazybackup_CometServersLoader(),
                'Description' => 'Select a configured Comet server (System Settings → Servers). Preferred over legacy fields below.',
            ],
            'comet_root_url' => [
                'FriendlyName' => 'Comet Root URL',
                'Type' => 'text',
                'Size' => '120',
                'Description' => 'Deprecated – prefer Comet Server dropdown. e.g., https://panel.example.com/',
            ],
            'comet_root_admin' => [
                'FriendlyName' => 'Comet Root Admin',
                'Type' => 'text',
                'Size' => '60',
                'Description' => 'Deprecated – prefer Comet Server dropdown.',
            ],
            'comet_root_password' => [
                'FriendlyName' => 'Comet Root Password',
                'Type' => 'password',
                'Size' => '60',
                'Description' => 'Deprecated – prefer Comet Server dropdown.',
            ],
            // Host operations
            'ops_mode' => [
                'FriendlyName' => 'Ops Mode',
                'Type' => 'dropdown',
                'Options' => [ 'ssh' => 'SSH', 'sudo' => 'Local sudo script' ],
                'Default' => 'ssh',
            ],
            'ops_ssh_host' => [
                'FriendlyName' => 'Ops SSH Host',
                'Type' => 'text',
                'Size' => '120',
            ],
            'ops_ssh_user' => [
                'FriendlyName' => 'Ops SSH User',
                'Type' => 'text',
                'Size' => '60',
            ],
            'ops_ssh_key_path' => [
                'FriendlyName' => 'Ops SSH Key Path',
                'Type' => 'text',
                'Size' => '120',
            ],
            'ops_sudo_script' => [
                'FriendlyName' => 'Ops sudo Script',
                'Type' => 'text',
                'Size' => '120',
            ],
            // Host ops extras
            'certbot_email' => [
                'FriendlyName' => 'Certbot Email',
                'Type' => 'text',
                'Size' => '120',
                'Description' => 'Email address used for cert issuance/renewal',
            ],
            'nginx_upstream' => [
                'FriendlyName' => 'Nginx Upstream',
                'Type' => 'text',
                'Size' => '120',
                'Default' => 'http://obc_servers',
                'Description' => 'Upstream to use when writing HTTPS vhost',
            ],
            // Partner Hub (public signup) settings
            'PARTNER_HUB_SIGNUP_ENABLED' => [
                'FriendlyName' => 'Partner Hub: Public Signup',
                'Type' => 'yesno',
                'Description' => 'Enable MSP-branded public signup and downloads (Partner Hub Phase 1). When disabled, public routes render a disabled/invalid page.',
            ],
            // Stripe Connect (platform-level)
            'stripe_platform_publishable_key' => [
                'FriendlyName' => 'Stripe Publishable Key',
                'Type' => 'text',
                'Size' => '120',
                'Description' => 'pk_live_xxx or pk_test_xxx (used by Stripe.js in client area)'
            ],
            'stripe_platform_secret' => [
                'FriendlyName' => 'Stripe Secret Key',
                'Type' => 'password',
                'Size' => '120',
                'Description' => 'sk_live_xxx or sk_test_xxx (stored encrypted by WHMCS)'
            ],
            'stripe_webhook_secret' => [
                'FriendlyName' => 'Stripe Webhook Secret',
                'Type' => 'password',
                'Size' => '120',
                'Description' => 'whsec_xxx for webhook signature verification'
            ],
            'stripe_connect_client_id' => [
                'FriendlyName' => 'Stripe Connect Client ID',
                'Type' => 'text',
                'Size' => '120',
                'Description' => 'ca_xxx for OAuth (Standard Connect)'
            ],
            'ops_whmcs_upstream' => [
                'FriendlyName' => 'Ops WHMCS Upstream',
                'Type' => 'text',
                'Size' => '120',
                'Default' => 'http://billing.internal',
                'Description' => 'Base URL of WHMCS upstream for signup.* hosts (reverse proxy target).',
            ],
            'acme_selftest_ip' => [
                'FriendlyName' => 'ACME Self-Test IP',
                'Type' => 'text',
                'Size' => '64',
                'Description' => 'Optional. Sets ACME_SELFTEST_IP environment for cert issuance.',
            ],
            // WHMCS wiring
            'whitelabel_template_pid' => [
                'FriendlyName' => 'Template Product PID',
                'Type' => 'text',
                'Size' => '8',
                'Description' => 'Product to clone per tenant (PID)',
            ],
            'server_module_name' => [
                'FriendlyName' => 'Server Module Name',
                'Type' => 'text',
                'Size' => '40',
                'Default' => 'comet',
            ],
            // DEV mode toggles
            'whitelabel_dev_mode' => [
                'FriendlyName' => 'DEV Mode',
                'Type' => 'yesno',
            ],
            'whitelabel_dev_fixture_dir' => [
                'FriendlyName' => 'DEV Fixtures Dir',
                'Type' => 'text',
                'Size' => '120',
            ],
            'whitelabel_dev_skip_dns' => [
                'FriendlyName' => 'DEV: Skip DNS',
                'Type' => 'yesno',
            ],
            'whitelabel_dev_skip_cert' => [
                'FriendlyName' => 'DEV: Skip Certbot',
                'Type' => 'yesno',
            ],
            'whitelabel_dev_skip_nginx' => [
                'FriendlyName' => 'DEV: Skip nginx',
                'Type' => 'yesno',
            ],
            // NFR Settings
            'nfr_enable' => [
                'FriendlyName' => 'NFR: Enable',
                'Type' => 'yesno',
                'Description' => 'Enable NFR application flow and admin tracking',
            ],
            'nfr_pids' => [
                'FriendlyName' => 'NFR: Product IDs',
                'Type' => 'text',
                'Size' => '120',
                'Description' => 'Comma-separated list of WHMCS Product IDs eligible for NFR',
            ],
            'nfr_admin_email' => [
                'FriendlyName' => 'NFR: Admin notify email',
                'Type' => 'text',
                'Size' => '120',
                'Description' => 'Where NFR submissions and approvals are sent',
            ],
            'nfr_require_approval' => [
                'FriendlyName' => 'NFR: Require approval',
                'Type' => 'yesno',
                'Description' => 'When off, single-PID flows may auto-approve. Multiple PIDs always require approval.',
            ],
            'nfr_default_duration_days' => [
                'FriendlyName' => 'NFR: Default duration (days)',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '365',
            ],
            'nfr_default_quota_gib' => [
                'FriendlyName' => 'NFR: Default quota (GiB)',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '0',
            ],
            'nfr_max_active_per_client' => [
                'FriendlyName' => 'NFR: Max active grants per client',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '1',
            ],
            'nfr_conversion_behavior' => [
                'FriendlyName' => 'NFR: Conversion behavior',
                'Type' => 'text',
                'Size' => '60',
                'Default' => 'suspend',
                'Description' => 'suspend | convert_to_pid:<id> | do_nothing',
            ],
            'nfr_captcha' => [
                'FriendlyName' => 'NFR: Captcha on client form',
                'Type' => 'yesno',
                'Description' => 'Uses Cloudflare Turnstile settings when enabled',
            ],
            'nfr_auto_ticket' => [
                'FriendlyName' => 'NFR: Auto-create ticket on approval',
                'Type' => 'yesno',
            ],
            'nfr_admin_email_template' => [
                'FriendlyName' => 'NFR: Admin email template (optional)',
                'Type' => 'dropdown',
                'Options' => eazybackup_EmailTemplatesLoader(),
                'Description' => 'If set, this template is used as a reference for admin notifications. Custom send is used to the configured admin email.'
            ],
            'nfr_end_admin_email_template' => [
                'FriendlyName' => 'NFR: End-date Admin Email Template',
                'Type' => 'dropdown',
                'Options' => eazybackup_EndReminderTemplateLoader(),
                'Description' => 'Choose from General or Product/Service templates. We send to the admin address using this template content.'
            ],
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
 * Load Comet servers from WHMCS Servers (tblservers)
 */
function eazybackup_CometServersLoader(): array
{
    $opts = [];
    try {
        $rows = Capsule::table('tblservers')->where('type','comet')->orderBy('name','asc')->get();
        foreach ($rows as $r) {
            $label = trim((string)($r->name ?? ''));
            $host  = trim((string)($r->hostname ?? ''));
            if ($host !== '') { $label .= ' (' . $host . ')'; }
            $opts[(string)$r->id] = $label;
        }
    } catch (\Throwable $_) { /* ignore */ }
    if (empty($opts)) { $opts = ['' => '— No Comet servers found —']; }
    return $opts;
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
 * List Admin email templates by name for SendAdminEmail(messagename).
 */
function eazybackup_AdminEmailTemplatesLoader(): array
{
    try {
        $results = localAPI('GetEmailTemplates', ['type' => 'admin']);
        $out = [];
        if (isset($results['emailtemplates']['emailtemplate']) && is_array($results['emailtemplates']['emailtemplate'])) {
            foreach ($results['emailtemplates']['emailtemplate'] as $tpl) {
                $name = (string)($tpl['name'] ?? '');
                if ($name !== '') { $out[$name] = $name; }
            }
        }
        return $out ?: ['' => '— No admin templates —'];
    } catch (\Throwable $e) {
        return ['' => '— Error loading admin templates —'];
    }
}

/**
 * End-date reminder template loader: show General and Product/Service templates.
 * Keys encode "type:id" for later retrieval of subject/body.
 */
function eazybackup_EndReminderTemplateLoader(): array
{
    $opts = ['' => '— None (use built-in message) —'];
    try {
        foreach (['general' => 'General', 'product' => 'Product/Service'] as $type => $label) {
            $res = localAPI('GetEmailTemplates', ['type' => $type]);
            if (isset($res['emailtemplates']['emailtemplate']) && is_array($res['emailtemplates']['emailtemplate'])) {
                foreach ($res['emailtemplates']['emailtemplate'] as $tpl) {
                    $id = isset($tpl['id']) ? (string)$tpl['id'] : '';
                    $name = (string)($tpl['name'] ?? '');
                    if ($id !== '' && $name !== '') {
                        $opts[$type . ':' . $id] = $label . ' — ' . $name;
                    }
                }
            }
        }
    } catch (\Throwable $_) {}
    return $opts;
}



/** Build a helper description for admin settings showing client groups with ids */
function eazybackup_AdminGroupsDescription(): string {
    try {
        $rows = Capsule::table('tblclientgroups')->select('id','groupname')->orderBy('id')->get();
        $savedCsv = Capsule::table('tbladdonmodules')
            ->where('module','eazybackup')
            ->where('setting','resellergroups')
            ->value('value');
        $selected = [];
        if (is_string($savedCsv) && $savedCsv !== '') {
            foreach (explode(',', $savedCsv) as $v) {
                $v = trim($v);
                if ($v !== '') { $selected[(int)$v] = true; }
            }
        }

        $available = [];
        $resellers = [];
        foreach ($rows as $r) {
            $entry = [ 'id' => (int)$r->id, 'name' => (string)$r->groupname ];
            if (isset($selected[$entry['id']])) { $resellers[] = $entry; } else { $available[] = $entry; }
        }

        // Build dual-pane UI; synchronize to the textarea named 'resellergroups'
        ob_start();
        ?>
<style>
  .eb-dual { display:flex; gap:16px; align-items:flex-start; margin-top:8px; }
  .eb-dual .pane { width: 50%; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; }
  .eb-dual .pane h4 { margin:0; padding:8px 10px; font-weight:600; background:#e2e8f0; border-bottom:1px solid #cbd5e1; }
  .eb-dual .pane ul { list-style:none; margin:0; padding:8px; max-height:220px; overflow:auto; }
  .eb-dual .pane li { padding:6px 8px; margin-bottom:6px; background:#fff; border:1px solid #e5e7eb; border-radius:4px; cursor:pointer; transition:background-color .15s ease,border-color .15s ease, box-shadow .15s ease; }
  .eb-dual .pane li:hover { background:#f8fafc; }
  .eb-dual .pane li.eb-selected { background:#e0f2fe; border-color:#38bdf8; box-shadow:0 0 0 2px rgba(56,189,248,.25) inset; }
  .eb-dual .actions { display:flex; flex-direction:column; gap:8px; }
  .eb-dual .actions button { padding:6px 10px; }
  .eb-muted { color:#64748b; font-size:12px; margin-top:6px; }
</style>
<div id="eb-reseller-groups-ui">
  <input type="hidden" name="resellergroups" id="eb-resellergroups" value="<?= htmlspecialchars($savedCsv ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
  <div class="eb-dual">
    <div class="pane" id="eb-pane-available">
      <h4>Available groups</h4>
      <ul>
        <?php foreach ($available as $g): ?>
          <li data-id="<?= (int)$g['id'] ?>"><?php echo (int)$g['id']; ?> — <?php echo htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="actions">
      <button type="button" id="eb-move-right" class="btn btn-default">&gt;&gt;</button>
      <button type="button" id="eb-move-left" class="btn btn-default">&lt;&lt;</button>
    </div>
    <div class="pane" id="eb-pane-selected">
      <h4>Reseller groups</h4>
      <ul>
        <?php foreach ($resellers as $g): ?>
          <li data-id="<?= (int)$g['id'] ?>"><?php echo (int)$g['id']; ?> — <?php echo htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="eb-muted">Tip: Click to select; double‑click to move. The selected IDs are saved to the hidden field when you click Save Changes.</div>
</div>
<script>
  (function(){
    function q(sel){ return document.querySelector(sel); }
    function all(sel,root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }
    function findField(){ return document.querySelector('[name$="[resellergroups]"]') || document.querySelector('[name="resellergroups"]'); }
    function val(){ var h = q('#eb-resellergroups'); if (h && h.value) return h.value; var f = findField(); return f ? f.value : ''; }
    function setVal(v){ var h = q('#eb-resellergroups'); if (h){ h.value = v; } var f = findField(); if (f){ f.value = v; } }
    function sync(){ var ids = all('#eb-pane-selected ul li').map(function(li){ return li.getAttribute('data-id'); }); setVal(ids.join(', ')); }
    function move(fromSel, toSel){ var sel = all(fromSel+' ul li.eb-selected'); sel.forEach(function(li){ li.classList.remove('eb-selected'); q(toSel+' ul').appendChild(li); }); sync(); }
    function toggle(li){ li.classList.toggle('eb-selected'); }
    function dbl(fromSel, toSel){ return function(ev){ var li = ev.target.closest('li'); if (!li) return; q(toSel+' ul').appendChild(li); sync(); }}
    function clk(ev){ var li = ev.target.closest('li'); if (!li) return; toggle(li); }
    var avail = q('#eb-pane-available'); var sel = q('#eb-pane-selected'); if (!avail || !sel) return;
    // hide the raw textarea
    var ta = findField(); if (ta) { ta.style.display='none'; }
    avail.addEventListener('click', clk); sel.addEventListener('click', clk);
    avail.addEventListener('dblclick', dbl('#eb-pane-available','#eb-pane-selected'));
    sel.addEventListener('dblclick', dbl('#eb-pane-selected','#eb-pane-available'));
    var btnR = q('#eb-move-right'); var btnL = q('#eb-move-left');
    if (btnR) btnR.addEventListener('click', function(){ move('#eb-pane-available','#eb-pane-selected'); });
    if (btnL) btnL.addEventListener('click', function(){ move('#eb-pane-selected','#eb-pane-available'); });
    // ensure textarea reflects current selected list on load
    sync();
    // ensure value is synced at submit time
    var form = q('#eb-reseller-groups-ui');
    while (form && form.tagName !== 'FORM') { form = form.parentElement; }
    if (form) { form.addEventListener('submit', function(){ sync(); }); }
  })();
</script>
<?php
        return ob_get_clean();
    } catch (\Throwable $e) {
        return 'Enter a comma-separated list of client group IDs to classify as resellers.';
    }
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
    } else if ($_REQUEST["a"] == "nfr-apply") {
        // Client NFR application form
        $ret = require __DIR__ . "/pages/client/nfr-apply.php";
        return $ret;
    } else if ($_REQUEST["a"] == "email-reports") {
        // Isolated Email Reports AJAX endpoint
        require_once __DIR__ . "/pages/console/email-reports.php";
        exit; // script handles output
    } else if ($_REQUEST["a"] == "job-reports") {
        // Isolated Job Reports AJAX endpoint (shared between profile and dashboard)
        require_once __DIR__ . "/pages/console/job-reports.php";
        exit; // script handles output
    } else if ($_REQUEST["a"] == "job-reports-global") {
        // Global job reports endpoint (across all client services)
        require_once __DIR__ . "/pages/console/job-reports-global.php";
        exit; // script handles output
    } else if ($_REQUEST["a"] == "job-logs") {
        require_once __DIR__ . "/pages/console/job-logs-global.php";
        return eazybackup_job_logs_global($vars);
    } else if ($_REQUEST["a"] == "notifications") {
        // Simple client list of recent notifications (scoped)
        require_once __DIR__ . "/pages/notifications.php";
        exit; // script outputs HTML
    } else if ($_REQUEST["a"] == "notify-settings") {
        // Client-managed notification preferences
        try {
            if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
                return [
                    "pagetitle" => "Notification Preferences",
                    "templatefile" => "templates/error",
                    "requirelogin" => true,
                    "vars" => ["error" => "Not authenticated"],
                ];
            }
            $clientId = (int)$_SESSION['uid'];
            $successful = false;
            $errormessage = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $notify_storage = isset($_POST['notify_storage']) ? 1 : 0;
                $notify_devices = isset($_POST['notify_devices']) ? 1 : 0;
                $notify_addons  = isset($_POST['notify_addons'])  ? 1 : 0;
                $routing_policy = isset($_POST['routing_policy']) ? strtolower(trim((string)$_POST['routing_policy'])) : 'billing';
                if (!in_array($routing_policy, ['primary','billing','technical','custom'], true)) {
                    $routing_policy = 'billing';
                }
                $custom_recipients = isset($_POST['custom_recipients']) ? trim((string)$_POST['custom_recipients']) : '';
                $show_upcoming = isset($_POST['show_upcoming_charges']) ? 1 : 0;

                try {
                    Capsule::table('eb_client_notify_prefs')->updateOrInsert(
                        ['client_id' => $clientId],
                        [
                            'notify_storage' => $notify_storage,
                            'notify_devices' => $notify_devices,
                            'notify_addons' => $notify_addons,
                            'routing_policy' => $routing_policy,
                            'custom_recipients' => $custom_recipients,
                            'show_upcoming_charges' => $show_upcoming,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                    try { logActivity('eazybackup: Client updated notification preferences: storage=' . $notify_storage . ', devices=' . $notify_devices . ', addons=' . $notify_addons . ', routing=' . $routing_policy . ', upcoming=' . $show_upcoming, $clientId); } catch (\Throwable $_) {}
                    $successful = true;
                } catch (\Throwable $e) {
                    $errormessage = 'Failed to save preferences.';
                }
            }

            $prefs = [
                'notify_storage' => 1,
                'notify_devices' => 1,
                'notify_addons' => 1,
                'routing_policy' => 'billing',
                'custom_recipients' => '',
                'show_upcoming_charges' => 1,
            ];
            try {
                $row = Capsule::table('eb_client_notify_prefs')->where('client_id', $clientId)->first();
                if ($row) {
                    $prefs['notify_storage'] = (int)$row->notify_storage;
                    $prefs['notify_devices'] = (int)$row->notify_devices;
                    $prefs['notify_addons']  = (int)$row->notify_addons;
                    $prefs['routing_policy'] = (string)$row->routing_policy;
                    $prefs['custom_recipients'] = (string)($row->custom_recipients ?? '');
                    $prefs['show_upcoming_charges'] = (int)$row->show_upcoming_charges;
                }
            } catch (\Throwable $_) { /* defaults */ }

            return [
                "pagetitle" => "Notifications",
                "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
                "templatefile" => "templates/clientarea/notify-settings",
                "requirelogin" => true,
                "forcessl" => true,
                "vars" => array_merge($vars, [
                    'prefs' => $prefs,
                    'successful' => $successful,
                    'errormessage' => $errormessage,
                ]),
            ];
        } catch (\Throwable $e) {
            return [
                "pagetitle" => "Notifications",
                "templatefile" => "templates/error",
                "requirelogin" => true,
                "vars" => ["error" => "Server error"],
            ];
        }
    } else if ($_REQUEST["a"] == "bulk_validate") {
        header('Content-Type: application/json');
        try {
            if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
                echo json_encode(['ok' => false, 'errors' => ['Not authenticated'], 'row_errors' => []]);
                exit;
            }
            $clientId = (int)$_SESSION['uid'];
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $mode = (string)($payload['mode'] ?? '');
            $pid  = (int)($payload['product_pid'] ?? 0);
            $term = (string)($payload['billing_term'] ?? 'monthly');
            $consolidated = (int)($payload['consolidated_billing'] ?? 0);
            $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

            if ($mode !== 'bulk') { echo json_encode(['ok'=>false,'errors'=>['Invalid mode'],'row_errors'=>[]]); exit; }
            if (!in_array($pid, [52,53,54,58,60,57], true)) { echo json_encode(['ok'=>false,'errors'=>['Invalid product selection'],'row_errors'=>[]]); exit; }
            $currencyData = getCurrency($clientId);
            $currencyId = (int)($currencyData['id'] ?? 1);

            $res = eb_bulk_validate_rows($clientId, $pid, $term, $rows, $currencyId);
            // Add estimate string
            $perAccount = eazybackup_estimate_amount_for_pid($pid, $term === 'annual' ? 'annually' : 'monthly', $currencyId);
            $estimateFloat = $perAccount * (int)($res['valid_count'] ?? 0);
            $estimateStr = eazybackup_format_currency($estimateFloat, $currencyId) . ' / ' . ($term === 'annual' ? 'annually' : 'monthly');
            $ok = empty($res['errors']) && empty($res['row_errors']);
            echo json_encode([
                'ok' => $ok,
                'summary' => 'Validated ' . (int)($res['valid_count'] ?? 0) . ' rows; ' . (int)($res['error_count'] ?? 0) . ' errors.',
                'estimate' => $ok ? $estimateStr : null,
                'errors' => $res['errors'] ?? [],
                'row_errors' => $res['row_errors'] ?? [],
            ]);
            exit;
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'errors'=>['Server error'],'row_errors'=>[]]);
            exit;
        }
    } else if ($_REQUEST["a"] == "bulk_create") {
        header('Content-Type: application/json');
        try {
            if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
                echo json_encode(['ok' => false, 'errors' => ['Not authenticated']]);
                exit;
            }
            $clientId = (int)$_SESSION['uid'];
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $pid  = (int)($payload['product_pid'] ?? 0);
            $term = (string)($payload['billing_term'] ?? 'monthly');
            $consolidated = (int)($payload['consolidated_billing'] ?? 0);
            $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

            if (!in_array($pid, [52,53,54,58,60,57], true)) { echo json_encode(['ok'=>false,'errors'=>['Invalid product selection']]); exit; }
            // Require payment method when default gateway is stripe
            $defaultGateway = (string)(Capsule::table('tblclients')->where('id', $clientId)->value('defaultgateway') ?? '');
            $needsCard = (strtolower($defaultGateway) === 'stripe');
            $hasCard = false;
            if ($needsCard) {
                try {
                    if (class_exists('\\WHMCS\\Payment\\PayMethod\\PayMethod')) {
                        $hasCard = (\WHMCS\Payment\PayMethod\PayMethod::where('userid', $clientId)
                            ->whereNull('deleted_at')
                            ->whereIn('payment_type', ['CreditCard','RemoteCreditCard'])
                            ->where('gateway_name', 'stripe')
                            ->count()) > 0;
                    } else if (Capsule::schema()->hasTable('tblpaymethods')) {
                        $hasCard = Capsule::table('tblpaymethods')
                            ->where('userid', $clientId)
                            ->whereNull('deleted_at')
                            ->whereIn('payment_type', ['CreditCard','RemoteCreditCard'])
                            ->where('gateway_name', 'stripe')
                            ->exists();
                    }
                } catch (\Throwable $_) { $hasCard = false; }
                if (!$hasCard) { echo json_encode(['ok'=>false,'errors'=>['Payment method required']]); exit; }
            }

            $currencyData = getCurrency($clientId);
            $currencyId = (int)($currencyData['id'] ?? 1);
            $validation = eb_bulk_validate_rows($clientId, $pid, $term, $rows, $currencyId);
            if (!empty($validation['errors']) || !empty($validation['row_errors'])) {
                echo json_encode(['ok'=>false,'errors'=>['Validation failed'],'row_errors'=>$validation['row_errors']]);
                exit;
            }

            $results = eazybackup_bulk_create_accounts($clientId, $pid, $term, $consolidated, $rows, $currencyId);
            echo json_encode($results);
            exit;
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'errors'=>['Server error']]);
            exit;
        }
    } else if ($_REQUEST["a"] == "bulk_csv_sample") {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bulk_sample.csv"');
        echo "username,password,email\n";
        echo "acme-001,ExamplePass!234,backupreports+001@example.com\n";
        echo "acme-002,ExamplePass!234,backupreports+002@example.com\n";
        echo "acme-003,ExamplePass!234,backupreports+003@example.com\n";
        exit;
    } else if ($_REQUEST["a"] == "user-actions") {
        // Isolated User Actions AJAX endpoint
        require_once __DIR__ . "/pages/console/user-actions.php";
        exit; // script handles output
    } else if ($_REQUEST["a"] == "device-actions") {
        // Isolated Device Actions AJAX endpoint
        require_once __DIR__ . "/pages/console/device-actions.php";
        exit; // script handles output
    } else if ($_REQUEST["a"] == "pulse-events") {
        // SSE: Protection Pulse live stream
        require_once __DIR__ . "/pages/console/pulse.php";
        eb_pulse_events();
        exit;
    } else if ($_REQUEST["a"] == "pulse-snapshot") {
        // JSON snapshot for reconnects / initial load
        require_once __DIR__ . "/pages/console/pulse.php";
        eb_pulse_snapshot();
        exit;
    } else if ($_REQUEST["a"] == "pulse-snooze") {
        // Snooze incidents
        require_once __DIR__ . "/pages/console/pulse.php";
        eb_pulse_snooze();
        exit;
    } else if ($_REQUEST["a"] == "notification-dismiss") {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'message'=>'Invalid method']); exit; }
        if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['ok'=>false,'message'=>'Not authenticated']); exit; }
        if (!function_exists('check_token') || !check_token('WHMCS.clientarea.default')) { echo json_encode(['ok'=>false,'message'=>'Invalid token']); exit; }
        $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
        if ($notificationId <= 0) { echo json_encode(['ok'=>false,'message'=>'Invalid notification']); exit; }
        $clientId = (int)$_SESSION['uid'];
        try {
            $row = Capsule::table('eb_notifications_sent as n')
                ->join('tblhosting', 'tblhosting.id', '=', 'n.service_id')
                ->where('n.id', $notificationId)
                ->where('tblhosting.userid', $clientId)
                ->select('n.id')
                ->first();
            if (!$row) {
                echo json_encode(['ok'=>false,'message'=>'Notification not found']); exit;
            }
            Capsule::table('eb_notifications_sent')
                ->where('id', $notificationId)
                ->update([
                    'acknowledged_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            echo json_encode(['ok'=>true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'message'=>'Failed to dismiss notification']);
        }
        exit;
    } else if ($_REQUEST["a"] == "tos-block") {
        // Blocking page with Legal Agreements modal (TOS + Privacy)
        if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
            return ['pagetitle' => 'Legal Agreements', 'templatefile' => 'templates/error', 'requirelogin' => true, 'vars' => ['error' => 'Not authenticated']];
        }
        $tos = null;
        $privacy = null;
        try { $tos = Capsule::table('eb_tos_versions')->where('is_active', 1)->orderBy('published_at', 'desc')->first(); } catch (\Throwable $_) {}
        try { $privacy = Capsule::table('eb_privacy_versions')->where('is_active', 1)->orderBy('published_at', 'desc')->first(); } catch (\Throwable $_) {}
        if (!$tos && !$privacy) {
            // Nothing to show; fall back to dashboard
            header('Location: index.php?m=eazybackup&a=dashboard'); exit;
        }
        $requireTos = ($tos && (int)($tos->require_acceptance ?? 0) === 1) ? 1 : 0;
        $requirePrivacy = ($privacy && (int)($privacy->require_acceptance ?? 0) === 1) ? 1 : 0;
        $returnTo = isset($_GET['return_to']) ? (string)$_GET['return_to'] : 'clientarea.php';
        // Limit return_to to same origin paths only
        if (strpos($returnTo, 'http://') === 0 || strpos($returnTo, 'https://') === 0) { $returnTo = 'clientarea.php'; }
        return [
            'pagetitle' => 'Legal Agreements',
            'templatefile' => 'templates/tos-block',
            'requirelogin' => true,
            'vars' => array_merge($vars, [
                'tos' => $tos,
                'privacy' => $privacy,
                'require_tos' => $requireTos,
                'require_privacy' => $requirePrivacy,
                'return_to' => $returnTo,
            ]),
        ];
    } else if ($_REQUEST["a"] == "legal-accept" || $_REQUEST["a"] == "tos-accept") {
        // Record acceptance for TOS and/or Privacy Policy and redirect back
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: clientarea.php'); exit; }
        if (!function_exists('check_token') || !check_token('WHMCS.clientarea.default')) { header('Location: clientarea.php'); exit; }
        $clientId = (int)($_SESSION['uid'] ?? 0);
        if ($clientId <= 0) { header('Location: clientarea.php'); exit; }
        $contactId = (int)($_SESSION['cid'] ?? 0);
        $tosVersion = (string)($_POST['tos_version'] ?? '');
        $privacyVersion = (string)($_POST['privacy_version'] ?? '');

        // Backfill missing versions from active docs
        if ($tosVersion === '') {
            try {
                $activeTos = Capsule::table('eb_tos_versions')->where('is_active', 1)->orderBy('published_at', 'desc')->first();
                $tosVersion = $activeTos ? (string)$activeTos->version : '';
            } catch (\Throwable $_) {}
        }
        if ($privacyVersion === '') {
            try {
                $activePriv = Capsule::table('eb_privacy_versions')->where('is_active', 1)->orderBy('published_at', 'desc')->first();
                $privacyVersion = $activePriv ? (string)$activePriv->version : '';
            } catch (\Throwable $_) {}
        }

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        $recordAcceptance = function (string $clientTable, string $userTable, string $versionField, string $version) use ($clientId, $contactId, $ip, $ua) {
            if ($version === '') {
                return;
            }
            // Client-level acceptance (once per version)
            try {
                Capsule::table($clientTable)->updateOrInsert(
                    ['client_id' => $clientId, $versionField => $version],
                    ['accepted_at' => date('Y-m-d H:i:s'), 'ip_address' => $ip, 'user_agent' => $ua]
                );
            } catch (\Throwable $_) {}

            // Per-user acceptance (owner: null ids; sub-user: contact_id)
            try {
                $conds = ['client_id' => $clientId, $versionField => $version];
                if ($contactId > 0) {
                    $conds['contact_id'] = $contactId;
                } else {
                    $conds['user_id'] = null;
                    $conds['contact_id'] = null;
                }
                Capsule::table($userTable)->updateOrInsert(
                    $conds,
                    ['accepted_at' => date('Y-m-d H:i:s'), 'ip_address' => $ip, 'user_agent' => $ua]
                );
            } catch (\Throwable $_) {}
        };

        $recordAcceptance('eb_tos_client_acceptances', 'eb_tos_user_acceptances', 'tos_version', $tosVersion);
        $recordAcceptance('eb_privacy_client_acceptances', 'eb_privacy_user_acceptances', 'privacy_version', $privacyVersion);

        $returnTo = isset($_POST['return_to']) ? (string)$_POST['return_to'] : 'clientarea.php';
        if (strpos($returnTo, 'http://') === 0 || strpos($returnTo, 'https://') === 0) { $returnTo = 'clientarea.php'; }

        // If this client is flagged for first-login password onboarding, send them
        // directly to the bespoke password panel instead of the original page.
        // Updated: Keep user on intended destination; the dashboard will open a modal if needed.
        $target = $returnTo;

        header('Location: ' . $target); exit;
    } else if ($_REQUEST["a"] == "terms") {
        // Client Legal Agreements page: show current user's accepted versions/times and link to view
        if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
            return ['pagetitle' => 'Legal Agreements', 'templatefile' => 'templates/error', 'requirelogin' => true, 'vars' => ['error' => 'Not authenticated']];
        }
        $clientId = (int)$_SESSION['uid'];
        $contactId = (int)($_SESSION['cid'] ?? 0);

        $getMostRecentAcceptance = function (string $userTable, string $clientTable, string $versionField) use ($clientId, $contactId) {
            $uaRow = null;
            $caRow = null;
            try {
                $q = Capsule::table($userTable)->where('client_id', $clientId);
                if ($contactId > 0) { $q->where('contact_id', $contactId); } else { $q->whereNull('user_id')->whereNull('contact_id'); }
                $uaRow = $q->orderBy('accepted_at', 'desc')->first();
            } catch (\Throwable $_) { $uaRow = null; }
            try {
                $caRow = Capsule::table($clientTable)->where('client_id', $clientId)->orderBy('accepted_at','desc')->first();
            } catch (\Throwable $_) { $caRow = null; }
            return [
                'version' => $uaRow->{$versionField} ?? ($caRow->{$versionField} ?? null),
                'accepted_at' => $uaRow->accepted_at ?? ($caRow->accepted_at ?? null),
                'ip' => $uaRow->ip_address ?? ($caRow->ip_address ?? null),
                'ua' => $uaRow->user_agent ?? ($caRow->user_agent ?? null),
            ];
        };

        $tosAcc = $getMostRecentAcceptance('eb_tos_user_acceptances', 'eb_tos_client_acceptances', 'tos_version');
        $privacyAcc = $getMostRecentAcceptance('eb_privacy_user_acceptances', 'eb_privacy_client_acceptances', 'privacy_version');

        // Resolve user name/email
        $name = trim(($vars['clientsdetails']['firstname'] ?? '') . ' ' . ($vars['clientsdetails']['lastname'] ?? ''));
        $email = (string)($vars['clientsdetails']['email'] ?? '');
        return [
            'pagetitle' => 'Legal Agreements',
            'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
            'templatefile' => 'templates/terms',
            'requirelogin' => true,
            'vars' => array_merge($vars, [
                'tos_accepted_version' => $tosAcc['version'],
                'tos_accepted_at' => $tosAcc['accepted_at'],
                'tos_accepted_ip' => $tosAcc['ip'],
                'tos_accepted_ua' => $tosAcc['ua'],
                'privacy_accepted_version' => $privacyAcc['version'],
                'privacy_accepted_at' => $privacyAcc['accepted_at'],
                'privacy_accepted_ip' => $privacyAcc['ip'],
                'privacy_accepted_ua' => $privacyAcc['ua'],
                'user_name' => $name,
                'user_email' => $email,
            ]),
        ];
    } else if ($_REQUEST["a"] == "tos-view") {
        // Render a specific TOS version (read-only)
        $ver = isset($_GET['version']) ? (string)$_GET['version'] : '';
        if ($ver === '') {
            $row = Capsule::table('eb_tos_versions')->where('is_active', 1)->orderBy('published_at', 'desc')->first();
        } else {
            $row = Capsule::table('eb_tos_versions')->where('version', $ver)->first();
        }
        if (!$row) {
            return [
                'pagetitle' => 'Terms of Service',
                'templatefile' => 'templates/error',
                'requirelogin' => true,
                'vars' => ['error' => 'Terms not found'],
            ];
        }
        return [
            'pagetitle' => 'Terms of Service',
            'templatefile' => 'templates/tos-view',
            'requirelogin' => true,
            'vars' => array_merge($vars, ['tos' => $row]),
        ];
    } else if ($_REQUEST["a"] == "privacy-view") {
        // Render a specific Privacy Policy version (read-only)
        $ver = isset($_GET['version']) ? (string)$_GET['version'] : '';
        if ($ver === '') {
            $row = Capsule::table('eb_privacy_versions')->where('is_active', 1)->orderBy('published_at', 'desc')->first();
        } else {
            $row = Capsule::table('eb_privacy_versions')->where('version', $ver)->first();
        }
        if (!$row) {
            return [
                'pagetitle' => 'Privacy Policy',
                'templatefile' => 'templates/error',
                'requirelogin' => true,
                'vars' => ['error' => 'Privacy Policy not found'],
            ];
        }
        return [
            'pagetitle' => 'Privacy Policy',
            'templatefile' => 'templates/privacy-view',
            'requirelogin' => true,
            'vars' => array_merge($vars, ['privacy' => $row]),
        ];
    } else if ($_REQUEST["a"] == "admin-workers") {
        // Admin JSON endpoint: systemd workers status (read-only)
        require_once __DIR__ . "/pages/admin/workers.php";
        exit; // script handles output
    } else if ($_REQUEST["a"] == "storage-history") {
        // JSON: per-user storage history (daily maxima)
        require_once __DIR__ . "/pages/console/storage_history.php";
        eb_storage_history();
        exit;
    } else if ($_REQUEST["a"] == "dashboard-usage-metrics") {
        // JSON: dashboard cards metrics and trend series
        require_once __DIR__ . "/pages/console/dashboard_usage_metrics.php";
        eb_dashboard_usage_metrics();
        exit;
    } else if ($_REQUEST["a"] == "device-groups") {
        // JSON: client-scoped device grouping (Phase 1)
        require_once __DIR__ . "/pages/console/device_groups.php";
        eb_device_groups();
        exit;
    } else if ($_REQUEST["a"] == "dashboard") {
        // Load the dashboard backend logic.
        $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
        // Determine initial dashboard tab from query param with whitelist
        $tabParam = isset($_GET['tab']) ? strtolower(trim($_GET['tab'])) : '';
        $allowedTabs = ['dashboard', 'users'];
        $initialTab = in_array($tabParam, $allowedTabs, true) ? $tabParam : 'dashboard';
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
            ->whereNull('revoked_at')
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

        $onlineDevices = Capsule::table('comet_devices')
            ->where('client_id', $clientId)
            ->whereIn('username', $activeUsernames)
            ->whereNull('revoked_at')
            ->where('is_active', 1)
            ->count();

        $offlineDevices = Capsule::table('comet_devices')
            ->where('client_id', $clientId)
            ->whereIn('username', $activeUsernames)
            ->whereNull('revoked_at')
            ->where('is_active', 0)
            ->count();

        $engineCountsRow = Capsule::table('comet_items')
            ->selectRaw("SUM(CASE WHEN type = 'engine1/file' THEN 1 ELSE 0 END) AS files_folders")
            ->selectRaw("SUM(CASE WHEN type = 'engine1/windisk' THEN 1 ELSE 0 END) AS disk_image")
            ->selectRaw("SUM(CASE WHEN type = 'engine1/hyperv' THEN 1 ELSE 0 END) AS hyper_v")
            ->selectRaw("SUM(CASE WHEN type = 'engine1/mssql' THEN 1 ELSE 0 END) AS sql_server")
            ->selectRaw("SUM(CASE WHEN type = 'engine1/winmsofficemail' THEN 1 ELSE 0 END) AS office_365")
            ->selectRaw("SUM(CASE WHEN type = 'engine1/systemstate' THEN 1 ELSE 0 END) AS system_state")
            ->selectRaw("SUM(CASE WHEN type = 'engine1/proxmox' THEN 1 ELSE 0 END) AS proxmox")
            ->where('client_id', $clientId)
            ->whereIn('username', $activeUsernames)
            ->first();

        $protectedItemEngineCounts = [
            'files_folders' => (int)($engineCountsRow->files_folders ?? 0),
            'disk_image' => (int)($engineCountsRow->disk_image ?? 0),
            'hyper_v' => (int)($engineCountsRow->hyper_v ?? 0),
            'sql_server' => (int)($engineCountsRow->sql_server ?? 0),
            'office_365' => (int)($engineCountsRow->office_365 ?? 0),
            'system_state' => (int)($engineCountsRow->system_state ?? 0),
            'proxmox' => (int)($engineCountsRow->proxmox ?? 0),
        ];

        // Get all devices for the client, but only from active WHMCS services
        // Include raw DeviceID in 'hash' so we can derive a stable join key for jobs
        $devices = Capsule::table('comet_devices')
            ->select('id', 'hash', 'is_active', 'name', 'username', 'content')
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
            // Derive the stable Comet device key used in comet_jobs: sha256(client_id . OwnerDevice)
            // Fall back to the stored id if raw hash is unavailable
            $cometDeviceKey = (!empty($device->hash)) ? hash('sha256', (string)$clientId . $device->hash) : $device->id;
            // 1) grab the raw rows
            $rawJobs = Capsule::table('comet_jobs')
                ->select(
                    'comet_jobs.id as job_guid',
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
                ->where('comet_jobs.comet_device_id', $cometDeviceKey)
                ->whereDate('started_at', '>=', $startDate)
                ->whereDate('started_at', '<=', $endDate)
                ->orderBy('comet_jobs.started_at', 'desc')
                ->get();  // this gives you a Collection of StdClass
        
            // 2) map over them and overwrite the two fields your tooltip uses
            $device->jobs = $rawJobs->map(function($job) {
                // format with the Comet helper
                $job->Uploaded          = comet_HumanFileSize($job->upload_bytes);
                $job->SelectedDataSize  = comet_HumanFileSize($job->total_bytes);
                // Provide common job id keys expected by the frontend
                $guid = $job->job_guid ?? '';
                if ($guid) {
                    $job->JobID = $guid; // table modal expects JobID
                    $job->GUID  = $guid; // fallback used in dashboard
                    $job->id    = $guid; // generic fallback
                    $job->job_id = $guid; // another common alias
                }
                return $job;
            });        
          
        }

        $services = Capsule::table('tblhosting')
            ->select('username', 'id')
            ->where('domainstatus', 'Active')
            ->where('userid', $clientId)
            ->whereIn('packageid', $productIds)
            ->get();

        // Attach service id to each device for accurate modal requests
        if ($services && $devices) {
            $svcMap = [];
            foreach ($services as $svc) { $svcMap[$svc->username] = (int)$svc->id; }
            foreach ($devices as $device) {
                $uname = $device->username ?? '';
                if ($uname !== '' && isset($svcMap[$uname])) {
                    $device->serviceid = $svcMap[$uname];
                }
            }
        }

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
                ->where('is_active', 1)
                ->whereNull('removed_at')
                ->count();

            // Get the full list of vaults for the current user
            $vaultsForUser = Capsule::table('comet_vaults')
                ->select('name', 'total_bytes')
                ->where('client_id', $clientId)
                ->where('username', $service->username)
                ->where('is_active', 1)
                ->whereNull('removed_at')
                ->get()
                ->map(function ($vault) {
                    // Convert the vault object to an array and add the formatted size
                    $vaultArray = (array) $vault;
                    $vaultArray['size_formatted'] = Helper::humanFileSize($vaultArray['total_bytes']);
                    return $vaultArray;
                })
                ->toArray(); // This now returns an array of arrays


            // Fetch Comet user profile for account name, email reporting, and VM / M365 counts
            $accountName = '';
            $emailsCsv = '';
            $emailReportsEnabled = null; // null => unknown, bool when known
            $hvCount = 0;
            $vmwCount = 0;
            $m365Accounts = 0;
            try {
                $params = comet_ServiceParams($service->id);
                $params['username'] = $service->username;
                $server = comet_Server($params);
                if ($server) {
                    // Prefer AdminGetUserProfileAndHash (contains Profile + hash)
                    $ph = $server->AdminGetUserProfileAndHash($service->username);
                    $prof = $ph && isset($ph->Profile) ? $ph->Profile : null;
                    if (!$prof) {
                        // Fallback to AdminGetUserProfile
                        $prof = $server->AdminGetUserProfile($service->username);
                    }
                    if ($prof) {
                        $accountName = (string)($prof->AccountName ?? '');
                        $emailsArr = isset($prof->Emails) && is_array($prof->Emails) ? $prof->Emails : [];
                        $emailsCsv = implode(', ', array_values($emailsArr));
                        $emailReportsEnabled = isset($prof->SendEmailReports) ? (bool)$prof->SendEmailReports : null;

                        // QuotaOffice365ProtectedAccounts (as requested to display)
                        if (isset($prof->QuotaOffice365ProtectedAccounts)) {
                            $m365Accounts = (int)$prof->QuotaOffice365ProtectedAccounts;
                        }

                        // Sum VM counts per engine from Sources; ignore job status
                        try {
                            if (isset($prof->Sources) && is_array($prof->Sources)) {
                                // First pass: collect per-device fallback counts per engine
                                $deviceVmDefaults = [];
                                foreach ($prof->Sources as $sid => $src) {
                                    if (!is_object($src)) { continue; }
                                    $engine = isset($src->Engine) ? strtolower((string)$src->Engine) : '';
                                    if ($engine === '' || (strpos($engine, 'hyperv') === false && strpos($engine, 'vmware') === false)) { continue; }
                                    $owner = isset($src->OwnerDevice) ? (string)$src->OwnerDevice : '';
                                    $candidate = 0;
                                    if (isset($src->Statistics) && is_object($src->Statistics)) {
                                        $vm1 = 0; $vm2 = 0;
                                        if (isset($src->Statistics->LastSuccessfulBackupJob) && is_object($src->Statistics->LastSuccessfulBackupJob)) {
                                            $job1 = $src->Statistics->LastSuccessfulBackupJob;
                                            if (isset($job1->TotalVmCount) && is_numeric($job1->TotalVmCount)) { $vm1 = (int)$job1->TotalVmCount; }
                                        }
                                        if (isset($src->Statistics->LastBackupJob) && is_object($src->Statistics->LastBackupJob)) {
                                            $job2 = $src->Statistics->LastBackupJob;
                                            if (isset($job2->TotalVmCount) && is_numeric($job2->TotalVmCount)) { $vm2 = (int)$job2->TotalVmCount; }
                                        }
                                        $candidate = max($vm1, $vm2);
                                    }
                                    if ($candidate > 0 && $owner !== '') {
                                        $ek = strpos($engine, 'hyperv') !== false ? 'hyperv' : (strpos($engine, 'vmware') !== false ? 'vmware' : '');
                                        if ($ek !== '') {
                                            if (!isset($deviceVmDefaults[$owner])) { $deviceVmDefaults[$owner] = ['hyperv' => 0, 'vmware' => 0]; }
                                            if ($candidate > $deviceVmDefaults[$owner][$ek]) { $deviceVmDefaults[$owner][$ek] = $candidate; }
                                        }
                                    }
                                }
                                // Second pass: sum counts with fallbacks per item
                                foreach ($prof->Sources as $sid => $src) {
                                    if (!is_object($src)) { continue; }
                                    $engine = isset($src->Engine) ? strtolower((string)$src->Engine) : '';
                                    if ($engine === '' || (strpos($engine, 'hyperv') === false && strpos($engine, 'vmware') === false)) { continue; }
                                    $owner = isset($src->OwnerDevice) ? (string)$src->OwnerDevice : '';
                                    $totalVm = 0;
                                    if (isset($src->Statistics) && is_object($src->Statistics)) {
                                        // Prefer the larger of LastSuccessful.TotalVmCount and LastBackupJob.TotalVmCount
                                        $vm1 = 0; $vm2 = 0;
                                        if (isset($src->Statistics->LastSuccessfulBackupJob) && is_object($src->Statistics->LastSuccessfulBackupJob)) {
                                            $job1 = $src->Statistics->LastSuccessfulBackupJob;
                                            if (isset($job1->TotalVmCount) && is_numeric($job1->TotalVmCount)) { $vm1 = (int)$job1->TotalVmCount; }
                                        }
                                        if (isset($src->Statistics->LastBackupJob) && is_object($src->Statistics->LastBackupJob)) {
                                            $job2 = $src->Statistics->LastBackupJob;
                                            if (isset($job2->TotalVmCount) && is_numeric($job2->TotalVmCount)) { $vm2 = (int)$job2->TotalVmCount; }
                                        }
                                        $totalVm = max($vm1, $vm2);
                                    }
                                    // Fallback: if stats report 0, try to infer from EngineProps selections
                                    if ($totalVm === 0 && (isset($src->EngineProps) && (is_object($src->EngineProps) || is_array($src->EngineProps)))) {
                                        $props = is_object($src->EngineProps) ? get_object_vars($src->EngineProps) : $src->EngineProps;
                                        $vmKeys = 0;
                                        foreach ($props as $k => $_v) {
                                            if (is_string($k) && strpos($k, 'VM-') === 0) { $vmKeys++; }
                                        }
                                        if ($vmKeys > 0) {
                                            $totalVm = $vmKeys;
                                        } else {
                                            // If ALL_VMS is enabled, use device-level default for this engine if available; else at least 1
                                            $allVmsRaw = $props['ALL_VMS'] ?? $props['AllVms'] ?? $props['all_vms'] ?? null;
                                            $allVms = ($allVmsRaw === true || $allVmsRaw === 1 || $allVmsRaw === '1' || $allVmsRaw === 'true');
                                            if ($allVms) {
                                                $ek = strpos($engine, 'hyperv') !== false ? 'hyperv' : (strpos($engine, 'vmware') !== false ? 'vmware' : '');
                                                $fallback = ($owner !== '' && isset($deviceVmDefaults[$owner]) && $ek !== '') ? (int)$deviceVmDefaults[$owner][$ek] : 0;
                                                $totalVm = $fallback > 0 ? $fallback : 1;
                                            }
                                        }
                                    }
                                    if ($totalVm > 0) {
                                        if (strpos($engine, 'hyperv') !== false) { $hvCount += $totalVm; }
                                        if (strpos($engine, 'vmware') !== false) { $vmwCount += $totalVm; }
                                    }
                                }
                            }
                        } catch (\Throwable $e) { /* ignore per-user errors */ }
                    }
                }
            } catch (\Throwable $e) {
                // Leave defaults on error; do not disrupt dashboard rendering
            }

            $accounts[] = [
                'id' => $service->id,
                'username' => $service->username,
                'account_name' => $accountName,
                'report_emails' => $emailsCsv,
                'email_reports_enabled' => $emailReportsEnabled,
                'total_devices' => $total_devices,
                'total_protected_items' => $total_protected_items,
                'vaults' => $vaultsForUser, // Pass the detailed vault list
                'hv_vm_count' => $hvCount,
                'vmw_vm_count' => $vmwCount,
                'm365_accounts' => $m365Accounts,
            ];
        }

        // Upcoming Charges panel data: show entries until grace_expires_at (when available), otherwise recent (30 days)
        $serviceIds = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus','Active')
            ->pluck('id');
        $upcomingCharges = [];
        try {
            $now = date('Y-m-d H:i:s');
            $recentCutoff = date('Y-m-d H:i:s', strtotime('-5 days'));
            $upcomingCharges = Capsule::table('eb_notifications_sent as n')
                ->leftJoin('eb_billing_grace as g', function($j){ $j->on('g.username','=','n.username'); })
                ->whereIn('n.service_id', $serviceIds)
                ->whereNull('n.acknowledged_at')
                ->where(function($q) use ($now, $recentCutoff){
                    // keep if grace active OR no grace row (fallback to last 5 days)
                    $q->whereNotNull('g.grace_expires_at')->where('g.grace_expires_at','>=',$now)
                      ->orWhere(function($q2) use ($recentCutoff){ $q2->whereNull('g.grace_expires_at')->where('n.created_at','>=', $recentCutoff); });
                })
                ->orderBy('n.created_at','desc')
                ->limit(20)
                ->get([
                    'n.id as notification_id','n.created_at','n.category','n.subject','n.username as username',
                    'g.first_seen_at as grace_first_seen_at','g.grace_expires_at as grace_expires_at','g.grace_days as grace_days'
                ]);
        } catch (\Throwable $e) { /* table may not exist yet */ }

        // Merge the dashboard-specific data into the existing $vars array.
        return [
            "pagetitle" => "Dashboard",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/clientarea/dashboard",
            "requirelogin" => true,  // if needed
            "forcessl" => true,  // if needed
            "vars" => [
                'modulelink' => $vars['modulelink'],
                'initialTab' => $initialTab,
                'totalAccounts' => $totalAccounts,
                'totalDevices' => $totalDevices,
                'totalProtectedItems' => $totalProtectedItems,
                'totalStorageUsed' => Helper::humanFileSize($totalStorageUsed, 2),
                'onlineDevices' => $onlineDevices,
                'offlineDevices' => $offlineDevices,
                'protectedItemEngineCounts' => $protectedItemEngineCounts,
                'devices' => $devices,
                'accounts' => $accounts,
                'upcomingCharges' => $upcomingCharges,
                'upcomingChargesToken' => function_exists('generate_token') ? generate_token('plain') : '',
                'upcomingDismissUrl' => $vars['modulelink'] . '&a=notification-dismiss',
                // Flag to trigger password modal on dashboard load
                'mustSetPassword' => (function_exists('eazybackup_must_set_password') ? eazybackup_must_set_password((int)$clientId) : false),
                // Client notification prefs for template conditionals
                'notifyPrefs' => (function() use ($clientId){
                    try { $r = Capsule::table('eb_client_notify_prefs')->where('client_id', $clientId)->first(); if ($r) { return [
                        'show_upcoming_charges' => (int)$r->show_upcoming_charges,
                    ]; } } catch (\Throwable $_) {}
                    return ['show_upcoming_charges' => 1];
                })(),
            ]
        ];

    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'users') {
        // This action is now merged into the dashboard
        header('Location: ' . $vars['modulelink'] . '&a=dashboard&tab=users');
        exit;
    } else if ($_REQUEST["a"] == "vaults") {
        // Load the dashboard backend logic.
        $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
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


        // Build username serviceid map without joins to avoid collation issues
        $serviceMap = Capsule::table('tblhosting')
            ->select('username', 'id')
            ->where('userid', $clientId)
            ->whereIn('username', $accounts)
            ->pluck('id', 'username')
            ->toArray();

        // Fetch vaults then attach serviceid per username in PHP
        $vaults = Capsule::table('comet_vaults')
            ->select(
                'id',
                'username',
                'name',
                'total_bytes',
                'type as DestinationType',
                'has_storage_limit as quota_enabled',
                'storage_limit_bytes as quota_bytes'
            )
            ->where('client_id', $clientId)
            ->whereIn('username', $accounts)
            ->where('is_active', 1)
            ->whereNull('removed_at')
            ->get();

        foreach ($vaults as $v) {
            $uname = $v->username ?? '';
            $v->serviceid = isset($serviceMap[$uname]) ? $serviceMap[$uname] : null;
        }

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

    } else if ($_REQUEST["a"] == "password-onboarding") {
        // Bespoke first-login password setup flow (after TOS acceptance)
        try {
            if (!isset($_SESSION['uid']) || (int) $_SESSION['uid'] <= 0) {
                return [
                    "pagetitle"    => "Set Your Password",
                    "templatefile" => "templates/error",
                    "requirelogin" => true,
                    "vars"         => ["error" => "Not authenticated"],
                ];
            }

            $viewVars = require __DIR__ . "/pages/password_onboarding.php";

            return [
                "pagetitle"    => "Set Your Password",
                "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
                "templatefile" => "templates/password-onboarding",
                "requirelogin" => true,
                "forcessl"     => true,
                "vars"         => array_merge($vars, $viewVars),
            ];
        } catch (\Throwable $e) {
            return [
                "pagetitle"    => "Set Your Password",
                "templatefile" => "templates/error",
                "requirelogin" => true,
                "vars"         => ["error" => "Server error: " . $e->getMessage()],
            ];
        }

    } else if ($_REQUEST["a"] == "whitelabel-signup") {
        return whitelabel_signup($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel') {
        // New intake controller
        require_once __DIR__ . "/pages/whitelabel/BuildController.php";
        return eazybackup_whitelabel_intake($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-branding') {
        require_once __DIR__ . "/pages/whitelabel/BuildController.php";
        return eazybackup_whitelabel_branding($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-branding-checkdns') {
        // CSRF/token is handled by WHMCS; this endpoint returns JSON
        require_once __DIR__ . "/pages/whitelabel/BuildController.php";
        eazybackup_whitelabel_branding_checkdns($vars);
        exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-branding-attachdomain') {
        require_once __DIR__ . "/pages/whitelabel/BuildController.php";
        eazybackup_whitelabel_branding_attachdomain($vars);
        exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-status') {
        require_once __DIR__ . "/pages/whitelabel/BuildController.php";
        eazybackup_whitelabel_status($vars);
        exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-loader') {
        require_once __DIR__ . "/pages/whitelabel/BuildController.php";
        return eazybackup_whitelabel_loader($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-signup-settings') {
        require_once __DIR__ . "/pages/whitelabel/SignupSettingsController.php";
        return eazybackup_whitelabel_signup_settings($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-signup-checkdns') {
        require_once __DIR__ . "/pages/whitelabel/SignupSettingsController.php";
        eazybackup_whitelabel_signup_checkdns($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-signup-attachdomain') {
        require_once __DIR__ . "/pages/whitelabel/SignupSettingsController.php";
        eazybackup_whitelabel_signup_attachdomain($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'public-signup') {
        require_once __DIR__ . '/pages/whitelabel/PublicSignupController.php';
        return eazybackup_public_signup($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'public-download') {
        require_once __DIR__ . '/pages/whitelabel/PublicDownloadController.php';
        return eazybackup_public_download($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'public-setupintent') {
        require_once __DIR__ . '/pages/whitelabel/PublicSignupController.php';
        eazybackup_public_setupintent($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-email-templates') {
        require_once __DIR__ . '/pages/whitelabel/EmailTemplatesController.php';
        return eazybackup_whitelabel_email_templates($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'whitelabel-email-template-edit') {
        require_once __DIR__ . '/pages/whitelabel/EmailTemplatesController.php';
        return eazybackup_whitelabel_email_template_edit($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-clients') {
        require_once __DIR__ . '/pages/partnerhub/ClientsController.php';
        return eb_ph_clients_index($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-client') {
        require_once __DIR__ . '/pages/partnerhub/ClientViewController.php';
        return eb_ph_client_view($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-stripe-onboard') {
        require_once __DIR__ . '/pages/partnerhub/StripeController.php';
        eb_ph_stripe_onboard($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-stripe-account-session') {
        require_once __DIR__ . '/pages/partnerhub/StripeController.php';
        eb_ph_stripe_account_session($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-stripe-manage') {
        require_once __DIR__ . '/pages/partnerhub/StripeController.php';
        return eb_ph_stripe_manage($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-stripe-manage-redirect') {
        require_once __DIR__ . '/pages/partnerhub/StripeController.php';
        eb_ph_stripe_manage_redirect($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-stripe-setupintent') {
        require_once __DIR__ . '/pages/partnerhub/StripeController.php';
        eb_ph_stripe_setup_intent($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-stripe-connect') {
        require_once __DIR__ . '/pages/partnerhub/StripeController.php';
        return eb_ph_stripe_connect_status($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-stripe-webhook') {
        require_once __DIR__ . '/pages/partnerhub/StripeWebhookController.php';
        eb_ph_stripe_webhook(); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-subscriptions') {
        require_once __DIR__ . '/pages/partnerhub/SubscriptionsController.php';
        return eb_ph_subscriptions_new($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-billing-subscriptions') {
        require_once __DIR__ . '/pages/partnerhub/BillingController.php';
        return eb_ph_billing_subscriptions($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-billing-invoices') {
        require_once __DIR__ . '/pages/partnerhub/BillingController.php';
        return eb_ph_billing_invoices($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-billing-payments') {
        require_once __DIR__ . '/pages/partnerhub/BillingController.php';
        return eb_ph_billing_payments($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-billing-payment-new') {
        require_once __DIR__ . '/pages/partnerhub/BillingController.php';
        return eb_ph_billing_payment_new($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-billing-create-payment') {
        require_once __DIR__ . '/pages/partnerhub/BillingController.php';
        eb_ph_billing_create_payment($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-money-payouts') {
        require_once __DIR__ . '/pages/partnerhub/BillingController.php';
        return eb_ph_money_payouts($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-money-disputes') {
        require_once __DIR__ . '/pages/partnerhub/BillingController.php';
        return eb_ph_money_disputes($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-money-balance') {
        require_once __DIR__ . '/pages/partnerhub/BillingController.php';
        return eb_ph_money_balance($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plans') {
        require_once __DIR__ . '/pages/partnerhub/PlansController.php';
        return eb_ph_plans_index($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-products') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        return eb_ph_catalog_products_list($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        return eb_ph_catalog_product_show($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-products-create') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_products_create($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-price-create') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_price_create($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-toggle') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_product_toggle($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-price-toggle') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_price_toggle($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-plans') {
        require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
        return eb_ph_catalog_plans_index($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-save') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_product_save($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-split') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_product_split($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-get') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_product_get($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-get-stripe') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_product_get_stripe($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-save-stripe') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_product_save_stripe($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-settings-checkout') {
        require_once __DIR__ . '/pages/partnerhub/SettingsController.php';
        return eb_ph_settings_checkout_show($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-settings-checkout-save') {
        require_once __DIR__ . '/pages/partnerhub/SettingsController.php';
        eb_ph_settings_checkout_save($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-settings-tax') {
        require_once __DIR__ . '/pages/partnerhub/TaxSettingsController.php';
        return eb_ph_settings_tax_show($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-settings-tax-save') {
        require_once __DIR__ . '/pages/partnerhub/TaxSettingsController.php';
        eb_ph_settings_tax_save($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-tax-registrations') {
        require_once __DIR__ . '/pages/partnerhub/TaxSettingsController.php';
        eb_ph_tax_registrations($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-tax-registration-upsert') {
        require_once __DIR__ . '/pages/partnerhub/TaxSettingsController.php';
        eb_ph_tax_registration_upsert($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-tax-registration-delete') {
        require_once __DIR__ . '/pages/partnerhub/TaxSettingsController.php';
        eb_ph_tax_registration_delete($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-settings-email') {
        require_once __DIR__ . '/pages/partnerhub/EmailSettingsController.php';
        return eb_ph_settings_email_show($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-settings-email-save') {
        require_once __DIR__ . '/pages/partnerhub/EmailSettingsController.php';
        eb_ph_settings_email_save($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-email-test') {
        require_once __DIR__ . '/pages/partnerhub/EmailSettingsController.php';
        eb_ph_email_test($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-email-restore-default') {
        require_once __DIR__ . '/pages/partnerhub/EmailSettingsController.php';
        eb_ph_email_restore_default($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-unarchive-stripe') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_product_unarchive_stripe($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-export-products') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_export_products($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-export-prices') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_export_prices($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-archive-stripe') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_product_archive_stripe($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-delete-stripe') {
        require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
        eb_ph_catalog_product_delete_stripe($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plan-template-create') {
        require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
        eb_ph_plan_template_create($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plan-component-add') {
        require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
        eb_ph_plan_component_add($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plan-assign') {
        require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
        eb_ph_plan_assign($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-stripe-subscribe') {
        require_once __DIR__ . '/pages/partnerhub/SubscriptionsController.php';
        return eb_ph_stripe_subscribe($vars);
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-services-link') {
        require_once __DIR__ . '/pages/partnerhub/ServicesController.php';
        eb_ph_services_link($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-usage-push') {
        require_once __DIR__ . '/pages/partnerhub/UsageController.php';
        eb_ph_usage_push($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-invoices-refresh') {
        require_once __DIR__ . '/pages/partnerhub/BackfillController.php';
        eb_ph_invoices_refresh($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-payouts-refresh') {
        require_once __DIR__ . '/pages/partnerhub/BackfillController.php';
        eb_ph_payouts_refresh($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-disputes-refresh') {
        require_once __DIR__ . '/pages/partnerhub/BackfillController.php';
        eb_ph_disputes_refresh($vars); exit;
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-client-profile-update') {
        require_once __DIR__ . '/pages/partnerhub/ProfileController.php';
        eb_ph_client_profile_update($vars); exit;
    } else if ($_REQUEST["a"] == "createorder") {
        return eazybackup_createorder($vars);
    } else if ($_REQUEST["a"] == "add-card") {
        // Short-circuit for Stripe: delegate to WHMCS native Add Card page
        header('Content-Type: application/json');
        try {
            if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
                exit;
            }
            $clientId = (int)$_SESSION['uid'];
            $adminUsername = 'API';

            // Determine gateway: prefer explicit POST, else client's default, else fallback
            $gateway = isset($_POST['gateway_module_name']) && $_POST['gateway_module_name'] !== ''
                ? (string)$_POST['gateway_module_name']
                : (string)(Capsule::table('tblclients')->where('id', $clientId)->value('defaultgateway') ?? '');
            if ($gateway === '') { $gateway = 'stripe'; }
            $gatewayNormalized = strtolower($gateway);

            // Safe debug trace: log keys only, no sensitive values
            try {
                $keys = implode(',', array_keys($_POST ?? []));
                logActivity("eazybackup: add-card invoked (gateway={$gatewayNormalized}) keys={$keys}");
            } catch (\Throwable $_) { /* ignore logging errors */ }

            // If Stripe (tokenized gateway), instruct frontend to redirect to secure native page
            if ($gatewayNormalized === 'stripe') {
                $redirectUrl = rtrim((string)($vars['systemurl'] ?? ''), '/') . '/index.php/account/paymentmethods/add';
                echo json_encode([
                    'status'   => 'redirect',
                    'redirect' => $redirectUrl,
                    'message'  => 'Use the secure Add Card flow.'
                ]);
                exit;
            }

            // Optional: support non-Stripe gateways that accept AddPayMethod via API
            $payload = [
                'clientid'            => $clientId,
                'type'                => 'RemoteCreditCard',
                'gateway_module_name' => $gateway,
                'description'         => 'Primary Card',
                'set_as_default'      => true,
            ];

            // Token/ID fields (non-PAN) if provided by the gateway
            $pmId = $_POST['payment_method_id'] ?? $_POST['payment_method'] ?? $_POST['pm'] ?? '';
            if ($pmId !== '') {
                $payload['payment_method_id'] = $pmId;
                $payload['payment_method']    = $pmId;
                $payload['gateway_token']     = $pmId;
                $payload['gatewayid']         = $pmId;
                $payload['gateway_id']        = $pmId;
                $payload['remote_token']      = $pmId;
                $payload['remoteStorageToken']= $pmId;
            }

            $result = localAPI('AddPayMethod', $payload, $adminUsername);
            if (($result['result'] ?? '') !== 'success') {
                echo json_encode(['status' => 'error', 'message' => $result['message'] ?? 'AddPayMethod failed', 'raw' => $result]);
                exit;
            }

            echo json_encode(['status' => 'success', 'paymethodid' => $result['paymethodid'] ?? null]);
            exit;
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    } else if ($_REQUEST["a"] == "signup") {
        return eazybackup_signup($vars);
    } else if ($_REQUEST["a"] == "obc-signup") {
        return obc_signup($vars);
    } else if ($_REQUEST["a"] == "verifytrial") {
        // Trial email verification → provision order and SSO to password change
        try {
            $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
            if ($token === '') {
                return [
                    "pagetitle"    => "Sign Up",
                    "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
                    "templatefile" => "templates/trialsignup",
                    "requirelogin" => false,
                    "forcessl"     => true,
                    "vars"         => [
                        "modulelink"         => $vars["modulelink"],
                        "errors"             => ["error" => "Invalid verification link."],
                        "POST"               => [],
                        "TURNSTILE_SITE_KEY" => (string)($vars['turnstilesitekey'] ?? ''),
                    ],
                ];
            }
            $row = Capsule::table('eazybackup_trial_verifications')
                ->where('token', $token)
                ->whereNull('consumed_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', date('Y-m-d H:i:s'));
                })
                ->first();
            if (!$row) {
                return [
                    "pagetitle"    => "Sign Up",
                    "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
                    "templatefile" => "templates/trialsignup",
                    "requirelogin" => false,
                    "forcessl"     => true,
                    "vars"         => [
                        "modulelink"         => $vars["modulelink"],
                        "errors"             => ["error" => "This verification link is invalid or has expired. Please submit the form again."],
                        "POST"               => [],
                        "TURNSTILE_SITE_KEY" => (string)($vars['turnstilesitekey'] ?? ''),
                    ],
                ];
            }
            $clientId = (int)$row->client_id;
            $email    = (string)($row->email ?? '');
            $meta     = json_decode((string)($row->meta ?? '{}'), true) ?: [];
            $username = (string)($meta['username'] ?? '');
            $productId= (string)($meta['productId'] ?? '');
            if ($username === '' || $productId === '') {
                throw new \Exception('Verification data incomplete.');
            }

            $adminUser = 'API';

            // Create order with trial promo (no invoice, no email yet)
            $orderData = [
                "clientid"      => $clientId,
                "pid"           => [$productId],
                "promocode"     => "trial",
                "paymentmethod" => "stripe",
                "noinvoice"     => true,
                "noemail"       => true,
            ];
            $order = localAPI("AddOrder", $orderData, $adminUser);
            if (($order["result"] ?? '') !== "success") {
                customFileLog("AddOrder (verifytrial) failed", $order);
                throw new \Exception("AddOrder: " . ($order['message'] ?? 'unknown'));
            }

            // Accept the order and set service credentials
            $servicePassword = bin2hex(random_bytes(12));
            $accept = localAPI("AcceptOrder", [
                "orderid"         => $order["orderid"],
                "autosetup"       => true,
                "sendemail"       => true,
                "serviceusername" => $username,
                "servicepassword" => $servicePassword,
            ], $adminUser);
            if (($accept["result"] ?? '') !== "success") {
                customFileLog("AcceptOrder (verifytrial) failed", $accept);
                throw new \Exception("AcceptOrder: " . ($accept['message'] ?? 'unknown'));
            }

            // Locate the created service
            $service = Capsule::table('tblhosting')->where('orderid', $order["orderid"])->first();
            if ($service) {
                // Set 14 day trial window
                $newDate = date('Y-m-d', strtotime('+14 days'));
                $upd = localAPI('UpdateClientProduct', [
                    'serviceid'       => $service->id,
                    'nextduedate'     => $newDate,
                    'nextinvoicedate' => $newDate,
                ], $adminUser);
                if (($upd['result'] ?? '') !== 'success') {
                    customFileLog("UpdateClientProduct (verifytrial) failed", $upd);
                }
            }

            // Optional: product-specific provisioning (e.g., OBC/MS365)
            if ($productId === "52") {
                try {
                    $provisionResponse = EazybackupObcMs365::provisionLXDContainer(
                        $username,
                        $servicePassword,
                        $productId
                    );
                    if (isset($provisionResponse['error'])) {
                        customFileLog("Container provisioning failed (verifytrial)", $provisionResponse);
                        // not fatal for verification completion; continue
                    }
                } catch (\Throwable $e) {
                    customFileLog("Container provisioning exception (verifytrial)", $e->getMessage());
                }
            }

            // Mark token consumed
            Capsule::table('eazybackup_trial_verifications')->where('token', $token)->update([
                'consumed_at' => date('Y-m-d H:i:s'),
            ]);

            // SSO to password change
            $ssoResult = localAPI('CreateSsoToken', [
                'client_id'         => $clientId,
                'destination'       => 'sso:custom_redirect',
                'sso_redirect_path' => 'index.php?m=eazybackup&a=dashboard',
            ], $adminUser);
            if (($ssoResult['result'] ?? '') === 'success') {
                header("Location: {$ssoResult['redirect_url']}");
                exit;
            }

            // Fallback: go to client area login
            header("Location: clientarea.php");
            exit;
        } catch (\Throwable $e) {
            return [
                "pagetitle"    => "Sign Up",
                "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
                "templatefile" => "templates/trialsignup",
                "requirelogin" => false,
                "forcessl"     => true,
                "vars"         => [
                    "modulelink"         => $vars["modulelink"],
                    "errors"             => ["error" => "We couldn't complete verification. Please try again."],
                    "POST"               => [],
                    "TURNSTILE_SITE_KEY" => (string)($vars['turnstilesitekey'] ?? ''),
                ],
            ];
        }
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

    } else if ($_REQUEST["a"] == "eazybackup-download") {
        return [
            "pagetitle" => "Download eazyBackup",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/eazybackup-download",
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

/**
 * Admin Area Output (WHMCS admin addonmodules.php?module=eazybackup)
 * Routes the eazyBackup Power Panel.
 */
function eazybackup_output($vars)
{
    $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
    if ($action === 'admin-workers') {
        // Admin JSON endpoint: workers status
        require_once __DIR__ . '/pages/admin/workers.php';
        exit; // prevent WHMCS from wrapping HTML around JSON
    }
    if ($action === '') {
        $_REQUEST['action'] = $action = 'powerpanel';
        if (!isset($_REQUEST['view']) || $_REQUEST['view'] === '') {
            $_REQUEST['view'] = 'storage';
        }
    }
    if ($action === 'powerpanel') {
        $view = isset($_REQUEST['view']) ? (string) $_REQUEST['view'] : 'storage';
        switch ($view) {
            case 'storage': {
                $controller = __DIR__ . '/pages/admin/powerpanel/storage.php';
                if (!is_file($controller)) {
                    echo '<div class="alert alert-danger">Controller not found.</div>';
                    return;
                }
                $data = require $controller;
                $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
                $filters    = $data['filters'] ?? ['username' => '', 'server' => ''];
                $servers    = $data['servers'] ?? [];
                $rows       = $data['rows'] ?? [];
                $sort       = $data['sort'] ?? 'username';
                $dir        = $data['dir'] ?? 'asc';
                $perPage    = (int)($data['perPage'] ?? 25);
                $pagination = $data['pagination'] ?? '';
                $totalRows  = (int)($data['totalRows'] ?? count($rows));
                $sortLinks  = $data['sortLinks'] ?? [
                    'username' => '#', 'server' => '#', 'bytes' => '#', 'units' => '#'
                ];

                // --- Notifications: Test send (admin-only) ---
                $testNotice = '';
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_send_test']) && check_token('WHMCS.admin.default')) {
                    try {
                        require_once __DIR__ . '/lib/Notifications/bootstrap.php';
                        if (function_exists('eb_notifications_service')) { eb_notifications_service(); }
                        $choice = isset($_POST['eb_test_template']) ? (string)$_POST['eb_test_template'] : 'device';
                        $map = [
                            'device' => ['key' => 'tpl_device_added', 'subject' => 'Test: Device Added', 'vars' => [
                                'username' => 'testuser', 'service_id' => 0, 'client_id' => 0, 'device_id' => 'TESTHASH', 'device_name' => 'Test Device'
                            ]],
                            'addon' => ['key' => 'tpl_addon_enabled', 'subject' => 'Test: Add-on Enabled', 'vars' => [
                                'username' => 'testuser', 'service_id' => 0, 'client_id' => 0, 'addon_code' => 'disk_image'
                            ]],
                            'storage_warning' => ['key' => 'tpl_storage_warning', 'subject' => 'Test: Storage Warning', 'vars' => [
                                'username' => 'testuser', 'service_id' => 0, 'client_id' => 0, 'paid_tib' => 1, 'current_usage_tib' => 0.95, 'threshold_k_tib' => 1, 'projected_tib' => 1, 'projected_monthly_delta' => 0
                            ]],
                            'storage_overage' => ['key' => 'tpl_storage_overage', 'subject' => 'Test: Storage Overage', 'vars' => [
                                'username' => 'testuser', 'service_id' => 0, 'client_id' => 0, 'paid_tib' => 1, 'current_usage_tib' => 1.25, 'threshold_k_tib' => 1, 'projected_tib' => 2, 'projected_monthly_delta' => 25
                            ]],
                        ];
                        $cfg = $map[$choice] ?? $map['device'];
                        $varsToSend = array_merge(['subject' => $cfg['subject']], $cfg['vars']);
                        $resp = \EazyBackup\Notifications\TemplateRenderer::send($cfg['key'], $varsToSend);
                        $ok = (is_array($resp) && (isset($resp['result']) ? $resp['result'] === 'success' : true));
                        $testNotice = '<div class="alert ' . ($ok ? 'alert-success' : 'alert-danger') . '"><strong>Test Send</strong> ' . $e(json_encode($resp)) . '</div>';
                    } catch (\Throwable $ex) {
                        $testNotice = '<div class="alert alert-danger"><strong>Test Send</strong> Error: ' . $e($ex->getMessage()) . '</div>';
                    }
                }

                $html = '';
                $html .= '<div class="container-fluid">';
                $html .= '<ul class="nav nav-tabs mb-3">'
                      . '<li class="nav-item"><a class="nav-link active" href="#">Storage</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices">Devices</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=items">Protected Items</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing">Billing</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr">NFR</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=terms">Terms</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=privacy">Privacy</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>'
                      . '</ul>';

                // Test notification UI
                $html .= ($testNotice ?: '');
                $html .= '<form method="post" class="mb-3" style="padding:10px;border:1px solid #ddd;border-radius:4px;background:#f8f9fa">'
                      . generate_token('input')
                      . '<div class="form-inline">'
                      . '<label class="mr-2">Send Test Notification to Test Recipient(s):</label>'
                      . '<select name="eb_test_template" class="form-control mr-2">'
                      . '<option value="device">Device Added</option>'
                      . '<option value="addon">Add-on Enabled</option>'
                      . '<option value="storage_warning">Storage Warning</option>'
                      . '<option value="storage_overage">Storage Overage</option>'
                      . '</select>'
                      . '<button type="submit" name="eb_send_test" value="1" class="btn btn-primary">Send Test</button>'
                      . '</div>'
                      . '<div class="text-muted small" style="margin-top:6px">Uses current module template settings. In Test Mode, emails are sent only to Test Recipient(s).</div>'
                      . '</form>';

                $html .= '<form method="get" class="mb-3 form-inline" style="margin-bottom:15px">'
                      . '<input type="hidden" name="module" value="eazybackup"/>'
                      . '<input type="hidden" name="action" value="powerpanel"/>'
                      . '<input type="hidden" name="view" value="storage"/>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="filter-username" class="mr-2">Username</label>'
                      . '<input id="filter-username" type="text" class="form-control" name="username" value="' . $e($filters['username'] ?? '') . '" placeholder="Contains…"/>'
                      . '</div>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="filter-server" class="mr-2">Comet Server</label>'
                      . '<select id="filter-server" class="form-control" name="server">'
                      . '<option value="">All</option>';
                foreach ($servers as $srv) {
                    $sel = (($filters['server'] ?? '') === $srv) ? ' selected' : '';
                    $html .= '<option value="' . $e($srv) . '"' . $sel . '>' . $e($srv) . '</option>';
                }
                $html .= '</select>'
                      . '</div>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="perPage" class="mr-2">Per Page</label>'
                      . '<select id="perPage" class="form-control" name="perPage">';
                foreach ([25,50,100,250,2000] as $pp) {
                    $sel = ($perPage === $pp) ? ' selected' : '';
                    $html .= '<option value="' . $pp . '"' . $sel . '>' . $pp . '</option>';
                }
                $html .= '</select>'
                      . '</div>'
                      . '<button type="submit" class="btn btn-primary mb-2 mr-2">Filter</button>'
                      . '<a href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage" class="btn btn-default mb-2">Reset</a>'
                      . '</form>';

                // Header toolbar: total users and top pagination
                $html .= '<div class="clearfix" style="margin:10px 0 15px 0">'
                      .   '<div class="pull-left" style="padding-top:7px; font-weight:600;">Total Users: ' . (int)$totalRows . '</div>'
                      .   '<div class="pull-right">' . $pagination . '</div>'
                      . '</div>';

                $html .= '<div class="table-responsive">'
                      . '<table class="table table-striped table-condensed">'
                      . '<thead><tr>'
                      . '<th><a href="' . $e($sortLinks['username']) . '">Username' . ($sort==='username' ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th><a href="' . $e($sortLinks['server'])   . '">Comet Server' . ($sort==='server'   ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['bytes']) . '">Storage Size' . ($sort==='bytes' ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['units']) . '">Storage Billing Units' . ($sort==='units' ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right">Adjustment</th>'
                      . '</tr></thead><tbody>';
                if (!empty($rows)) {
                                        foreach ($rows as $r) {
                        // Compute expected billed units using TiB (2^40) thresholds, min 1
                        $tbDivisor = pow(1024, 4); // 1 TiB
                        $computedUnits = max(1, (int)ceil(((float)$r['total_bytes']) / $tbDivisor));
                        $billedUnits = (int)$r['billed_units'];
                        $labelStart = '';
                        $labelEnd = '';
                        if ($computedUnits > $billedUnits) { $labelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $labelEnd = '</span>'; }
                        else if ($computedUnits < $billedUnits) { $labelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $labelEnd = '</span>'; }
                        $delta = $computedUnits - $billedUnits;
                        $deltaText = '-';
                        if ($delta > 0) { $deltaText = 'Increase +' . $delta . ' TB'; }
                        else if ($delta < 0) { $deltaText = 'Decrease ' . abs($delta) . ' TB'; }
                        $serviceLink = 'clientsservices.php?userid=' . (int)$r['user_id'] . '&id=' . (int)$r['service_id'];
                        $html .= '<tr>'
                              . '<td><a href="' . $e($serviceLink) . '">' . $e($r['username']) . '</a></td>'
                              . '<td>' . $e($r['comet_server_url']) . '</td>'
                              . '<td class="text-right">' . $e($r['total_bytes_hr']) . '<div class="text-muted small">' . $e($r['total_bytes']) . ' bytes</div></td>'
                              . '<td class="text-right">' . ($labelStart ?: '') . $billedUnits . ($labelEnd ?: '') . '</td>'
                              . '<td class="text-right">' . ($delta !== 0 ? ($labelStart ?: '') . $deltaText . ($labelEnd ?: '') : '-') . '</td>'
                              . '</tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="5" class="text-center text-muted">No results</td></tr>';
                }
                $html .= '</tbody></table></div>';
                $html .= '<div class="mt-2">' . $pagination . '</div>';
                $html .= '</div>';
                echo $html;
                return;
            }
            case 'terms': {
                // Try multiple controller locations to be resilient across environments
                $candidates = [
                    __DIR__ . '/pages/admin/terms/index.php',
                    __DIR__ . '/pages/admin/terms.php',
                    __DIR__ . '/pages/admin/powerpanel/terms.php',
                ];
                $loaded = false;
                foreach ($candidates as $controller) {
                    if (is_file($controller)) {
                        $html = require $controller;
                        echo $html;
                        $loaded = true;
                        break;
                    }
                }
                if (!$loaded) {
                    echo '<div class="alert alert-danger">Terms controller not found.</div>';
                }
                return;
            }
            case 'privacy': {
                // Try multiple controller locations to be resilient across environments
                $candidates = [
                    __DIR__ . '/pages/admin/privacy/index.php',
                    __DIR__ . '/pages/admin/privacy.php',
                    __DIR__ . '/pages/admin/powerpanel/privacy.php',
                ];
                $loaded = false;
                foreach ($candidates as $controller) {
                    if (is_file($controller)) {
                        $html = require $controller;
                        echo $html;
                        $loaded = true;
                        break;
                    }
                }
                if (!$loaded) {
                    echo '<div class="alert alert-danger">Privacy controller not found.</div>';
                }
                return;
            }
            case 'partnerhub': {
                $controller = __DIR__ . '/pages/admin/partnerhub/settings.php';
                if (!is_file($controller)) { echo '<div class="alert alert-danger">Controller not found.</div>'; return; }
                $data = require $controller;
                $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
                $rows = $data['rows'] ?? [];
                echo '<div class="container-fluid">';
                echo '<ul class="nav nav-tabs mb-3">'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Storage</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices">Devices</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=items">Protected Items</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing">Billing</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr">NFR</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=terms">Terms</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=privacy">Privacy</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>'
                   . '<li class="nav-item"><a class="nav-link active" href="#">Partner Hub</a></li>'
                   . '</ul>';
                echo ($data['notice'] ?? '');
                echo '<div class="panel panel-default">'
                   . ' <div class="panel-heading"><strong>MSP Defaults</strong></div>'
                   . ' <div class="panel-body">'
                   . '   <div class="table-responsive">'
                   . '   <table class="table table-striped table-condensed">'
                   . '     <thead><tr>'
                   . '       <th>ID</th><th>Client</th><th>Email</th><th>Stripe Account</th><th>Charges</th><th>Payouts</th><th>Default Fee %</th><th>Action</th>'
                   . '     </tr></thead><tbody>';
                foreach ($rows as $r) {
                    $clientName = ($r->companyname ?: trim(($r->firstname ?: '') . ' ' . ($r->lastname ?: '')));
                    echo '<tr>'
                       . ' <td>' . (int)$r->id . '</td>'
                       . ' <td>' . $e($clientName) . '</td>'
                       . ' <td>' . $e((string)$r->email) . '</td>'
                       . ' <td>' . $e((string)$r->stripe_connect_id) . '</td>'
                       . ' <td>' . ((int)$r->charges_enabled ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>') . '</td>'
                       . ' <td>' . ((int)$r->payouts_enabled ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>') . '</td>'
                       . ' <td>'
                       . '   <form method="post" class="form-inline" style="margin:0">'
                       .        generate_token('input')
                       . '     <input type="hidden" name="save_msp_fee" value="1"/>'
                       . '     <input type="hidden" name="msp_id" value="' . (int)$r->id . '"/>'
                       . '     <input type="text" name="default_fee_percent" value="' . $e((string)($r->default_fee_percent ?? '')) . '" class="form-control" style="width:90px" placeholder="e.g., 10.00"/>'
                       . '     <button type="submit" class="btn btn-primary btn-sm" style="margin-left:6px">Save</button>'
                       . '   </form>'
                       . ' </td>'
                       . ' <td><a class="btn btn-default btn-sm" href="addonmodules.php?module=eazybackup&action=powerpanel&view=partnerhub">Refresh</a></td>'
                       . '</tr>';
                }
                if (empty($rows)) {
                    echo '<tr><td colspan="8" class="text-center text-muted">No MSPs found.</td></tr>';
                }
                echo '    </tbody></table></div></div></div></div>';
                return;
            }
            case 'nfr': {
                $controller = __DIR__ . '/pages/admin/nfr.php';
                if (!is_file($controller)) { echo '<div class="alert alert-danger">Controller not found.</div>'; return; }
                $data = require $controller;
                $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
                $tab = $data['tab'] ?? 'applications';
                $rows = $data['rows'] ?? [];
                $products = $data['products'] ?? [];
                echo '<div class="container-fluid">';
                echo '<ul class="nav nav-tabs mb-3">'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Storage</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices">Devices</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=items">Protected Items</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing">Billing</a></li>'
                   . '<li class="nav-item"><a class="nav-link active" href="#">NFR</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=terms">Terms</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=privacy">Privacy</a></li>'
                   . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>'
                   . '</ul>';

                echo ($data['notice'] ?? '');
                echo '<ul class="nav nav-pills" style="margin-bottom:10px">'
                   . '<li role="presentation"' . ($tab==='applications'?' class="active"':'') . '><a href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr&tab=applications">Applications</a></li>'
                   . '<li role="presentation"' . ($tab==='active'?' class="active"':'') . '><a href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr&tab=active">Active NFRs</a></li>'
                   . '<li role="presentation"' . ($tab==='all'?' class="active"':'') . '><a href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr&tab=all">All</a></li>'
                   . '</ul>';

                echo '<div class="table-responsive">'
                   . '<table class="table table-striped table-condensed">'
                   . '<thead><tr>'
                   . '<th>ID</th><th>Client ID</th><th>Date Registered</th><th>Username</th><th>Company</th><th>Status</th><th>Start</th><th>End</th><th>Quota (GiB)</th><th>Device Cap</th><th>Actions</th>'
                   . '</tr></thead><tbody>';
                if (!empty($rows)) {
                    foreach ($rows as $r) {
                        echo '<tr>'
                           . '<td>' . (int)$r->id . '</td>'
                           . '<td>' . ($tab==='active' ? '<a href="clientsservices.php?userid=' . (int)$r->client_id . '">' . (int)$r->client_id . '</a>' : (string)(int)$r->client_id) . '</td>'
                           . '<td>' . $e((string)$r->created_at) . '</td>'
                           . '<td>' . $e((string)($r->service_username ?? '')) . '</td>'
                           . '<td>' . $e((string)$r->company_name) . '</td>'
                           . '<td>' . $e((string)$r->status) . '</td>'
                           . '<td>' . $e((string)($r->start_date ?? '')) . '</td>'
                           . '<td>' . $e((string)($r->end_date ?? '')) . '</td>'
                           . '<td class="text-right">' . ($r->approved_quota_gib !== null ? (int)$r->approved_quota_gib : '-') . '</td>'
                           . '<td class="text-right">' . ($r->device_cap !== null ? (int)$r->device_cap : '-') . '</td>'
                           . '<td>';
                        if ($r->status === 'pending') {
                            echo '<form method="post" class="form-inline" style="display:inline-block;margin-right:6px">'
                               . generate_token('input')
                               . '<input type="hidden" name="nfr_action" value="approve"/>'
                               . '<input type="hidden" name="id" value="' . (int)$r->id . '"/>'
                               . '<select name="product_id" class="form-control" style="width:140px;margin-right:6px">';
                            foreach ($products as $p) {
                                echo '<option value="' . (int)$p['id'] . '">' . $e($p['name']) . '</option>';
                            }
                            echo '</select>'
                               . '<input type="number" name="duration_days" class="form-control" style="width:90px;margin-right:6px" placeholder="Days" value="' . (int)(\WHMCS\Module\Addon\Eazybackup\Nfr::defaultDurationDays()) . '"/>'
                               . '<input type="number" name="approved_quota_gib" class="form-control" style="width:110px;margin-right:6px" placeholder="Quota GiB" value="' . (int)(\WHMCS\Module\Addon\Eazybackup\Nfr::defaultQuotaGiB()) . '"/>'
                               . '<input type="number" name="device_cap" class="form-control" style="width:100px;margin-right:6px" placeholder="Devices"/>'
                               . '<button class="btn btn-success btn-sm" type="submit">Approve</button>'
                               . '</form>';
                            echo '<form method="post" style="display:inline-block">'
                               . generate_token('input')
                               . '<input type="hidden" name="nfr_action" value="reject"/>'
                               . '<input type="hidden" name="id" value="' . (int)$r->id . '"/>'
                               . '<button class="btn btn-default btn-sm" type="submit">Reject</button>'
                               . '</form>';
                        } elseif (in_array($r->status, ['approved','provisioned','suspended'], true)) {
                            // Provision only when not yet provisioned (approved without service)
                            if ($r->status === 'approved' && (int)($r->service_id ?? 0) === 0) {
                                echo '<form method="post" style="display:inline-block;margin-right:6px">'
                                   . generate_token('input')
                                   . '<input type="hidden" name="nfr_action" value="provision"/>'
                                   . '<input type="hidden" name="id" value="' . (int)$r->id . '"/>'
                                   . '<button class="btn btn-primary btn-sm" type="submit">Provision</button>'
                                   . '</form>';
                            }
                            if ($r->status !== 'suspended') {
                                echo '<form method="post" style="display:inline-block;margin-right:6px">'
                                   . generate_token('input')
                                   . '<input type="hidden" name="nfr_action" value="suspend"/>'
                                   . '<input type="hidden" name="id" value="' . (int)$r->id . '"/>'
                                   . '<button class="btn btn-warning btn-sm" type="submit">Suspend</button>'
                                   . '</form>';
                            }
                            if ($r->status === 'suspended') {
                                echo '<form method="post" style="display:inline-block;margin-right:6px">'
                                   . generate_token('input')
                                   . '<input type="hidden" name="nfr_action" value="resume"/>'
                                   . '<input type="hidden" name="id" value="' . (int)$r->id . '"/>'
                                   . '<button class="btn btn-default btn-sm" type="submit">Resume</button>'
                                   . '</form>';
                            }
                            // Removed Convert action per requirements
                            // Inline update: End date, Quota GiB, Device cap
                            echo '<form method="post" class="form-inline" style="display:inline-block;margin-right:6px">'
                               . generate_token('input')
                               . '<input type="hidden" name="nfr_action" value="update_active"/>'
                               . '<input type="hidden" name="id" value="' . (int)$r->id . '"/>'
                               . '<input type="date" name="end_date" class="form-control" style="width:140px;margin-right:6px" value="' . $e((string)($r->end_date ?? '')) . '" placeholder="End date"/>'
                               . '<input type="number" name="approved_quota_gib" class="form-control" style="width:110px;margin-right:6px" placeholder="Quota GiB" value="' . ($r->approved_quota_gib !== null ? (int)$r->approved_quota_gib : '') . '"/>'
                               . '<input type="number" name="device_cap" class="form-control" style="width:100px;margin-right:6px" placeholder="Devices" value="' . ($r->device_cap !== null ? (int)$r->device_cap : '') . '"/>'
                               . '<button class="btn btn-success btn-sm" type="submit">Update</button>'
                               . '</form>';
                            echo '<form method="post" style="display:inline-block">'
                               . generate_token('input')
                               . '<input type="hidden" name="nfr_action" value="expire_now"/>'
                               . '<input type="hidden" name="id" value="' . (int)$r->id . '"/>'
                               . '<button class="btn btn-default btn-sm" type="submit">Expire now</button>'
                               . '</form>';
                        } else {
                            echo '-';
                        }
                        echo '</td>'
                           . '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="11" class="text-center text-muted">No results</td></tr>';
                }
                echo '</tbody></table></div>';
                echo '</div>';
                return;
            }
            case 'devices': {
                $controller = __DIR__ . '/pages/admin/powerpanel/devices.php';
                if (!is_file($controller)) {
                    echo '<div class="alert alert-danger">Controller not found.</div>';
                    return;
                }
                $data = require $controller;
                $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
                $filters    = $data['filters'] ?? ['username' => '', 'product' => ''];
                $products   = $data['products'] ?? [];
                $rows       = $data['rows'] ?? [];
                $sort       = $data['sort'] ?? 'username';
                $dir        = $data['dir'] ?? 'asc';
                $perPage    = (int)($data['perPage'] ?? 25);
                $pagination = $data['pagination'] ?? '';
                $totalRows  = (int)($data['totalRows'] ?? count($rows));
                $sortLinks  = $data['sortLinks'] ?? [
                    'product' => '#', 'username' => '#', 'devices' => '#', 'units' => '#'
                ];

                $html = '';
                $html .= '<div class="container-fluid">';
                $html .= '<ul class="nav nav-tabs mb-3">'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Storage</a></li>'
                      . '<li class="nav-item"><a class="nav-link active" href="#">Devices</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=items">Protected Items</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing">Billing</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr">NFR</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=terms">Terms</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=privacy">Privacy</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>'
                      . '</ul>';

                $html .= '<form method="get" class="mb-3 form-inline" style="margin-bottom:15px">'
                      . '<input type="hidden" name="module" value="eazybackup"/>'
                      . '<input type="hidden" name="action" value="powerpanel"/>'
                      . '<input type="hidden" name="view" value="devices"/>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="filter-username" class="mr-2">Username</label>'
                      . '<input id="filter-username" type="text" class="form-control" name="username" value="' . $e($filters['username'] ?? '') . '" placeholder="Contains…"/>'
                      . '</div>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="filter-product" class="mr-2">Product</label>'
                      . '<select id="filter-product" class="form-control" name="product">'
                      . '<option value="">All</option>';
                foreach ($products as $p) {
                    $sel = ((int)($filters['product'] ?? 0) === (int)$p['id']) ? ' selected' : '';
                    $html .= '<option value="' . (int)$p['id'] . '"' . $sel . '>' . $e($p['name']) . '</option>';
                }
                $html .= '</select>'
                      . '</div>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="perPage" class="mr-2">Per Page</label>'
                      . '<select id="perPage" class="form-control" name="perPage">';
                foreach ([25,50,100,250,2000] as $pp) {
                    $sel = ($perPage === $pp) ? ' selected' : '';
                    $html .= '<option value="' . $pp . '"' . $sel . '>' . $pp . '</option>';
                }
                $html .= '</select>'
                      . '</div>'
                      . '<button type="submit" class="btn btn-primary mb-2 mr-2">Filter</button>'
                      . '<a href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices" class="btn btn-default mb-2">Reset</a>'
                      . '</form>';

                // Header toolbar: total services and top pagination
                $html .= '<div class="clearfix" style="margin:10px 0 15px 0">'
                      .   '<div class="pull-left" style="padding-top:7px; font-weight:600;">Total Services: ' . (int)$totalRows . '</div>'
                      .   '<div class="pull-right">' . $pagination . '</div>'
                      . '</div>';

                $html .= '<div class="table-responsive">'
                      . '<table class="table table-striped table-condensed">'
                      . '<thead><tr>'
                      . '<th><a href="' . $e($sortLinks['product'])  . '">Product'  . ($sort==='product'  ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th><a href="' . $e($sortLinks['username']) . '">Username' . ($sort==='username' ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['devices']) . '">Devices' . ($sort==='devices' ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['units'])   . '">Device Billing' . ($sort==='units'   ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right">Adjustment</th>'
                      . '</tr></thead><tbody>';
                if (!empty($rows)) {
                    foreach ($rows as $r) {
                        $devices = (int)$r['device_count'];
                        $billed  = (int)$r['billed_units'];
                        $productId = (int)$r['product_id'];
                        
                        // Special handling for Microsoft 365 products (52, 57)
                        // For these packages, a billed value of 0 is expected and should NOT be flagged
                        if ($productId === 52 || $productId === 57) {
                            if ($billed === 0) {
                                // Do not show badge even if devices > 0
                                $labelStart = '';
                                $labelEnd = '';
                                $delta = 0;
                                $deltaText = '-';
                            } else {
                                // If somehow billed units present, fall back to standard comparison against devices
                                $labelStart = '';
                                $labelEnd = '';
                                if ($devices > $billed) { $labelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $labelEnd = '</span>'; }
                                else if ($devices < $billed) { $labelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $labelEnd = '</span>'; }
                                $delta = $devices - $billed;
                                $deltaText = '-';
                                if ($delta > 0) { $deltaText = 'Increase +' . $delta . ' devices'; }
                                else if ($delta < 0) { $deltaText = 'Decrease ' . abs($delta) . ' devices'; }
                            }
                        } else {
                            // Standard logic for other products
                            $labelStart = '';
                            $labelEnd = '';
                            if ($devices > $billed) { $labelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $labelEnd = '</span>'; }
                            else if ($devices < $billed) { $labelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $labelEnd = '</span>'; }
                            $delta = $devices - $billed;
                            $deltaText = '-';
                            if ($delta > 0) { $deltaText = 'Increase +' . $delta . ' devices'; }
                            else if ($delta < 0) { $deltaText = 'Decrease ' . abs($delta) . ' devices'; }
                        }
                        // Defensive override: ensure no badge for M365 with 0 devices
                        if (($productId === 52 || $productId === 57) && $devices === 0) {
                            $labelStart = '';
                            $labelEnd = '';
                            $delta = 0;
                            $deltaText = '-';
                        }
                        $serviceLink = 'clientsservices.php?userid=' . (int)$r['user_id'] . '&id=' . (int)$r['service_id'];
                        // Device Billing display rules per product
                        $deviceBillingDisplay = $billed;
                        if ($productId === 52 || $productId === 57) {
                            // 52/57: devices are not billable
                            if ($billed > 0) {
                                // units present → show yellow to reduce to 0
                                $deviceBillingDisplay = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">' . (int)$billed . '</span>';
                            } else if ($devices > 0) {
                                // no units but devices exist → show yellow 0 to reduce
                                $deviceBillingDisplay = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">0</span>';
                            } else {
                                // neither units nor devices → plain 0
                                $deviceBillingDisplay = 0;
                            }
                        } else if ($productId === 53 || $productId === 54) {
                            // Unique products where devices are not charged
                            if ($deviceUnits === 0) {
                                $deviceBillingDisplay = 0; // <-- plain zero, no label
                            } else {
                                // If units present, fall back to standard label logic
                                $deviceBillingDisplay = ($dLabelStart ?: '') . (int)$deviceUnits . ($dLabelEnd ?: '');
                            }
                        } else {
                            $deviceBillingDisplay = ($dLabelStart ?: '') . (int)$deviceUnits . ($dLabelEnd ?: '');
                        }
                        
                        $html .= '<tr>'
                              . '<td>' . $e($r['product_name']) . '</td>'
                              . '<td><a href="' . $e($serviceLink) . '">' . $e($r['username']) . '</a></td>'
                              . '<td class="text-right">' . $devices . '</td>'
                              . '<td class="text-right">' . $deviceBillingDisplay . '</td>'
                              . '<td class="text-right">' . ($delta !== 0 ? ($labelStart ?: '') . $deltaText . ($labelEnd ?: '') : '-') . '</td>'
                              . '</tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="5" class="text-center text-muted">No results</td></tr>';
                }
                $html .= '</tbody></table></div>';
                $html .= '<div class="mt-2">' . $pagination . '</div>';
                $html .= '</div>';
                echo $html;
                return;
            }
            case 'items': {
                $controller = __DIR__ . '/pages/admin/powerpanel/items.php';
                if (!is_file($controller)) {
                    echo '<div class="alert alert-danger">Controller not found.</div>';
                    return;
                }
                $data = require $controller;
                $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
                $filters    = $data['filters'] ?? ['username' => '', 'product' => ''];
                $products   = $data['products'] ?? [];
                $rows       = $data['rows'] ?? [];
                $sort       = $data['sort'] ?? 'username';
                $dir        = $data['dir'] ?? 'asc';
                $perPage    = (int)($data['perPage'] ?? 25);
                $pagination = $data['pagination'] ?? '';
                $totalRows  = (int)($data['totalRows'] ?? count($rows));
                $sortLinks  = $data['sortLinks'] ?? [
                    'product' => '#', 'username' => '#', 'hv' => '#', 'hv_units' => '#', 'di' => '#', 'di_units' => '#', 'm365' => '#', 'm365_units' => '#', 'vmw' => '#', 'vmw_units' => '#'
                ];

                $html = '';
                $html .= '<div class="container-fluid">';
                $html .= '<ul class="nav nav-tabs mb-3">'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Storage</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices">Devices</a></li>'
                      . '<li class="nav-item"><a class="nav-link active" href="#">Protected Items</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing">Billing</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr">NFR</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=terms">Terms</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=privacy">Privacy</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>'
                      . '</ul>';

                $html .= '<form method="get" class="mb-3 form-inline" style="margin-bottom:15px">'
                      . '<input type="hidden" name="module" value="eazybackup"/>'
                      . '<input type="hidden" name="action" value="powerpanel"/>'
                      . '<input type="hidden" name="view" value="items"/>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="filter-username" class="mr-2">Username</label>'
                      . '<input id="filter-username" type="text" class="form-control" name="username" value="' . $e($filters['username'] ?? '') . '" placeholder="Contains…"/>'
                      . '</div>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="filter-product" class="mr-2">Product</label>'
                      . '<select id="filter-product" class="form-control" name="product">'
                      . '<option value="">All</option>';
                foreach ($products as $p) {
                    $sel = ((int)($filters['product'] ?? 0) === (int)$p['id']) ? ' selected' : '';
                    $html .= '<option value="' . (int)$p['id'] . '"' . $sel . '>' . $e($p['name']) . '</option>';
                }
                $html .= '</select>'
                      . '</div>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="perPage" class="mr-2">Per Page</label>'
                      . '<select id="perPage" class="form-control" name="perPage">';
                foreach ([25,50,100,250,2000] as $pp) {
                    $sel = ($perPage === $pp) ? ' selected' : '';
                    $html .= '<option value="' . $pp . '"' . $sel . '>' . $pp . '</option>';
                }
                $html .= '</select>'
                      . '</div>'
                      . '<button type="submit" class="btn btn-primary mb-2 mr-2">Filter</button>'
                      . '<a href="addonmodules.php?module=eazybackup&action=powerpanel&view=items" class="btn btn-default mb-2">Reset</a>'
                      . '</form>';

                // Header toolbar
                $html .= '<div class="clearfix" style="margin:10px 0 15px 0">'
                      .   '<div class="pull-left" style="padding-top:7px; font-weight:600;">Total Services: ' . (int)$totalRows . '</div>'
                      .   '<div class="pull-right">' . $pagination . '</div>'
                      . '</div>';

                $html .= '<div class="table-responsive">'
                      . '<table class="table table-striped table-condensed">'
                      . '<thead><tr>'
                      . '<th><a href="' . $e($sortLinks['product'])   . '">Product'  . ($sort==='product'    ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th><a href="' . $e($sortLinks['username'])  . '">Username' . ($sort==='username'   ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['hv'])        . '">Microsoft Hyper-V' . ($sort==='hv'        ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['hv_units'])  . '">Hyper-V Billing'    . ($sort==='hv_units'  ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['di'])        . '">Disk Image'         . ($sort==='di'        ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['di_units'])  . '">Disk Image Billing'  . ($sort==='di_units'  ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['m365'])      . '">Office 365'         . ($sort==='m365'      ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['m365_units']). '">Office 365 Billing'  . ($sort==='m365_units'? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['vmw'])       . '">VMware'             . ($sort==='vmw'       ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['vmw_units']) . '">VMware Billing'      . ($sort==='vmw_units' ? ' <span class="text-muted">(' . strtoupper($e($dir)) . ')</span>' : '') . '</a></th>'
                      . '</tr></thead><tbody>';
                if (!empty($rows)) {
                    foreach ($rows as $r) {
                        $serviceLink = 'clientsservices.php?userid=' . (int)$r['user_id'] . '&id=' . (int)$r['service_id'];


                        $html .= '<tr>'
                            . '<td>' . $e($r['product_name']) . '</td>'
                            . '<td><a href="' . $e($serviceLink) . '">' . $e($r['username']) . '</a></td>'
                            . '<td class="text-right">' . (int)$r['hv_count'] . '</td>'
                            . '<td class="text-right">' . ((($r['hv_count']??0) !== ($r['hv_units']??0)) ? '<span class="label ' . (((int)$r['hv_count'] > (int)$r['hv_units']) ? 'label-danger' : 'label-warning') . '" style="display:inline-block;padding:4px 6px">' . (int)$r['hv_units'] . '</span>' : (int)$r['hv_units']) . '</td>'
                            . '<td class="text-right">' . (int)$r['di_count'] . '</td>'
                              . '<td class="text-right">' . ((($r['di_count']??0) !== ($r['di_units']??0)) ? '<span class="label ' . (((int)$r['di_count'] > (int)$r['di_units']) ? 'label-danger' : 'label-warning') . '" style="display:inline-block;padding:4px 6px">' . (int)$r['di_units'] . '</span>' : (int)$r['di_units']) . '</td>'
                            . '<td class="text-right">' . (int)$r['m365_count'] . '</td>'
                            . '<td class="text-right">' . ((($r['m365_count']??0) !== ($r['m365_units']??0)) ? '<span class="label ' . (((int)$r['m365_count'] > (int)$r['m365_units']) ? 'label-danger' : 'label-warning') . '" style="display:inline-block;padding:4px 6px">' . (int)$r['m365_units'] . '</span>' : (int)$r['m365_units']) . '</td>'
                            . '<td class="text-right">' . (int)$r['vmw_count'] . '</td>'
                            . '<td class="text-right">' . ((($r['vmw_count']??0) !== ($r['vmw_units']??0)) ? '<span class="label ' . (((int)$r['vmw_count'] > (int)$r['vmw_units']) ? 'label-danger' : 'label-warning') . '" style="display:inline-block;padding:4px 6px">' . (int)$r['vmw_units'] . '</span>' : (int)$r['vmw_units']) . '</td>'
                            . '</tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="10" class="text-center text-muted">No results</td></tr>';
                }
                $html .= '</tbody></table></div>';
                $html .= '<div class="mt-2">' . $pagination . '</div>';
                $html .= '</div>';
                echo $html;
                return;
            }
            case 'billing': {
                $controller = __DIR__ . '/pages/admin/powerpanel/billing.php';
                if (!is_file($controller)) {
                    echo '<div class="alert alert-danger">Controller not found.</div>';
                    return;
                }
                $data = require $controller;
                $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
                $filters    = $data['filters'] ?? ['username' => '', 'product' => ''];
                $products   = $data['products'] ?? [];
                $rows       = $data['rows'] ?? [];
                $sort       = $data['sort'] ?? 'username';
                $dir        = $data['dir'] ?? 'asc';
                $perPage    = (int)($data['perPage'] ?? 25);
                $pagination = $data['pagination'] ?? '';
                $totalRows  = (int)($data['totalRows'] ?? count($rows));
                $sortLinks  = $data['sortLinks'] ?? [];

                $html = '';
                $html .= '<div class="container-fluid">';
                $html .= '<ul class="nav nav-tabs mb-3">'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Storage</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices">Devices</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=items">Protected Items</a></li>'
                      . '<li class="nav-item"><a class="nav-link active" href="#">Billing</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr">NFR</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=terms">Terms</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=privacy">Privacy</a></li>'
                      . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>'
                      . '</ul>';

                $html .= '<form method="get" class="mb-3 form-inline" style="margin-bottom:15px">'
                      . '<input type="hidden" name="module" value="eazybackup"/>'
                      . '<input type="hidden" name="action" value="powerpanel"/>'
                      . '<input type="hidden" name="view" value="billing"/>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="filter-username" class="mr-2">Username</label>'
                      . '<input id="filter-username" type="text" class="form-control" name="username" value="' . $e($filters['username'] ?? '') . '" placeholder="Contains…"/>'
                      . '</div>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="filter-product" class="mr-2">Product</label>'
                      . '<select id="filter-product" class="form-control" name="product">'
                      . '<option value="">All</option>';
                foreach ($products as $p) {
                    $sel = ((int)($filters['product'] ?? 0) === (int)$p['id']) ? ' selected' : '';
                    $html .= '<option value="' . (int)$p['id'] . '"' . $sel . '>' . $e($p['name']) . '</option>';
                }
                $html .= '</select>'
                      . '</div>'
                      . '<div class="form-group" style="margin-right:15px;margin-bottom:10px">'
                      . '<label for="perPage" class="mr-2">Per Page</label>'
                      . '<select id="perPage" class="form-control" name="perPage">';
                foreach ([25,50,100,250,2000] as $pp) {
                    $sel = ($perPage === $pp) ? ' selected' : '';
                    $html .= '<option value="' . $pp . '"' . $sel . '>' . $pp . '</option>';
                }
                $html .= '</select>'
                      . '</div>'
                      . '<button type="submit" class="btn btn-primary mb-2 mr-2">Filter</button>'
                      . '<a href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing" class="btn btn-default mb-2">Reset</a>'
                      . '</form>';

                $html .= '<div class="clearfix" style="margin:10px 0 15px 0">'
                      .   '<div class="pull-left" style="padding-top:7px; font-weight:600;">Total Services: ' . (int)$totalRows . '</div>'
                      .   '<div class="pull-right">' . $pagination . '</div>'
                      . '</div>';

                $html .= '<div class="table-responsive">'
                      . '<table class="table table-striped table-condensed">'
                      . '<thead><tr>'
                      . '<th><a href="' . $e($sortLinks['product'] ?? '#')       . '">Product</a></th>'
                      . '<th><a href="' . $e($sortLinks['username'] ?? '#')      . '">Username</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['bytes'] ?? '#')         . '">Storage Size</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['units_storage'] ?? '#') . '">Storage Billing</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['devices'] ?? '#')       . '">Devices</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['units_devices'] ?? '#') . '">Device Billing</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['hv'] ?? '#')            . '">Hyper-V</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['hv_units'] ?? '#')      . '">Hyper-V Billing</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['di'] ?? '#')            . '">Disk Image</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['di_units'] ?? '#')      . '">Disk Image Billing</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['m365'] ?? '#')          . '">Office 365</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['m365_units'] ?? '#')    . '">Office 365 Billing</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['vmw'] ?? '#')           . '">VMware</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['vmw_units'] ?? '#')     . '">VMware Billing</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['pmx'] ?? '#')           . '">Proxmox</a></th>'
                      . '<th class="text-right"><a href="' . $e($sortLinks['pmx_units'] ?? '#')     . '">Proxmox Billing</a></th>'
                      . '<th class="text-right">Adjustments</th>'
                      . '</tr></thead><tbody>';

                if (!empty($rows)) {
                    foreach ($rows as $r) {
                        $serviceLink = 'clientsservices.php?userid=' . (int)$r['user_id'] . '&id=' . (int)$r['service_id'];

                        // Storage: compute units by TiB
                        $tbDivisor = pow(1024, 4);
                        $computedStorageUnits = max(1, (int)ceil(((float)$r['total_bytes']) / $tbDivisor));
                        $storageUnits = (int)$r['storage_units'];
                        $sLabelStart = '';
                        $sLabelEnd = '';
                        if ($computedStorageUnits > $storageUnits) { $sLabelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $sLabelEnd = '</span>'; }
                        else if ($computedStorageUnits < $storageUnits) { $sLabelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $sLabelEnd = '</span>'; }
                        $sDelta = $computedStorageUnits - $storageUnits;
                        $sDeltaText = ($sDelta > 0) ? ('Increase +' . $sDelta . ' TB') : (($sDelta < 0) ? ('Decrease ' . abs($sDelta) . ' TB') : '-');

                        // Devices
                        $devices = (int)$r['device_count'];
                        $deviceUnits = (int)$r['device_units'];
                        $productId = (int)$r['product_id'];
                        // For products 52 and 57, 0 storage units is correct — do NOT flag
                        if ($productId === 52 || $productId === 57) {
                            if ($storageUnits === 0) {
                                $sLabelStart = '';
                                $sLabelEnd   = '';
                                $sDelta      = 0;
                                $sDeltaText  = '-';
                            }
                        }
                        
                        // Special handling for Microsoft 365 products (52, 57)
                        if ($productId === 52 || $productId === 57) {
                            // For M365 products, 0 devices is correct, any devices should show warning
                            if ($devices > 0) {
                                $dLabelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">';
                                $dLabelEnd = '</span>';
                                $dDelta = $devices;
                                $dDeltaText = 'Decrease ' . $devices;
                            } else {
                                // 0 devices is correct for M365 products - no badge needed
                                $dLabelStart = '';
                                $dLabelEnd = '';
                                $dDelta = 0;
                                $dDeltaText = '-';
                            }
                        } else {
                            // Standard logic for other products
                            $dLabelStart = '';
                            $dLabelEnd = '';
                            if ($devices > $deviceUnits) { $dLabelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $dLabelEnd = '</span>'; }
                            else if ($devices < $deviceUnits) { $dLabelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $dLabelEnd = '</span>'; }
                            $dDelta = $devices - $deviceUnits;
                            $dDeltaText = ($dDelta > 0) ? ('Increase +' . $dDelta) : (($dDelta < 0) ? ('Decrease ' . abs($dDelta)) : '-');
                        }

                        // Compute Device Billing cell display once to avoid conflicting logic
                        $deviceBillingDisplay = (int)$deviceUnits;
                        if ($productId === 52 || $productId === 57) {
                            if ($deviceUnits > 0) {
                                // >0 still flagged
                                $deviceBillingDisplay = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">' . (int)$deviceUnits . '</span>';
                            } else {
                                // 0 should be plain, even if devices > 0
                                $deviceBillingDisplay = 0;
                            }                        
                        } else if ($productId === 53 || $productId === 54) {
                            // 53/54: do not charge devices; render plain 0 with no badge when billed is 0
                            if ($billed === 0) {
                                $deviceBillingDisplay = 0; // <-- plain zero, no label
                            } else {
                                $deviceBillingDisplay = ($labelStart ?: '') . (int)$billed . ($labelEnd ?: '');
                            }
                        } else {
                            // Standard products → apply label wrappers from the comparison above
                            $deviceBillingDisplay = ($dLabelStart ?: '') . (int)$deviceUnits . ($dLabelEnd ?: '');
                        }

                        // Items categories (include Proxmox now)
                        $cats = [
                            ['count' => (int)$r['hv_count'],   'units' => (int)$r['hv_units']],
                            ['count' => (int)$r['di_count'],   'units' => (int)$r['di_units']],
                            ['count' => (int)$r['m365_count'], 'units' => (int)$r['m365_units']],
                            ['count' => (int)$r['vmw_count'],  'units' => (int)$r['vmw_units']],
                            ['count' => (int)($r['pmx_count'] ?? 0),  'units' => (int)($r['pmx_units'] ?? 0)],
                        ];
                        // Per-category unit label wrappers (danger for increase, warning for decrease)
                        $hvLabelStart = $hvLabelEnd = $diLabelStart = $diLabelEnd = $m365LabelStart = $m365LabelEnd = $vmwLabelStart = $vmwLabelEnd = $pmxLabelStart = $pmxLabelEnd = '';
                        if ((int)$r['hv_count'] > (int)$r['hv_units']) { $hvLabelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $hvLabelEnd = '</span>'; }
                        else if ((int)$r['hv_count'] < (int)$r['hv_units']) { $hvLabelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $hvLabelEnd = '</span>'; }
                        if ((int)$r['di_count'] > (int)$r['di_units']) { $diLabelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $diLabelEnd = '</span>'; }
                        else if ((int)$r['di_count'] < (int)$r['di_units']) { $diLabelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $diLabelEnd = '</span>'; }
                        if ((int)$r['m365_count'] > (int)$r['m365_units']) { $m365LabelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $m365LabelEnd = '</span>'; }
                        else if ((int)$r['m365_count'] < (int)$r['m365_units']) { $m365LabelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $m365LabelEnd = '</span>'; }
                        if ((int)$r['vmw_count'] > (int)$r['vmw_units']) { $vmwLabelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $vmwLabelEnd = '</span>'; }
                        else if ((int)$r['vmw_count'] < (int)$r['vmw_units']) { $vmwLabelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $vmwLabelEnd = '</span>'; }
                        if ((int)($r['pmx_count'] ?? 0) > (int)($r['pmx_units'] ?? 0)) { $pmxLabelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $pmxLabelEnd = '</span>'; }
                        else if ((int)($r['pmx_count'] ?? 0) < (int)($r['pmx_units'] ?? 0)) { $pmxLabelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $pmxLabelEnd = '</span>'; }
                        $adjParts = [];
                        foreach ($cats as $pair) {
                            $c = $pair['count']; $u = $pair['units'];
                            $labelStart = '';
                            $labelEnd = '';
                            if ($c > $u) { $labelStart = '<span class="label label-danger" style="display:inline-block;padding:4px 6px">'; $labelEnd = '</span>'; }
                            else if ($c < $u) { $labelStart = '<span class="label label-warning" style="display:inline-block;padding:4px 6px">'; $labelEnd = '</span>'; }
                            $delta = $c - $u;
                            $deltaText = ($delta > 0) ? ('Increase +' . $delta) : (($delta < 0) ? ('Decrease ' . abs($delta)) : '-');
                            $adjParts[] = ($delta !== 0 ? ($labelStart ?: '') . $deltaText . ($labelEnd ?: '') : '-');
                        }

                        // Build grace badges
                        $badge = function ($dueNow, $grace, $days) {
                            if ((int)$dueNow > 0) {
                                return ' <span class="label label-danger" style="display:inline-block;padding:2px 4px">Due now +' . (int)$dueNow . '</span>';
                            } elseif ((int)$grace > 0) {
                                $d = (int)$days; $txt = $d > 0 ? ($d . ' days') : 'due';
                                return ' <span class="label label-warning" style="display:inline-block;padding:2px 4px">Grace ' . (int)$grace . ' (' . $txt . ')</span>';
                            }
                            return '';
                        };

                        $html .= '<tr>'
                              . '<td>' . $e($r['product_name']) . '</td>'
                              . '<td><a href="' . $e($serviceLink) . '">' . $e($r['username']) . '</a></td>'
                              . '<td class="text-right">' . $e($r['total_bytes_hr'] ?? '') . '<div class="text-muted small">' . (int)$r['total_bytes'] . ' bytes</div></td>'
                              . '<td class="text-right">' . ($sLabelStart ?: '') . $storageUnits . ($sLabelEnd ?: '') . '</td>'
                              . '<td class="text-right">' . $devices . '</td>'
                              . '<td class="text-right">' . $deviceBillingDisplay . $badge(($r['devices_due_now'] ?? 0), ($r['devices_grace'] ?? 0), ($r['devices_grace_days'] ?? 0)) . '</td>'
                              . '<td class="text-right">' . (int)$r['hv_count'] . '</td>'
                              . '<td class="text-right">' . ($hvLabelStart ?: '') . (int)$r['hv_units'] . ($hvLabelEnd ?: '') . $badge(($r['hv_due_now'] ?? 0), ($r['hv_grace'] ?? 0), ($r['hv_grace_days'] ?? 0)) . '</td>'
                              . '<td class="text-right">' . (int)$r['di_count'] . '</td>'
                              . '<td class="text-right">' . ($diLabelStart ?: '') . (int)$r['di_units'] . ($diLabelEnd ?: '') . $badge(($r['di_due_now'] ?? 0), ($r['di_grace'] ?? 0), ($r['di_grace_days'] ?? 0)) . '</td>'
                              . '<td class="text-right">' . (int)$r['m365_count'] . '</td>'
                              . '<td class="text-right">' . ($m365LabelStart ?: '') . (int)$r['m365_units'] . ($m365LabelEnd ?: '') . $badge(($r['m365_due_now'] ?? 0), ($r['m365_grace'] ?? 0), ($r['m365_grace_days'] ?? 0)) . '</td>'
                              . '<td class="text-right">' . (int)$r['vmw_count'] . '</td>'
                              . '<td class="text-right">' . ($vmwLabelStart ?: '') . (int)$r['vmw_units'] . ($vmwLabelEnd ?: '') . $badge(($r['vmw_due_now'] ?? 0), ($r['vmw_grace'] ?? 0), ($r['vmw_grace_days'] ?? 0)) . '</td>'
                              . '<td class="text-right">' . (int)($r['pmx_count'] ?? 0) . '</td>'
                              . '<td class="text-right">' . ($pmxLabelStart ?: '') . (int)($r['pmx_units'] ?? 0) . ($pmxLabelEnd ?: '') . $badge(($r['pmx_due_now'] ?? 0), ($r['pmx_grace'] ?? 0), ($r['pmx_grace_days'] ?? 0)) . '</td>'
                              . '<td class="text-right">' . implode(' | ', array_merge([$sDeltaText, $dDeltaText], $adjParts)) . '</td>'
                              . '</tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="17" class="text-center text-muted">No results</td></tr>';
                }
                $html .= '</tbody></table></div>';
                $html .= '<div class="mt-2">' . $pagination . '</div>';
                $html .= '</div>';
                echo $html;
                return;
            }
            default:
                // Admin white-label tenants view
                if ($view === 'whitelabel') {
                    $controller = __DIR__ . '/pages/admin/whitelabel/index.php';
                    if (!is_file($controller)) { echo '<div class="alert alert-danger">Controller not found.</div>'; return; }
                    $html = require $controller;
                    echo $html; return;
                }
                echo '<div class="alert alert-info">This section is under construction.</div>'; return;
        }
    } else if ($action === 'billing-cooldown') {
        // Admin endpoint to extend grace by +3 days for a service
        if (!function_exists('check_token') || !check_token('WHMCS.admin.default')) { echo json_encode(['ok'=>false,'message'=>'Invalid token']); exit; }
        $sid = isset($_GET['serviceid']) ? (int)$_GET['serviceid'] : 0;
        if ($sid <= 0) { echo json_encode(['ok'=>false,'message'=>'Invalid service id']); exit; }
        try {
            $rc = Capsule::table('eb_billing_grace')->where('service_id',$sid)->update([
                'grace_expires_at' => Capsule::raw("DATE_ADD(grace_expires_at, INTERVAL 3 DAY)"),
                'updated_at' => Capsule::raw('NOW()'),
            ]);
            echo json_encode(['ok'=>true,'affected'=>$rc,'message'=>'+3 days applied to grace expiry']);
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'message'=>'Error applying cooldown']);
        }
        exit;
    }

    $linkStorage = 'addonmodules.php?module=eazybackup&action=powerpanel&view=storage';
    $linkDevices = 'addonmodules.php?module=eazybackup&action=powerpanel&view=devices';
    $linkItems   = 'addonmodules.php?module=eazybackup&action=powerpanel&view=items';
    $linkBilling = 'addonmodules.php?module=eazybackup&action=powerpanel&view=billing';
    echo '<div class="alert alert-info">eazyBackup Power Panel: '
        . '<a class="btn btn-primary" href="' . $linkStorage . '">Open Storage</a> '
        . '<a class="btn btn-default" href="' . $linkDevices . '">Open Devices</a> '
        . '<a class="btn btn-default" href="' . $linkItems   . '">Open Protected Items</a> '
        . '<a class="btn btn-default" href="' . $linkBilling . '">Open Billing</a>'
        . '</div>';
}

function eazybackup_sidebar($vars)
{
    $base = $_SERVER['PHP_SELF'] . '?module=eazybackup';
    $sidebar = '<div class="list-group">'
        . '<a href="' . $base . '&action=powerpanel&view=storage" class="list-group-item">'
        . '<i class="fa fa-database"></i> Power Panel: Storage'
        . '</a>'
        . '<a href="' . $base . '&action=powerpanel&view=devices" class="list-group-item"><i class="fa fa-hdd"></i> Power Panel: Devices</a>'
        . '<a href="' . $base . '&action=powerpanel&view=items" class="list-group-item"><i class="fa fa-shield-alt"></i> Power Panel: Protected Items</a>'
        . '<a href="' . $base . '&action=powerpanel&view=billing" class="list-group-item"><i class="fa fa-balance-scale"></i> Power Panel: Billing</a>'
        . '<a href="' . $base . '&action=powerpanel&view=terms" class="list-group-item"><i class="fa fa-file-text-o"></i> Power Panel: Terms</a>'
        . '<a href="' . $base . '&action=powerpanel&view=privacy" class="list-group-item"><i class="fa fa-user-secret"></i> Power Panel: Privacy</a>'
        . '</div>';
    return $sidebar;
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
    // Resolve Turnstile site key from addon settings first, then constant/env as fallback
    $siteKey = ($vars['turnstilesitekey'] ?? '')
        ?: (defined('TURNSTILE_SITE_KEY') ? constant('TURNSTILE_SITE_KEY') : '')
        ?: (getenv('TURNSTILE_SITE_KEY') ?: '');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Preserve the original user input EXACTLY as submitted (for redisplay on errors)
    $rawPost = $_POST;

    // Handle POST (form submit)
    if (!empty($_POST)) {

        // 1) Validate form data (validator should NOT mutate $_POST)
        $errors = eazybackup_validate($_POST, $vars);

        // If validation fails, re-render with original inputs and site key
        if (!empty($errors)) {
            // Debug probe to help locate accidental mutation elsewhere (optional)
            error_log('PHONEDBG at entry: ' . ($rawPost['phonenumber'] ?? '(none)'));
            error_log('PHONEDBG before return: ' . ($_POST['phonenumber'] ?? '(none)'));

            return [
                "pagetitle"    => "Sign Up",
                "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
                "templatefile" => "templates/trialsignup",
                "requirelogin" => false,
                "forcessl"     => true,
                "vars"         => [
                    "modulelink"         => $vars["modulelink"],
                    "errors"             => $errors,
                    "POST"               => $rawPost,            // ← preserve exactly what the user typed
                    "TURNSTILE_SITE_KEY" => $siteKey,
                ],
            ];
        }

        try {
            // 2) Create the client with a strong random password (user sets their password after verification)
            $randomClientPassword = bin2hex(random_bytes(12));
            // Prefer posted full name if available; otherwise fall back to firstname/lastname; final fallback to placeholder
            $firstName = '';
            $lastName  = '';
            $fullNameIn = trim((string)($_POST['fullName'] ?? ''));
            if ($fullNameIn !== '') {
                $parts = preg_split('/\s+/', $fullNameIn, 2);
                $firstName = $parts[0];
                $lastName  = $parts[1] ?? $parts[0];
            } else {
                $firstName = trim((string)($_POST['firstname'] ?? ''));
                $lastName  = trim((string)($_POST['lastname'] ?? ''));
                if ($firstName === '' && $lastName !== '') {
                    $firstName = $lastName;
                } elseif ($firstName !== '' && $lastName === '') {
                    $lastName = $firstName;
                }
            }
            if ($firstName === '') {
                $firstName = 'eazyBackup User';
                $lastName  = '';
            }
            $clientData = [
                "firstname"      => $firstName,
                "lastname"       => $lastName,
                "email"          => (string)$_POST["email"],
                "phonenumber"    => (string)$_POST["phonenumber"],
                "password2"      => $randomClientPassword,
                "notes"          => "Trial signup (pending verification)",
                "skipvalidation" => true,
            ];
            $client = localAPI("AddClient", $clientData);
            if (($client["result"] ?? '') !== "success") {
                customFileLog("AddClient failed", $client);
                throw new \Exception("AddClient: " . ($client['message'] ?? 'unknown'));
            }
            // 2a) Flag client for first-login password onboarding (modal flow)
            try {
                if (function_exists('eazybackup_mark_must_set_password')) {
                    eazybackup_mark_must_set_password((int)$client["clientid"]);
                } else {
                    // Fallback direct insert/update if helper is unavailable
                    try {
                        $row = Capsule::table('eb_password_onboarding')->where('client_id', (int)$client["clientid"])->first();
                        $data = ['must_set' => 1, 'created_at' => date('Y-m-d H:i:s'), 'completed_at' => null];
                        if ($row) {
                            Capsule::table('eb_password_onboarding')->where('client_id', (int)$client["clientid"])->update($data);
                        } else {
                            $data['client_id'] = (int)$client["clientid"];
                            Capsule::table('eb_password_onboarding')->insert($data);
                        }
                    } catch (\Throwable $_) { /* non-fatal */ }
                }
            } catch (\Throwable $_) { /* non-fatal */ }

            // 3) Generate token and store verification record (48h expiry)
            $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $expiresAt = date('Y-m-d H:i:s', time() + 48 * 3600);
            $meta = [
                'username'  => (string)($_POST['username'] ?? ''),
                'productId' => (string)($_POST['product'] ?? ''),
                'phone'     => (string)($_POST['phonenumber'] ?? ''),
            ];
            Capsule::table('eazybackup_trial_verifications')->insert([
                'client_id'  => (int)$client["clientid"],
                'email'      => (string)$_POST["email"],
                'token'      => $token,
                'meta'       => json_encode($meta, JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
            ]);

            // 4) Build verification URL (robust SystemURL resolution)
            $systemUrl = (string)($vars['systemurl'] ?? '');
            if ($systemUrl === '') {
                try { $systemUrl = (string)(\WHMCS\Config\Setting::getValue('SystemURL') ?? ''); } catch (\Throwable $__) { $systemUrl = ''; }
            }
            if ($systemUrl === '' && !empty($_SERVER['HTTP_HOST'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $systemUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
            }
            if ($systemUrl === '') { $systemUrl = '/'; }
            if (substr($systemUrl, -1) !== '/') { $systemUrl .= '/'; }
            $verificationUrl = $systemUrl . 'index.php?m=eazybackup&a=verifytrial&token=' . urlencode($token);

            // 5) Resolve selected template name (General) by ID
            $tplId = (string)($vars['trial_verification_email_template'] ?? '');
            if ($tplId === '') {
                throw new \Exception('Trial verification email template is not configured.');
            }
            $templates = localAPI("GetEmailTemplates", ["type" => "general"]);
            $tplName = '';
            if (isset($templates["emailtemplates"]["emailtemplate"]) && is_array($templates["emailtemplates"]["emailtemplate"])) {
                foreach ($templates["emailtemplates"]["emailtemplate"] as $tpl) {
                    if ((string)($tpl["id"] ?? '') === $tplId) {
                        $tplName = (string)($tpl["name"] ?? '');
                        break;
                    }
                }
            }
            if ($tplName === '') {
                throw new \Exception('Selected trial verification email template not found.');
            }

            // 6) Send verification email with merge field
            $send = localAPI('SendEmail', [
                'messagename' => $tplName,
                'id'          => (int)$client["clientid"],
                // WHMCS expects base64(serialize(array)) for customvars
                'customvars'  => base64_encode(serialize([
                    'trial_verification_link' => $verificationUrl,
                ])),
            ]);
            if (($send['result'] ?? '') !== 'success') {
                customFileLog('SendEmail failed', $send);
                throw new \Exception('Failed to send verification email: ' . ($send['message'] ?? 'unknown'));
            }

            // 7) Re-render form with confirmation message (emailSent)
            return [
                "pagetitle"    => "Sign Up",
                "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
                "templatefile" => "templates/trialsignup",
                "requirelogin" => false,
                "forcessl"     => true,
                "vars"         => [
                    "modulelink"         => $vars["modulelink"],
                    "errors"             => [],
                    "POST"               => [], // don't echo sensitive inputs back
                    "TURNSTILE_SITE_KEY" => $siteKey,
                    "emailSent"          => true,
                    "email"              => (string)$_POST["email"],
                ],
            ];

        } catch (\Exception $e) {
            // Log and re-render the form with errors using the preserved POST
            $errors = $errors ?? [];
            if (empty($errors["error"])) {
                $errors["error"] = "There was an error completing your sign up. Please contact support.";
            }
            customFileLog("Signup process failed", $e->getMessage() . ' - ' . $e->getTraceAsString());

            return [
                "pagetitle"    => "Sign Up",
                "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
                "templatefile" => "templates/trialsignup",
                "requirelogin" => false,
                "forcessl"     => true,
                "vars"         => [
                    "modulelink"         => $vars["modulelink"],
                    "errors"             => $errors,
                    "POST"               => $rawPost,           // ← preserve user input here too
                    "TURNSTILE_SITE_KEY" => $siteKey,
                ],
            ];
        }
    }

    // Initial GET — just render the form (no POST yet)
    return [
        "pagetitle"    => "Sign Up",
        "breadcrumb"   => ["index.php?m=eazybackup" => "eazyBackup"],
        "templatefile" => "templates/trialsignup",
        "requirelogin" => false,
        "forcessl"     => true,
        "vars"         => [
            "modulelink"         => $vars["modulelink"],
            "errors"             => [],
            "POST"               => [],                      // no prior input on first load
            "TURNSTILE_SITE_KEY" => $siteKey,
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

        $errors = eazybackup_validate($_POST, $vars);
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
        // Compute payment + reseller flags (same logic as createorder)
        $clientId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
        $defaultGateway = '';
        $lastFour = '';
        $hasStripePayMethod = false;
        $isStripeDefault = false;
        try {
            $row = \WHMCS\Database\Capsule::table('tblclients')->where('id', $clientId)->first();
            if ($row) {
                $defaultGateway = (string)($row->defaultgateway ?? '');
                $lastFour = (string)($row->cardlastfour ?? '');
            }
        } catch (\Throwable $e) { /* ignore */ }
        $isStripeDefault = in_array(strtolower($defaultGateway), ['stripe','creditcard'], true);
        try {
            $hasStripePayMethod = \WHMCS\Database\Capsule::table('tblpaymethods')
                ->where('userid', $clientId)
                ->whereNull('deleted_at')
                ->whereIn('payment_type', ['CreditCard','RemoteCreditCard'])
                ->exists();
        } catch (\Throwable $e) { /* ignore */ }
        $hasCard = ($hasStripePayMethod || ($lastFour !== ''));

        $payment = [
            'defaultGateway'       => $defaultGateway,
            'isStripeDefault'      => $isStripeDefault,
            'hasCardOnFile'        => $hasCard,
            'lastFour'             => $lastFour,
            'addCardExternalUrl'   => rtrim((string)($vars['systemurl'] ?? ''), '/') . '/index.php/account/paymentmethods/add',
        ];

        // Reseller detection from module setting 'resellergroups'
        $resellerGroupsSetting = (string)($vars['resellergroups'] ?? '');
        if ($resellerGroupsSetting === '') {
            try {
                $resellerGroupsSetting = (string)(\WHMCS\Database\Capsule::table('tbladdonmodules')
                    ->where('module','eazybackup')
                    ->where('setting','resellergroups')
                    ->value('value') ?? '');
            } catch (\Throwable $e) { /* ignore */ }
        }
        $isResellerClient = false;
        if ($clientId > 0) {
            try {
                $gid = (int)(\WHMCS\Database\Capsule::table('tblclients')->where('id', $clientId)->value('groupid') ?? 0);
                if ($gid > 0 && $resellerGroupsSetting !== '') {
                    $ids = array_filter(array_map('trim', explode(',', $resellerGroupsSetting)), function($v){ return $v !== ''; });
                    $ids = array_map('intval', $ids);
                    $isResellerClient = in_array($gid, $ids, true);
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        return [
            "pagetitle" => "White Label Signup",
            "breadcrumb" => ["index.php?m=eazybackup" => "eazyBackup"],
            "templatefile" => "templates/whitelabel-signup",
            "requirelogin" => false,
            "forcessl" => true,
            "vars" => array_merge($vars, [
                "custom_domain" => $custom_domain,
                "payment" => $payment,
                "isResellerClient" => $isResellerClient,
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
         *  Ensure one product group per client (mapping is source of truth)
         *  Never associate by matching names.
         * ------------------------------------------------------------------*/
        try {
            $ops = new \EazyBackup\Whitelabel\WhmcsOps();
            $groupId = (int)$ops->ensureClientProductGroup((int)$clientId);
        } catch (\Throwable $e) {
            logActivity('eazybackup: ensureClientProductGroup failed for client '.$clientId.' => '.$e->getMessage());
        }


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
 * @param int    $targetGroup  Destination productgroup ID
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

        // POST enforcement: block OBC products for non-resellers before proceeding
        if (empty($errors)) {
            try {
                $clientIdPost = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
                $resellerGroupsSetting = (string)($vars['resellergroups'] ?? '');
                if ($resellerGroupsSetting === '') {
                    try {
                        $resellerGroupsSetting = (string)(Capsule::table('tbladdonmodules')
                            ->where('module','eazybackup')
                            ->where('setting','resellergroups')
                            ->value('value') ?? '');
                    } catch (\Throwable $_) { /* ignore */ }
                }
                $isResellerClientPost = false;
                if ($clientIdPost > 0 && $resellerGroupsSetting !== '') {
                    $gid = (int)(Capsule::table('tblclients')->where('id', $clientIdPost)->value('groupid') ?? 0);
                    if ($gid > 0) {
                        $ids = array_filter(array_map('trim', explode(',', $resellerGroupsSetting)), function($v){ return $v !== ''; });
                        $ids = array_map('intval', $ids);
                        $isResellerClientPost = in_array($gid, $ids, true);
                    }
                }
                if (!$isResellerClientPost && in_array((int)$selectedPid, [60,57,54], true)) {
                    $errors['product'] = 'This product is available to resellers only.';
                }
            } catch (\Throwable $_) { /* fail open on error */ }
        }

        if (empty($errors)) {
            $notes = "Reseller account created on " . date("Y-m-d H:i:s");
            try {
                // Check for Client ID in session
                $clientid = $_SESSION['uid'] ?? null;
                if (!$clientid) {
                    throw new \Exception("Client ID is missing from session.");
                }
                logActivity("eazybackup: Client ID => " . $clientid);

                // Persist consolidated billing preference once (immutable for client)
                try {
                    $cbEnabled = isset($_POST['cb_enabled']) && (string)$_POST['cb_enabled'] === '1';
                    $cbDomRaw  = isset($_POST['cb_dom']) ? (int)$_POST['cb_dom'] : 0;
                    if ($cbEnabled && $cbDomRaw >= 1 && $cbDomRaw <= 31) {
                        $exists = Capsule::table('mod_eazy_consolidated_billing')
                            ->where('clientid', (int)$clientid)
                            ->where('enabled', 1)
                            ->exists();
                        if (!$exists) {
                            $tz = 'America/Toronto';
                            Capsule::table('mod_eazy_consolidated_billing')->updateOrInsert(
                                ['clientid' => (int)$clientid],
                                [
                                    'enabled'        => 1,
                                    'dom'            => (int)$cbDomRaw,
                                    'timezone'       => $tz,
                                    'effective_from' => Carbon::now($tz)->toDateString(),
                                    'updated_at'     => Carbon::now()->toDateTimeString(),
                                ]
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    logActivity('eazybackup: consolidated billing persist failed: ' . $e->getMessage());
                }

                // Honour the requested billing term when placing the order so WHMCS
                // can always pick a valid cycle (important for tenant / white‑label products).
                $reqBillingTerm  = (($_POST['billingterm'] ?? 'monthly') === 'annual') ? 'annual' : 'monthly';
                $reqBillingCycle = $reqBillingTerm === 'annual' ? 'Annually' : 'Monthly';

                $orderData = [
                    "clientid"      => $clientid,
                    "pid"           => [$selectedPid],
                    "promocode"     => "trial",
                    "paymentmethod" => "stripe",
                    "billingcycle"  => $reqBillingCycle,
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

                // If WHMCS accepted the order but did not attach any services, treat this as
                // a hard failure instead of continuing with null service IDs (which causes
                // confusing follow‑on errors and empty orders).
                if (!$service) {
                    logActivity("eazybackup: ERROR – no service row found for order {$order['orderid']} after AcceptOrder; aborting createorder flow.");
                    throw new \Exception("Service record not found after order acceptance (orderid={$order['orderid']}).");
                }

                if ($isWhiteLabel) {
                    // handled below by universal billing settings
                }
                
                // ----- Billing start date and cycle (respect consolidated billing if enabled) -----
                $billingTerm = (($_POST['billingterm'] ?? 'monthly') === 'annual') ? 'annual' : 'monthly';
                $billingCycle = $billingTerm === 'annual' ? 'Annually' : 'Monthly';
                $pref = null;
                try {
                    $pref = Capsule::table('mod_eazy_consolidated_billing')
                        ->where('clientid', (int)$clientid)
                        ->where('enabled', 1)
                        ->first();
                } catch (\Throwable $e) { $pref = null; }

                $tz = is_string($pref->timezone ?? '') && $pref->timezone !== '' ? (string)$pref->timezone : 'America/Toronto';
                $updateData = [
                    'serviceid'    => $service->id,
                    'billingcycle' => $billingCycle,
                ];
                if ($pref) {
                    $base = Carbon::now($tz)->startOfDay();
                    $dom  = (int)$pref->dom;
                    $candidate = eb_computeConsolidatedDueDate($base, $dom, $billingTerm, $tz);
                    $candidateStr = $candidate->toDateString();
                    $updateData['nextduedate']     = $candidateStr;
                    $updateData['nextinvoicedate'] = $candidateStr;
                    $updateData['regdate']         = $base->toDateString();
                } else {
                    $nextDate = date('Y-m-d', strtotime('+30 days'));
                    $updateData['nextduedate']     = $nextDate;
                    $updateData['nextinvoicedate'] = $nextDate;
                }
                logActivity("eazybackup: UpdateClientProduct => " . json_encode($updateData));
                $updateResult = localAPI('UpdateClientProduct', $updateData);
                logActivity("eazybackup: UpdateClientProduct Response => " . json_encode($updateResult));

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

                // 1) Build the "module parameters" that comet_UpdateUser() expects
                $params = comet_ServiceParams($service->id);

                // overwrite/add the two things we care about:
                $params['username']         = $_POST['username'];
                $params['clientsdetails']   = [
                    // comet_UpdateUser() does $params["clientsdetails"]["email"]
                    'email' => $_POST['reportemail'] ?? ''
                ];

                // 2) Call the Comet helper to update that user's notification email
                try {
                    logActivity("eazybackup: Updating Comet user email to {$params['clientsdetails']['email']}");
                    comet_UpdateUser($params);
                } catch (\Exception $e) {
                    // if you want to fail the order on error, throw; otherwise just log
                    logActivity("eazybackup: comet_UpdateUser failed: " . $e->getMessage());
                }

                // ----- Apply default configuration options per product selection -----
                try {
                    eazybackup_apply_default_config_options((int)$service->id, (int)$selectedPid);
                } catch (\Throwable $e) {
                    logActivity("eazybackup: apply_default_config_options failed: " . $e->getMessage());
                }

                // Compute recurring amount from config options only and persist to hosting
                try {
                    $cycle = ($billingTerm === 'annual') ? 'annually' : 'monthly';
                    $amount = eazybackup_compute_recurring_amount_from_options((int)$service->id, $cycle);
                    // Persist to hosting: amount, recurringamount, firstpaymentamount
                    localAPI('UpdateClientProduct', [
                        'serviceid'          => (int)$service->id,
                        'amount'             => $amount,
                        'recurringamount'    => $amount,
                        'firstpaymentamount' => $amount,
                    ]);
                } catch (\Throwable $e) {
                    logActivity('eazybackup: set recurring amount failed for service ' . (int)$service->id . ' - ' . $e->getMessage());
                }

                // --- Begin Redirect Logic ---
                // Default settings
                $redirectProductParam = "eazybackup";  // default product param
                $template = "complete";                // default template action

                // 1) Check user's client group ID (legacy check)
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
                    // Anything outside groups 6 & 7 is a white-label product
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

    // Resolve reseller group membership early for filtering
    $resellerGroupsSetting = (string)($vars['resellergroups'] ?? '');
    if ($resellerGroupsSetting === '') {
        try {
            $resellerGroupsSetting = (string)(Capsule::table('tbladdonmodules')
                ->where('module','eazybackup')
                ->where('setting','resellergroups')
                ->value('value') ?? '');
        } catch (\Throwable $e) { /* ignore */ }
    }
    $isResellerClient = false;
    if ($clientid) {
        try {
            $gidEarly = (int)(Capsule::table('tblclients')->where('id', $clientid)->value('groupid') ?? 0);
            if ($gidEarly > 0 && $resellerGroupsSetting !== '') {
                $idsEarly = array_filter(array_map('trim', explode(',', $resellerGroupsSetting)), function($v){ return $v !== ''; });
                $idsEarly = array_map('intval', $idsEarly);
                $isResellerClient = in_array($gidEarly, $idsEarly, true);
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

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
    // logActivity("eazybackup: Total products => " . count($allProducts));

    // Define category arrays
    $categories = [
        'whitelabel' => [],
        'ms365'      => [], // 52, 57
        'usage'      => [], // 58, 60
        'hyperv'     => [], // 53, 54
    ];

    // a) White‑label products for this client
    //    1) All tenant-bound products for this client (multi-tenant)
    try {
        $tenantPids = [];
        if ($clientid) {
            $rows = Capsule::table('eb_whitelabel_tenants')
                ->select('product_id')
                ->where('client_id', (int)$clientid)
                ->where('status', 'active')
                ->whereNotNull('product_id')
                ->get();
            foreach ($rows as $r) {
                $pid = (int)($r->product_id ?? 0);
                if ($pid > 0) { $tenantPids[$pid] = true; }
            }
        }
        if (!empty($tenantPids)) {
            $pidList = array_keys($tenantPids);
            // Fetch exact products by PID to avoid leaking other clients' products
            $prodRows = Capsule::table('tblproducts')
                ->select(['id','name','gid'])
                ->whereIn('id', $pidList)
                ->get();
            $wl = [];
            foreach ($prodRows as $pr) {
                $wl[] = [
                    'pid' => (int)$pr->id,
                    'name' => (string)$pr->name,
                    'gid' => (int)$pr->gid,
                ];
            }
            // Deduplicate by pid (defensive)
            if (!empty($wl)) {
                $seen = [];
                $out = [];
                foreach ($wl as $p) {
                    $pp = (int)($p['pid'] ?? 0);
                    if ($pp > 0 && !isset($seen[$pp])) { $seen[$pp] = true; $out[] = $p; }
                }
                $categories['whitelabel'] = $out;
            }
        } else {
            // No tenant rows for this client → leave whitelabel empty (do not fallback to group to avoid cross-client leakage)
            $categories['whitelabel'] = [];
        }
    } catch (\Throwable $e) {
        try { logActivity('eazybackup: unable to load tenant products: ' . $e->getMessage()); } catch (\Throwable $_) {}
        $categories['whitelabel'] = [];
    }

    // b) Include the six specified products with reseller filtering for OBC
    $blockedOBCPids = [60, 57, 54];
    foreach ($allProducts as $p) {
        $pid = (int)$p['pid'];
        // Skip OBC products for non-reseller clients
        if (!$isResellerClient && in_array($pid, $blockedOBCPids, true)) {
            continue;
        }
        if ($pid === 52 || $pid === 57) { $categories['ms365'][]  = $p; }
        if ($pid === 58 || $pid === 60) { $categories['usage'][]  = $p; }
        if ($pid === 53 || $pid === 54) { $categories['hyperv'][] = $p; }
    }

    // logActivity("eazybackup: Final categories => " . print_r($categories, true));

    // -----------------------------
    // 3) Payment gating + Live pricing
    // -----------------------------
    $clientId = $_SESSION['uid'] ?? ($vars['clientsdetails']['id'] ?? null);
    $clientId = $clientId ? (int)$clientId : 0;
    $currencyData = $clientId ? getCurrency($clientId) : getCurrency();
    $currencyId = (int)($currencyData['id'] ?? 1);

    // Detect default gateway and whether a non-deleted Stripe card pay method exists
    $defaultGateway = '';
    $lastFour = '';
    $hasStripePayMethod = false;
    if ($clientId > 0) {
        $row = Capsule::table('tblclients')->select('defaultgateway','cardlastfour')->where('id', $clientId)->first();
        if ($row) {
            $defaultGateway = (string)($row->defaultgateway ?? '');
            $lastFour = (string)($row->cardlastfour ?? '');
        }
        try {
            // Prefer WHMCS PayMethod model when available
            if (class_exists('\\WHMCS\\Payment\\PayMethod\\PayMethod')) {
                $hasStripePayMethod = (\WHMCS\Payment\PayMethod\PayMethod::where('userid', $clientId)
                    ->whereNull('deleted_at')
                    ->whereIn('payment_type', ['CreditCard','RemoteCreditCard'])
                    ->where('gateway_name', 'stripe')
                    ->count()) > 0;
            } else if (Capsule::schema()->hasTable('tblpaymethods')) {
                // Fallback direct DB query
                $hasStripePayMethod = Capsule::table('tblpaymethods')
                    ->where('userid', $clientId)
                    ->whereNull('deleted_at')
                    ->whereIn('payment_type', ['CreditCard','RemoteCreditCard'])
                    ->where('gateway_name', 'stripe')
                    ->exists();
            }
        } catch (\Throwable $e) {
            // ignore – environments may vary
        }
    }
    $isStripeDefault = in_array(strtolower($defaultGateway), ['stripe','creditcard'], true);
    // Primary: non-deleted Stripe card pay method; Fallback: legacy last-4 value
    $hasCard = ($hasStripePayMethod || ($lastFour !== ''));
    $showStripeCapture = ($isStripeDefault && !$hasCard);

    // Attempt to read Stripe publishable key (must look like pk_...)
    $stripePublishableKey = '';
    // Ensure gateway helper is available so values can be decrypted by WHMCS
    if (!function_exists('getGatewayVariables')) {
        $gwInc = __DIR__ . '/../../../includes/gatewayfunctions.php';
        if (is_file($gwInc)) {
            require_once $gwInc;
        }
    }
    try {
        if (Capsule::schema()->hasTable('tblpaymentgateways')) {
            // Primary lookup: standard publishable key settings
            $candidate = (string)(
                Capsule::table('tblpaymentgateways')
                    ->where('gateway', 'stripe')
                    ->whereIn('setting', ['publishableKey','publishablekey'])
                    ->value('value') ?? ''
            );

            // Fallback: search any value that looks like a publishable key for the stripe gateway
            if ($candidate === '' || strpos($candidate, 'pk_') !== 0) {
                $fallback = Capsule::table('tblpaymentgateways')
                    ->where('gateway', 'stripe')
                    ->where('value', 'like', 'pk\_%')
                    ->orderBy('setting')
                    ->value('value');
                if (is_string($fallback) && $fallback !== '') {
                    $candidate = $fallback;
                }
            }

            if (strpos((string)$candidate, 'pk_') === 0) {
                $stripePublishableKey = (string)$candidate;
            }
        }
        // Extra fallback via gateway variables resolver when available (decrypted values)
        if ($stripePublishableKey === '' && function_exists('getGatewayVariables')) {
            $gw = getGatewayVariables('stripe');
            if (is_array($gw)) {
                $cand2 = (string)($gw['publishableKey'] ?? $gw['publishablekey'] ?? $gw['publishable_key'] ?? $gw['public_key'] ?? '');
                if ($cand2 !== '' && strpos($cand2, 'pk_') === 0) {
                    $stripePublishableKey = $cand2;
                }
            }
        }
    } catch (\Throwable $e) {
        // ignore
    }

    // If Stripe is default and we want capture but no valid pk_ key, disable capture to avoid JS errors
    if ($showStripeCapture && !(strpos($stripePublishableKey, 'pk_') === 0)) {
        $showStripeCapture = false;
    }

    // Live pricing map: pre-compute monthly and annually strings for each cid
    $CONFIG_CIDS = [60,67,88,91,97,99,102];
    $pricing = [];
    foreach ($CONFIG_CIDS as $cid) {
        $pricing[$cid] = [
            'monthly'  => eazybackup_format_currency(eazybackup_get_config_unit_price((int)$cid, $currencyId, 'monthly'), $currencyId),
            'annually' => eazybackup_format_currency(eazybackup_get_config_unit_price((int)$cid, $currencyId, 'annually'), $currencyId),
        ];
    }

    $units = [
        67  => 'terabyte',
        88  => 'device',
        91  => 'protected machine',
        97  => 'virtual machine',
        99  => 'virtual machine',
        102 => 'virtual machine',
        60  => 'account',
    ];

    // Compute lastFour for UI display (tokenized gateways)
    $lastFour = '';
    $cardDisplayName = '';
    try {
        if (class_exists('\\WHMCS\\Payment\\PayMethod\\PayMethod')) {
            $pm = \WHMCS\Payment\PayMethod\PayMethod::where('userid', $clientId)
                ->whereNull('deleted_at')
                ->whereIn('payment_type', ['CreditCard','RemoteCreditCard'])
                ->orderBy('is_default', 'desc')
                ->first();
            if ($pm && $pm->payment && method_exists($pm->payment, 'getDisplayName')) {
                $disp = $pm->payment->getDisplayName(); // e.g. "Visa - 4242"
                if (is_string($disp) && $disp !== '') { $cardDisplayName = $disp; }
                if (preg_match('/(\d{4})\s*$/', $disp, $m)) { $lastFour = $m[1]; }
            } elseif ($pm && is_array($pm->data ?? null)) {
                $lastFour = $pm->data['lastFour'] ?? ($pm->data['cardLastFour'] ?? '');
                if ($lastFour === '' && !empty($pm->data['maskedCardNumber'])) {
                    $lastFour = substr(preg_replace('/\D+/', '', $pm->data['maskedCardNumber']), -4);
                }
            }
        }
    } catch (\Throwable $e) { /* ignore */ }

    if ($lastFour === '' && class_exists('\\WHMCS\\Database\\Capsule')) {
        try {
            $row = \WHMCS\Database\Capsule::table('tblpaymethods')
                ->where('userid', $clientId)
                ->whereNull('deleted_at')
                ->whereIn('payment_type', ['CreditCard','RemoteCreditCard'])
                ->orderBy('is_default','desc')
                ->value('data');
            if (is_string($row) && $row !== '') {
                $data = json_decode($row, true) ?: [];
                $lastFour = $data['lastFour'] ?? ($data['cardLastFour'] ?? '');
                if ($lastFour === '' && !empty($data['maskedCardNumber'])) {
                    $lastFour = substr(preg_replace('/\D+/', '', $data['maskedCardNumber']), -4);
                }
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    // API fallback for environments where model/data not available
    if ($lastFour === '') {
        try {
            $resp = localAPI('GetPayMethods', ['clientid' => $clientId]);
            if (($resp['result'] ?? '') === 'success' && !empty($resp['paymethods']) && is_array($resp['paymethods'])) {
                $card = null;
                foreach ($resp['paymethods'] as $pm) {
                    $ptype = $pm['payment_type'] ?? '';
                    if ($ptype === 'CreditCard' || $ptype === 'RemoteCreditCard') {
                        if (!empty($pm['is_default'])) { $card = $pm; break; }
                        if ($card === null) { $card = $pm; }
                    }
                }
                if ($card) {
                    if (isset($card['last_four']) && is_scalar($card['last_four'])) {
                        $lastFour = (string)$card['last_four'];
                    }
                    // Build display name if provided
                    if (!empty($card['description']) && is_string($card['description'])) {
                        $cardDisplayName = (string)$card['description'];
                    } elseif (!empty($card['brand']) && $lastFour !== '') {
                        $cardDisplayName = (string)$card['brand'] . ' - ' . $lastFour;
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    if ($lastFour === '') {
        try {
            $legacyLast4 = \WHMCS\Database\Capsule::table('tblclients')->where('id', $clientId)->value('cardlastfour');
            if (is_string($legacyLast4) && $legacyLast4 !== '') { $lastFour = $legacyLast4; }
        } catch (\Throwable $e) { /* ignore */ }
    }

    $payment = [
        'defaultGateway'     => $defaultGateway,
        'isStripeDefault'    => $isStripeDefault,
        'hasCardOnFile'      => $hasCard,
        'lastFour'           => $lastFour,
        'cardDisplayName'    => $cardDisplayName,
        'showStripeCapture'  => $showStripeCapture,
        'addCardUrl'         => $vars['modulelink'] . '&a=add-card',
        'stripeJsUrl'        => $vars['systemurl'] . '/modules/gateways/stripe/stripe.js',
        'stripePublishableKey' => $stripePublishableKey,
        // Fallback: open WHMCS native Add Payment Method UI
        'addCardExternalUrl' => $vars['systemurl'] . '/index.php/account/paymentmethods/add',
    ];

    // -----------------------------
    // 4) Reseller group check + Return Data to Template
    // -----------------------------
    // Pull reseller groups from module config (with DB fallback if not present)
    $resellerGroupsSetting = (string)($vars['resellergroups'] ?? '');
    if ($resellerGroupsSetting === '') {
        try {
            $resellerGroupsSetting = (string)(Capsule::table('tbladdonmodules')
                ->where('module','eazybackup')
                ->where('setting','resellergroups')
                ->value('value') ?? '');
        } catch (\Throwable $e) { /* ignore */ }
    }
    $isResellerClient = false;
    if ($clientId > 0) {
        try {
            $gid = (int)(Capsule::table('tblclients')->where('id', $clientId)->value('groupid') ?? 0);
            if ($gid > 0 && $resellerGroupsSetting !== '') {
                $ids = array_filter(array_map('trim', explode(',', $resellerGroupsSetting)), function($v){ return $v !== ''; });
                $ids = array_map('intval', $ids);
                $isResellerClient = in_array($gid, $ids, true);
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    // Determine announcement dismissal state for this session
    $showCreateOrderAnnouncement = false;
    try {
        $userId = null;
        try {
            if (class_exists('\\WHMCS\\User\\User')) {
                $u = \WHMCS\User\User::fromSession();
                if ($u && $u->id) { $userId = (int)$u->id; }
            }
        } catch (\Throwable $e) { /* ignore */ }
        $clientId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
        $q = Capsule::table('mod_eazybackup_dismissals')->where('announcement_key', ANNOUNCEMENT_KEY);
        if ($userId) { $q->where(function($qq) use ($userId, $clientId) { $qq->where('user_id', $userId)->orWhere('client_id', $clientId); }); }
        else if ($clientId) { $q->where('client_id', $clientId); }
        $dismissed = $q->exists();
        $showCreateOrderAnnouncement = !$dismissed;
    } catch (\Throwable $e) { $showCreateOrderAnnouncement = false; }
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
            "payment" => $payment,
            "pricing" => $pricing,
            "units" => $units,
            "currency" => $currencyData,
            "isResellerClient" => $isResellerClient,
            // Consolidated billing preference for UI hydration
            "cbPref" => (function() use ($clientId) {
                try {
                    $pref = Capsule::table('mod_eazy_consolidated_billing')
                        ->where('clientid', (int)$clientId)
                        ->where('enabled', 1)
                        ->first();
                    $tz = 'America/Toronto';
                    $today = Carbon::now($tz)->toDateString();
                    if ($pref) {
                        return [
                            'enabled' => true,
                            'dom'     => (int)$pref->dom,
                            'timezone'=> (string)($pref->timezone ?? $tz),
                            'locked'  => true,
                            'today'   => $today,
                        ];
                    }
                    $todayDom = (int)Carbon::now($tz)->day;
                    return [
                        'enabled' => false, // default OFF when no saved preference
                        'dom'     => $todayDom,
                        'timezone'=> $tz,
                        'locked'  => false,
                        'today'   => $today,
                    ];
                } catch (\Throwable $e) {
                    $tz = 'America/Toronto';
                    return [
                        'enabled' => false,
                        'dom'     => (int)Carbon::now($tz)->day,
                        'timezone'=> $tz,
                        'locked'  => false,
                        'today'   => Carbon::now($tz)->toDateString(),
                    ];
                }
            })(),
            // Announcement modal
            "showCreateOrderAnnouncement" => (bool)$showCreateOrderAnnouncement,
            "createOrderAnnouncementKey" => ANNOUNCEMENT_KEY,
            "csrfTokenPlain" => function_exists('generate_token') ? generate_token('plain') : '',
            "dismissEndpointUrl" => rtrim((string)($vars['systemurl'] ?? ''), '/') . '/modules/addons/eazybackup/endpoints/dismiss_announcement.php',
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

function eazybackup_validate(array $vars, array $settings = [])
{
    // ---- Safe logging (mask secrets) ---------------------------------------
    $toLog = $vars;
    foreach (['password','confirmpassword','cf-turnstile-response','responseToken','turnstile_response'] as $s) {
        if (isset($toLog[$s])) $toLog[$s] = '***redacted***';
    }
    error_log("Form submission data (masked): " . print_r($toLog, true));

    $errors = [];

    // ---- Turnstile token + secret ------------------------------------------
    $token = $vars['cf-turnstile-response']
        ?? $vars['responseToken']
        ?? $vars['turnstile_response']
        ?? '';

    // Prefer addon settings arg, then $vars from WHMCS, then constants/env
    $secret = ($settings['turnstilesecret'] ?? '')
        ?: ($vars['turnstilesecret'] ?? '')
        ?: (defined('TURNSTILE_SECRET_KEY') ? constant('TURNSTILE_SECRET_KEY') : '')
        ?: (getenv('TURNSTILE_SECRET_KEY') ?: '');

    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($token === '' || !validateTurnstile($token, $secret, $remoteIp)) {
        $errors['turnstile'] = 'Please complete the verification.';
        logModuleCall(
            'eazybackup',
            'ValidateTurnstile',
            ['hasToken' => $token !== '', 'hasSecret' => $secret !== ''],
            ['success' => false]
        );
    }

    // ---- Username -----------------------------------------------------------
    if (empty($vars['username']) || !preg_match('/^[a-zA-Z0-9._-]{6,}$/', $vars['username'])) {
        $errors['username'] = 'Username must be at least 6 characters and may contain only letters, numbers, periods, underscores, or hyphens.';
    } else {
        try {
            // If this does not throw, user exists -> reject
            comet_Server(['pid' => $vars['product']])->AdminGetUserProfile($vars['username']);
            $errors['username'] = 'That username is not available, please try another.';
        } catch (\Throwable $e) {
            // Username likely available; do nothing
        }
    }

    // ---- Password strength (only validate if provided) ----------------------
    $hasPassword = isset($vars['password']) && $vars['password'] !== '';
    $hasConfirm  = isset($vars['confirmpassword']) && $vars['confirmpassword'] !== '';
    if ($hasPassword || $hasConfirm) {
        if (!$hasPassword || !isValidPassword($vars['password'])) {
        $errors['password'] = 'Password must be at least 8 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character.';
    }
        if (!$hasConfirm) {
        $errors['confirmpassword'] = 'You must confirm your password.';
    } elseif (($vars['confirmpassword'] ?? '') !== ($vars['password'] ?? '')) {
        $errors['confirmpassword'] = 'Passwords do not match.';
        }
    }

    // ---- Email --------------------------------------------------------------
    if (empty($vars['email'])) {
        $errors['email'] = 'You must provide an email address.';
    } else {
        $email = trim($vars['email']);
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        $blocked = defined('BLOCKED_EMAIL_DOMAINS') && is_array(BLOCKED_EMAIL_DOMAINS)
            ? BLOCKED_EMAIL_DOMAINS
            : []; // default if not defined

        if ($domain && in_array($domain, $blocked, true)) {
            $errors['email'] = 'Please sign up with your business email address.';
        } else {
            // Use GetClients to search by email
            $resp = localAPI('GetClients', ['search' => $email, 'limitnum' => 1]);
            error_log('WHMCS GetClients (masked): ' . print_r(['result' => $resp['result'] ?? null, 'numreturned' => $resp['numreturned'] ?? null], true));
            if (($resp['result'] ?? '') === 'success' && (int)($resp['numreturned'] ?? 0) > 0) {
                $errors['email'] = 'This email address is already in use. <a href="clientarea.php" target="_top">Log in to Client Area</a>.';
            }
        }
    }

    // ---- Product (optional) -------------------------------------------------
    // if (!in_array($vars['product'] ?? null, comet_GetPids(), true)) {
    //     $errors['product'] = 'Please select a backup plan.';
    // }

    return $errors;
}

function validateTurnstile(string $cfToken, string $secretKey, ?string $remoteIp = null): bool
{
    // Fast fail with safe logging (no secrets/token)
    if ($secretKey === '' || $cfToken === '') {
        logModuleCall(
            'eazybackup',
            'TurnstileSiteVerify',
            ['hasToken' => $cfToken !== '', 'hasSecret' => $secretKey !== ''],
            ['success' => false, 'reason' => 'missing key or token']
        );
        return false;
    }

    $url  = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret'   => $secretKey,
        'response' => $cfToken,
    ];
    if (!empty($remoteIp)) {
        $data['remoteip'] = $remoteIp;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_TIMEOUT        => 10,
        // SSL verification is on by default; do not disable it.
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        logModuleCall('eazybackup', 'TurnstileSiteVerifyCurlError', ['hasToken' => true], ['error' => $err]);
        return false;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
    curl_close($ch);

    // Decode safely
    $resp = json_decode($raw, true);
    if (!is_array($resp)) {
        logModuleCall('eazybackup', 'TurnstileSiteVerifyBadJSON', ['hasToken' => true, 'httpCode' => $httpCode], ['raw' => substr($raw, 0, 400)]);
        return false;
    }

    // Log outcome without secrets
    logModuleCall(
        'eazybackup',
        'TurnstileSiteVerify',
        ['hasToken' => true, 'httpCode' => $httpCode],
        ['success' => $resp['success'] ?? null, 'error-codes' => $resp['error-codes'] ?? null]
    );

    return !empty($resp['success']);
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

/** Simple currency formatting that honors WHMCS currencies */
function eazybackup_format_currency(float $amount, int $currencyId): string {
    try {
        if (!function_exists('formatCurrency')) {
            // Fallback: prefix with symbol from tblcurrencies
            $c = Capsule::table('tblcurrencies')->where('id', $currencyId)->first();
            $prefix = $c && isset($c->prefix) ? (string)$c->prefix : '$';
            return $prefix . number_format($amount, 2);
        }
        return formatCurrency($amount, $currencyId);
    } catch (\Throwable $_) {
        return '$' . number_format($amount, 2);
    }
}

/**
 * Resolve a Quantity-type configuration option unit price for a given currency and cycle.
 * - Validates optiontype is Quantity (treat 3 or 4 as quantity to be tolerant across versions)
 * - Resolves the first sub-option by sortorder/id to get pricing relid
 * - Returns the price column matching $cycle (monthly|annually) from tblpricing
 */
function eazybackup_get_config_unit_price(int $configId, int $currencyId, string $cycle = 'monthly'): float {
    try {
        $opt = Capsule::table('tblproductconfigoptions')
            ->select(['id','optiontype'])
            ->where('id', $configId)->first();
        if (!$opt) { return 0.0; }
        $type = (int)$opt->optiontype;
        if (!in_array($type, [3,4], true)) {
            // Data mismatch vs. expectation; log once per id
            logActivity("eazybackup: Config option $configId optiontype=$type is not Quantity (3/4).");
        }
        // pick the first suboption as the unit relid
        $subId = Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $configId)
            ->orderBy('sortorder')
            ->orderBy('id')
            ->value('id');
        $relid = $subId ? (int)$subId : (int)$configId; // fallback
        $row = Capsule::table('tblpricing')
            ->where('type','configoptions')
            ->where('currency', $currencyId)
            ->where('relid', $relid)
            ->first();
        if (!$row) { return 0.0; }
        $col = ($cycle === 'annually') ? 'annually' : 'monthly';
        return (float)($row->{$col} ?? 0.0);
    } catch (\Throwable $e) {
        return 0.0;
    }
}

/**
 * Apply default config options with qty=1 for a new service according to rules.
 */
function eazybackup_apply_default_config_options(int $serviceId, int $pid): void {
    // Map of product id => array of config option ids to set qty=1
    $map = [
        58 => [67, 88], // eazyBackup
        60 => [67, 88], // OBC
        // 52,57,53,54 => no defaults
    ];
    if (!isset($map[$pid])) { return; }

    foreach ($map[$pid] as $configId) {
        // find unit suboption id
        $subId = Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $configId)
            ->orderBy('sortorder')->orderBy('id')
            ->value('id');
        $optionId = $subId ? (int)$subId : (int)$configId; // fallback
        // upsert hosting config option row
        $exists = Capsule::table('tblhostingconfigoptions')
            ->where('relid', $serviceId)
            ->where('configid', $configId)
            ->exists();
        if ($exists) {
            Capsule::table('tblhostingconfigoptions')
                ->where('relid', $serviceId)
                ->where('configid', $configId)
                ->update(['optionid' => $optionId, 'qty' => 1]);
        } else {
            Capsule::table('tblhostingconfigoptions')->insert([
                'relid'    => $serviceId,
                'configid' => $configId,
                'optionid' => $optionId,
                'qty'      => 1,
            ]);
        }
    }
}

/**
 * Compute the recurring amount for a service from config options only.
 * $cycle: 'monthly' | 'annually'
 * Missing or -1.00 prices are treated as 0.
 */
function eazybackup_compute_recurring_amount_from_options(int $serviceId, string $cycle): float {
    $cycleCol = ($cycle === 'annually') ? 'annually' : 'monthly';
    try {
        $hosting = Capsule::table('tblhosting')->select('userid','packageid')->where('id', $serviceId)->first();
        if (!$hosting) { return 0.0; }
        $clientId = (int)($hosting->userid ?? 0);
        $pid      = (int)($hosting->packageid ?? 0);

        // Resolve currency for client
        $currency = function_exists('getCurrency') ? getCurrency($clientId) : null;
        $currencyId = (int)($currency['id'] ?? 1);

        // Only bill the intended config options per product rules
        $allowedCids = [];
        if ($pid === 58 || $pid === 60) {
            $allowedCids = [67, 88];
        } else {
            // Other PIDs (52,57,53,54, etc.) → no config billing per rules
            return 0.0;
        }

        $rows = Capsule::table('tblhostingconfigoptions')
            ->select('configid','optionid','qty')
            ->where('relid', $serviceId)
            ->whereIn('configid', $allowedCids)
            ->get();

        if (!$rows || count($rows) === 0) { return 0.0; }

        $total = 0.0;
        foreach ($rows as $r) {
            $relid = isset($r->optionid) ? (int)$r->optionid : 0;
            if ($relid <= 0) { continue; }
            $qty = isset($r->qty) ? (int)$r->qty : 0;
            if ($qty <= 0) { continue; } // Only bill positive quantities

            $priceRow = Capsule::table('tblpricing')
                ->where('type', 'configoptions')
                ->where('currency', $currencyId)
                ->where('relid', $relid)
                ->first();
            if (!$priceRow) { continue; }

            $raw = $priceRow->{$cycleCol} ?? null;
            $price = is_numeric($raw) ? (float)$raw : 0.0;
            if ($price < 0) { $price = 0.0; } // treat -1.00 as 0

            $total += ($price * $qty);
        }

        return round($total, 2);
    } catch (\Throwable $e) {
        try { logActivity('eazybackup: compute amount failed for service ' . (int)$serviceId . ' - ' . $e->getMessage()); } catch (\Throwable $_) {}
        return 0.0;
    }
}

/** Validate bulk rows and return errors and counts. */
function eb_bulk_validate_rows(int $clientId, int $pid, string $term, array $rows, int $currencyId): array {
    $maxRows = 100;
    $out = ['errors' => [], 'row_errors' => [], 'valid_count' => 0, 'error_count' => 0];
    if (count($rows) > $maxRows) {
        $out['errors'][] = 'Too many rows. Max 100.';
        return $out;
    }
    // Basic validators
    $seenUsernames = [];
    $indices = range(0, count($rows)-1);
    foreach ($indices as $i) {
        $r = $rows[$i] ?? [];
        $u = (string)($r['username'] ?? '');
        $p = (string)($r['password'] ?? '');
        $e = (string)($r['email'] ?? '');
        $errs = [];
        if (!preg_match('/^[a-z0-9._-]{3,64}$/i', $u)) { $errs['username'] = 'Invalid username format'; }
        if (isset($seenUsernames[strtolower($u)])) { $errs['username'] = 'Duplicate in submission'; }
        // Bulk policy: at least 10 chars and at least 3 of 4 classes (upper, lower, digit, symbol)
        $lenOk = (strlen($p) >= 10);
        $classes = 0;
        if (preg_match('/[A-Z]/', $p)) { $classes++; }
        if (preg_match('/[a-z]/', $p)) { $classes++; }
        if (preg_match('/\d/', $p))   { $classes++; }
        if (preg_match('/[^a-zA-Z\d]/', $p)) { $classes++; }
        if (!($lenOk && $classes >= 3)) { $errs['password'] = 'Password must be 10+ chars and include 3 of: upper, lower, number, symbol.'; }
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { $errs['email'] = 'Invalid email'; }
        // Check exists in system
        if (empty($errs['username'])) {
            try {
                comet_Server(['pid' => $pid])->AdminGetUserProfile($u);
                $errs['username'] = 'Username already exists';
            } catch (\Throwable $_) { /* available */ }
        }
        if (!empty($errs)) {
            $out['row_errors'][] = array_merge(['index' => $i], $errs);
            $out['error_count']++;
        } else {
            $seenUsernames[strtolower($u)] = true;
            $out['valid_count']++;
        }
    }
    return $out;
}

/** Estimate per-account amount for product pid using existing rules. */
function eazybackup_estimate_amount_for_pid(int $pid, string $cycleCol, int $currencyId): float {
    // Only PIDs 58/60 have billed config options: cid 67 + 88
    if ($pid !== 58 && $pid !== 60) { return 0.0; }
    $sum = 0.0;
    foreach ([67, 88] as $configId) {
        try {
            $subId = Capsule::table('tblproductconfigoptionssub')
                ->where('configid', $configId)
                ->orderBy('sortorder')->orderBy('id')
                ->value('id');
            $relid = $subId ? (int)$subId : (int)$configId;
            $row = Capsule::table('tblpricing')
                ->where('type','configoptions')
                ->where('currency', $currencyId)
                ->where('relid', $relid)
                ->first();
            if ($row) {
                $raw = $row->{$cycleCol} ?? null;
                $v = is_numeric($raw) ? (float)$raw : 0.0;
                if ($v < 0) { $v = 0.0; }
                $sum += $v; // qty=1 for estimate
            }
        } catch (\Throwable $_) {}
    }
    return round($sum, 2);
}
/** Create accounts in bulk: one order per account; partial failures allowed. */
function eazybackup_bulk_create_accounts(int $clientId, int $pid, string $term, int $consolidated, array $rows, int $currencyId): array {
    $results = ['ok' => true, 'results' => [], 'summary' => '' ];
    $adminUser = 'API';
    $success = 0; $fail = 0; $i = 0;
    foreach ($rows as $row) {
        $i++;
        $username = (string)($row['username'] ?? '');
        $password = (string)($row['password'] ?? '');
        $email    = (string)($row['email'] ?? '');
        try {
            // Persist consolidated billing preference once when enabled
            if ($consolidated) {
                try {
                    $exists = Capsule::table('mod_eazy_consolidated_billing')
                        ->where('clientid', $clientId)->where('enabled', 1)->exists();
                    if (!$exists) {
                        $tz = 'America/Toronto';
                        Capsule::table('mod_eazy_consolidated_billing')->updateOrInsert(
                            ['clientid' => $clientId],
                            [ 'enabled' => 1, 'dom' => (int)date('j'), 'timezone' => $tz, 'effective_from' => Carbon::now($tz)->toDateString(), 'updated_at' => Carbon::now()->toDateTimeString() ]
                        );
                    }
                } catch (\Throwable $_) {}
            }

            // One order per account
            $order = localAPI('AddOrder', [
                'clientid' => $clientId,
                'pid' => [$pid],
                'promocode' => 'trial',
                'paymentmethod' => 'stripe',
                'noinvoice' => false,
            ]);
            if (($order['result'] ?? '') !== 'success') { throw new \Exception('AddOrder failed: ' . ($order['message'] ?? '')); }

            $accept = localAPI('AcceptOrder', [
                'orderid' => $order['orderid'],
                'autosetup' => true,
                'sendemail' => true,
                'serviceusername' => $username,
                'servicepassword' => $password,
            ], $adminUser);
            if (($accept['result'] ?? '') !== 'success') { throw new \Exception('AcceptOrder failed: ' . ($accept['message'] ?? '')); }

            $service = Capsule::table('tblhosting')->where('orderid', $order['orderid'])->first();
            if (!$service) { throw new \Exception('Service not found'); }

            // Comet user update for report emails
            try {
                $params = comet_ServiceParams($service->id);
                $params['username'] = $username;
                $params['clientsdetails'] = ['email' => $email];
                comet_UpdateUser($params);
            } catch (\Throwable $_) {}

            // Defaults and amount
            try { eazybackup_apply_default_config_options((int)$service->id, (int)$pid); } catch (\Throwable $_) {}
            try {
                $cycle = ($term === 'annual') ? 'annually' : 'monthly';
                $amount = eazybackup_compute_recurring_amount_from_options((int)$service->id, $cycle);
                localAPI('UpdateClientProduct', [
                    'serviceid' => (int)$service->id,
                    'billingcycle' => ($term === 'annual' ? 'Annually' : 'Monthly'),
                    'amount' => $amount,
                    'recurringamount' => $amount,
                    'firstpaymentamount' => $amount,
                ]);
            } catch (\Throwable $_) {}

            $results['results'][] = ['index' => $i-1, 'username' => $username, 'serviceid' => (int)$service->id];
            $success++;
        } catch (\Throwable $e) {
            $results['results'][] = ['index' => $i-1, 'username' => $username, 'error' => $e->getMessage()];
            $results['ok'] = false;
            $fail++;
        }
    }
    $results['summary'] = 'Created ' . $success . ' of ' . ($success + $fail) . ' accounts.';
    return $results;
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