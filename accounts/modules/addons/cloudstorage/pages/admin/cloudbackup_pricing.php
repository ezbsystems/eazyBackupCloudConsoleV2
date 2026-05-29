<?php

require_once __DIR__ . '/../../lib/Provision/E3CloudBackupProductBootstrap.php';
require_once __DIR__ . '/../../lib/Admin/E3CloudBackupPricing.php';
require_once __DIR__ . '/../../lib/Admin/E3CloudBackupBilling.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupBilling;
use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupPricing;
use WHMCS\Module\Addon\CloudStorage\Provision\E3CloudBackupProductBootstrap;

/**
 * Admin: Cloud Backup Pricing.
 *
 * Two cards:
 *   1. Global defaults (writes to tblpricing for the configured currency).
 *   2. Per-client overrides table + Add/Edit modal + Preview drawer.
 */
function cloudstorage_admin_cloudbackup_pricing($vars)
{
    if (isset($_REQUEST['cs_action'])) {
        header('Content-Type: application/json');
        try {
            echo cloudstorage_admin_cb_pricing_ajax($_REQUEST);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
        }
        exit;
    }
    cloudstorage_admin_cb_pricing_render();
}

function cloudstorage_admin_cb_pricing_ajax(array $req)
{
    $action = (string) ($req['cs_action'] ?? '');
    switch ($action) {
        case 'set_default_unit':
            return cloudstorage_admin_cb_pricing_set_default_unit($req);
        case 'list_overrides':
            return cloudstorage_admin_cb_pricing_list_overrides($req);
        case 'upsert_override':
            return cloudstorage_admin_cb_pricing_upsert_override($req);
        case 'delete_override':
            return cloudstorage_admin_cb_pricing_delete_override($req);
        case 'preview':
            return json_encode([
                'status'  => 'success',
                'preview' => E3CloudBackupBilling::dryRun((int) ($req['client_id'] ?? 0)),
            ]);
        case 'search_clients':
            $term = trim((string) ($req['q'] ?? ''));
            return cloudstorage_admin_cb_pricing_search_clients($term);
        default:
            return json_encode(['status' => 'fail', 'message' => 'unknown_action']);
    }
}

function cloudstorage_admin_cb_pricing_set_default_unit(array $req): string
{
    $metric = (string) ($req['metric'] ?? '');
    $price = (float) ($req['monthly'] ?? 0);
    if (!in_array($metric, E3CloudBackupPricing::METRICS, true)) {
        return json_encode(['status' => 'fail', 'message' => 'bad_metric']);
    }
    $configMap = E3CloudBackupProductBootstrap::getConfigOptionMap();
    $configId = (int) ($configMap[$metric] ?? 0);
    if ($configId <= 0) {
        return json_encode(['status' => 'fail', 'message' => 'no_config_id']);
    }
    try {
        $subId = (int) Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $configId)
            ->orderBy('sortorder', 'asc')
            ->orderBy('id', 'asc')
            ->value('id');
        if ($subId <= 0) {
            return json_encode(['status' => 'fail', 'message' => 'no_sub_id']);
        }
        $currencyId = (int) (Capsule::table('tbladdonmodules')->where('module','cloudstorage')->where('setting','e3cb_currency_id')->value('value') ?: 1);

        $exists = Capsule::table('tblpricing')
            ->where('type','configoptions')->where('currency',$currencyId)->where('relid',$subId)->exists();
        if ($exists) {
            Capsule::table('tblpricing')
                ->where('type','configoptions')->where('currency',$currencyId)->where('relid',$subId)
                ->update(['monthly' => $price, 'annually' => round($price * 12, 2)]);
        } else {
            Capsule::table('tblpricing')->insert([
                'type'         => 'configoptions',
                'currency'     => $currencyId,
                'relid'        => $subId,
                'msetupfee'    => 0, 'qsetupfee' => 0, 'ssetupfee' => 0, 'asetupfee' => 0, 'bsetupfee' => 0, 'tsetupfee' => 0,
                'monthly'      => $price,
                'quarterly'    => -1.00,
                'semiannually' => -1.00,
                'annually'     => round($price * 12, 2),
                'biennially'   => -1.00,
                'triennially'  => -1.00,
            ]);
        }
        return json_encode(['status' => 'success']);
    } catch (\Throwable $e) {
        return json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
    }
}

