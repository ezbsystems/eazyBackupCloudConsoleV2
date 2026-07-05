<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/ms365backup_autoload.php';

use Ms365Backup\Ms365AdminCustomerSearch;
use Ms365Backup\Ms365AdminProvisionService;

/**
 * Admin: provision Microsoft 365 Backup for a WHMCS client (unified path).
 */
function ms365backup_admin_provision(array $vars): void
{
    if (isset($_REQUEST['ms365_action'])) {
        header('Content-Type: application/json');
        try {
            echo ms365backup_admin_provision_ajax($_REQUEST);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
        }
        exit;
    }

    ms365backup_admin_provision_render($vars);
}

/** @param array<string, mixed> $req */
function ms365backup_admin_provision_ajax(array $req): string
{
    $action = (string) ($req['ms365_action'] ?? '');

    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    switch ($action) {
        case 'customer_search':
            $query = isset($req['q']) ? (string) $req['q'] : '';
            if (trim($query) === '' || strlen(trim($query)) < 2) {
                return json_encode(['status' => 'success', 'results' => []]);
            }
            try {
                $results = Ms365AdminCustomerSearch::search($query, 15);

                return json_encode(['status' => 'success', 'results' => $results]);
            } catch (\Throwable $e) {
                try {
                    logModuleCall('ms365backup', 'admin_provision_search_fail', ['q' => $query], $e->getMessage());
                } catch (\Throwable $_) {
                }

                return json_encode(['status' => 'fail', 'message' => 'Customer search failed.', 'results' => []]);
            }

        case 'preview':
            $clientId = (int) ($req['client_id'] ?? 0);
            if ($clientId <= 0) {
                return json_encode(['status' => 'fail', 'message' => 'client_id is required']);
            }
            try {
                $preview = Ms365AdminProvisionService::preview($clientId);

                return json_encode(['status' => 'success', 'preview' => $preview]);
            } catch (\Throwable $e) {
                return json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
            }

        case 'provision':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                return json_encode(['status' => 'fail', 'message' => 'POST required']);
            }
            $token = (string) ($req['token'] ?? '');
            if ($token === '' || !check_token('plain', $token)) {
                http_response_code(403);

                return json_encode(['status' => 'fail', 'message' => 'Invalid or missing CSRF token.']);
            }

            $clientId = (int) ($req['client_id'] ?? 0);
            $username = trim((string) ($req['username'] ?? ''));
            $password = (string) ($req['password'] ?? '');
            $passwordConfirm = (string) ($req['password_confirm'] ?? '');
            $existingClient = !empty($req['existing_client']);

            $errors = [];
            if ($clientId <= 0) {
                $errors['client_id'] = 'Select a client first.';
            }
            if ($username === '' || !preg_match('/^[A-Za-z0-9_.-]{6,}$/', $username)) {
                $errors['username'] = 'Backup username must be at least 6 characters (a-z, A-Z, 0-9, _, ., -).';
            }
            if ($password === '') {
                $errors['password'] = 'Backup password is required.';
            } elseif (strlen($password) < 8) {
                $errors['password'] = 'Backup password must be at least 8 characters.';
            }
            if ($passwordConfirm === '' || $password !== $passwordConfirm) {
                $errors['password_confirm'] = 'Password confirmation does not match.';
            }
            if ($errors !== []) {
                return json_encode(['status' => 'fail', 'message' => 'Validation failed.', 'errors' => $errors]);
            }

            $adminId = 0;
            try {
                $adminId = (int) ($_SESSION['adminid'] ?? 0);
            } catch (\Throwable $_) {
            }

            try {
                $result = Ms365AdminProvisionService::provision(
                    $clientId,
                    $username,
                    $password,
                    $existingClient,
                    $adminId
                );

                return json_encode(['status' => 'success', 'result' => $result]);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $fieldErrors = [];
                if (stripos($msg, 'username') !== false && stripos($msg, 'taken') !== false) {
                    $fieldErrors['username'] = $msg;
                }

                return json_encode([
                    'status' => 'fail',
                    'message' => $msg,
                    'errors' => $fieldErrors !== [] ? $fieldErrors : null,
                ]);
            }

        default:
            return json_encode(['status' => 'fail', 'message' => 'unknown_action']);
    }
}

