<?php

declare(strict_types=1);

/**
 * Phase A QA reset.
 *
 * Removes every row tagged with EazyBackup\Tests\Support\Seeder::SEED_TAG_PREFIX
 * across the tables seed_phase_a.php touched. Safe to re-run.
 *
 * Usage:
 *   php accounts/modules/addons/eazybackup/bin/dev/reset_phase_a.php
 *   php accounts/modules/addons/eazybackup/bin/dev/reset_phase_a.php --dry-run
 *
 * Exit codes:
 *   0 success
 *   2 setup error (bootstrap failed, prod guard tripped)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(2);
}

require_once __DIR__ . '/../../tests/bootstrap.php';

use EazyBackup\Tests\Support\Seeder;
use WHMCS\Database\Capsule;

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);
$tag = Seeder::SEED_TAG_PREFIX;
$like = '%' . str_replace(['_', '%'], ['\\_', '\\%'], $tag) . '%';

$schema = Capsule::schema();
$totals = [];

$connection = Capsule::connection();
$connection->beginTransaction();

try {
    // Order matters: child tables first.
    $deletes = [
        // Audit and registrations
        ['table' => 'eb_msp_tax_audit', 'where' => function ($q) use ($like) {
            $q->where('before_json', 'LIKE', $like)
              ->orWhere('after_json', 'LIKE', $like)
              ->orWhere('meta_json', 'LIKE', $like);
        }],
        ['table' => 'eb_msp_tax_regs', 'where' => function ($q) use ($like) {
            $q->where('legal_name', 'LIKE', $like);
        }],
        // Settings — match by JSON content (saved values) and by mirrored from_name
        ['table' => 'eb_msp_settings', 'where' => function ($q) use ($like) {
            $q->where('email_json', 'LIKE', $like)
              ->orWhere('tax_json', 'LIKE', $like)
              ->orWhere('checkout_json', 'LIKE', $like);
        }],
        // Plan templates + components
        ['table' => 'eb_plan_components', 'where' => function ($q) use ($like) {
            $q->whereIn('plan_id', function ($sub) use ($like) {
                $sub->select('id')->from('eb_plan_templates')
                    ->where('name', 'LIKE', $like)
                    ->orWhere('description', 'LIKE', $like)
                    ->orWhere('metadata_json', 'LIKE', $like);
            });
        }],
        ['table' => 'eb_plan_templates', 'where' => function ($q) use ($like) {
            $q->where('name', 'LIKE', $like)
              ->orWhere('description', 'LIKE', $like)
              ->orWhere('metadata_json', 'LIKE', $like);
        }],
        // Catalog
        ['table' => 'eb_catalog_prices', 'where' => function ($q) use ($like) {
            $q->where('name', 'LIKE', $like)
              ->orWhereIn('product_id', function ($sub) use ($like) {
                  $sub->select('id')->from('eb_catalog_products')
                      ->where('name', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like);
              });
        }],
        ['table' => 'eb_catalog_products', 'where' => function ($q) use ($like) {
            $q->where('name', 'LIKE', $like)
              ->orWhere('description', 'LIKE', $like);
        }],
        // Tenants + MSPs
        ['table' => 'eb_tenants', 'where' => function ($q) use ($like) {
            $q->where('name', 'LIKE', $like)
              ->orWhere('contact_email', 'phase-a@example.test');
        }],
        ['table' => 'eb_msp_accounts', 'where' => function ($q) use ($like) {
            $q->where('name', 'LIKE', $like);
        }],
    ];

    foreach ($deletes as $step) {
        $table = $step['table'];
        if (!$schema->hasTable($table)) {
            $totals[$table] = 0;
            continue;
        }
        $query = Capsule::table($table)->where($step['where']);
        $count = (int) $query->count();
        if (!$dryRun && $count > 0) {
            $query = Capsule::table($table)->where($step['where']);
            $query->delete();
        }
        $totals[$table] = $count;
    }

    if ($dryRun) {
        $connection->rollBack();
    } else {
        $connection->commit();
    }

    $verb = $dryRun ? 'WOULD DELETE' : 'DELETED';
    fwrite(STDOUT, "Phase A reset {$verb} (seed_tag={$tag}):\n");
    foreach ($totals as $table => $count) {
        fwrite(STDOUT, sprintf("  %-22s %d\n", $table, $count));
    }
    if ($dryRun) {
        fwrite(STDOUT, "\n(no rows were modified; --dry-run)\n");
    }
    exit(0);
} catch (\Throwable $e) {
    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
    fwrite(STDERR, "[reset_phase_a] FAILED: " . $e->getMessage() . "\n");
    exit(2);
}