function cloudstorage_admin_cb_pricing_list_overrides(array $req): string
{
    $rows = Capsule::table('s3_cloudbackup_pricing as p')
        ->leftJoin('tblclients as c', 'c.id', '=', 'p.client_id')
        ->select([
            'p.*',
            'c.firstname',
            'c.lastname',
            'c.companyname',
            'c.email',
        ])
        ->orderBy('p.client_id', 'asc')
        ->orderBy('p.metric', 'asc')
        ->orderBy('p.effective_from', 'desc')
        ->get();

    $out = [];
    foreach ($rows as $r) {
        $name = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
        if ($name === '' && !empty($r->companyname)) {
            $name = $r->companyname;
        }
        $out[] = [
            'id'              => (int) $r->id,
            'client_id'       => $r->client_id !== null ? (int) $r->client_id : null,
            'client_label'    => $r->client_id !== null ? ($name . ' (#' . (int) $r->client_id . ')') : 'GLOBAL DEFAULT',
            'metric'          => (string) $r->metric,
            'metric_label'    => E3CloudBackupProductBootstrap::metricFriendlyName((string) $r->metric),
            'mode'            => (string) $r->mode,
            'unit_price'      => $r->unit_price !== null ? (float) $r->unit_price : null,
            'tiers_json'      => $r->tiers_json !== null ? json_decode($r->tiers_json, true) : null,
            'flat_monthly'    => $r->flat_monthly !== null ? (float) $r->flat_monthly : null,
            'currency_id'     => (int) $r->currency_id,
            'effective_from'  => substr((string) $r->effective_from, 0, 10),
            'effective_to'    => $r->effective_to ? substr((string) $r->effective_to, 0, 10) : null,
            'notes'           => (string) ($r->notes ?? ''),
        ];
    }
    return json_encode(['status' => 'success', 'rows' => $out]);
}

