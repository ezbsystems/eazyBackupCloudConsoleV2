<?php
/**
 * e3 Cloud Backup invoice + daily-rater WHMCS hooks.
 *
 * Wired up from accounts/modules/addons/cloudstorage/hooks.php via a single
 * require_once at the bottom of that file. Kept in its own file so the
 * billing logic can be reasoned about (and disabled) independently.
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupBilling;
use WHMCS\Module\Addon\CloudStorage\Provision\E3CloudBackupProductBootstrap;

// Lazy-load the classes (autoloader is not registered for the cloudstorage
// addon - everything is required-on-demand).
$cs_e3cb_base = __DIR__ . '/..';
$cs_e3cb_loads = [
    $cs_e3cb_base . '/lib/Provision/E3CloudBackupProductBootstrap.php',
    $cs_e3cb_base . '/lib/Admin/E3CloudBackupPricing.php',
    $cs_e3cb_base . '/lib/Admin/E3CloudBackupBilling.php',
];
foreach ($cs_e3cb_loads as $cs_e3cb_path) {
    if (is_file($cs_e3cb_path)) {
        require_once $cs_e3cb_path;
    }
}
unset($cs_e3cb_base, $cs_e3cb_loads, $cs_e3cb_path);

/**
 * Run the rater immediately before WHMCS invoice generation so that
 * tblhostingconfigoptions.qty + s3_cloudbackup_rated_lines are both fresh.
 */
add_hook('DailyCronJob', 1, function ($vars) {
    if (!class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Admin\\E3CloudBackupBilling')) {
        return;
    }
    try {
        $meter = E3CloudBackupBilling::meterAll();
        $rate = E3CloudBackupBilling::rateAll();
        cloudstorage_e3cb_apply_all_services();
        try {
            logModuleCall('cloudstorage', 'e3cb_daily_cron_hook', [], [
                'meter' => $meter,
                'rate'  => $rate,
            ], [], []);
        } catch (\Throwable $_) {
        }
    } catch (\Throwable $e) {
        try {
            logModuleCall('cloudstorage', 'e3cb_daily_cron_hook_fail', [], $e->getMessage(), [], []);
        } catch (\Throwable $_) {
        }
    }
});

/**
 * Sync rated-line qty into tblhostingconfigoptions for every active e3 Cloud
 * Backup service. Called both by the daily hook and by ad-hoc admin actions.
 */
function cloudstorage_e3cb_apply_all_services(): void
{
    $pid = E3CloudBackupProductBootstrap::getPid();
    if ($pid <= 0) {
        return;
    }
    try {
        $svcIds = Capsule::table('tblhosting')
            ->where('packageid', $pid)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->pluck('id');
        foreach ($svcIds as $sid) {
            try {
                E3CloudBackupBilling::applyToWhmcs((int) $sid);
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'e3cb_apply_to_whmcs_fail', ['service_id' => (int) $sid], $e->getMessage(), [], []);
            }
        }
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'e3cb_apply_all_services_fail', [], $e->getMessage(), [], []);
    }
}

/**
 * Rewrite invoice line items for the e3 Cloud Backup product whenever the
 * rated line has a pricing_source other than tblpricing.
 *
 * Trigger event: InvoiceCreationPreEmail (fires after WHMCS creates invoice
 * line items but before the customer email is sent). At this point we can
 * safely overwrite tblinvoiceitems.amount/description and recompute totals.
 */
add_hook('InvoiceCreationPreEmail', 1, function ($vars) {
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return;
    }
    if (!class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Admin\\E3CloudBackupBilling')) {
        return;
    }
    $pid = E3CloudBackupProductBootstrap::getPid();
    if ($pid <= 0) {
        return;
    }

    try {
        cloudstorage_e3cb_apply_invoice_overrides($invoiceId, $pid);
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'e3cb_invoice_hook_fail', ['invoice_id' => $invoiceId], $e->getMessage(), [], []);
    }
});

/**
 * Walk every line item on the given invoice. For any line tied to an e3 Cloud
 * Backup config option, overwrite the amount and description from the latest
 * rated_lines row when the pricing source is anything other than tblpricing.
 *
 * Zero-amount lines that survive the override stay on the invoice during a
 * trial (so the customer can clearly see "Endpoints (12 x $4.50 - trial period)"
 * even though they owe $0 for it).
 */
