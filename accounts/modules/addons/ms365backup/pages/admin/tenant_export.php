<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/ms365backup_autoload.php';

use Ms365Backup\Ms365AdminTenantExportService;

/**
 * Admin: export MS365 tenant manual-connect credentials for e3 backup users.
 */
function ms365backup_admin_tenant_export(array $vars): void
{
    if (isset($_REQUEST['ms365_action'])) {
        header('Content-Type: application/json');
        try {
            echo ms365backup_admin_tenant_export_ajax($_REQUEST);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
        }
        exit;
    }

    ms365backup_admin_tenant_export_render($vars);
}

/** @param array<string, mixed> $req */
function ms365backup_admin_tenant_export_ajax(array $req): string
{
    $action = (string) ($req['ms365_action'] ?? '');

    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    switch ($action) {
        case 'backup_user_search':
            $query = isset($req['q']) ? (string) $req['q'] : '';
            if (trim($query) === '' || strlen(trim($query)) < 2) {
                return json_encode(['status' => 'success', 'results' => []]);
            }
            try {
                $results = Ms365AdminTenantExportService::searchBackupUsers($query, 15);

                return json_encode(['status' => 'success', 'results' => $results]);
            } catch (\Throwable $e) {
                return json_encode(['status' => 'fail', 'message' => 'Search failed.', 'results' => []]);
            }

        case 'backup_user_detail':
            $backupUserId = (int) ($req['backup_user_id'] ?? 0);
            if ($backupUserId <= 0) {
                return json_encode(['status' => 'fail', 'message' => 'backup_user_id is required']);
            }
            try {
                $detail = Ms365AdminTenantExportService::getBackupUserDetail($backupUserId);

                return json_encode(['status' => 'success', 'detail' => $detail]);
            } catch (\Throwable $e) {
                return json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
            }

        case 'export_credentials':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                return json_encode(['status' => 'fail', 'message' => 'POST required']);
            }
            $token = (string) ($req['token'] ?? '');
            if ($token === '' || !check_token('plain', $token)) {
                http_response_code(403);

                return json_encode(['status' => 'fail', 'message' => 'Invalid or missing CSRF token.']);
            }
            if (empty($req['confirm'])) {
                return json_encode(['status' => 'fail', 'message' => 'Confirmation required.']);
            }

            $backupUserId = (int) ($req['backup_user_id'] ?? 0);
            if ($backupUserId <= 0) {
                return json_encode(['status' => 'fail', 'message' => 'backup_user_id is required']);
            }

            $adminId = 0;
            try {
                $adminId = (int) ($_SESSION['adminid'] ?? 0);
            } catch (\Throwable $_) {
            }

            try {
                $export = Ms365AdminTenantExportService::exportForBackupUser($backupUserId, $adminId);

                return json_encode(['status' => 'success', 'export' => $export]);
            } catch (\Throwable $e) {
                return json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
            }

        default:
            return json_encode(['status' => 'fail', 'message' => 'unknown_action']);
    }
}

