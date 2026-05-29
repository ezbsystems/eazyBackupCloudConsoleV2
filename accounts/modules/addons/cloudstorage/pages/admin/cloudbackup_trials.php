<?php

require_once __DIR__ . '/../../lib/Provision/E3CloudBackupProductBootstrap.php';
require_once __DIR__ . '/../../lib/Admin/E3CloudBackupPricing.php';
require_once __DIR__ . '/../../lib/Admin/E3CloudBackupBilling.php';
require_once __DIR__ . '/../../lib/Admin/E3CloudBackupTrial.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupBilling;
use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupTrial;

/**
 * Admin: Cloud Backup Trials.
 *
 * Lists all rows in s3_cloudbackup_trial_state with filters and per-row
 * actions (Convert manually, Cancel, View preview, Run evaluation now).
 */
function cloudstorage_admin_cloudbackup_trials($vars)
{
    if (isset($_REQUEST['cs_action'])) {
        header('Content-Type: application/json');
        try {
            echo cloudstorage_admin_cb_trials_ajax($_REQUEST);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
        }
        exit;
    }

    $filter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
    $allowed = ['trialing', 'converted', 'suspended_no_payment', 'cancelled'];
    if (!in_array($filter, $allowed, true)) {
        $filter = '';
    }

    $query = Capsule::table('s3_cloudbackup_trial_state as t')
        ->leftJoin('tblclients as c', 'c.id', '=', 't.client_id')
        ->leftJoin('tblhosting as h', 'h.id', '=', 't.service_id')
        ->select([
            't.service_id',
            't.client_id',
            't.status',
            't.trial_started_at',
            't.trial_ends_at',
            't.converted_at',
            't.suspended_at',
            't.last_evaluated_at',
            't.notes',
            'c.firstname',
            'c.lastname',
            'c.companyname',
            'c.email',
            'h.domainstatus',
            'h.nextduedate',
        ]);
    if ($filter !== '') {
        $query->where('t.status', $filter);
    }
    $rows = $query->orderBy('t.updated_at', 'desc')->limit(200)->get();

    $counts = [];
    foreach ($allowed as $st) {
        $counts[$st] = (int) Capsule::table('s3_cloudbackup_trial_state')->where('status', $st)->count();
    }

    cloudstorage_admin_cb_trials_render($rows, $counts, $filter);
}

function cloudstorage_admin_cb_trials_ajax(array $req)
{
    $action = (string) ($req['cs_action'] ?? '');
    $serviceId = (int) ($req['service_id'] ?? 0);
    switch ($action) {
        case 'evaluate':
            $newState = E3CloudBackupTrial::evaluateService($serviceId);
            return json_encode(['status' => 'success', 'new_state' => $newState]);
        case 'convert':
            $newState = E3CloudBackupTrial::evaluateService($serviceId, 'converted');
            return json_encode(['status' => 'success', 'new_state' => $newState]);
        case 'cancel':
            $newState = E3CloudBackupTrial::evaluateService($serviceId, 'cancelled');
            return json_encode(['status' => 'success', 'new_state' => $newState]);
        case 'suspend':
            $newState = E3CloudBackupTrial::evaluateService($serviceId, 'suspended_no_payment');
            return json_encode(['status' => 'success', 'new_state' => $newState]);
        case 'preview':
            $clientId = (int) ($req['client_id'] ?? 0);
            if ($clientId <= 0) {
                $row = Capsule::table('s3_cloudbackup_trial_state')->where('service_id', $serviceId)->first();
                $clientId = $row ? (int) $row->client_id : 0;
            }
            $preview = E3CloudBackupBilling::dryRun($clientId);
            return json_encode(['status' => 'success', 'preview' => $preview]);
        case 'evaluate_all':
            $r = E3CloudBackupTrial::evaluateAll();
            return json_encode(['status' => 'success', 'result' => $r]);
        default:
            return json_encode(['status' => 'fail', 'message' => 'unknown_action']);
    }
}