function cloudstorage_e3cb_apply_invoice_overrides(int $invoiceId, int $pid): void
{
    $items = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->get();
    if (count($items) === 0) {
        return;
    }
    $configMap = E3CloudBackupProductBootstrap::getConfigOptionMap();
    if (empty($configMap)) {
        return;
    }
    // Build a reverse map: subId -> metric, configId -> metric.
    $reverse = [];
    foreach ($configMap as $metric => $configId) {
        $configId = (int) $configId;
        if ($configId <= 0) {
            continue;
        }
        $reverse['cfg_' . $configId] = $metric;
        try {
            $subIds = Capsule::table('tblproductconfigoptionssub')
                ->where('configid', $configId)
                ->pluck('id');
            foreach ($subIds as $sid) {
                $reverse['sub_' . (int) $sid] = $metric;
            }
        } catch (\Throwable $_) {
        }
    }

    $serviceIdsTouched = [];
    $writtenAny = false;

    foreach ($items as $item) {
        $serviceId = 0;
        $itemType = (string) ($item->type ?? '');
        $itemRelId = (int) ($item->relid ?? 0);

        // WHMCS line types we care about:
        //  - 'Hosting' / 'Recurring' for the parent service (skip - product is $0)
        //  - 'ConfigOptions' for config option lines (relid = tblhostingconfigoptions.id)
        if ($itemType !== 'ConfigOptions' || $itemRelId <= 0) {
            continue;
        }

        try {
            $hco = Capsule::table('tblhostingconfigoptions')->where('id', $itemRelId)->first();
            if (!$hco) {
                continue;
            }
            $serviceId = (int) ($hco->relid ?? 0);
            $configId = (int) ($hco->configid ?? 0);
            $optionId = (int) ($hco->optionid ?? 0);
        } catch (\Throwable $e) {
            continue;
        }

        // Service must belong to the e3 Cloud Backup product.
        try {
            $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if (!$svc || (int) $svc->packageid !== $pid) {
                continue;
            }
        } catch (\Throwable $e) {
            continue;
        }

        $metric = $reverse['sub_' . $optionId] ?? $reverse['cfg_' . $configId] ?? null;
        if ($metric === null) {
            continue;
        }

        // Use the most recent rated line for this (service, metric).
        try {
            $rated = Capsule::table('s3_cloudbackup_rated_lines')
                ->where('service_id', $serviceId)
                ->where('metric', $metric)
                ->orderBy('billing_window_start', 'desc')
                ->orderBy('id', 'desc')
                ->first();
        } catch (\Throwable $e) {
            continue;
        }
        if (!$rated) {
            continue;
        }
        $source = (string) ($rated->pricing_source ?? 'tblpricing');
        if (!in_array($source, ['client_override', 'global_default', 'flat_monthly', 'trial_zeroed', 'beta_zeroed'], true)) {
            // Pure tblpricing - let WHMCS keep its native amount.
            continue;
        }

        $newAmount = (float) ($rated->line_amount ?? 0.0);
        $description = cloudstorage_e3cb_invoice_description($metric, $rated);

        try {
            Capsule::table('tblinvoiceitems')
                ->where('id', $item->id)
                ->update([
                    'amount'      => $newAmount,
                    'description' => $description,
                ]);
            $writtenAny = true;
            $serviceIdsTouched[$serviceId] = true;
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'e3cb_invoice_item_update_fail', [
                'invoice_id' => $invoiceId,
                'item_id'    => $item->id,
                'metric'     => $metric,
            ], $e->getMessage(), [], []);
        }
    }

    if ($writtenAny) {
        cloudstorage_e3cb_recompute_invoice_total($invoiceId);
        try {
            logModuleCall('cloudstorage', 'e3cb_invoice_overrides_applied', [
                'invoice_id'   => $invoiceId,
                'services'     => array_keys($serviceIdsTouched),
            ], [
                'overridden_lines' => $writtenAny,
            ], [], []);
        } catch (\Throwable $_) {
        }
    }
}

/**
 * Build a human-readable invoice line description from a rated line.
 */
function cloudstorage_e3cb_invoice_description(string $metric, object $rated): string
{
    $label = E3CloudBackupProductBootstrap::metricFriendlyName($metric);
    $qty = (int) ($rated->qty ?? 0);
    $unit = (float) ($rated->unit_price ?? 0.0);
    $source = (string) ($rated->pricing_source ?? 'tblpricing');
    $tier = (string) ($rated->tier_label ?? '');
    $unitFmt = number_format($unit, 2);

    if ($source === 'flat_monthly') {
        return "e3 Cloud Backup - {$label} (flat monthly)";
    }
    if ($source === 'trial_zeroed') {
        return "e3 Cloud Backup - {$label} ({$qty} x \${$unitFmt} - trial period)";
    }
    if ($source === 'beta_zeroed') {
        return "e3 Cloud Backup - {$label} ({$qty} x \${$unitFmt} - beta, no charge)";
    }
    if ($tier !== '') {
        return "e3 Cloud Backup - {$label} ({$qty} x \${$unitFmt} - {$tier})";
    }
    return "e3 Cloud Backup - {$label} ({$qty} x \${$unitFmt})";
}

/**
 * Sum tblinvoiceitems.amount, rewrite tblinvoices subtotal/total/tax.
 *
 * Tax is preserved at the same percentage rate the invoice already has (we
 * apply taxrate to the new subtotal). Credits and the existing balance owing
 * are not touched.
 */
function cloudstorage_e3cb_recompute_invoice_total(int $invoiceId): void
{
    try {
        $items = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->get();
        $subtotal = 0.0;
        foreach ($items as $i) {
            $subtotal += (float) ($i->amount ?? 0.0);
        }
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) {
            return;
        }
        $taxRate = (float) ($invoice->taxrate ?? 0.0);
        $taxRate2 = (float) ($invoice->taxrate2 ?? 0.0);
        $tax = round($subtotal * ($taxRate / 100), 2);
        $tax2 = round($subtotal * ($taxRate2 / 100), 2);
        $credit = (float) ($invoice->credit ?? 0.0);
        $total = max(0.0, round($subtotal + $tax + $tax2 - $credit, 2));
        Capsule::table('tblinvoices')->where('id', $invoiceId)->update([
            'subtotal' => round($subtotal, 2),
            'tax'      => $tax,
            'tax2'     => $tax2,
            'total'    => $total,
        ]);
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'e3cb_invoice_recompute_fail', ['invoice_id' => $invoiceId], $e->getMessage(), [], []);
    }
}
