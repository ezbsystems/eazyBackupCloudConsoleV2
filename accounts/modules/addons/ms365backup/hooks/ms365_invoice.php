<?php
/**
 * MS365 Backup invoice + daily-rater WHMCS hooks.
 */

use WHMCS\Database\Capsule;
use Ms365Backup\Ms365BillingConfig;
use Ms365Backup\Ms365BillingService;

$ms365_hook_base = dirname(__DIR__);
require_once $ms365_hook_base . '/ms365backup_autoload.php';
unset($ms365_hook_base);

add_hook('DailyCronJob', 2, function ($vars) {
    if (!class_exists('\\Ms365Backup\\Ms365BillingService')) {
        return;
    }
    try {
        $meter = Ms365BillingService::meterAll();
        $rate = Ms365BillingService::rateAll();
        ms365_apply_all_services();
        try {
            logModuleCall('ms365backup', 'ms365_daily_cron_hook', [], [
                'meter' => $meter,
                'rate' => $rate,
            ], [], []);
        } catch (\Throwable $_) {
        }
    } catch (\Throwable $e) {
        try {
            logModuleCall('ms365backup', 'ms365_daily_cron_hook_fail', [], $e->getMessage(), [], []);
        } catch (\Throwable $_) {
        }
    }
});

function ms365_apply_all_services(): void
{
    $pids = Ms365BillingConfig::getBillablePids();
    if ($pids === []) {
        return;
    }
    try {
        $svcIds = Capsule::table('tblhosting')
            ->whereIn('packageid', $pids)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->pluck('id');
        foreach ($svcIds as $sid) {
            try {
                Ms365BillingService::applyToWhmcs((int) $sid);
            } catch (\Throwable $e) {
                logModuleCall('ms365backup', 'ms365_apply_to_whmcs_fail', ['service_id' => (int) $sid], $e->getMessage(), [], []);
            }
        }
    } catch (\Throwable $e) {
        logModuleCall('ms365backup', 'ms365_apply_all_services_fail', [], $e->getMessage(), [], []);
    }
}

add_hook('InvoiceCreationPreEmail', 2, function ($vars) {
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return;
    }
    if (!class_exists('\\Ms365Backup\\Ms365BillingService')) {
        return;
    }
    $legacyPid = Ms365BillingConfig::getPid();
    $pids = Ms365BillingConfig::getBillablePids();
    if ($pids === []) {
        return;
    }
    try {
        ms365_apply_invoice_overrides($invoiceId, $legacyPid, $pids);
    } catch (\Throwable $e) {
        logModuleCall('ms365backup', 'ms365_invoice_hook_fail', ['invoice_id' => $invoiceId], $e->getMessage(), [], []);
    }
});

function ms365_apply_invoice_overrides(int $invoiceId, int $legacyPid, array $billablePids = []): void
{
    $items = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->get();
    if (count($items) === 0) {
        return;
    }
    if ($billablePids === []) {
        $billablePids = Ms365BillingConfig::getBillablePids();
    }

    $writtenAny = false;
    foreach ($items as $item) {
        $itemType = (string) ($item->type ?? '');
        $itemRelId = (int) ($item->relid ?? 0);
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
        } catch (\Throwable $_) {
            continue;
        }

        try {
            $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if (!$svc || !in_array((int) ($svc->packageid ?? 0), $billablePids, true)) {
                continue;
            }
        } catch (\Throwable $_) {
            continue;
        }

        $configMap = Ms365BillingConfig::getConfigOptionMap($serviceId);
        if ($configMap === []) {
            continue;
        }

        $reverse = [];
        foreach ($configMap as $metric => $mapConfigId) {
            $mapConfigId = (int) $mapConfigId;
            if ($mapConfigId <= 0) {
                continue;
            }
            $reverse['cfg_' . $mapConfigId] = $metric;
            try {
                $subIds = Capsule::table('tblproductconfigoptionssub')
                    ->where('configid', $mapConfigId)
                    ->pluck('id');
                foreach ($subIds as $sid) {
                    $reverse['sub_' . (int) $sid] = $metric;
                }
            } catch (\Throwable $_) {
            }
        }

        $metric = $reverse['sub_' . $optionId] ?? $reverse['cfg_' . $configId] ?? null;
        if ($metric === null) {
            continue;
        }

        try {
            $rated = Capsule::table('ms365_billing_rated_lines')
                ->where('service_id', $serviceId)
                ->where('metric', $metric)
                ->orderBy('billing_window_start', 'desc')
                ->orderBy('id', 'desc')
                ->first();
        } catch (\Throwable $_) {
            continue;
        }
        if (!$rated) {
            continue;
        }

        $source = (string) ($rated->pricing_source ?? 'settings');
        if (!in_array($source, ['settings', 'trial_zeroed'], true)) {
            continue;
        }

        $newAmount = (float) ($rated->line_amount ?? 0.0);
        $description = ms365_invoice_description($metric, $rated);

        try {
            Capsule::table('tblinvoiceitems')
                ->where('id', $item->id)
                ->update([
                    'amount' => $newAmount,
                    'description' => $description,
                ]);
            $writtenAny = true;
        } catch (\Throwable $e) {
            logModuleCall('ms365backup', 'ms365_invoice_item_update_fail', [
                'invoice_id' => $invoiceId,
                'item_id' => $item->id,
                'metric' => $metric,
            ], $e->getMessage(), [], []);
        }
    }

    if ($writtenAny) {
        ms365_recompute_invoice_total($invoiceId);
    }
}

function ms365_invoice_description(string $metric, object $rated): string
{
    $label = Ms365BillingConfig::metricFriendlyName($metric);
    $qty = (int) ($rated->qty ?? 0);
    $unit = (float) ($rated->unit_price ?? 0.0);
    $source = (string) ($rated->pricing_source ?? 'settings');
    $unitFmt = number_format($unit, 2);

    if ($source === 'trial_zeroed') {
        return "Microsoft 365 Backup - {$label} ({$qty} x \${$unitFmt} - trial period)";
    }

    return "Microsoft 365 Backup - {$label} ({$qty} x \${$unitFmt})";
}

function ms365_recompute_invoice_total(int $invoiceId): void
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
            'tax' => $tax,
            'tax2' => $tax2,
            'total' => $total,
        ]);
    } catch (\Throwable $e) {
        logModuleCall('ms365backup', 'ms365_invoice_recompute_fail', ['invoice_id' => $invoiceId], $e->getMessage(), [], []);
    }
}