function cloudstorage_admin_cb_trials_render($rows, $counts, $filter)
{
    $baseUrl = $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=cloudbackup_trials';
    echo '<div class="content-padded">';
    echo '<h2 class="page-title">Cloud Backup Trials</h2>';
    echo '<p class="text-muted">Lifecycle state for every e3 Cloud Backup service. Trials transition to <strong>converted</strong> when a payment method is on file at trial end, or <strong>suspended_no_payment</strong> otherwise. Data is preserved on suspension; admins may convert or cancel manually.</p>';

    echo '<div class="panel panel-default" style="margin-bottom:14px;">';
    echo '<div class="panel-body">';
    $filters = [
        ''                     => 'All',
        'trialing'             => 'Trialing',
        'suspended_no_payment' => 'Suspended (No Payment)',
        'converted'            => 'Converted',
        'cancelled'            => 'Cancelled',
    ];
    foreach ($filters as $key => $label) {
        $count = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0);
        $cls = ($filter === $key) ? 'btn-primary' : 'btn-default';
        $href = htmlspecialchars($baseUrl . ($key !== '' ? '&status=' . urlencode($key) : ''));
        echo '<a href="' . $href . '" class="btn btn-sm ' . $cls . '" style="margin-right:6px;">' . htmlspecialchars($label) . ' <span class="badge">' . (int) $count . '</span></a>';
    }
    echo '<button type="button" class="btn btn-sm btn-warning pull-right" onclick="cbTrialsEvaluateAll()"><i class="fa fa-refresh"></i> Run evaluation now</button>';
    echo '</div></div>';

    echo '<table class="table table-striped table-hover" style="font-size:13px;">';
    echo '<thead><tr>';
    echo '<th>Service</th><th>Client</th><th>Status</th><th>Trial Ends</th><th>Service Status</th><th>Next Due</th><th>Notes</th><th>Actions</th>';
    echo '</tr></thead><tbody>';
    if (count($rows) === 0) {
        echo '<tr><td colspan="8" class="text-center text-muted">No trial-state rows yet.</td></tr>';
    } else {
        foreach ($rows as $r) {
            $clientName = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
            if ($clientName === '' && !empty($r->companyname)) {
                $clientName = $r->companyname;
            }
            $statusClass = [
                'trialing'             => 'label-info',
                'converted'            => 'label-success',
                'suspended_no_payment' => 'label-warning',
                'cancelled'            => 'label-danger',
            ][$r->status] ?? 'label-default';
            echo '<tr>';
            echo '<td>#' . (int) $r->service_id . '</td>';
            echo '<td>'
                . '<strong>' . htmlspecialchars($clientName) . '</strong><br>'
                . '<small class="text-muted">' . htmlspecialchars((string) $r->email) . ' (#' . (int) $r->client_id . ')</small>'
                . '</td>';
            echo '<td><span class="label ' . $statusClass . '">' . htmlspecialchars((string) $r->status) . '</span></td>';
            echo '<td>' . htmlspecialchars(substr((string) $r->trial_ends_at, 0, 10)) . '</td>';
            echo '<td>' . htmlspecialchars((string) $r->domainstatus) . '</td>';
            echo '<td>' . htmlspecialchars((string) $r->nextduedate) . '</td>';
            echo '<td><small>' . htmlspecialchars((string) $r->notes) . '</small></td>';
            echo '<td>';
            echo '<button class="btn btn-xs btn-default" onclick="cbTrialsPreview(' . (int) $r->service_id . ',' . (int) $r->client_id . ')"><i class="fa fa-eye"></i> Preview</button> ';
            if ($r->status === 'trialing' || $r->status === 'suspended_no_payment') {
                echo '<button class="btn btn-xs btn-success" onclick="cbTrialsConvert(' . (int) $r->service_id . ')"><i class="fa fa-check"></i> Convert</button> ';
                echo '<button class="btn btn-xs btn-danger" onclick="cbTrialsCancel(' . (int) $r->service_id . ')"><i class="fa fa-times"></i> Cancel</button> ';
                echo '<button class="btn btn-xs btn-warning" onclick="cbTrialsEvaluate(' . (int) $r->service_id . ')"><i class="fa fa-refresh"></i> Re-evaluate</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    echo '<div id="cbTrialsPreview" class="panel panel-info" style="display:none; margin-top:12px;">';
    echo '<div class="panel-heading"><strong>Estimated next invoice</strong> <button type="button" class="close" onclick="document.getElementById(\'cbTrialsPreview\').style.display=\'none\';">&times;</button></div>';
    echo '<div class="panel-body" id="cbTrialsPreviewBody"></div>';
    echo '</div>';

    echo '</div>';
    ?>
    <script>
    function cbTrialsAjax(payload) {
        var url = '<?php echo $baseUrl; ?>' + '&' + Object.keys(payload).map(function(k){
            return encodeURIComponent(k) + '=' + encodeURIComponent(payload[k]);
        }).join('&');
        return fetch(url, {credentials: 'same-origin'}).then(function(r){ return r.json(); });
    }
    function cbTrialsConvert(sid) {
        if (!confirm('Force convert service #' + sid + ' to PAID? This anchors billing to today.')) return;
        cbTrialsAjax({cs_action: 'convert', service_id: sid}).then(function(j){
            alert('New state: ' + (j.new_state || j.message || 'error'));
            location.reload();
        });
    }
    function cbTrialsCancel(sid) {
        if (!confirm('Cancel service #' + sid + '? This will terminate the Cloud Backup AND Cloud Storage services for the client.')) return;
        cbTrialsAjax({cs_action: 'cancel', service_id: sid}).then(function(j){
            alert('New state: ' + (j.new_state || j.message || 'error'));
            location.reload();
        });
    }
    function cbTrialsEvaluate(sid) {
        cbTrialsAjax({cs_action: 'evaluate', service_id: sid}).then(function(j){
            alert('New state: ' + (j.new_state || j.message || 'error'));
            location.reload();
        });
    }
    function cbTrialsEvaluateAll() {
        if (!confirm('Re-run the trial evaluation for every row?')) return;
        cbTrialsAjax({cs_action: 'evaluate_all'}).then(function(j){
            alert('Result: ' + JSON.stringify(j.result || j));
            location.reload();
        });
    }
    function cbTrialsPreview(sid, cid) {
        cbTrialsAjax({cs_action: 'preview', service_id: sid, client_id: cid}).then(function(j){
            if (j.status !== 'success') { alert('Preview error: ' + (j.message || 'unknown')); return; }
            var p = j.preview;
            var html = '<p><strong>Window:</strong> ' + p.window.start + ' &rarr; ' + p.window.end + ' &nbsp; <strong>Trial:</strong> ' + (p.trial_status || 'none') + '</p>';
            html += '<table class="table table-condensed"><thead><tr><th>Metric</th><th class="text-right">Qty</th><th class="text-right">Unit</th><th class="text-right">Line</th><th>Source</th><th>Tier</th></tr></thead><tbody>';
            (p.lines || []).forEach(function(l) {
                html += '<tr><td>' + l.metric_label + '</td><td class="text-right">' + l.qty + '</td><td class="text-right">' + l.unit_price.toFixed(2) + '</td><td class="text-right">' + l.line_amount.toFixed(2) + '</td><td>' + l.source + '</td><td>' + (l.tier_label || '') + '</td></tr>';
            });
            html += '</tbody><tfoot>';
            html += '<tr><th colspan="3" class="text-right">Total (billable):</th><th class="text-right">' + p.total_billable.toFixed(2) + '</th><th colspan="2"></th></tr>';
            html += '<tr><th colspan="3" class="text-right">Total (if paid):</th><th class="text-right">' + p.total_if_paid.toFixed(2) + '</th><th colspan="2"></th></tr>';
            html += '</tfoot></table>';
            document.getElementById('cbTrialsPreviewBody').innerHTML = html;
            document.getElementById('cbTrialsPreview').style.display = '';
            window.scrollTo({top: document.getElementById('cbTrialsPreview').offsetTop - 20, behavior: 'smooth'});
        });
    }
    </script>
    <?php
}