/** @param array<string, mixed> $vars */
function ms365backup_admin_tenant_export_render(array $vars): void
{
    $moduleUrl = ($_SERVER['PHP_SELF'] ?? 'addonmodules.php') . '?module=ms365backup&action=tenant_export';
    $csrfToken = generate_token('plain');

    echo '<style>
    .ms365-export-wrap { padding-top: .5rem; max-width: 960px; overflow: visible; }
    .ms365-export-card { border: 1px solid #dee2e6; border-radius: 10px; background: #fff; padding: 1rem 1.1rem; margin-bottom: 1rem; overflow: visible; }
    .cs-typeahead { position: relative; overflow: visible; z-index: 1; }
    .cs-typeahead-input-wrap { position: relative; }
    .cs-typeahead-input-wrap .cs-typeahead-spinner { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: none; }
    .cs-typeahead-input-wrap.is-loading .cs-typeahead-spinner { display: inline-block; }
    .cs-typeahead-results { position: absolute; z-index: 1050; top: 100%; left: 0; right: 0; max-height: 360px; overflow-y: auto; background: #fff; border: 1px solid #ced4da; border-top: 0; border-radius: 0 0 6px 6px; box-shadow: 0 6px 18px rgba(0,0,0,.10); display: none; }
    .cs-typeahead-results.is-open { display: block; }
    .cs-typeahead-item { padding: .55rem .85rem; border-bottom: 1px solid #f1f3f5; cursor: pointer; }
    .cs-typeahead-item:hover, .cs-typeahead-item.is-active { background: #f1f8ff; }
    .cs-typeahead-item .cs-ti-line1 { font-weight: 600; font-size: .9rem; }
    .cs-typeahead-item .cs-ti-line2 { font-size: .78rem; color: #6c757d; margin-top: 2px; }
  .ms365-export-detail { display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e9ecef; }
    .ms365-export-detail.is-visible { display: block; }
    .ms365-export-output { display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; }
    .ms365-export-output.is-visible { display: block; }
    .ms365-export-field { margin-bottom: .75rem; }
    .ms365-export-field label { display: block; font-size: .75rem; font-weight: 600; color: #495057; margin-bottom: .2rem; }
    .ms365-export-field textarea, .ms365-export-field input { width: 100%; font-family: monospace; font-size: .82rem; }
    </style>';

    echo '<div class="ms365-export-wrap">';

    echo '<div class="alert alert-warning">';
    echo '<strong>Dev / testing only.</strong> Export includes a <strong>plaintext app secret</strong>. ';
    echo 'Store securely; do not share. Use exported values in the job wizard <strong>Manual connect</strong> form (Test → Save). ';
    echo 'OAuth connections export the platform Entra app credentials for the tenant; saving manually stores as <code>customer_app</code>.';
    echo '</div>';

    echo '<div class="ms365-export-card">';
    echo '<h3 style="margin:0 0 .75rem;font-size:1.05rem;"><i class="fa fa-download"></i> Export tenant connection</h3>';
    echo '<label class="form-label" for="exportUserInput">Find backup user</label>';
    echo '<div class="cs-typeahead" id="exportUserSearch">';
    echo '<div class="cs-typeahead-input-wrap">';
    echo '<input type="text" class="form-control" id="exportUserInput" placeholder="Search by backup username, client email, name, or ID..." autocomplete="off">';
    echo '<i class="fas fa-spinner fa-spin cs-typeahead-spinner text-muted"></i>';
    echo '</div>';
    echo '<div class="cs-typeahead-results" id="exportUserResults"></div>';
    echo '<small class="text-muted">Type at least 2 characters, then click a result.</small>';
    echo '<input type="hidden" id="exportBackupUserId" value="">';
    echo '</div>';

    echo '<div id="exportDetailPanel" class="ms365-export-detail">';
    echo '<div id="exportDetailContent"><p class="text-muted small">Select a backup user.</p></div>';
    echo '<div id="exportConfirmWrap" style="display:none;margin-top:1rem;">';
    echo '<label class="form-check-label"><input type="checkbox" class="form-check-input" id="exportConfirm"> ';
    echo 'I understand this export includes a plaintext app secret</label>';
    echo '<div class="mt-2">';
    echo '<button type="button" class="btn btn-primary" id="exportBtn" disabled><i class="fa fa-download"></i> Export credentials</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div id="exportOutputPanel" class="ms365-export-output">';
    echo '<h4 style="font-size:1rem;margin:0 0 .75rem;">Exported credentials</h4>';
    echo '<p id="exportNotes" class="small text-muted"></p>';
    echo '<div class="ms365-export-field"><label>REGION</label><input type="text" id="exportRegion" readonly></div>';
    echo '<div class="ms365-export-field"><label>CLIENT_ID</label><input type="text" id="exportClientId" readonly></div>';
    echo '<div class="ms365-export-field"><label>TENANT_ID</label><input type="text" id="exportTenantId" readonly></div>';
    echo '<div class="ms365-export-field"><label>APP_SECRET</label><textarea id="exportAppSecret" rows="2" readonly></textarea></div>';
    echo '<div class="mt-2">';
    echo '<button type="button" class="btn btn-default btn-sm" id="exportCopyJsonBtn"><i class="fa fa-copy"></i> Copy JSON</button> ';
    echo '<button type="button" class="btn btn-default btn-sm" id="exportDownloadBtn"><i class="fa fa-file"></i> Download JSON</button>';
    echo '</div>';
    echo '<textarea id="exportJsonRaw" rows="6" readonly style="width:100%;margin-top:.75rem;font-family:monospace;font-size:.75rem;display:none;"></textarea>';
    echo '</div>';

    echo '</div></div>';

    $moduleUrlJs = json_encode($moduleUrl, JSON_UNESCAPED_SLASHES);
    $tokenJs = json_encode($csrfToken);

    echo <<<HTML
<script>
(function() {
    var moduleUrl = {$moduleUrlJs};
    var exportToken = {$tokenJs};
    var selectedUserId = 0;
    var lastExport = null;
    var searchTimer = null;

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function fetchJson(params) {
        var body = new URLSearchParams(params);
        return fetch(moduleUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function(r) { return r.json(); });
    }

    function renderResults(results) {
        var el = document.getElementById('exportUserResults');
        if (!results || !results.length) {
            el.innerHTML = '<div class="cs-typeahead-empty">No backup users found.</div>';
            el.classList.add('is-open');
            return;
        }
        el.innerHTML = results.map(function(r) {
            var clientLabel = r.client_name || r.client_company || r.client_email || ('Client #' + r.client_id);
            var conn = r.connection_status || 'none';
            return '<div class="cs-typeahead-item" data-id="' + r.backup_user_id + '">' +
                '<div class="cs-ti-line1">' + escapeHtml(r.username) + ' <span class="text-muted">#' + r.backup_user_id + '</span></div>' +
                '<div class="cs-ti-line2">' + escapeHtml(clientLabel) + ' · client #' + r.client_id + ' · ' + escapeHtml(conn) + '</div></div>';
        }).join('');
        el.classList.add('is-open');
        el.querySelectorAll('.cs-typeahead-item').forEach(function(item) {
            item.addEventListener('click', function() {
                selectUser(parseInt(item.getAttribute('data-id'), 10));
            });
        });
    }

    function selectUser(id) {
        selectedUserId = id;
        document.getElementById('exportBackupUserId').value = String(id);
        document.getElementById('exportUserResults').classList.remove('is-open');
        document.getElementById('exportOutputPanel').classList.remove('is-visible');
        lastExport = null;
        loadDetail(id);
    }

    function loadDetail(id) {
        var panel = document.getElementById('exportDetailPanel');
        var content = document.getElementById('exportDetailContent');
        panel.classList.add('is-visible');
        content.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading…</p>';
        fetchJson({ ms365_action: 'backup_user_detail', backup_user_id: String(id) }).then(function(res) {
            if (!res || res.status !== 'success') {
                content.innerHTML = '<div class="alert alert-danger">' + escapeHtml((res && res.message) || 'Failed') + '</div>';
                document.getElementById('exportConfirmWrap').style.display = 'none';
                return;
            }
            var d = res.detail || {};
            var bu = d.backup_user || {};
            var t = d.tenant;
            var p = d.credentials_preview || {};
            var html = '<table class="table table-condensed table-bordered" style="font-size:.85rem;">';
            html += '<tr><th>Backup user</th><td>' + escapeHtml(bu.username) + ' #' + bu.id + '</td></tr>';
            html += '<tr><th>Client ID</th><td>' + bu.client_id + '</td></tr>';
            if (t) {
                html += '<tr><th>Tenant record</th><td>#' + t.id + '</td></tr>';
                html += '<tr><th>Status</th><td>' + escapeHtml(t.connection_status) + '</td></tr>';
                html += '<tr><th>Auth mode</th><td>' + escapeHtml(t.connection_auth_mode) + '</td></tr>';
                html += '<tr><th>Azure tenant</th><td><code>' + escapeHtml(t.azure_tenant_id || '—') + '</code></td></tr>';
            } else {
                html += '<tr><th>Tenant</th><td class="text-danger">No tenant record</td></tr>';
            }
            html += '<tr><th>Preview client_id</th><td><code>' + escapeHtml(p.client_id || '—') + '</code></td></tr>';
            html += '<tr><th>Preview tenant_id</th><td><code>' + escapeHtml(p.tenant_id || '—') + '</code></td></tr>';
            html += '<tr><th>Region</th><td>' + escapeHtml(p.region || '—') + '</td></tr>';
            html += '<tr><th>Has secret</th><td>' + (p.has_secret ? 'yes' : 'no') + '</td></tr>';
            html += '</table>';
            if (d.is_production_server) {
                html += '<div class="alert alert-warning small">Production server — export only for authorized debugging.</div>';
            }
            if (!d.can_export && d.export_block_reason) {
                html += '<div class="alert alert-danger small">' + escapeHtml(d.export_block_reason) + '</div>';
            }
            content.innerHTML = html;
            var wrap = document.getElementById('exportConfirmWrap');
            wrap.style.display = d.can_export ? 'block' : 'none';
            document.getElementById('exportConfirm').checked = false;
            document.getElementById('exportBtn').disabled = true;
        });
    }

    function showExport(exp) {
        lastExport = exp;
        var mc = exp.manual_connect || {};
        document.getElementById('exportRegion').value = mc.region || '';
        document.getElementById('exportClientId').value = mc.client_id || '';
        document.getElementById('exportTenantId').value = mc.tenant_id || '';
        document.getElementById('exportAppSecret').value = mc.app_secret || '';
        document.getElementById('exportNotes').textContent = exp.notes || '';
        var json = JSON.stringify(exp, null, 2);
        document.getElementById('exportJsonRaw').value = json;
        document.getElementById('exportOutputPanel').classList.add('is-visible');
    }

    document.getElementById('exportUserInput').addEventListener('input', function() {
        var q = this.value.trim();
        clearTimeout(searchTimer);
        if (q.length < 2) {
            document.getElementById('exportUserResults').classList.remove('is-open');
            return;
        }
        document.querySelector('#exportUserSearch .cs-typeahead-input-wrap').classList.add('is-loading');
        searchTimer = setTimeout(function() {
            fetchJson({ ms365_action: 'backup_user_search', q: q }).then(function(res) {
                document.querySelector('#exportUserSearch .cs-typeahead-input-wrap').classList.remove('is-loading');
                if (res && res.status === 'success') renderResults(res.results || []);
            });
        }, 250);
    });

    document.getElementById('exportConfirm').addEventListener('change', function() {
        document.getElementById('exportBtn').disabled = !this.checked;
    });

    document.getElementById('exportBtn').addEventListener('click', function() {
        if (!selectedUserId) return;
        var btn = this;
        btn.disabled = true;
        fetchJson({
            ms365_action: 'export_credentials',
            token: exportToken,
            backup_user_id: String(selectedUserId),
            confirm: '1'
        }).then(function(res) {
            btn.disabled = !document.getElementById('exportConfirm').checked;
            if (!res || res.status !== 'success') {
                alert((res && res.message) || 'Export failed');
                return;
            }
            showExport(res.export);
        });
    });

    document.getElementById('exportCopyJsonBtn').addEventListener('click', function() {
        if (!lastExport) return;
        var text = JSON.stringify(lastExport, null, 2);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
        } else {
            var ta = document.getElementById('exportJsonRaw');
            ta.style.display = 'block';
            ta.select();
            document.execCommand('copy');
            ta.style.display = 'none';
        }
    });

    document.getElementById('exportDownloadBtn').addEventListener('click', function() {
        if (!lastExport) return;
        var blob = new Blob([JSON.stringify(lastExport, null, 2)], { type: 'application/json' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'ms365-tenant-export-' + (lastExport.backup_user_id || 'user') + '.json';
        a.click();
        URL.revokeObjectURL(a.href);
    });

    document.addEventListener('click', function(e) {
        if (!document.getElementById('exportUserSearch').contains(e.target)) {
            document.getElementById('exportUserResults').classList.remove('is-open');
        }
    });
})();
</script>
HTML;
}
