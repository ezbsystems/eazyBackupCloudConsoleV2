<?php
declare(strict_types=1);

use Ms365Backup\Ms365BillingService;
use Ms365Backup\Ms365BillingTrial;
use WHMCS\Database\Capsule;

/**
 * Admin: MS365 Backup Trials.
 *
 * Lists rows in ms365_billing_trial_state with filters and per-row actions.
 */
function ms365backup_admin_trials(array $vars): void
{
    if (isset($_REQUEST['ms365_action'])) {
        header('Content-Type: application/json');
        try {
            echo ms365backup_admin_trials_ajax($_REQUEST);
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

    if (!Capsule::schema()->hasTable('ms365_billing_trial_state')) {
        echo '<div class="alert alert-warning">Trial table not found. Upgrade the ms365backup addon to apply billing migrations.</div>';
        return;
    }

    $query = Capsule::table('ms365_billing_trial_state as t')
        ->leftJoin('tblclients as c', 'c.id', '=', 't.client_id')
        ->leftJoin('tblhosting as h', 'h.id', '=', 't.service_id')
        ->select([
            't.service_id',
            't.client_id',
            't.status',
            't.trial_started_at',
            't.trial_ends_at',
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
    $rows = $query->orderByDesc('t.updated_at')->limit(200)->get();

    $counts = [];
    foreach ($allowed as $st) {
        $counts[$st] = (int) Capsule::table('ms365_billing_trial_state')->where('status', $st)->count();
    }

    ms365backup_admin_trials_render($rows, $counts, $filter);
}

/** @param array<string, mixed> $req */
function ms365backup_admin_trials_ajax(array $req): string
{
    $action = (string) ($req['ms365_action'] ?? '');
    $serviceId = (int) ($req['service_id'] ?? 0);

    switch ($action) {
        case 'evaluate':
            $newState = Ms365BillingTrial::evaluateService($serviceId);
            return json_encode(['status' => 'success', 'new_state' => $newState]);
        case 'convert':
            $newState = Ms365BillingTrial::evaluateService($serviceId, 'converted');
            return json_encode(['status' => 'success', 'new_state' => $newState]);
        case 'cancel':
            $newState = Ms365BillingTrial::evaluateService($serviceId, 'cancelled');
            return json_encode(['status' => 'success', 'new_state' => $newState]);
        case 'suspend':
            $newState = Ms365BillingTrial::evaluateService($serviceId, 'suspended_no_payment');
            return json_encode(['status' => 'success', 'new_state' => $newState]);
        case 'preview':
            $clientId = (int) ($req['client_id'] ?? 0);
            if ($clientId <= 0) {
                $row = Capsule::table('ms365_billing_trial_state')->where('service_id', $serviceId)->first();
                $clientId = $row ? (int) $row->client_id : 0;
            }
            if ($clientId <= 0) {
                return json_encode(['status' => 'fail', 'message' => 'client_id required']);
            }
            $preview = Ms365BillingService::dryRun($clientId, $serviceId > 0 ? $serviceId : null);
            return json_encode(['status' => 'success', 'preview' => $preview]);
        case 'evaluate_all':
            $r = Ms365BillingTrial::evaluateAll();
            return json_encode(['status' => 'success', 'result' => $r]);
        default:
            return json_encode(['status' => 'fail', 'message' => 'unknown_action']);
    }
}

/**
 * @param iterable<object> $rows
 * @param array<string, int> $counts
 */
function ms365backup_admin_trials_render($rows, array $counts, string $filter): void
{
    $baseUrl = ($_SERVER['PHP_SELF'] ?? 'addonmodules.php') . '?module=ms365backup&action=trials';
    $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

    echo '<div class="content-padded">';
    echo '<h3 class="page-title">MS365 Backup Trials</h3>';
    echo '<p class="text-muted">Lifecycle state for every Microsoft 365 Backup service. Trials transition to <strong>converted</strong> when a payment method is on file at trial end, or <strong>suspended_no_payment</strong> otherwise. Data is preserved on suspension; admins may convert or cancel manually.</p>';

    echo '<div class="panel panel-default" style="margin-bottom:14px;">';
    echo '<div class="panel-body">';
    $filters = [
        '' => 'All',
        'trialing' => 'Trialing',
        'suspended_no_payment' => 'Suspended (No Payment)',
        'converted' => 'Converted',
        'cancelled' => 'Cancelled',
    ];
    foreach ($filters as $key => $label) {
        $count = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0);
        $cls = ($filter === $key) ? 'btn-primary' : 'btn-default';
        $href = $e($baseUrl . ($key !== '' ? '&status=' . urlencode($key) : ''));
        echo '<a href="' . $href . '" class="btn btn-sm ' . $cls . '" style="margin-right:6px;">' . $e($label) . ' <span class="badge">' . (int) $count . '</span></a>';
    }
    echo '<button type="button" class="btn btn-sm btn-warning pull-right" onclick="ms365TrialsEvaluateAll()"><i class="fa fa-refresh"></i> Run evaluation now</button>';
    echo '</div></div>';

    echo '<table class="table table-striped table-hover" style="font-size:13px;">';
    echo '<thead><tr>';
    echo '<th>Service</th><th>Client</th><th>Status</th><th>Trial Started</th><th>Trial Ends</th><th>Service Status</th><th>Next Due</th><th>Card</th><th>Notes</th><th>Actions</th>';
    echo '</tr></thead><tbody>';

    $rowList = is_array($rows) ? $rows : iterator_to_array($rows);
    if (count($rowList) === 0) {
        echo '<tr><td colspan="10" class="text-center text-muted">No trial-state rows yet.</td></tr>';
    } else {
        foreach ($rowList as $r) {
            $clientName = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
            if ($clientName === '' && !empty($r->companyname)) {
                $clientName = (string) $r->companyname;
            }
            $statusClass = [
                'trialing' => 'label-info',
                'converted' => 'label-success',
                'suspended_no_payment' => 'label-warning',
                'cancelled' => 'label-danger',
            ][(string) ($r->status ?? '')] ?? 'label-default';
            $hasCard = Ms365BillingTrial::clientHasCard((int) $r->client_id);
            $cardLabel = $hasCard ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>';

            echo '<tr>';
            echo '<td>#' . (int) $r->service_id . '</td>';
            echo '<td><strong>' . $e($clientName) . '</strong><br><small class="text-muted">' . $e((string) $r->email) . ' (#' . (int) $r->client_id . ')</small></td>';
            echo '<td><span class="label ' . $statusClass . '">' . $e((string) $r->status) . '</span></td>';
            echo '<td>' . $e(substr((string) ($r->trial_started_at ?? ''), 0, 10)) . '</td>';
            echo '<td>' . $e(substr((string) ($r->trial_ends_at ?? ''), 0, 10)) . '</td>';
            echo '<td>' . $e((string) ($r->domainstatus ?? '')) . '</td>';
            echo '<td>' . $e((string) ($r->nextduedate ?? '')) . '</td>';
            echo '<td>' . $cardLabel . '</td>';
            echo '<td><small>' . $e((string) ($r->notes ?? '')) . '</small></td>';
            echo '<td style="white-space:nowrap;">';
            echo '<button type="button" class="btn btn-xs btn-default" onclick="ms365TrialsPreview(' . (int) $r->service_id . ',' . (int) $r->client_id . ')"><i class="fa fa-eye"></i> Preview</button> ';
            if (in_array((string) $r->status, ['trialing', 'suspended_no_payment'], true)) {
                echo '<button type="button" class="btn btn-xs btn-success" onclick="ms365TrialsConvert(' . (int) $r->service_id . ')"><i class="fa fa-check"></i> Convert</button> ';
                echo '<button type="button" class="btn btn-xs btn-danger" onclick="ms365TrialsCancel(' . (int) $r->service_id . ')"><i class="fa fa-times"></i> Cancel</button> ';
                echo '<button type="button" class="btn btn-xs btn-warning" onclick="ms365TrialsEvaluate(' . (int) $r->service_id . ')"><i class="fa fa-refresh"></i> Re-evaluate</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    echo '<div id="ms365TrialsPreview" class="panel panel-info" style="display:none; margin-top:12px;">';
    echo '<div class="panel-heading"><strong>Estimated next invoice</strong> <button type="button" class="close" onclick="document.getElementById(\'ms365TrialsPreview\').style.display=\'none\';">&times;</button></div>';
    echo '<div class="panel-body" id="ms365TrialsPreviewBody"></div>';
    echo '</div>';

    echo '</div>';
    ?>
    <script>
    function ms365TrialsAjax(payload) {
        var url = <?php echo json_encode($baseUrl); ?> + '&' + Object.keys(payload).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(payload[k]);
        }).join('&');
        return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function ms365TrialsConvert(sid) {
        if (!confirm('Force convert service #' + sid + ' to PAID? This anchors billing to today.')) return;
        ms365TrialsAjax({ ms365_action: 'convert', service_id: sid }).then(function (j) {
            alert('New state: ' + (j.new_state || j.message || 'error'));
            location.reload();
        });
    }
    function ms365TrialsCancel(sid) {
        if (!confirm('Cancel service #' + sid + '? This will terminate the MS365 Backup service for the client.')) return;
        ms365TrialsAjax({ ms365_action: 'cancel', service_id: sid }).then(function (j) {
            alert('New state: ' + (j.new_state || j.message || 'error'));
            location.reload();
        });
    }
    function ms365TrialsEvaluate(sid) {
        ms365TrialsAjax({ ms365_action: 'evaluate', service_id: sid }).then(function (j) {
            alert('New state: ' + (j.new_state || j.message || 'error'));
            location.reload();
        });
    }
    function ms365TrialsEvaluateAll() {
        if (!confirm('Re-run the trial evaluation for every row?')) return;
        ms365TrialsAjax({ ms365_action: 'evaluate_all' }).then(function (j) {
            alert('Result: ' + JSON.stringify(j.result || j));
            location.reload();
        });
    }
    function ms365TrialsPreview(sid, cid) {
        ms365TrialsAjax({ ms365_action: 'preview', service_id: sid, client_id: cid }).then(function (j) {
            if (j.status !== 'success') {
                alert('Preview error: ' + (j.message || 'unknown'));
                return;
            }
            var p = j.preview;
            var html = '<p><strong>Window:</strong> ' + p.window.start + ' &rarr; ' + p.window.end
                + ' &nbsp; <strong>Trial:</strong> ' + (p.trial_status || 'none')
                + ' &nbsp; <strong>Card on file:</strong> ' + (p.has_payment_method ? 'Yes' : 'No') + '</p>';
            html += '<table class="table table-condensed"><thead><tr><th>Metric</th><th class="text-right">Qty</th><th class="text-right">Unit (CAD)</th><th class="text-right">Line (CAD)</th><th>Source</th></tr></thead><tbody>';
            (p.lines || []).forEach(function (l) {
                html += '<tr><td>' + l.metric_label + '</td><td class="text-right">' + l.qty + '</td><td class="text-right">' + Number(l.unit_price).toFixed(2) + '</td><td class="text-right">' + Number(l.line_amount).toFixed(2) + '</td><td>' + l.source + '</td></tr>';
            });
            html += '</tbody><tfoot>';
            html += '<tr><th colspan="3" class="text-right">Total (billable):</th><th class="text-right">' + Number(p.total_billable).toFixed(2) + '</th><th></th></tr>';
            html += '<tr><th colspan="3" class="text-right">Total (if paid):</th><th class="text-right">' + Number(p.total_if_paid).toFixed(2) + '</th><th></th></tr>';
            html += '</tfoot></table>';
            document.getElementById('ms365TrialsPreviewBody').innerHTML = html;
            document.getElementById('ms365TrialsPreview').style.display = '';
            var panel = document.getElementById('ms365TrialsPreview');
            window.scrollTo({ top: panel.offsetTop - 20, behavior: 'smooth' });
        });
    }
    </script>
    <?php
}
