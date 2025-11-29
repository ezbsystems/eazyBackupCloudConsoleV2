<?php

require_once __DIR__ . "/../../init.php";

use WHMCS\Database\Capsule;
use WHMCS\Session;

// Only handle direct AJAX calls; do nothing when this file is included during normal WHMCS requests.
if (!isset($_REQUEST['ajax_action'])) {
    // If invoked directly without ajax_action, return a JSON error for debugging
    $isDirect = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
    if ($isDirect) {
        header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Missing ajax_action']);
        exit;
    }
    return;
}

header('Content-Type: application/json');

function mod_rd_out_json($ok, $data = [], $msg = '')
{
    echo json_encode(['status' => (bool) $ok, 'data' => $data, 'message' => $msg]);
    exit;
}

function mod_rd_cycle_col($cycle)
{
    // Normalize cycle for robustness (trim + case-insensitive)
    $norm = strtolower(trim((string) $cycle));
    $map = [
        'monthly'        => 'monthly',
        'quarterly'      => 'quarterly',
        'semi-annually'  => 'semiannually',
        'semiannually'   => 'semiannually',
        'annually'       => 'annually',
        'biennially'     => 'biennially',
        'triennially'    => 'triennially',
    ];
    return $map[$norm] ?? '';
}

function mod_rd_is_recurring_cycle($cycle): bool
{
    $norm = strtolower(trim((string) $cycle));
    return in_array($norm, [
        'monthly','quarterly','semi-annually','semiannually','annually','biennially','triennially'
    ], true);
}

function mod_rd_price_row($type, $relid, $currencyId)
{
    return Capsule::table('tblpricing')
        ->where('type', $type)
        ->where('relid', $relid)
        ->where('currency', $currencyId)
        ->first();
}

function mod_rd_is_quantity_opt($optType): bool
{
    $t = (int) $optType;
    // Handle both common mappings defensively:
    //  - Some installs use 4=Quantity (WHMCS default)
    //  - Others may have legacy/custom mappings where 3 was treated as quantity
    return in_array($t, [3, 4], true);
}

function mod_rd_get_recurring_for($row, $cycleCol)
{
    if (!$row) {
        return 0.0;
    }
    return (float) ($row->$cycleCol ?? 0.0);
}

function mod_rd_resolve_currency_id(int $clientId): int
{
    $cid = Capsule::table('tblclients')->where('id', $clientId)->value('currency');
    $cid = (int)($cid ?? 0);
    if ($cid > 0) return $cid;

    $def = Capsule::table('tblcurrencies')->where('default', 1)->value('id');
    if ($def) return (int)$def;
    $first = Capsule::table('tblcurrencies')->orderBy('id')->value('id');
    return (int)($first ?: 1);
}

