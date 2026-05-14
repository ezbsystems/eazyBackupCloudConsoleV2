<?php

declare(strict_types=1);

/**
 * Phase A QA seeder.
 *
 * Creates a deterministic Partner Hub fixture set that QA can interact with via
 * the dev WHMCS UI (Partner Hub → Settings, Catalog, Plans). Every row carries
 * the EazyBackup\Tests\Support\Seeder::SEED_TAG_PREFIX marker so reset_phase_a.php
 * can clean up without manual SQL.
 *
 * Usage:
 *   php accounts/modules/addons/eazybackup/bin/dev/seed_phase_a.php
 *   php accounts/modules/addons/eazybackup/bin/dev/seed_phase_a.php --whmcs-client-id=42
 *   php accounts/modules/addons/eazybackup/bin/dev/seed_phase_a.php --reuse-msp-id=17
 *
 * Flags:
 *   --whmcs-client-id=N   Use this WHMCS client id when creating the eb_msp_accounts row.
 *                         Required when you want the MSP to be reachable via Partner Hub
 *                         (so the logged-in client matches). Default: random sentinel.
 *   --reuse-msp-id=N      Skip MSP creation and seed catalog/plans/settings under this msp_id.
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

$argMap = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
        [$k, $v] = explode('=', substr($arg, 2), 2);
        $argMap[$k] = $v;
    }
}

$reuseMspId = isset($argMap['reuse-msp-id']) ? (int) $argMap['reuse-msp-id'] : 0;

$connection = Capsule::connection();
$connection->beginTransaction();

try {
    if ($reuseMspId > 0) {
        $exists = Capsule::table('eb_msp_accounts')->where('id', $reuseMspId)->exists();
        if (!$exists) {
            throw new \RuntimeException("--reuse-msp-id={$reuseMspId} not found in eb_msp_accounts");
        }
        $mspId = $reuseMspId;
    } else {
        $mspOpts = [];
        if (isset($argMap['whmcs-client-id'])) {
            $mspOpts['whmcs_client_id'] = (int) $argMap['whmcs-client-id'];
        }
        $mspId = Seeder::seedMsp($mspOpts);
    }

    $tenantId = Seeder::seedTenant($mspId);
    $productId = Seeder::seedCatalogProduct($mspId);
    $priceId = Seeder::seedCatalogPrice($productId);
    $plan = Seeder::seedPlanTemplate($mspId, $priceId);

    Seeder::seedMspSettings($mspId, [
        'tax_mode' => ['stripe_tax_enabled' => true],
        'invoice_presentation' => ['invoice_prefix' => 'EBPHA-'],
    ], [
        'sender' => [
            'from_name' => Seeder::SEED_TAG_PREFIX . 'Phase A QA',
            'from_address' => 'phase-a@example.test',
            'brand' => ['primary_color' => '#1B2C50'],
        ],
    ]);

    $regId = Seeder::seedTaxRegistration($mspId, ['country' => 'CA', 'region' => 'BC']);

    $connection->commit();

    fwrite(STDOUT, "Phase A seed complete.\n");
    fwrite(STDOUT, "  seed_tag        = " . Seeder::SEED_TAG_PREFIX . "\n");
    fwrite(STDOUT, "  msp_id          = {$mspId}\n");
    fwrite(STDOUT, "  tenant_id       = {$tenantId}\n");
    fwrite(STDOUT, "  product_id      = {$productId}\n");
    fwrite(STDOUT, "  price_id        = {$priceId}\n");
    fwrite(STDOUT, "  plan_template_id= {$plan['plan_id']}\n");
    fwrite(STDOUT, "  plan_component  = {$plan['component_id']}\n");
    fwrite(STDOUT, "  tax_reg_id      = {$regId}\n");
    fwrite(STDOUT, "\nReset with: php accounts/modules/addons/eazybackup/bin/dev/reset_phase_a.php\n");
    exit(0);
} catch (\Throwable $e) {
    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
    fwrite(STDERR, "[seed_phase_a] FAILED: " . $e->getMessage() . "\n");
    exit(2);
}
