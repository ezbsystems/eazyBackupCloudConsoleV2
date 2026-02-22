<?php

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

/**
 * Admin Client Services panel: Annual Entitlement (MANUAL-ASSIST mode only).
 * Hooks on filename=clientsservices.
 * Renders rows for tracked config IDs; supports snapshot refresh to eb_annual_entitlement_ledger.
 * No automatic qty changes or invoice generation.
 */

use WHMCS\Database\Capsule;

add_hook('AdminAreaHeadOutput', 112223, function ($vars) {
    if (($vars['filename'] ?? '') !== 'clientsservices') {
        return '';
    }

    try {
        $addonPath = __DIR__ . '/../../modules/addons/eazybackup';
        $autoload = $addonPath . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            return '';
        }
        require_once $autoload;

        if (!class_exists('EazyBackup\Billing\AnnualEntitlementConfig')) {
            return '';
        }

        $mode = \EazyBackup\Billing\AnnualEntitlementConfig::mode();
        if (strtolower($mode) !== 'manual') {
            return '';
        }

        global $whmcs;
        $return = '';
        $userid = $whmcs->get_req_var('userid');
        $service_id = null;

        if (empty($whmcs->get_req_var('id'))) {
            $service_id = $whmcs->get_req_var('productselect');
        } else {
            $service_id = $whmcs->get_req_var('id');
        }

        if (!empty($userid) && empty($whmcs->get_req_var('id')) && empty($whmcs->get_req_var('productselect'))) {
            $svc = Capsule::table('tblhosting')->select('id')->where('userid', $userid)->first();
            $service_id = $svc ? $svc->id : null;
        }

        $service = $service_id ? Capsule::table('tblhosting')->find($service_id) : null;
        if (!$service) {
            return $return;
        }

        $billingCycle = (string)($service->billingcycle ?? '');
        $billingLower = strtolower(str_replace([' ', '-'], '', $billingCycle));
        $isAnnual = in_array($billingLower, ['annually', 'yearly'], true);

        if (!$isAnnual) {
            $msg = 'Annual entitlement not applicable (billing cycle: ' . $billingCycle . ').';
            $notApplicableHtml = '<tr><td colspan="2" class="fieldarea"><div class="alert alert-info" style="margin:0; color:#6c757d;">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div></td></tr>';
            $return .= '<script type="text/javascript">
                jQuery(document).ready(function() {
                    var content = ' . json_encode($notApplicableHtml) . ';
                    var $c = jQuery(\'#servicecontent\');
                    var $anchor = $c.find(\'table.form tr\').filter(\':nth-child(9)\');
                    if ($anchor.length) { $anchor.after(content); } else {
                        var $tb = $c.find(\'table.form tbody\').first();
                        $tb.length ? $tb.append(content) : $c.append(content);
                    }
                });
            </script>';
            return $return;
        }

        $username = (string)($service->username ?? '');
        if ($username === '') {
            return $return;
        }

        $configIds = \EazyBackup\Billing\AnnualEntitlementConfig::billableConfigIds();
        $nextDue = (string)($service->nextduedate ?? '');
        if ($nextDue === '' || $nextDue === '0000-00-00') {
            return $return;
        }

        $clientId = (int)($service->userid ?? 0);
        $serviceId = (int)$service->id;

        $rows = annual_entitlement_build_rows($serviceId, $clientId, $username, $nextDue, $configIds);

        $cycle = \EazyBackup\Billing\AnnualCycleWindow::fromNextDueDate($nextDue);
        $cycleStart = $cycle['cycle_start'];

        $labelMap = [
            67  => 'Cloud Storage (TiB)',
            88  => 'Endpoints',
            89  => 'Endpoints (alt)',
            91  => 'Disk Image',
            60  => 'M365 Accounts',
            97  => 'Hyper-V',
            99  => 'VMware',
            102 => 'Proxmox',
        ];

        $html = annual_entitlement_render_panel($rows, $labelMap, $serviceId, $cycleStart);
        $return .= '<script type="text/javascript">
            jQuery(document).ready(function() {
                var content = ' . json_encode($html) . ';
                var $c = jQuery(\'#servicecontent\');
                var $anchor = $c.find(\'table.form tr\').filter(\':nth-child(9)\');
                if ($anchor.length) { $anchor.after(content); } else {
                    var $tb = $c.find(\'table.form tbody\').first();
                    $tb.length ? $tb.append(content) : $c.append(content);
                }
            });
        </script>';

        return $return;
    } catch (\Throwable $e) {
        try {
            logActivity('eazybackup: annual entitlement panel error: ' . $e->getMessage());
        } catch (\Throwable $_) {
            /* ignore */
        }
        return '';
    }
});

/**
 * Build ledger rows for each config ID; performs snapshot refresh (upsert to ledger).
 *
 * @param int $serviceId
 * @param int $clientId
 * @param string $username
 * @param string $nextDue Y-m-d
 * @param int[] $configIds
 * @return array<int, array{config_id: int, name: string, usage_qty: int, config_qty: int, max_paid_qty: int, status: string, suggested_delta: int}>
 */
function annual_entitlement_build_rows(int $serviceId, int $clientId, string $username, string $nextDue, array $configIds): array
{
    $snapshotService = new \EazyBackup\Billing\AnnualEntitlementSnapshotService();

    try {
        $cycle = \EazyBackup\Billing\AnnualCycleWindow::fromNextDueDate($nextDue);
    } catch (\InvalidArgumentException $e) {
        return [];
    }

    $cycleStart = $cycle['cycle_start'];
    $cycleEnd = $cycle['cycle_end'];

    $rows = [];
    foreach ($configIds as $configId) {
        $usageQty = annual_entitlement_compute_usage_qty($configId, $username);
        $configQty = (int)Capsule::table('tblhostingconfigoptions')
            ->where('relid', $serviceId)
            ->where('configid', $configId)
            ->value('qty');
        $configQty = max(0, $configQty);

        $existing = Capsule::table('eb_annual_entitlement_ledger')
            ->where('service_id', $serviceId)
            ->where('config_id', $configId)
            ->where('cycle_start', $cycleStart)
            ->first();

        $maxPaidQty = $existing ? (int)$existing->max_paid_qty : $configQty;
        if ($maxPaidQty < 0) {
            $maxPaidQty = $configQty;
        }

        $in = [
            'service_id'     => $serviceId,
            'client_id'      => $clientId,
            'username'       => $username,
            'config_id'      => $configId,
            'usage_qty'      => $usageQty,
            'config_qty'     => $configQty,
            'max_paid_qty'   => $maxPaidQty,
            'next_due'       => $nextDue,
        ];
        $built = $snapshotService->buildLedgerRow($in);

        Capsule::table('eb_annual_entitlement_ledger')->updateOrInsert(
            [
                'service_id'  => $serviceId,
                'config_id'   => $configId,
                'cycle_start' => $cycleStart,
            ],
            [
                'client_id'          => $clientId,
                'username'           => $username,
                'cycle_end'          => $cycleEnd,
                'current_usage_qty'  => $usageQty,
                'current_config_qty' => $configQty,
                'max_paid_qty'       => $maxPaidQty,
                'status'             => $built['status'],
                'recommended_delta'  => $built['recommended_delta'],
            ]
        );

        $rows[$configId] = [
            'config_id'       => $configId,
            'name'            => '', // filled by caller from labelMap
            'usage_qty'       => $usageQty,
            'config_qty'      => $configQty,
            'max_paid_qty'    => $maxPaidQty,
            'status'          => $built['status'],
            'suggested_delta' => $built['recommended_delta'],
        ];
    }

    return $rows;
}

/**
 * Compute usage quantity for a config ID from comet tables.
 */
function annual_entitlement_compute_usage_qty(int $configId, string $username): int
{
    $TIB = 1099511627776; // 2^40

    switch ($configId) {
        case 67:
            // Cloud storage: ceil(sum comet_vaults.total_bytes active type 1000/1003 in TiB)
            $bytes = (int)Capsule::table('comet_vaults')
                ->where('username', $username)
                ->where('is_active', 1)
                ->whereIn('type', [1000, 1003])
                ->sum('total_bytes');
            return (int)ceil($bytes / $TIB);

        case 88:
        case 89:
            // Endpoints: active device count from comet_devices for username
            return (int)Capsule::table('comet_devices')
                ->where('username', $username)
                ->whereNull('revoked_at')
                ->count();

        case 91:
            // Disk image: distinct owner_device count in comet_items type engine1/windisk
            $raw = Capsule::table('comet_items')
                ->where('username', $username)
                ->where('type', 'engine1/windisk')
                ->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(comet_device_id,''), NULLIF(owner_device,''))) as cnt")
                ->value('cnt');
            return (int)($raw ?? 0);

        case 60:
            // M365: sum of TotalAccountsCount from comet_items type engine1/winmsofficemail
            $raw = Capsule::table('comet_items')
                ->where('username', $username)
                ->where('type', 'engine1/winmsofficemail')
                ->selectRaw("COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(content,'$.Statistics.LastBackupJob.TotalAccountsCount')) AS UNSIGNED)), 0) as cnt")
                ->value('cnt');
            return (int)($raw ?? 0);

        case 97:
            return (int)Capsule::table('comet_items')
                ->where('username', $username)
                ->where('type', 'engine1/hyperv')
                ->count();

        case 99:
            return (int)Capsule::table('comet_items')
                ->where('username', $username)
                ->where('type', 'engine1/vmware')
                ->count();

        case 102:
            return (int)Capsule::table('comet_items')
                ->where('username', $username)
                ->where('type', 'engine1/proxmox')
                ->count();

        default:
            return 0;
    }
}

/**
 * Render panel HTML for the entitlement rows.
 *
 * @param array<int, array{config_id: int, name: string, usage_qty: int, config_qty: int, max_paid_qty: int, status: string, suggested_delta: int}> $rows
 * @param array<int, string> $labelMap
 * @param int $serviceId
 * @param string $cycleStart Y-m-d
 */
function annual_entitlement_render_panel(array $rows, array $labelMap, int $serviceId, string $cycleStart): string
{
    $ajaxPath = '/includes/hooks/annualEntitlement_ajax.php';
    // Plain token is used throughout addon admin flows and validated by check_token('WHMCS.admin.default').
    $csrfToken = function_exists('generate_token') ? (string)generate_token('plain') : '';
    $note = 'Manual-assist mode: no automatic qty changes, no automatic invoice generation.';
    $header = '<tr><td colspan="2" class="fieldarea"><div class="panel panel-default"><div class="panel-heading"><strong>Annual Entitlement</strong></div><div class="panel-body"><p class="text-muted" style="margin-bottom:12px;">' . htmlspecialchars($note) . '</p>';
    $table = '<table class="table table-striped table-condensed ae-entitlement-table"><thead><tr><th>Config</th><th>Usage</th><th>Config qty</th><th>Max paid</th><th>Status</th><th>Suggested &Delta;</th><th>Action</th></tr></thead><tbody>';

    foreach ($rows as $configId => $r) {
        $name = $labelMap[$configId] ?? 'Config ' . $configId;
        $usageQty = (int)$r['usage_qty'];
        $btn = '';
        if ($usageQty >= 0) {
            $btn = '<button type="button" class="btn btn-default btn-sm ae-mark-paid" data-service-id="' . (int)$serviceId . '" data-config-id="' . (int)$configId . '" data-cycle-start="' . htmlspecialchars($cycleStart, ENT_QUOTES, 'UTF-8') . '" data-new-max="' . $usageQty . '">Mark paid</button>';
        }
        $table .= '<tr>';
        $table .= '<td>' . htmlspecialchars((string)$configId) . ' / ' . htmlspecialchars($name) . '</td>';
        $table .= '<td>' . $usageQty . '</td>';
        $table .= '<td>' . (int)$r['config_qty'] . '</td>';
        $table .= '<td>' . (int)$r['max_paid_qty'] . '</td>';
        $table .= '<td>' . htmlspecialchars($r['status']) . '</td>';
        $table .= '<td>' . (int)$r['suggested_delta'] . '</td>';
        $table .= '<td>' . $btn . '</td>';
        $table .= '</tr>';
    }

    $table .= '</tbody></table></div></div></td></tr>';
    $tokenEscaped = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
    $script = '<input type="hidden" id="ae-csrf-token" value="' . $tokenEscaped . '">
    <script type="text/javascript">
        jQuery(function() {
            var ajaxPath = ' . json_encode($ajaxPath) . ';
            var tokenEl = document.getElementById("ae-csrf-token");
            var getToken = function() { return (tokenEl && tokenEl.value) || (typeof csrfToken !== "undefined" ? csrfToken : "") || (jQuery("input[name=\'token\']").first().val() || ""); };
            jQuery(document).on("click", ".ae-mark-paid", function() {
                var $btn = jQuery(this);
                if ($btn.prop("disabled")) return;
                var note = prompt("Optional note for this action:", "");
                if (note === null) return;
                $btn.prop("disabled", true);
                var params = {
                    action: "mark_paid_max",
                    token: getToken(),
                    service_id: $btn.data("service-id"),
                    config_id: $btn.data("config-id"),
                    cycle_start: $btn.data("cycle-start"),
                    new_max_paid_qty: $btn.data("new-max"),
                    note: note || ""
                };
                jQuery.post(ajaxPath, params).done(function(r) {
                    if (r && r.status) { location.reload(); } else { alert(r && r.message ? r.message : "Error"); $btn.prop("disabled", false); }
                }).fail(function() { alert("Request failed"); $btn.prop("disabled", false); });
            });
        });
    </script>';
    return $header . $table . $script;
}