/** @param array<string, mixed> $vars */
function ms365backup_admin_provision_render(array $vars): void
{
    $moduleUrl = ($_SERVER['PHP_SELF'] ?? 'addonmodules.php') . '?module=ms365backup&action=provision';
    $csrfToken = generate_token('plain');
    $unifiedEnabled = Ms365AdminProvisionService::isUnifiedEnabled();
    $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

    echo '<style>
    .ms365-prov-wrap { padding-top: .5rem; max-width: 920px; overflow: visible; }
    .ms365-prov-intro { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
    .ms365-prov-card { border: 1px solid #dee2e6; border-radius: 10px; background: #fff; padding: 1rem 1.1rem 1.1rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); margin-bottom: 1rem; overflow: visible; }
    .cs-typeahead { position: relative; overflow: visible; z-index: 1; }
    .cs-typeahead-input-wrap { position: relative; }
    .cs-typeahead-input-wrap .cs-typeahead-spinner { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: none; }
    .cs-typeahead-input-wrap.is-loading .cs-typeahead-spinner { display: inline-block; }
    .cs-typeahead-results { position: absolute; z-index: 1050; top: 100%; left: 0; right: 0; max-height: 360px; overflow-y: auto; background: #fff; border: 1px solid #ced4da; border-top: 0; border-radius: 0 0 6px 6px; box-shadow: 0 6px 18px rgba(0,0,0,.10); display: none; }
    .cs-typeahead-results.is-open { display: block; }
    .cs-typeahead-empty { padding: .65rem .9rem; color: #6c757d; font-size: .85rem; }
    .cs-typeahead-item { padding: .55rem .85rem; border-bottom: 1px solid #f1f3f5; cursor: pointer; }
    .cs-typeahead-item:last-child { border-bottom: 0; }
    .cs-typeahead-item:hover, .cs-typeahead-item.is-active { background: #f1f8ff; }
    .cs-typeahead-item .cs-ti-line1 { font-weight: 600; color: #212529; font-size: .9rem; }
    .cs-typeahead-item .cs-ti-line2 { font-size: .78rem; color: #6c757d; margin-top: 2px; }
    .cs-typeahead-item .cs-ti-id { color: #adb5bd; font-weight: 500; }
    .cs-typeahead-item .cs-svc-pill { display: inline-block; margin: 4px 4px 0 0; padding: 2px 8px; background: #e7f5ff; color: #1864ab; border-radius: 999px; font-size: .72rem; }
    .cs-selected-pill { display: inline-flex; align-items: center; gap: .5rem; background: #e7f5ff; color: #1864ab; padding: 4px 10px; border-radius: 999px; font-size: .82rem; margin-top: .5rem; }
    .cs-selected-pill .cs-clear { cursor: pointer; opacity: .6; border: 0; background: none; padding: 0; color: inherit; }
    .cs-selected-pill .cs-clear:hover { opacity: 1; }
    .ms365-preview-panel { display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e9ecef; }
    .ms365-preview-panel.is-visible { display: block; }
    .ms365-blocker { color: #c92a2a; font-size: .85rem; }
    .ms365-warning { color: #8a6d00; font-size: .85rem; }
    .ms365-success-panel { display: none; background: #d1e7dd; border: 1px solid #a3cfbb; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
    .ms365-success-panel.is-visible { display: block; }
    .ms365-mini-table { width: 100%; font-size: .82rem; margin: .5rem 0; }
    .ms365-mini-table th, .ms365-mini-table td { padding: 4px 8px; border-bottom: 1px solid #eee; text-align: left; }
    </style>';

    echo '<div class="ms365-prov-wrap">';

    echo '<div class="ms365-prov-intro">';
    echo '<h3 style="margin:0 0 .5rem;font-size:1.1rem;"><i class="fa fa-user-plus"></i> Provision Microsoft 365 Backup</h3>';
    echo '<p class="text-muted small mb-0">Provision the unified <strong>e3 Backup User</strong> product with MS365 intent for a WHMCS client. ';
    echo 'Under unified billing, MS365 is provisioned on the <strong>e3 Backup User</strong> service (not a separate legacy MS365 product row). ';
    echo 'Does not require the client portal password. After provision, the client (or admin via impersonate) must complete Entra connect, inventory, and first backup.</p>';
    echo '</div>';

    if (!$unifiedEnabled) {
        echo '<div class="alert alert-danger"><strong>Unified provisioning is disabled.</strong> Enable ';
        echo '<code>e3_backup_user_unified_enabled</code> in the Cloud Storage addon settings before using this tool.</div>';
    }

    echo '<div class="ms365-prov-card">';
    echo '<label class="form-label" for="provCustomerInput">Find customer</label>';
    echo '<div class="cs-typeahead" id="provCustomerSearch">';
    echo '<div class="cs-typeahead-input-wrap">';
    echo '<input type="text" class="form-control" id="provCustomerInput" placeholder="Search by name, company, email, or client ID..." autocomplete="off"' . ($unifiedEnabled ? '' : ' disabled') . '>';
    echo '<i class="fas fa-spinner fa-spin cs-typeahead-spinner text-muted"></i>';
    echo '</div>';
    echo '<div class="cs-typeahead-results" id="provCustomerResults"></div>';
    echo '<small class="text-muted">Type at least 2 characters, then click a result.</small>';
    echo '<div id="provSelectedPillWrap"></div>';
    echo '<input type="hidden" id="provClientId" value="">';
    echo '</div>';

    echo '<div id="provPreviewPanel" class="ms365-preview-panel">';
    echo '<h4 style="font-size:1rem;margin-bottom:.75rem;">Client preview</h4>';
    echo '<div id="provPreviewContent"><p class="text-muted small">Select a client to load preview.</p></div>';

    echo '<div id="provFormSection" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid #e9ecef;">';
    echo '<h4 style="font-size:1rem;margin-bottom:.75rem;">Provision backup user</h4>';
    echo '<form id="provForm">';
    echo '<div class="row g-3">';
    echo '<div class="col-md-6"><label class="form-label" for="provUsername">Backup username</label>';
    echo '<input type="text" class="form-control" id="provUsername" name="username" autocomplete="off" placeholder="e.g. acme-backup"></div>';
    echo '<div class="col-md-6"><label class="form-label">&nbsp;</label>';
    echo '<div><label class="form-check-label"><input type="checkbox" class="form-check-input" id="provExistingClient" name="existing_client" value="1" checked> Existing WHMCS customer (trial anchoring)</label></div></div>';
    echo '<div class="col-md-6"><label class="form-label" for="provPassword">Backup password</label>';
    echo '<input type="password" class="form-control" id="provPassword" name="password" autocomplete="new-password"></div>';
    echo '<div class="col-md-6"><label class="form-label" for="provPasswordConfirm">Confirm password</label>';
    echo '<input type="password" class="form-control" id="provPasswordConfirm" name="password_confirm" autocomplete="new-password"></div>';
    echo '</div>';
    echo '<div class="mt-2"><button type="button" class="btn btn-sm btn-default" id="provGenPasswordBtn"><i class="fa fa-random"></i> Generate password</button></div>';
    echo '<div id="provFormErrors" class="text-danger small mt-2" style="display:none;"></div>';
    echo '<div class="mt-3">';
    echo '<button type="submit" class="btn btn-primary" id="provSubmitBtn"' . ($unifiedEnabled ? '' : ' disabled') . '><i class="fa fa-check"></i> Provision MS365 Backup</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    echo '<div id="provSuccessPanel" class="ms365-success-panel">';
    echo '<h4 style="margin:0 0 .5rem;font-size:1rem;"><i class="fa fa-check-circle"></i> Provisioned successfully</h4>';
    echo '<div id="provSuccessContent"></div>';
    echo '</div>';

    echo '</div></div>';

    $moduleUrlJs = json_encode($moduleUrl, JSON_UNESCAPED_SLASHES);
    $tokenJs = json_encode($csrfToken);
    $unifiedJs = $unifiedEnabled ? 'true' : 'false';

    echo <<<HTML
<script>
(function() {
    var moduleUrl = {$moduleUrlJs};
    var provisionToken = {$tokenJs};
    var unifiedEnabled = {$unifiedJs};
    var selectedClient = null;

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function getStatusColor(st) {
        st = (st || '').toLowerCase();
        if (st === 'active') return 'success';
        if (st === 'suspended') return 'warning';
        if (st === 'closed' || st === 'cancelled' || st === 'terminated') return 'secondary';
        return 'info';
    }

    function initCustomerTypeahead() {
        var root = document.getElementById('provCustomerSearch');
        var input = document.getElementById('provCustomerInput');
        var results = document.getElementById('provCustomerResults');
        var wrap = root ? root.querySelector('.cs-typeahead-input-wrap') : null;
        if (!root || !input || !results || !wrap) return;

        var debounceTimer = null;
        var lastQuery = '';
        var activeIndex = -1;
        var currentItems = [];

        input.addEventListener('input', function() {
            if (debounceTimer) clearTimeout(debounceTimer);
            var q = input.value.trim();
            if (q.length < 2) {
                results.classList.remove('is-open');
                results.innerHTML = '';
                currentItems = [];
                return;
            }
            debounceTimer = setTimeout(function() { runSearch(q); }, 220);
        });

        input.addEventListener('keydown', function(e) {
            if (!results.classList.contains('is-open')) return;
            var nodes = Array.prototype.slice.call(results.querySelectorAll('.cs-typeahead-item'));
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(nodes.length - 1, activeIndex + 1);
                applyActive(nodes);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(0, activeIndex - 1);
                applyActive(nodes);
            } else if (e.key === 'Enter') {
                if (activeIndex >= 0 && nodes[activeIndex]) {
                    e.preventDefault();
                    pickItem(currentItems[activeIndex]);
                }
            } else if (e.key === 'Escape') {
                results.classList.remove('is-open');
            }
        });

        document.addEventListener('click', function(e) {
            if (!root.contains(e.target)) results.classList.remove('is-open');
        });

        function applyActive(nodes) {
            nodes.forEach(function(n, i) { n.classList.toggle('is-active', i === activeIndex); });
        }

        function runSearch(q) {
            if (q === lastQuery) return;
            lastQuery = q;
            wrap.classList.add('is-loading');
            var params = new URLSearchParams();
            params.append('ms365_action', 'customer_search');
            params.append('q', q);
            fetch(moduleUrl + '&' + params.toString(), { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    wrap.classList.remove('is-loading');
                    if (j.status !== 'success') {
                        renderEmpty(j.message || 'Search failed.');
                        return;
                    }
                    currentItems = j.results || [];
                    renderResults(currentItems);
                })
                .catch(function(err) {
                    wrap.classList.remove('is-loading');
                    renderEmpty('Search error: ' + err.message);
                });
        }

        function renderEmpty(msg) {
            results.innerHTML = '<div class="cs-typeahead-empty">' + escapeHtml(msg) + '</div>';
            results.classList.add('is-open');
            currentItems = [];
            activeIndex = -1;
        }

        function renderResults(items) {
            if (!items.length) { renderEmpty('No matching customers.'); return; }
            var html = '';
            items.forEach(function(it, idx) {
                var line1 = '';
                if (it.name) line1 += escapeHtml(it.name);
                if (it.companyname) line1 += (line1 ? ' &middot; ' : '') + escapeHtml(it.companyname);
                if (!line1) line1 = escapeHtml(it.email || ('Client #' + it.id));
                line1 += ' <span class="cs-ti-id">#' + it.id + '</span>';
                var status = it.status ? '<span class="badge bg-' + getStatusColor(it.status) + ' ms-1" style="font-size:.65rem;">' + escapeHtml(it.status) + '</span>' : '';
                var line2 = '<span>' + escapeHtml(it.email || '—') + '</span>' + status;
                var pills = '';
                if (it.services && it.services.length) {
                    it.services.forEach(function(svc) {
                        pills += '<span class="cs-svc-pill">' + escapeHtml(svc.product || ('Product #' + svc.packageid)) + ' #' + svc.id + '</span>';
                    });
                }
                html += '<div class="cs-typeahead-item" data-index="' + idx + '"><div class="cs-ti-line1">' + line1 + '</div><div class="cs-ti-line2">' + line2 + '</div>' + (pills ? '<div>' + pills + '</div>' : '') + '</div>';
            });
            results.innerHTML = html;
            results.classList.add('is-open');
            activeIndex = -1;
            Array.prototype.forEach.call(results.querySelectorAll('.cs-typeahead-item'), function(el) {
                el.addEventListener('click', function() {
                    var idx = parseInt(el.getAttribute('data-index'), 10);
                    if (!isNaN(idx) && currentItems[idx]) pickItem(currentItems[idx]);
                });
            });
        }

        function pickItem(item) {
            results.classList.remove('is-open');
            input.value = '';
            selectClient(item);
        }
    }

    function selectClient(item) {
        selectedClient = item;
        document.getElementById('provClientId').value = String(item.id);
        var wrap = document.getElementById('provSelectedPillWrap');
        var label = (item.name || item.companyname || item.email || ('Client #' + item.id));
        wrap.innerHTML = '<span class="cs-selected-pill">' + escapeHtml(label) + ' <span class="text-muted">#' + item.id + '</span> <button type="button" class="cs-clear" id="provClearClient" title="Clear">&times;</button></span>';
        document.getElementById('provClearClient').addEventListener('click', clearClient);
        document.getElementById('provPreviewPanel').classList.add('is-visible');
        document.getElementById('provSuccessPanel').classList.remove('is-visible');
        loadPreview(item.id);
    }

    function clearClient() {
        selectedClient = null;
        document.getElementById('provClientId').value = '';
        document.getElementById('provSelectedPillWrap').innerHTML = '';
        document.getElementById('provPreviewPanel').classList.remove('is-visible');
        document.getElementById('provFormSection').style.display = 'none';
        document.getElementById('provSuccessPanel').classList.remove('is-visible');
    }

    function loadPreview(clientId) {
        var content = document.getElementById('provPreviewContent');
        content.innerHTML = '<p class="text-muted small"><i class="fas fa-spinner fa-spin"></i> Loading preview…</p>';
        var params = new URLSearchParams();
        params.append('ms365_action', 'preview');
        params.append('client_id', String(clientId));
        fetch(moduleUrl + '&' + params.toString(), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (j.status !== 'success') {
                    content.innerHTML = '<p class="text-danger">' + escapeHtml(j.message || 'Preview failed') + '</p>';
                    document.getElementById('provFormSection').style.display = 'none';
                    return;
                }
                renderPreview(j.preview || {});
            })
            .catch(function(err) {
                content.innerHTML = '<p class="text-danger">' + escapeHtml(err.message) + '</p>';
            });
    }

    function renderPreview(p) {
        var html = '';
        var c = p.client || {};
        html += '<p><strong>' + escapeHtml(c.name || c.companyname || c.email || ('Client #' + p.client_id)) + '</strong> ';
        html += '<span class="badge bg-' + getStatusColor(c.status) + '">' + escapeHtml(c.status || '—') + '</span></p>';
        html += '<p class="text-muted small mb-1">' + escapeHtml(c.email || '') + '</p>';

        if (p.blockers && p.blockers.length) {
            html += '<ul class="ms365-blocker">';
            p.blockers.forEach(function(b) { html += '<li>' + escapeHtml(b) + '</li>'; });
            html += '</ul>';
        }
        if (p.warnings && p.warnings.length) {
            html += '<ul class="ms365-warning">';
            p.warnings.forEach(function(w) { html += '<li>' + escapeHtml(w) + '</li>'; });
            html += '</ul>';
        }

        var cs = p.cloud_storage || {};
        html += '<p class="small mb-1"><strong>Cloud Storage:</strong> ' + (cs.has_active ? 'active service on file' : '<span class="ms365-warning">will be auto-ordered</span>') + '</p>';

        if (p.e3_backup_user && p.e3_backup_user.services && p.e3_backup_user.services.length) {
            html += '<p class="small mb-1"><strong>e3 Backup User services:</strong></p><table class="ms365-mini-table"><tr><th>ID</th><th>Username</th><th>Status</th></tr>';
            p.e3_backup_user.services.forEach(function(s) {
                html += '<tr><td>' + s.id + '</td><td>' + escapeHtml(s.username) + '</td><td>' + escapeHtml(s.domainstatus) + '</td></tr>';
            });
            html += '</table>';
        }

        if (p.backup_users && p.backup_users.length) {
            html += '<p class="small mb-1"><strong>Backup users:</strong></p><table class="ms365-mini-table"><tr><th>ID</th><th>Username</th><th>Type</th><th>Status</th></tr>';
            p.backup_users.forEach(function(u) {
                html += '<tr><td>' + u.id + '</td><td>' + escapeHtml(u.username) + '</td><td>' + escapeHtml(u.backup_type) + '</td><td>' + escapeHtml(u.status) + '</td></tr>';
            });
            html += '</table>';
        }

        if (p.ms365_tenants && p.ms365_tenants.length) {
            html += '<p class="small mb-1"><strong>MS365 tenant records:</strong></p><table class="ms365-mini-table"><tr><th>Backup user</th><th>Status</th><th>Tenant ID</th></tr>';
            p.ms365_tenants.forEach(function(t) {
                html += '<tr><td>' + t.backup_user_id + '</td><td>' + escapeHtml(t.connection_status) + '</td><td>' + escapeHtml(t.azure_tenant_id || '—') + '</td></tr>';
            });
            html += '</table>';
        }

        document.getElementById('provPreviewContent').innerHTML = html;
        var canProvision = unifiedEnabled && p.can_provision;
        document.getElementById('provFormSection').style.display = canProvision ? 'block' : 'none';
        document.getElementById('provSubmitBtn').disabled = !canProvision;
    }

    function generatePassword() {
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        var pass = '';
        try {
            var arr = new Uint8Array(16);
            window.crypto.getRandomValues(arr);
            for (var i = 0; i < 16; i++) pass += chars[arr[i] % chars.length];
        } catch (e) {
            for (var j = 0; j < 16; j++) pass += chars[Math.floor(Math.random() * chars.length)];
        }
        document.getElementById('provPassword').value = pass;
        document.getElementById('provPasswordConfirm').value = pass;
    }

    function showFormErrors(errors, general) {
        var el = document.getElementById('provFormErrors');
        var msgs = [];
        if (general) msgs.push(general);
        if (errors) {
            Object.keys(errors).forEach(function(k) { msgs.push(errors[k]); });
        }
        if (!msgs.length) {
            el.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        el.style.display = 'block';
        el.innerHTML = msgs.map(function(m) { return escapeHtml(m); }).join('<br>');
    }

    function submitProvision(e) {
        e.preventDefault();
        showFormErrors(null, null);
        var clientId = document.getElementById('provClientId').value;
        var body = new URLSearchParams();
        body.append('ms365_action', 'provision');
        body.append('token', provisionToken);
        body.append('client_id', clientId);
        body.append('username', document.getElementById('provUsername').value.trim());
        body.append('password', document.getElementById('provPassword').value);
        body.append('password_confirm', document.getElementById('provPasswordConfirm').value);
        if (document.getElementById('provExistingClient').checked) {
            body.append('existing_client', '1');
        }
        var btn = document.getElementById('provSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Provisioning…';
        fetch(moduleUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> Provision MS365 Backup';
                if (j.status !== 'success') {
                    showFormErrors(j.errors || null, j.message || 'Provision failed');
                    return;
                }
                renderSuccess(j.result || {});
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> Provision MS365 Backup';
                showFormErrors(null, err.message);
            });
    }

    function renderSuccess(r) {
        document.getElementById('provFormSection').style.display = 'none';
        var panel = document.getElementById('provSuccessPanel');
        var html = '<ul class="mb-2">';
        html += '<li><strong>Service ID:</strong> ' + escapeHtml(String(r.service_id || '')) + '</li>';
        html += '<li><strong>Backup user ID:</strong> ' + escapeHtml(String(r.user_id || ''));
        if (r.public_id) html += ' <span class="text-muted">(public: ' + escapeHtml(r.public_id) + ')</span>';
        html += '</li>';
        if (r.redirect) {
            html += '<li><strong>Client redirect:</strong> <code>' + escapeHtml(r.redirect) + '</code></li>';
        }
        html += '</ul>';
        html += '<p class="small text-muted mb-2">Next steps: Entra tenant connect, inventory refresh, and first backup job still happen in the client area. MS365 storage bucket is created at Entra connect.</p>';
        if (r.product_note) {
            html += '<p class="small text-muted mb-2">' + escapeHtml(r.product_note) + '</p>';
        }
        if (r.getting_started_sso_url) {
            html += '<a href="' + escapeHtml(r.getting_started_sso_url) + '" target="_blank" class="btn btn-sm btn-primary"><i class="fa fa-play"></i> Open Getting Started (SSO)</a> ';
        }
        if (r.impersonate_url) {
            html += '<a href="' + escapeHtml(r.impersonate_url) + '" target="_blank" class="btn btn-sm btn-info"><i class="fa fa-user-secret"></i> Impersonate client (Getting Started)</a>';
        }
        document.getElementById('provSuccessContent').innerHTML = html;
        panel.classList.add('is-visible');
        if (r.client_id) loadPreview(r.client_id);
    }

    document.addEventListener('DOMContentLoaded', function() {
        initCustomerTypeahead();
        document.getElementById('provGenPasswordBtn').addEventListener('click', generatePassword);
        document.getElementById('provForm').addEventListener('submit', submitProvision);
    });
})();
</script>
HTML;
}
