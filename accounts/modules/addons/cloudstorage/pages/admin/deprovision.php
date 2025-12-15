<?php

require_once __DIR__ . '/../../lib/Admin/DeprovisionHelper.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\DeprovisionHelper;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

/**
 * Admin Deprovision Page
 * Allows WHMCS admins to preview and queue deprovision jobs for Cloud Storage customers.
 */
function cloudstorage_admin_deprovision($vars)
{
    // Handle AJAX actions
    // Use a module-specific parameter name to avoid collisions with WHMCS core/admin JS
    if (isset($_REQUEST['cs_action'])) {
        header('Content-Type: application/json');
        try {
            echo handleDeprovisionAjax($_REQUEST);
        } catch (\Throwable $e) {
            // Ensure we always return valid JSON to XHR callers (avoid JSON.parse errors)
            try { logModuleCall('cloudstorage', 'admin_deprovision_ajax_fatal', ['cs_action' => $_REQUEST['cs_action'] ?? null], $e->getMessage()); } catch (\Throwable $_) {}
            http_response_code(500);
            echo json_encode(['status' => 'fail', 'message' => 'Internal error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Get CSRF token
    $csrfToken = generate_token('plain');

    // Get any existing queued jobs for display
    $queuedJobs = Capsule::table('s3_delete_users')
        ->join('s3_users', 's3_delete_users.primary_user_id', '=', 's3_users.id')
        ->select([
            's3_delete_users.*',
            's3_users.username as primary_username',
        ])
        ->orderBy('s3_delete_users.created_at', 'desc')
        ->limit(50)
        ->get();

    // Output the admin page HTML
    generateDeprovisionHTML($csrfToken, $queuedJobs);
}

/**
 * Handle AJAX requests for deprovision operations.
 */
function handleDeprovisionAjax($request)
{
    $action = $request['cs_action'] ?? '';

    switch ($action) {
        case 'lookup':
            return json_encode(handleLookup($request));

        case 'queue':
            return json_encode(handleQueueDeprovision($request));

        default:
            return json_encode(['status' => 'fail', 'message' => 'Unknown action.']);
    }
}

/**
 * Handle user lookup by service ID or username.
 */
function handleLookup($request)
{
    $serviceId = isset($request['service_id']) ? (int) $request['service_id'] : null;
    $username = isset($request['username']) ? trim($request['username']) : null;

    if (empty($serviceId) && empty($username)) {
        return ['status' => 'fail', 'message' => 'Please provide a Service ID or Username.'];
    }

    // Resolve primary user
    $primaryUser = DeprovisionHelper::resolvePrimaryUser(
        $serviceId > 0 ? $serviceId : null,
        !empty($username) ? $username : null
    );

    if ($primaryUser === null) {
        return ['status' => 'fail', 'message' => 'No storage user found for the provided criteria.'];
    }

    // Build deprovision plan
    $plan = DeprovisionHelper::buildDeprovisionPlan($primaryUser->id);

    // Get WHMCS service info
    $service = DeprovisionHelper::getServiceForUsername($primaryUser->username);
    $client = null;
    if ($service && $service->userid) {
        $client = DeprovisionHelper::getClientInfo($service->userid);
    }

    // Format response
    $response = [
        'status' => 'success',
        'primary' => [
            'id' => $plan['primary']->id,
            'username' => $plan['primary']->username,
            'name' => $plan['primary']->name ?? '',
            'ceph_uid' => $plan['primary']->ceph_uid,
            'is_active' => $plan['primary']->is_active ?? 1,
            'tenant_id' => $plan['primary']->tenant_id,
        ],
        'sub_tenants' => [],
        'buckets' => [],
        'service' => null,
        'client' => null,
        'protected_warnings' => $plan['protected_warnings'],
        'can_proceed' => $plan['can_proceed'],
    ];

    // Add sub-tenants
    foreach ($plan['sub_tenants'] as $tenant) {
        $response['sub_tenants'][] = [
            'id' => $tenant->id,
            'username' => $tenant->username,
            'name' => $tenant->name ?? '',
            'ceph_uid' => $tenant->ceph_uid,
            'is_active' => $tenant->is_active ?? 1,
        ];
    }

    // Add buckets
    foreach ($plan['buckets'] as $bucket) {
        $response['buckets'][] = [
            'id' => $bucket->id,
            'name' => $bucket->name,
            'user_id' => $bucket->user_id,
            'is_active' => $bucket->is_active,
            'object_lock_enabled' => $bucket->object_lock_enabled ?? 0,
            'versioning' => $bucket->versioning ?? 'off',
        ];
    }

    // Add service info
    if ($service) {
        $response['service'] = [
            'id' => $service->id,
            'userid' => $service->userid,
            'username' => $service->username,
            'domainstatus' => $service->domainstatus,
            'regdate' => $service->regdate,
        ];
    }

    // Add client info
    if ($client) {
        $response['client'] = [
            'id' => $client->id,
            'name' => trim($client->firstname . ' ' . $client->lastname),
            'companyname' => $client->companyname,
            'email' => $client->email,
            'status' => $client->status,
        ];
    }

    return $response;
}

/**
 * Handle queue deprovision action.
 */
function handleQueueDeprovision($request)
{
    // Verify CSRF token (WHMCS admin-safe)
    $token = $request['token'] ?? '';
    $tokenOk = false;
    if (function_exists('check_token')) {
        try {
            // check_token('plain', $token) verifies a plain token value directly
            $tokenOk = check_token('plain', $token);
        } catch (\Throwable $e) {
            $tokenOk = false;
        }
    } elseif (function_exists('verify_token')) {
        // Fallback for older contexts (should not be needed in WHMCS admin)
        try {
            $tokenOk = verify_token($token);
        } catch (\Throwable $e) {
            $tokenOk = false;
        }
    }
    if (!$tokenOk) {
        return ['status' => 'fail', 'message' => 'Invalid security token. Please refresh and try again.'];
    }

    $primaryUserId = isset($request['primary_user_id']) ? (int) $request['primary_user_id'] : 0;
    $confirmPhrase = trim($request['confirm_phrase'] ?? '');

    if ($primaryUserId <= 0) {
        return ['status' => 'fail', 'message' => 'Invalid primary user ID.'];
    }

    // Validate primary user exists
    $primaryUser = Capsule::table('s3_users')->where('id', $primaryUserId)->first();
    if (!$primaryUser) {
        return ['status' => 'fail', 'message' => 'Primary user not found.'];
    }

    // Validate confirmation phrase
    $expectedPhrase = 'DEPROVISION ' . strtoupper($primaryUser->username);
    if (strtoupper($confirmPhrase) !== $expectedPhrase) {
        return ['status' => 'fail', 'message' => 'Confirmation phrase does not match. Expected: ' . $expectedPhrase];
    }

    // Get admin ID from session
    $adminId = null;
    if (isset($_SESSION['adminid'])) {
        $adminId = (int) $_SESSION['adminid'];
    }

    // Build plan for snapshot
    $plan = DeprovisionHelper::buildDeprovisionPlan($primaryUserId);

    // Queue the deprovision
    $result = DeprovisionHelper::queueDeprovision($primaryUserId, $adminId, $plan);

    return $result;
}

/**
 * Generate the admin page HTML.
 */
function generateDeprovisionHTML($csrfToken, $queuedJobs)
{
    $moduleUrl = $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=deprovision';
    $jobsJson = json_encode($queuedJobs);

    echo <<<HTML
<style>
    .card { margin-bottom: 1.5rem; }
    .user-card { border-left: 4px solid #0d6efd; }
    .user-card.sub-tenant { border-left-color: #6c757d; margin-left: 20px; }
    .bucket-card { border-left: 4px solid #198754; }
    .bucket-card.object-lock { border-left-color: #dc3545; }
    .bucket-card.inactive { opacity: 0.6; }
    .warning-card { background-color: #fff3cd; border-color: #ffc107; }
    .protected-warning { color: #dc3545; font-weight: bold; }
    .status-badge { font-size: 0.75rem; }
    .preview-section { max-height: 500px; overflow-y: auto; }
    .confirmation-box { background-color: #f8d7da; border: 2px solid #dc3545; padding: 20px; border-radius: 8px; }
    .job-row.queued { background-color: #fff3cd; }
    .job-row.running { background-color: #cfe2ff; }
    .job-row.blocked { background-color: #f8d7da; }
    .job-row.failed { background-color: #f8d7da; }
    .job-row.success { background-color: #d1e7dd; }
</style>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <h1 class="card-title mb-0">
                        <i class="fas fa-user-times text-danger"></i>
                        Deprovision Cloud Storage Customer
                    </h1>
                    <p class="text-muted mb-0 mt-2">
                        Search for a customer by Service ID or Storage Username to preview and queue deprovision.
                        This will delete all buckets, sub-tenants, and the RGW user.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-search"></i> Lookup Customer
                </div>
                <div class="card-body">
                    <form id="lookupForm">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="serviceId" class="form-label">WHMCS Service ID</label>
                                <input type="number" class="form-control" id="serviceId" placeholder="e.g. 12345">
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-center">
                                <span class="text-muted">OR</span>
                            </div>
                            <div class="col-md-5">
                                <label for="username" class="form-label">Storage Username</label>
                                <input type="text" class="form-control" id="username" placeholder="e.g. user@example.com">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" id="lookupBtn">
                                <i class="fas fa-search"></i> Lookup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Section (hidden initially) -->
    <div class="row mb-4" id="previewSection" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-eye"></i> Deprovision Preview</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="closePreviewBtn">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
                <div class="card-body">
                    <!-- Client/Service Info -->
                    <div id="clientInfo" class="mb-4"></div>

                    <!-- Protected Warnings -->
                    <div id="protectedWarnings" class="mb-4"></div>

                    <!-- Preview Content -->
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-users"></i> Users to Delete</h5>
                            <div id="usersList" class="preview-section"></div>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-archive"></i> Buckets to Delete</h5>
                            <div id="bucketsList" class="preview-section"></div>
                        </div>
                    </div>

                    <!-- Confirmation Section -->
                    <div id="confirmationSection" class="mt-4" style="display: none;">
                        <div class="confirmation-box">
                            <h5 class="text-danger"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h5>
                            <p>This action is <strong>irreversible</strong>. All data will be permanently deleted.</p>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="confirmCheck">
                                <label class="form-check-label" for="confirmCheck">
                                    I understand this will permanently delete all buckets, objects, and user accounts shown above.
                                </label>
                            </div>

                            <div class="mb-3">
                                <label for="confirmPhrase" class="form-label">
                                    Type <strong id="expectedPhrase"></strong> to confirm:
                                </label>
                                <input type="text" class="form-control" id="confirmPhrase" autocomplete="off">
                            </div>

                            <button type="button" class="btn btn-danger" id="queueBtn" disabled>
                                <i class="fas fa-trash"></i> Queue Deprovision
                            </button>
                        </div>
                    </div>

                    <!-- Blocked Section (shown when protected resources detected) -->
                    <div id="blockedSection" class="mt-4" style="display: none;">
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-ban"></i> Cannot Proceed</h5>
                            <p>This customer cannot be deprovisioned because protected resources were detected.</p>
                            <div id="blockedReasons"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Jobs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history"></i> Recent Deprovision Jobs
                </div>
                <div class="card-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Primary Username</th>
                                <th>Status</th>
                                <th>Attempts</th>
                                <th>Created</th>
                                <th>Completed</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody id="jobsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // Wrap in IIFE to avoid global scope conflicts with WHMCS admin
    var deprovisionToken = '{$csrfToken}';
    var moduleUrl = '{$moduleUrl}';
    var queuedJobs = {$jobsJson};
    var currentPlan = null;

    document.addEventListener('DOMContentLoaded', function() {
        renderJobsTable();
        setupEventListeners();
    });

function setupEventListeners() {
    // Lookup form submit
    document.getElementById('lookupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        performLookup();
    });

    // Close preview
    document.getElementById('closePreviewBtn').addEventListener('click', function() {
        document.getElementById('previewSection').style.display = 'none';
        currentPlan = null;
    });

    // Confirmation checkbox and phrase
    document.getElementById('confirmCheck').addEventListener('change', validateConfirmation);
    document.getElementById('confirmPhrase').addEventListener('input', validateConfirmation);

    // Queue button
    document.getElementById('queueBtn').addEventListener('click', queueDeprovision);
}

function performLookup() {
    const serviceId = document.getElementById('serviceId').value.trim();
    const username = document.getElementById('username').value.trim();

    if (!serviceId && !username) {
        alert('Please enter a Service ID or Username.');
        return;
    }

    const btn = document.getElementById('lookupBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Looking up...';

    const params = new URLSearchParams();
    params.append('cs_action', 'lookup');
    if (serviceId) params.append('service_id', serviceId);
    if (username) params.append('username', username);

    fetch(moduleUrl + '&' + params.toString())
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i> Lookup';

            if (data.status === 'success') {
                currentPlan = data;
                renderPreview(data);
                document.getElementById('previewSection').style.display = 'block';
            } else {
                alert(data.message || 'Lookup failed.');
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i> Lookup';
            alert('Error performing lookup: ' + error.message);
        });
}

function renderPreview(data) {
    // Client/Service Info
    let clientHtml = '';
    if (data.client) {
        clientHtml += '<div class="alert alert-info">';
        clientHtml += '<strong>Client:</strong> ' + escapeHtml(data.client.name);
        if (data.client.companyname) clientHtml += ' (' + escapeHtml(data.client.companyname) + ')';
        clientHtml += ' - ' + escapeHtml(data.client.email);
        clientHtml += ' <span class="badge bg-' + (data.client.status === 'Active' ? 'success' : 'secondary') + '">' + data.client.status + '</span>';
        clientHtml += '</div>';
    }
    if (data.service) {
        clientHtml += '<div class="alert alert-secondary">';
        clientHtml += '<strong>Service #' + data.service.id + ':</strong> ';
        clientHtml += 'Status: <span class="badge bg-' + getStatusColor(data.service.domainstatus) + '">' + data.service.domainstatus + '</span>';
        clientHtml += ' | Registered: ' + data.service.regdate;
        clientHtml += '</div>';
    }
    document.getElementById('clientInfo').innerHTML = clientHtml;

    // Protected Warnings
    let warningsHtml = '';
    if (data.protected_warnings && data.protected_warnings.length > 0) {
        warningsHtml = '<div class="alert alert-danger"><h6><i class="fas fa-shield-alt"></i> Protected Resources Detected</h6><ul>';
        data.protected_warnings.forEach(w => {
            warningsHtml += '<li class="protected-warning">' + escapeHtml(w) + '</li>';
        });
        warningsHtml += '</ul></div>';
    }
    document.getElementById('protectedWarnings').innerHTML = warningsHtml;

    // Users List
    let usersHtml = '';
    usersHtml += '<div class="card user-card mb-2">';
    usersHtml += '<div class="card-body py-2">';
    usersHtml += '<strong><i class="fas fa-user"></i> ' + escapeHtml(data.primary.username) + '</strong>';
    usersHtml += ' <span class="badge bg-primary">Primary</span>';
    if (!data.primary.is_active) usersHtml += ' <span class="badge bg-secondary">Inactive</span>';
    usersHtml += '<br><small class="text-muted">Ceph UID: ' + escapeHtml(data.primary.ceph_uid) + '</small>';
    if (data.primary.name) usersHtml += '<br><small class="text-muted">Name: ' + escapeHtml(data.primary.name) + '</small>';
    usersHtml += '</div></div>';

    data.sub_tenants.forEach(tenant => {
        usersHtml += '<div class="card user-card sub-tenant mb-2">';
        usersHtml += '<div class="card-body py-2">';
        usersHtml += '<i class="fas fa-user-friends"></i> ' + escapeHtml(tenant.username);
        usersHtml += ' <span class="badge bg-secondary">Sub-tenant</span>';
        if (!tenant.is_active) usersHtml += ' <span class="badge bg-warning">Inactive</span>';
        usersHtml += '<br><small class="text-muted">Ceph UID: ' + escapeHtml(tenant.ceph_uid) + '</small>';
        usersHtml += '</div></div>';
    });
    document.getElementById('usersList').innerHTML = usersHtml;

    // Buckets List
    let bucketsHtml = '';
    if (data.buckets.length === 0) {
        bucketsHtml = '<div class="alert alert-info">No buckets found.</div>';
    } else {
        data.buckets.forEach(bucket => {
            let cardClass = 'bucket-card';
            if (bucket.object_lock_enabled) cardClass += ' object-lock';
            if (!bucket.is_active) cardClass += ' inactive';

            bucketsHtml += '<div class="card ' + cardClass + ' mb-2">';
            bucketsHtml += '<div class="card-body py-2">';
            bucketsHtml += '<i class="fas fa-archive"></i> ' + escapeHtml(bucket.name);
            if (bucket.object_lock_enabled) {
                bucketsHtml += ' <span class="badge bg-danger"><i class="fas fa-lock"></i> Object Lock</span>';
            }
            if (!bucket.is_active) {
                bucketsHtml += ' <span class="badge bg-secondary">Inactive</span>';
            }
            if (bucket.versioning === 'enabled') {
                bucketsHtml += ' <span class="badge bg-info">Versioned</span>';
            }
            bucketsHtml += '</div></div>';
        });
    }
    document.getElementById('bucketsList').innerHTML = bucketsHtml;

    // Show confirmation or blocked section
    if (data.can_proceed) {
        document.getElementById('confirmationSection').style.display = 'block';
        document.getElementById('blockedSection').style.display = 'none';
        document.getElementById('expectedPhrase').textContent = 'DEPROVISION ' + data.primary.username.toUpperCase();
        document.getElementById('confirmPhrase').value = '';
        document.getElementById('confirmCheck').checked = false;
        document.getElementById('queueBtn').disabled = true;
    } else {
        document.getElementById('confirmationSection').style.display = 'none';
        document.getElementById('blockedSection').style.display = 'block';
        let blockedHtml = '<ul>';
        data.protected_warnings.forEach(w => {
            blockedHtml += '<li>' + escapeHtml(w) + '</li>';
        });
        blockedHtml += '</ul>';
        document.getElementById('blockedReasons').innerHTML = blockedHtml;
    }
}

function validateConfirmation() {
    const checked = document.getElementById('confirmCheck').checked;
    const phrase = document.getElementById('confirmPhrase').value.trim().toUpperCase();
    const expected = 'DEPROVISION ' + currentPlan.primary.username.toUpperCase();

    document.getElementById('queueBtn').disabled = !(checked && phrase === expected);
}

function queueDeprovision() {
    if (!currentPlan) return;

    const btn = document.getElementById('queueBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Queueing...';

    const params = new URLSearchParams();
    params.append('cs_action', 'queue');
    params.append('token', deprovisionToken);
    params.append('primary_user_id', currentPlan.primary.id);
    params.append('confirm_phrase', document.getElementById('confirmPhrase').value);

    fetch(moduleUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = '<i class="fas fa-trash"></i> Queue Deprovision';

            if (data.status === 'success') {
                alert('Deprovision job queued successfully!\\n\\nUsers deactivated: ' + data.users_deactivated + '\\nBuckets queued: ' + data.buckets_queued);
                // Refresh page to show new job
                location.reload();
            } else {
                btn.disabled = false;
                alert(data.message || 'Failed to queue deprovision.');
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash"></i> Queue Deprovision';
            alert('Error queueing deprovision: ' + error.message);
        });
}

function renderJobsTable() {
    const tbody = document.getElementById('jobsTableBody');
    if (queuedJobs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No deprovision jobs found.</td></tr>';
        return;
    }

    let html = '';
    queuedJobs.forEach(job => {
        html += '<tr class="job-row ' + job.status + '">';
        html += '<td>' + job.id + '</td>';
        html += '<td>' + escapeHtml(job.primary_username) + '</td>';
        html += '<td><span class="badge bg-' + getJobStatusColor(job.status) + '">' + job.status + '</span></td>';
        html += '<td>' + job.attempt_count + '</td>';
        html += '<td>' + job.created_at + '</td>';
        html += '<td>' + (job.completed_at || '-') + '</td>';
        html += '<td>' + (job.error ? '<span class="text-danger" title="' + escapeHtml(job.error) + '"><i class="fas fa-exclamation-circle"></i></span>' : '-') + '</td>';
        html += '</tr>';
    });
    tbody.innerHTML = html;
}

function getStatusColor(status) {
    switch (status) {
        case 'Active': return 'success';
        case 'Suspended': return 'warning';
        case 'Terminated': return 'danger';
        case 'Cancelled': return 'secondary';
        default: return 'secondary';
    }
}

function getJobStatusColor(status) {
    switch (status) {
        case 'queued': return 'warning';
        case 'running': return 'primary';
        case 'blocked': return 'danger';
        case 'failed': return 'danger';
        case 'success': return 'success';
        default: return 'secondary';
    }
}

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

})(); // End IIFE
</script>
HTML;
}