try {
    $action = $_POST['ajax_action'] ?? $_GET['ajax_action'] ?? '';

    // Convenience alias: calculate_amount → recalc_service_amount
    if ($action === 'calculate_amount') {
        $_POST['ajax_action'] = 'recalc_service_amount';
        $action = 'recalc_service_amount';
    }

    // Helper: admin check
    $isAdmin = false;
    try { $isAdmin = (bool) (Session::get('adminid') ?? 0); } catch (\Throwable $e) { $isAdmin = false; }

    // Commit amount directly (admin-only)
    if ($action === 'commit_amount') {
        if (!$isAdmin) {
            mod_rd_out_json(false, [], 'Admin only');
        }
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $amountStr = trim((string) ($_POST['amount'] ?? ''));
        if ($serviceId <= 0 || $amountStr === '' || !is_numeric($amountStr)) {
            mod_rd_out_json(false, [], 'Invalid input');
        }
        $amount = number_format((float) $amountStr, 2, '.', '');
        $svc = Capsule::table('tblhosting')->select('id')->where('id', $serviceId)->first();
        if (!$svc) {
            mod_rd_out_json(false, [], 'Service not found');
        }
        Capsule::table('tblhosting')->where('id', $serviceId)->update(['amount' => $amount]);
        mod_rd_out_json(true, ['amount' => $amount], 'Committed');
    }

    if ($action === 'apply_config_discount') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $configId = (int) ($_POST['configoptionid'] ?? 0);
        $val = trim((string) ($_POST['discount_price'] ?? ''));

        if ($serviceId <= 0 || $configId <= 0 || $val === '' || !is_numeric($val) || (float) $val < 0) {
            mod_rd_out_json(false, [], 'Invalid input.');
        }

        $service = Capsule::table('tblhosting')->find($serviceId);
        if (!$service) {
            mod_rd_out_json(false, [], 'Service not found.');
        }

        $currencyId = mod_rd_resolve_currency_id((int)$service->userid);
        $isRecurring = mod_rd_is_recurring_cycle($service->billingcycle);
        $cycleCol = $isRecurring ? mod_rd_cycle_col($service->billingcycle) : '';
        $opt = Capsule::table('tblproductconfigoptions')->select('id', 'optiontype')->where('id', $configId)->first();
        if (!$opt) {
            mod_rd_out_json(false, [], 'Config option not found.');
        }

        // Capture current list price (for reference only)
        $basePrice = 0.0;
        if ($isRecurring) {
            // For both quantity and non-quantity options, prefer pricing on the selected sub-option (optionid)
            // when available, falling back to the base config option id for legacy installs.
            $hco = Capsule::table('tblhostingconfigoptions')
                ->select('optionid')
                ->where('relid', $serviceId)
                ->where('configid', $configId)
                ->first();

            $priceRelId = 0;
            if ($hco && $hco->optionid) {
                $priceRelId = (int) $hco->optionid;
            } else {
                // Legacy / safety net: older installs may store pricing directly on configid
                $priceRelId = (int) $configId;
            }

            $basePrice = mod_rd_get_recurring_for(
                mod_rd_price_row('configoptions', $priceRelId, $currencyId),
                $cycleCol
            );
        }

        // Upsert discount record
        $existing = Capsule::table('mod_rd_discountConfigOptions')
            ->where('serviceid', $serviceId)
            ->where('configoptionid', $configId)
            ->first();

        if ($existing) {
            Capsule::table('mod_rd_discountConfigOptions')
                ->where('id', $existing->id)
                ->update([
                    'discount_price' => (float) $val,
                    'price' => (float) $basePrice,
                    'updated_at' => Capsule::raw('NOW()'),
                ]);
        } else {
            Capsule::table('mod_rd_discountConfigOptions')->insert([
                'serviceid' => $serviceId,
                'configoptionid' => $configId,
                'discount_price' => (float) $val,
                'price' => (float) $basePrice,
                'created_at' => Capsule::raw('NOW()'),
                'updated_at' => Capsule::raw('NOW()'),
            ]);
        }

        mod_rd_out_json(true, ['discount_price' => (float) $val, 'base_price' => (float) $basePrice], 'Saved');
    }

    if ($action === 'remove_config_discount') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $configId = (int) ($_POST['configoptionid'] ?? 0);
        if ($serviceId <= 0 || $configId <= 0) {
            mod_rd_out_json(false, [], 'Invalid input.');
        }

        Capsule::table('mod_rd_discountConfigOptions')
            ->where('serviceid', $serviceId)
            ->where('configoptionid', $configId)
            ->delete();

        mod_rd_out_json(true, [], 'Removed');
    }

    if ($action === 'save_discounts') {
        // Batch upsert: discounts[] = {configoptionid, discount_price}
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $items = $_POST['discounts'] ?? [];
        if ($serviceId <= 0 || !is_array($items)) {
            mod_rd_out_json(false, [], 'Invalid payload.');
        }

        Capsule::connection()->transaction(function () use ($serviceId, $items) {
            foreach ($items as $it) {
                $configId = (int) ($it['configoptionid'] ?? 0);
                $val = trim((string) ($it['discount_price'] ?? ''));
                if ($configId <= 0 || $val === '' || !is_numeric($val) || (float) $val < 0) {
                    continue;
                }

                $existing = Capsule::table('mod_rd_discountConfigOptions')
                    ->where('serviceid', $serviceId)
                    ->where('configoptionid', $configId)
                    ->first();

                if ($existing) {
                    Capsule::table('mod_rd_discountConfigOptions')
                        ->where('id', $existing->id)
                        ->update([
                            'discount_price' => (float) $val,
                            'updated_at' => Capsule::raw('NOW()'),
                        ]);
                } else {
                    Capsule::table('mod_rd_discountConfigOptions')->insert([
                        'serviceid' => $serviceId,
                        'configoptionid' => $configId,
                        'discount_price' => (float) $val,
                        'price' => 0.0,
                        'created_at' => Capsule::raw('NOW()'),
                        'updated_at' => Capsule::raw('NOW()'),
                    ]);
                }
            }
        });

        mod_rd_out_json(true, [], 'Saved');
    }

    if ($action === 'recalc_service_amount') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        if ($serviceId <= 0) {
            mod_rd_out_json(false, [], 'Invalid service_id.');
        }

        $service = Capsule::table('tblhosting')->find($serviceId);
        if (!$service) {
            mod_rd_out_json(false, [], 'Service not found.');
        }

        $currencyId = mod_rd_resolve_currency_id((int)$service->userid);
        $isRecurring = mod_rd_is_recurring_cycle($service->billingcycle);
        $cycleCol = $isRecurring ? mod_rd_cycle_col($service->billingcycle) : '';

        // Base product recurring price: business rule uses only config options; product base does not contribute
        $baseRecurring = 0.0;

        $cfgTotal = 0.0;
        $meta = [
            'serviceId'     => $serviceId,
            'billingcycle'  => (string)$service->billingcycle,
            'currencyId'    => (int)$currencyId,
            'cycleCol'      => (string)$cycleCol,
            'baseRecurring' => (float)$baseRecurring,
            'lines'         => [],
        ];
        // Optional client-provided overrides from the current form (JSON or array)
        $overrides = [];
        if (isset($_POST['options'])) {
            $raw = $_POST['options'];
            if (is_string($raw)) {
                $dec = json_decode($raw, true);
                if (is_array($dec)) { $overrides = $dec; }
            } elseif (is_array($raw)) {
                $overrides = $raw;
            }
        }
        $useOverridesOnly = $isRecurring && !empty($overrides);
        if ($isRecurring) {
            // Build rows based on context:
            // - When overrides present: include ONLY those configids from the live form
            // - When no overrides: use DB snapshot
            $rows = [];
            if ($useOverridesOnly) {
                $cfgIds = [];
                foreach ($overrides as $ov) {
                    $cid = (int)($ov['configid'] ?? 0);
                    if ($cid > 0) $cfgIds[] = $cid;
                }
                $typeMap = [];
                if (!empty($cfgIds)) {
                    $typeMap = Capsule::table('tblproductconfigoptions')
                        ->whereIn('id', $cfgIds)
                        ->pluck('optiontype', 'id'); // [configid => optiontype]
                }
                $dbSelMap = [];
                if (!empty($cfgIds)) {
                    $dbSelMap = Capsule::table('tblhostingconfigoptions')
                        ->where('relid', $serviceId)
                        ->whereIn('configid', $cfgIds)
                        ->pluck('optionid', 'configid'); // [configid => optionid]
                }
                foreach ($overrides as $ov) {
                    $cfg = (int)($ov['configid'] ?? 0);
                    if ($cfg <= 0) continue;
                    $optType = (int)($typeMap[$cfg] ?? 1);
                    $row = (object)[
                        'configid'   => $cfg,
                        'optiontype' => $optType,
                        'optionid'   => 0,
                        'qty'        => 0,
                    ];
                    if (mod_rd_is_quantity_opt($optType)) {
                        // Quantity: take qty from override and optionid from DB snapshot (relid for pricing)
                        $row->qty = isset($ov['qty']) ? (int)$ov['qty'] : 0;
                        $row->optionid = (int)($dbSelMap[$cfg] ?? 0);
                    } else {
                        // Non-quantity: use subId override when present, else DB snapshot
                        $row->optionid = isset($ov['subId']) ? (int)$ov['subId'] : (int)($dbSelMap[$cfg] ?? 0);
                    }
                    $rows[] = $row;
                }
            } else {
                $rows = Capsule::table('tblhostingconfigoptions as hco')
                    ->join('tblproductconfigoptions as pco', 'pco.id', '=', 'hco.configid')
                    ->where('hco.relid', $serviceId)
                    ->select('hco.configid', 'hco.optionid', 'hco.qty', 'pco.optiontype')
                    ->get();
            }

            $discounts = Capsule::table('mod_rd_discountConfigOptions')
                ->where('serviceid', $serviceId)
                ->pluck('discount_price', 'configoptionid'); // [configid => price]

            foreach ($rows as $r) {
                $optType = (int) $r->optiontype;
                $configId = (int) $r->configid;
                $qty = (int) $r->qty;
                $subId = (int) $r->optionid;

                if (mod_rd_is_quantity_opt($optType)) {
                    // Quantity option:
                    //  - pricing lives on tblpricing.relid = tblhostingconfigoptions.optionid (subId) when present
                    //  - fall back to configid for legacy installs where pricing was stored directly on the option
                    $priceRelId = $subId ?: $configId;

                    $unit = isset($discounts[$configId])
                        ? (float) $discounts[$configId]
                        : mod_rd_get_recurring_for(
                            mod_rd_price_row('configoptions', $priceRelId, $currencyId),
                            $cycleCol
                        );
                    $contrib = $unit * max(0, $qty);
                    $cfgTotal += $contrib;
                    $meta['lines'][] = [
                        'configId' => $configId,
                        'optType'  => $optType,
                        'type'     => 'quantity',
                        'qty'      => max(0, $qty),
                        'unit'     => (float)$unit,
                        'discountApplied' => isset($discounts[$configId]) ? (float)$discounts[$configId] : null,
                        'listUnit' => isset($discounts[$configId])
                            ? null
                            : (float)mod_rd_get_recurring_for(
                                mod_rd_price_row('configoptions', $priceRelId, $currencyId),
                                $cycleCol
                            ),
                        'contribution' => (float)$contrib,
                    ];
                } else {
                    // Non-quantity (select/radio):
                    // If overrides are present, include the currently selected subId at its list price unless a discount overrides it.
                    // If no overrides (DB snapshot), include ONLY when a discount exists to avoid counting default non-billable selects.
                    $hasDiscount = isset($discounts[$configId]);
                    $includeSelect = $useOverridesOnly ? true : $hasDiscount;
                    if ($includeSelect) {
                        $price = $hasDiscount
                            ? (float)$discounts[$configId]
                            : ($subId ? mod_rd_get_recurring_for(mod_rd_price_row('configoptions', $subId, $currencyId), $cycleCol) : 0.0);
                        $cfgTotal += $price;
                        $meta['lines'][] = [
                            'configId' => $configId,
                            'optType'  => $optType,
                            'type'     => 'select',
                            'subId'    => $subId,
                            'discountApplied' => $hasDiscount ? (float)$discounts[$configId] : null,
                            'listSelected'    => $hasDiscount ? null : (float)($subId ? mod_rd_get_recurring_for(mod_rd_price_row('configoptions', $subId, $currencyId), $cycleCol) : 0.0),
                            'contribution'    => (float)$price,
                            'source'          => $useOverridesOnly ? 'override' : 'db',
                        ];
                    } else {
                        $meta['lines'][] = [
                            'configId' => $configId,
                            'optType'  => $optType,
                            'type'     => 'select',
                            'subId'    => $subId,
                            'discountApplied' => null,
                            'listSelected'    => (float)($subId ? mod_rd_get_recurring_for(mod_rd_price_row('configoptions', $subId, $currencyId), $cycleCol) : 0.0),
                            'contribution'    => 0.0,
                            'skipped'         => 'no-discount-select',
                            'source'          => 'db',
                        ];
                    }
                }
            }
        }

        $amount = round($baseRecurring + $cfgTotal, 2);
        mod_rd_out_json(true, [
            'amount' => number_format($amount, 2, '.', ''),
            'meta'   => $meta
        ], 'OK');
    }

    mod_rd_out_json(false, [], 'Unknown action.');
} catch (\Throwable $e) {
    logActivity('configOptionsDiscount_ajax error: ' . $e->getMessage());
    mod_rd_out_json(false, [], 'Server error.');
}