function cloudstorage_admin_cb_pricing_upsert_override(array $req): string
{
    $id = (int) ($req['id'] ?? 0);
    $clientId = (string) ($req['client_id'] ?? '');
    $metrics = $req['metrics'] ?? null;
    if (is_string($metrics)) {
        $metrics = array_filter(array_map('trim', explode(',', $metrics)));
    }
    if (!is_array($metrics) || count($metrics) === 0) {
        $single = (string) ($req['metric'] ?? '');
        if ($single !== '') {
            $metrics = [$single];
        }
    }
    if (!is_array($metrics) || count($metrics) === 0) {
        return json_encode(['status' => 'fail', 'message' => 'no_metrics']);
    }
    foreach ($metrics as $m) {
        if (!in_array($m, E3CloudBackupPricing::METRICS, true)) {
            return json_encode(['status' => 'fail', 'message' => 'bad_metric:' . $m]);
        }
    }
    $mode = (string) ($req['mode'] ?? 'flat_unit');
    if (!in_array($mode, ['flat_unit', 'tiered', 'flat_monthly'], true)) {
        return json_encode(['status' => 'fail', 'message' => 'bad_mode']);
    }
    $unitPrice = ($req['unit_price'] ?? '') === '' ? null : (float) $req['unit_price'];
    $flatMonthly = ($req['flat_monthly'] ?? '') === '' ? null : (float) $req['flat_monthly'];
    $tiersJson = null;
    if ($mode === 'tiered') {
        $rawTiers = $req['tiers_json'] ?? '';
        if (is_string($rawTiers) && $rawTiers !== '') {
            $decoded = json_decode($rawTiers, true);
            if (!is_array($decoded) || count($decoded) === 0) {
                return json_encode(['status' => 'fail', 'message' => 'invalid_tiers']);
            }
            $tiersJson = json_encode($decoded);
        } else {
            return json_encode(['status' => 'fail', 'message' => 'missing_tiers']);
        }
    }
    $currencyId = (int) ($req['currency_id'] ?? 1);
    $effectiveFrom = (string) ($req['effective_from'] ?? date('Y-m-d'));
    $effectiveTo = (string) ($req['effective_to'] ?? '');
    $effectiveTo = $effectiveTo === '' ? null : $effectiveTo;
    $notes = (string) ($req['notes'] ?? '');
    $clientIdInt = $clientId === '' ? null : (int) $clientId;

    $createdByAdmin = 0;
    try {
        if (isset($_SESSION['adminid'])) {
            $createdByAdmin = (int) $_SESSION['adminid'];
        }
    } catch (\Throwable $e) {
    }

    $payload = [
        'client_id'        => $clientIdInt,
        'mode'             => $mode,
        'unit_price'       => $unitPrice,
        'tiers_json'       => $tiersJson,
        'flat_monthly'     => $flatMonthly,
        'currency_id'      => $currencyId,
        'effective_from'   => $effectiveFrom,
        'effective_to'     => $effectiveTo,
        'notes'            => $notes,
        'created_by_admin' => $createdByAdmin,
        'updated_at'       => date('Y-m-d H:i:s'),
    ];

    if ($id > 0) {
        // Editing exactly one row: ignore $metrics, the existing row has one.
        try {
            Capsule::table('s3_cloudbackup_pricing')->where('id', $id)->update($payload);
            return json_encode(['status' => 'success', 'updated' => 1]);
        } catch (\Throwable $e) {
            return json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }

    $written = 0;
    foreach ($metrics as $m) {
        try {
            $row = $payload;
            $row['metric'] = $m;
            $row['created_at'] = date('Y-m-d H:i:s');
            Capsule::table('s3_cloudbackup_pricing')->insert($row);
            $written++;
        } catch (\Throwable $e) {
            // continue, but report
        }
    }
    return json_encode(['status' => 'success', 'inserted' => $written]);
}

function cloudstorage_admin_cb_pricing_delete_override(array $req): string
{
    $id = (int) ($req['id'] ?? 0);
    if ($id <= 0) {
        return json_encode(['status' => 'fail', 'message' => 'no_id']);
    }
    try {
        Capsule::table('s3_cloudbackup_pricing')->where('id', $id)->delete();
        return json_encode(['status' => 'success']);
    } catch (\Throwable $e) {
        return json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
    }
}

function cloudstorage_admin_cb_pricing_search_clients(string $term): string
{
    if ($term === '') {
        return json_encode(['status' => 'success', 'rows' => []]);
    }
    try {
        $rows = Capsule::table('tblclients')
            ->select(['id', 'firstname', 'lastname', 'companyname', 'email'])
            ->where(function ($q) use ($term) {
                $q->where('firstname', 'like', "%{$term}%")
                  ->orWhere('lastname', 'like', "%{$term}%")
                  ->orWhere('companyname', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('id', $term);
            })
            ->limit(20)
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $name = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
            if ($name === '' && !empty($r->companyname)) {
                $name = $r->companyname;
            }
            $out[] = [
                'id'    => (int) $r->id,
                'label' => $name . ' - ' . ($r->email ?? '') . ' (#' . (int) $r->id . ')',
            ];
        }
        return json_encode(['status' => 'success', 'rows' => $out]);
    } catch (\Throwable $e) {
        return json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
    }
}

function cloudstorage_admin_cb_pricing_render()
{
    $baseUrl = $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=cloudbackup_pricing';

    // Pull current global defaults from tblpricing for the configured currency.
    $currencyId = (int) (Capsule::table('tbladdonmodules')->where('module','cloudstorage')->where('setting','e3cb_currency_id')->value('value') ?: 1);
    $configMap = E3CloudBackupProductBootstrap::getConfigOptionMap();
    $defaults = [];
    foreach (E3CloudBackupPricing::METRICS as $metric) {
        $configId = (int) ($configMap[$metric] ?? 0);
        $unit = $configId > 0 ? E3CloudBackupPricing::tblpricingUnitPrice($configId, $currencyId) : 0.0;
        $defaults[$metric] = [
            'label'     => E3CloudBackupProductBootstrap::metricFriendlyName($metric),
            'unit'      => $unit,
            'config_id' => $configId,
        ];
    }

    echo '<div class="content-padded">';
    echo '<h2 class="page-title">Cloud Backup Pricing</h2>';
    echo '<p class="text-muted">Two layers: <strong>Global defaults</strong> are stored in WHMCS <code>tblpricing</code> and apply to every customer who does not have an override. <strong>Per-client overrides</strong> are evaluated by the InvoiceCreationPreEmail hook; volume tier pricing is supported (all units at the band the qty reached).</p>';

    // Global defaults card
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><strong>Global defaults</strong> <span class="text-muted">(currency #' . (int) $currencyId . ')</span></div>';
    echo '<table class="table table-condensed"><thead><tr><th>Metric</th><th class="text-right" style="width:240px;">Monthly unit price</th><th style="width:120px;">Save</th></tr></thead><tbody>';
    foreach ($defaults as $metric => $d) {
        $val = number_format((float) $d['unit'], 2);
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($d['label']) . '</strong> <span class="text-muted">(' . htmlspecialchars($metric) . ')</span></td>';
        echo '<td class="text-right"><input type="number" step="0.01" min="0" class="form-control text-right" id="def_' . $metric . '" value="' . $val . '"></td>';
        echo '<td><button class="btn btn-sm btn-primary" onclick="cbPricingSaveDefault(\'' . $metric . '\')">Save</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    // Per-client overrides
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<strong>Per-client overrides</strong>';
    echo '<button type="button" class="btn btn-sm btn-success pull-right" onclick="cbPricingOpenAdd()"><i class="fa fa-plus"></i> Add Override</button>';
    echo '</div>';
    echo '<div class="panel-body" id="cbOverridesList">Loading...</div>';
    echo '</div>';

    // Preview drawer
    echo '<div class="panel panel-info" id="cbPreviewDrawer" style="display:none;">';
    echo '<div class="panel-heading"><strong>Invoice preview</strong> <button type="button" class="close" onclick="document.getElementById(\'cbPreviewDrawer\').style.display=\'none\';">&times;</button></div>';
    echo '<div class="panel-body" id="cbPreviewBody"></div>';
    echo '</div>';

    // Add/Edit modal (simple inline panel)
    echo '<div class="panel panel-warning" id="cbOverrideModal" style="display:none;">';
    echo '<div class="panel-heading"><strong id="cbOverrideTitle">Add Override</strong>';
    echo '<button type="button" class="close" onclick="cbPricingCloseModal()">&times;</button></div>';
    echo '<div class="panel-body">';
    echo '<input type="hidden" id="cbOvr_id" value="">';
    echo '<div class="row"><div class="col-sm-6">';
    echo '<label>Client</label>';
    echo '<div class="input-group">';
    echo '<input type="text" class="form-control" id="cbOvr_clientSearch" placeholder="Type a name, email, or client ID (leave blank for GLOBAL default)">';
    echo '<span class="input-group-btn"><button class="btn btn-default" type="button" onclick="cbPricingSearchClient()">Search</button></span>';
    echo '</div>';
    echo '<input type="hidden" id="cbOvr_clientId" value="">';
    echo '<div id="cbOvr_clientResults" style="margin-top:6px;"></div>';
    echo '<div id="cbOvr_clientChosen" class="alert alert-info" style="display:none;margin-top:6px;"></div>';
    echo '</div>';
    echo '<div class="col-sm-6">';
    echo '<label>Metrics (multi)</label>';
    echo '<select id="cbOvr_metrics" multiple class="form-control" style="height:128px;">';
    foreach (E3CloudBackupPricing::METRICS as $m) {
        echo '<option value="' . htmlspecialchars($m) . '">' . htmlspecialchars(E3CloudBackupProductBootstrap::metricFriendlyName($m)) . ' (' . htmlspecialchars($m) . ')</option>';
    }
    echo '</select>';
    echo '</div></div>';

    echo '<hr>';

    echo '<div class="row"><div class="col-sm-4">';
    echo '<label>Mode</label>';
    echo '<select id="cbOvr_mode" class="form-control" onchange="cbPricingModeChange()">';
    echo '<option value="flat_unit">Flat per-unit price</option>';
    echo '<option value="tiered">Volume tiered (all units at band price)</option>';
    echo '<option value="flat_monthly">Flat monthly (one fee covers any qty)</option>';
    echo '</select>';
    echo '</div>';
    echo '<div class="col-sm-4" id="cbOvr_flatUnitWrap">';
    echo '<label>Unit price</label>';
    echo '<input type="number" min="0" step="0.0001" class="form-control" id="cbOvr_unitPrice" placeholder="e.g. 3.75">';
    echo '</div>';
    echo '<div class="col-sm-4" id="cbOvr_flatMonthlyWrap" style="display:none;">';
    echo '<label>Flat monthly fee</label>';
    echo '<input type="number" min="0" step="0.01" class="form-control" id="cbOvr_flatMonthly">';
    echo '</div></div>';

    echo '<div id="cbOvr_tieredWrap" style="display:none; margin-top:12px;">';
    echo '<label>Tier bands <small class="text-muted">(ascending; final band may omit "max" to mean "and above")</small></label>';
    echo '<table class="table table-condensed" id="cbOvr_tiersTable">';
    echo '<thead><tr><th>Min</th><th>Max</th><th>Unit price</th><th></th></tr></thead><tbody></tbody></table>';
    echo '<button type="button" class="btn btn-sm btn-default" onclick="cbPricingAddTierRow()">+ Add band</button>';
    echo '</div>';

    echo '<hr>';

    echo '<div class="row">';
    echo '<div class="col-sm-3"><label>Effective from</label><input type="date" id="cbOvr_effFrom" class="form-control" value="' . date('Y-m-d') . '"></div>';
    echo '<div class="col-sm-3"><label>Effective to (optional)</label><input type="date" id="cbOvr_effTo" class="form-control"></div>';
    echo '<div class="col-sm-3"><label>Currency ID</label><input type="number" id="cbOvr_currency" class="form-control" value="' . (int) $currencyId . '"></div>';
    echo '<div class="col-sm-3"><label>&nbsp;</label><div><button class="btn btn-primary btn-block" onclick="cbPricingSubmit()"><i class="fa fa-save"></i> Save</button></div></div>';
    echo '</div>';
    echo '<div style="margin-top:10px;"><label>Notes (optional)</label><textarea id="cbOvr_notes" class="form-control" rows="2"></textarea></div>';

    echo '</div></div>';

    echo '</div>'; // content-padded
    ?>
    <script>
    var CB_BASE = '<?php echo $baseUrl; ?>';
    function cbFetch(payload) {
        var url = CB_BASE + '&' + Object.keys(payload).map(function(k){
            return encodeURIComponent(k) + '=' + encodeURIComponent(payload[k]);
        }).join('&');
        return fetch(url, {credentials:'same-origin'}).then(function(r){ return r.json(); });
    }
    function cbPricingSaveDefault(metric) {
        var el = document.getElementById('def_' + metric);
        if (!el) return;
        cbFetch({cs_action:'set_default_unit', metric: metric, monthly: el.value}).then(function(j){
            if (j.status === 'success') { alert('Saved.'); }
            else { alert('Error: ' + (j.message || 'unknown')); }
        });
    }
    function cbPricingLoadOverrides() {
        cbFetch({cs_action:'list_overrides'}).then(function(j){
            if (j.status !== 'success') {
                document.getElementById('cbOverridesList').innerHTML = '<div class="alert alert-danger">' + (j.message || 'error') + '</div>';
                return;
            }
            var rows = j.rows || [];
            if (rows.length === 0) {
                document.getElementById('cbOverridesList').innerHTML = '<p class="text-muted">No overrides defined yet.</p>';
                return;
            }
            var html = '<table class="table table-striped table-hover" style="font-size:13px;"><thead><tr>';
            html += '<th>Client</th><th>Metric</th><th>Mode</th><th>Value</th><th>Effective</th><th>Notes</th><th></th></tr></thead><tbody>';
            rows.forEach(function(r) {
                var val = '';
                if (r.mode === 'flat_unit') val = '$' + (r.unit_price || 0).toFixed(4) + ' / unit';
                else if (r.mode === 'flat_monthly') val = '$' + (r.flat_monthly || 0).toFixed(2) + ' / mo';
                else if (r.mode === 'tiered') val = (r.tiers_json || []).map(function(b){
                    return (b.min || 1) + '-' + (b.max == null ? '∞' : b.max) + ' @ $' + (b.unit||0).toFixed(2);
                }).join(' | ');
                html += '<tr>';
                html += '<td>' + (r.client_label || '') + '</td>';
                html += '<td>' + r.metric_label + ' <small class="text-muted">(' + r.metric + ')</small></td>';
                html += '<td>' + r.mode + '</td>';
                html += '<td>' + val + '</td>';
                html += '<td>' + r.effective_from + (r.effective_to ? ' &rarr; ' + r.effective_to : '') + '</td>';
                html += '<td><small>' + (r.notes || '') + '</small></td>';
                html += '<td>';
                if (r.client_id) html += '<button class="btn btn-xs btn-default" onclick="cbPricingPreview(' + r.client_id + ')"><i class="fa fa-eye"></i></button> ';
                html += '<button class="btn btn-xs btn-danger" onclick="cbPricingDelete(' + r.id + ')"><i class="fa fa-trash"></i></button>';
                html += '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('cbOverridesList').innerHTML = html;
        });
    }
    function cbPricingDelete(id) {
        if (!confirm('Delete override #' + id + '?')) return;
        cbFetch({cs_action:'delete_override', id: id}).then(function(j){
            if (j.status === 'success') cbPricingLoadOverrides();
            else alert('Error: ' + (j.message || 'unknown'));
        });
    }
    function cbPricingOpenAdd() {
        document.getElementById('cbOverrideModal').style.display = '';
        document.getElementById('cbOverrideTitle').innerText = 'Add Override';
        document.getElementById('cbOvr_id').value = '';
        document.getElementById('cbOvr_clientSearch').value = '';
        document.getElementById('cbOvr_clientId').value = '';
        document.getElementById('cbOvr_clientChosen').style.display = 'none';
        document.getElementById('cbOvr_clientResults').innerHTML = '';
        document.getElementById('cbOvr_metrics').selectedIndex = -1;
        document.getElementById('cbOvr_mode').value = 'flat_unit';
        cbPricingModeChange();
        document.getElementById('cbOvr_unitPrice').value = '';
        document.getElementById('cbOvr_flatMonthly').value = '';
        document.getElementById('cbOvr_notes').value = '';
        document.querySelector('#cbOvr_tiersTable tbody').innerHTML = '';
        cbPricingAddTierRow();
        window.scrollTo({top: document.getElementById('cbOverrideModal').offsetTop - 20, behavior:'smooth'});
    }
    function cbPricingCloseModal() {
        document.getElementById('cbOverrideModal').style.display = 'none';
    }
    function cbPricingModeChange() {
        var mode = document.getElementById('cbOvr_mode').value;
        document.getElementById('cbOvr_flatUnitWrap').style.display = (mode === 'flat_unit') ? '' : 'none';
        document.getElementById('cbOvr_flatMonthlyWrap').style.display = (mode === 'flat_monthly') ? '' : 'none';
        document.getElementById('cbOvr_tieredWrap').style.display = (mode === 'tiered') ? '' : 'none';
    }
    function cbPricingAddTierRow(min, max, unit) {
        var tbody = document.querySelector('#cbOvr_tiersTable tbody');
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="number" class="form-control" min="1" placeholder="1" value="' + (min || '') + '"></td>' +
                       '<td><input type="number" class="form-control" min="1" placeholder="∞" value="' + (max || '') + '"></td>' +
                       '<td><input type="number" step="0.0001" class="form-control" placeholder="3.75" value="' + (unit || '') + '"></td>' +
                       '<td><button class="btn btn-xs btn-danger" onclick="this.closest(\'tr\').remove()"><i class="fa fa-trash"></i></button></td>';
        tbody.appendChild(tr);
    }
    function cbPricingSearchClient() {
        var q = document.getElementById('cbOvr_clientSearch').value;
        cbFetch({cs_action:'search_clients', q: q}).then(function(j){
            var html = '';
            (j.rows || []).forEach(function(r) {
                html += '<button class="btn btn-xs btn-default" style="margin:2px;" onclick="cbPricingPickClient(' + r.id + ',\'' + r.label.replace(/'/g,'') + '\')">' + r.label + '</button>';
            });
            document.getElementById('cbOvr_clientResults').innerHTML = html || '<em>No matches.</em>';
        });
    }
    function cbPricingPickClient(id, label) {
        document.getElementById('cbOvr_clientId').value = id;
        var el = document.getElementById('cbOvr_clientChosen');
        el.style.display = '';
        el.innerHTML = 'Selected: <strong>' + label + '</strong> <button class="btn btn-xs btn-default" style="margin-left:8px;" onclick="cbPricingClearClient()">Clear (GLOBAL)</button>';
    }
    function cbPricingClearClient() {
        document.getElementById('cbOvr_clientId').value = '';
        document.getElementById('cbOvr_clientChosen').style.display = 'none';
    }
    function cbPricingCollectTiers() {
        var rows = document.querySelectorAll('#cbOvr_tiersTable tbody tr');
        var out = [];
        rows.forEach(function(tr) {
            var inputs = tr.querySelectorAll('input');
            var min = parseInt(inputs[0].value || '0', 10) || 0;
            var max = inputs[1].value ? parseInt(inputs[1].value, 10) : null;
            var unit = parseFloat(inputs[2].value || '0') || 0;
            if (unit >= 0) out.push({min: min || 1, max: max, unit: unit});
        });
        out.sort(function(a, b) { return a.min - b.min; });
        return out;
    }
    function cbPricingSubmit() {
        var mode = document.getElementById('cbOvr_mode').value;
        var clientId = document.getElementById('cbOvr_clientId').value;
        var metrics = Array.from(document.getElementById('cbOvr_metrics').selectedOptions).map(function(o){ return o.value; });
        if (metrics.length === 0) { alert('Select at least one metric.'); return; }
        var payload = {
            cs_action: 'upsert_override',
            id: document.getElementById('cbOvr_id').value,
            client_id: clientId,
            metrics: metrics.join(','),
            mode: mode,
            unit_price: document.getElementById('cbOvr_unitPrice').value || '',
            flat_monthly: document.getElementById('cbOvr_flatMonthly').value || '',
            currency_id: document.getElementById('cbOvr_currency').value || 1,
            effective_from: document.getElementById('cbOvr_effFrom').value || '',
            effective_to: document.getElementById('cbOvr_effTo').value || '',
            notes: document.getElementById('cbOvr_notes').value || ''
        };
        if (mode === 'tiered') {
            payload.tiers_json = JSON.stringify(cbPricingCollectTiers());
        }
        cbFetch(payload).then(function(j){
            if (j.status === 'success') {
                cbPricingCloseModal();
                cbPricingLoadOverrides();
            } else {
                alert('Error: ' + (j.message || 'unknown'));
            }
        });
    }
    function cbPricingPreview(clientId) {
        cbFetch({cs_action:'preview', client_id: clientId}).then(function(j){
            if (j.status !== 'success') { alert('Preview error: ' + (j.message || 'unknown')); return; }
            var p = j.preview;
            var html = '<p><strong>Client #' + p.client_id + '</strong> &nbsp; <strong>Window:</strong> ' + p.window.start + ' &rarr; ' + p.window.end + ' &nbsp; <strong>Trial:</strong> ' + (p.trial_status || 'none') + '</p>';
            html += '<table class="table table-condensed"><thead><tr><th>Metric</th><th class="text-right">Qty</th><th class="text-right">Unit</th><th class="text-right">Line</th><th>Source</th><th>Tier</th></tr></thead><tbody>';
            (p.lines || []).forEach(function(l) {
                html += '<tr><td>' + l.metric_label + '</td><td class="text-right">' + l.qty + '</td><td class="text-right">' + l.unit_price.toFixed(2) + '</td><td class="text-right">' + l.line_amount.toFixed(2) + '</td><td>' + l.source + '</td><td>' + (l.tier_label || '') + '</td></tr>';
            });
            html += '</tbody><tfoot>';
            html += '<tr><th colspan="3" class="text-right">Total (billable):</th><th class="text-right">' + p.total_billable.toFixed(2) + '</th><th colspan="2"></th></tr>';
            html += '<tr><th colspan="3" class="text-right">Total (if paid):</th><th class="text-right">' + p.total_if_paid.toFixed(2) + '</th><th colspan="2"></th></tr>';
            html += '</tfoot></table>';
            document.getElementById('cbPreviewBody').innerHTML = html;
            document.getElementById('cbPreviewDrawer').style.display = '';
            window.scrollTo({top: document.getElementById('cbPreviewDrawer').offsetTop - 20, behavior:'smooth'});
        });
    }
    cbPricingLoadOverrides();
    </script>
    <?php
}
