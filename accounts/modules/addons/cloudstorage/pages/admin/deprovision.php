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

        case 'reset_onboarding':
            return json_encode(handleResetOnboarding($request));

        case 'customer_search':
            return json_encode(handleCustomerSearch($request));

        default:
            return json_encode(['status' => 'fail', 'message' => 'Unknown action.']);
    }
}

/**
 * Typeahead-style search across tblclients for the admin pickers.
 * Returns matches with attached cloudstorage services so the admin can
 * pick a specific service in one step.
 */
function handleCustomerSearch($request)
{
    $query = isset($request['q']) ? (string) $request['q'] : '';
    if (trim($query) === '' || strlen(trim($query)) < 2) {
        return ['status' => 'success', 'results' => []];
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
    try {
        $results = DeprovisionHelper::searchCustomers($query, 15);
        return ['status' => 'success', 'results' => $results];
    } catch (\Throwable $e) {
        try { logModuleCall('cloudstorage', 'admin_customer_search_fail', ['q' => $query], $e->getMessage()); } catch (\Throwable $_) {}
        return ['status' => 'fail', 'message' => 'Customer search failed.', 'results' => []];
    }
}

/**
 * Reset a client's onboarding so they (or a tester) can re-run the Welcome -> provision flow.
 */
function handleResetOnboarding($request)
{
    $clientId = (int) ($request['client_id'] ?? 0);
    if ($clientId <= 0) {
        return ['status' => 'fail', 'message' => 'Missing client_id.'];
    }
    try {
        $counts = DeprovisionHelper::resetOnboarding($clientId);
        return ['status' => 'success', 'counts' => $counts];
    } catch (\Throwable $e) {
        return ['status' => 'fail', 'message' => $e->getMessage()];
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

    // Release session lock before any potentially slow downstream calls (e.g., S3 status checks)
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
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
        'object_lock_assessment' => null,
    ];

    // Object Lock assessment (emptiness + default retention mode)
    try {
        $bucketNames = [];
        foreach ($plan['buckets'] as $b) {
            if (!empty($b->name)) {
                $bucketNames[] = (string) $b->name;
            }
        }
        $response['object_lock_assessment'] = DeprovisionHelper::buildObjectLockAssessmentForBuckets($bucketNames);
    } catch (\Throwable $e) {
        $response['object_lock_assessment'] = [
            'status' => 'fail',
            'message' => 'Unable to evaluate bucket Object Lock status at this time.',
        ];
    }

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
    $confirmBypassGovernance = !empty($request['confirm_bypass_governance']);

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

    // Enforce extra confirmation when Governance (or unknown) Object Lock buckets are non-empty and no Compliance is present.
    try {
        $bucketNames = [];
        foreach (($plan['buckets'] ?? []) as $b) {
            if (!empty($b->name)) {
                $bucketNames[] = (string) $b->name;
            }
        }
        $ola = DeprovisionHelper::buildObjectLockAssessmentForBuckets($bucketNames);
        if (($ola['status'] ?? 'fail') === 'success') {
            $summary = $ola['summary'] ?? [];
            $comp = $summary['non_empty_compliance_buckets'] ?? [];
            $gov = $summary['non_empty_governance_buckets'] ?? [];
            $unk = $summary['non_empty_unknown_mode_buckets'] ?? [];
            $requiresGovConfirm = (count($comp) === 0) && (count($gov) > 0 || count($unk) > 0);
            if ($requiresGovConfirm && !$confirmBypassGovernance) {
                return [
                    'status' => 'fail',
                    'message' => 'Governance/unknown Object Lock buckets are not empty. You must confirm governance bypass before queueing deprovision.',
                ];
            }
        }
    } catch (\Throwable $e) {
        // If assessment fails, do not hard-block queueing here; cron-side deletion rules still apply.
    }

    // Queue the deprovision
    $result = DeprovisionHelper::queueDeprovision($primaryUserId, $adminId, $plan, [
        'confirm_bypass_governance' => $confirmBypassGovernance,
    ]);

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
    /* Page layout */
    .cs-admin-wrap { padding-top: 1rem; }
    .cs-admin-intro { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
    .cs-admin-intro h2 { margin: 0 0 .5rem 0; font-size: 1.25rem; }
    .cs-tool-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
    @media (max-width: 991px) { .cs-tool-grid { grid-template-columns: 1fr; } }

    /* Tool cards - distinct framing for the two workflows */
    .cs-tool-card { border: 1px solid #dee2e6; border-radius: 10px; background: #fff; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    .cs-tool-card .cs-tool-header { padding: .9rem 1.1rem; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; gap: .65rem; }
    .cs-tool-card .cs-tool-header h3 { margin: 0; font-size: 1.05rem; font-weight: 600; }
    .cs-tool-card .cs-tool-tag { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
    .cs-tool-card .cs-tool-body { padding: 1rem 1.1rem 1.1rem; }
    .cs-tool-card .cs-tool-summary { color: #6c757d; font-size: .85rem; margin: 0 0 .85rem; }

    .cs-tool--danger .cs-tool-header { background: linear-gradient(180deg, #fff5f5 0%, #ffe9e9 100%); border-bottom-color: #f1c2c2; }
    .cs-tool--danger .cs-tool-tag { background: #dc3545; color: #fff; }
    .cs-tool--danger .cs-tool-header h3 i { color: #c92a2a; }

    .cs-tool--qa .cs-tool-header { background: linear-gradient(180deg, #fff9e6 0%, #fff1c2 100%); border-bottom-color: #f0d97a; }
    .cs-tool--qa .cs-tool-tag { background: #e0a800; color: #1f1300; }
    .cs-tool--qa .cs-tool-header h3 i { color: #8a6d00; }

    /* Customer typeahead */
    .cs-typeahead { position: relative; }
    .cs-typeahead-input-wrap { position: relative; }
    .cs-typeahead-input-wrap .cs-typeahead-spinner { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: none; }
    .cs-typeahead-input-wrap.is-loading .cs-typeahead-spinner { display: inline-block; }
    .cs-typeahead-results { position: absolute; z-index: 30; top: 100%; left: 0; right: 0; max-height: 360px; overflow-y: auto; background: #fff; border: 1px solid #ced4da; border-top: 0; border-radius: 0 0 6px 6px; box-shadow: 0 6px 18px rgba(0,0,0,.10); display: none; }
    .cs-typeahead-results.is-open { display: block; }
    .cs-typeahead-empty { padding: .65rem .9rem; color: #6c757d; font-size: .85rem; }
    .cs-typeahead-item { padding: .55rem .85rem; border-bottom: 1px solid #f1f3f5; cursor: pointer; }
    .cs-typeahead-item:last-child { border-bottom: 0; }
    .cs-typeahead-item:hover, .cs-typeahead-item.is-active { background: #f1f8ff; }
    .cs-typeahead-item .cs-ti-line1 { font-weight: 600; color: #212529; font-size: .9rem; }
    .cs-typeahead-item .cs-ti-line2 { font-size: .78rem; color: #6c757d; margin-top: 2px; }
    .cs-typeahead-item .cs-ti-id { color: #adb5bd; font-weight: 500; }
    .cs-typeahead-item .cs-svc-pill { display: inline-block; margin: 4px 4px 0 0; padding: 2px 8px; background: #e7f5ff; color: #1864ab; border-radius: 999px; font-size: .72rem; cursor: pointer; }
    .cs-typeahead-item .cs-svc-pill:hover { background: #d0ebff; }
    .cs-typeahead-item .cs-svc-pill.is-cancelled { background: #f1f3f5; color: #868e96; }

    .cs-selected-pill { display: inline-flex; align-items: center; gap: .5rem; background: #e7f5ff; color: #1864ab; padding: 4px 10px; border-radius: 999px; font-size: .82rem; }
    .cs-selected-pill .cs-clear { cursor: pointer; opacity: .6; }
    .cs-selected-pill .cs-clear:hover { opacity: 1; }

    .cs-divider-or { display: flex; align-items: center; gap: .5rem; color: #adb5bd; font-size: .75rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; margin: 1rem 0 .65rem; }
    .cs-divider-or:before, .cs-divider-or:after { content: ''; flex: 1; height: 1px; background: #e9ecef; }

    /* Legacy classes still used by preview/jobs */
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

<div class="container-fluid cs-admin-wrap">

    <!-- Intro / explainer banner -->
    <div class="cs-admin-intro">
        <h2><i class="fas fa-cogs text-secondary"></i> Cloud Storage Admin Tools</h2>
        <p class="mb-1 text-muted small">
            Two separate workflows live on this page. They <strong>do not</strong> overlap &mdash; pick the right one for the job:
        </p>
        <ul class="mb-0 text-muted small">
            <li>
                <strong style="color:#c92a2a;">Deprovision Customer</strong> &mdash; permanent. Queues deletion of
                <em>all</em> s3_users / sub-tenants / buckets / RGW data for a real customer. Use when off-boarding.
            </li>
            <li>
                <strong style="color:#8a6d00;">Reset Onboarding (Dev / QA)</strong> &mdash; cancels the client's
                cloudstorage services + wipes onboarding state so the same WHMCS client can re-run sign-up &rarr;
                provision &rarr; enroll. <strong>Does not touch s3_users / RGW buckets</strong>.
            </li>
        </ul>
    </div>

    <!-- Two distinct tool cards side-by-side -->
    <div class="cs-tool-grid">

        <!-- TOOL 1: Deprovision (danger) -->
        <section class="cs-tool-card cs-tool--danger">
            <div class="cs-tool-header">
                <h3><i class="fas fa-user-times"></i> Deprovision Customer</h3>
                <span class="cs-tool-tag">Destructive</span>
            </div>
            <div class="cs-tool-body">
                <p class="cs-tool-summary">
                    Preview and queue permanent deletion of a customer's storage. Lookup by Service ID,
                    storage username, or pick from customer search.
                </p>

                <!-- Customer typeahead -->
                <div class="cs-typeahead" id="depCustomerSearch">
                    <label class="form-label" for="depCustomerInput">Find customer</label>
                    <div class="cs-typeahead-input-wrap">
                        <input type="text" class="form-control" id="depCustomerInput"
                               placeholder="Search by name, company, email, or client ID..."
                               autocomplete="off">
                        <i class="fas fa-spinner fa-spin cs-typeahead-spinner text-muted"></i>
                    </div>
                    <div class="cs-typeahead-results" id="depCustomerResults"></div>
                    <small class="text-muted">
                        Click a service pill in the results to auto-fill the form. Type at least 2 characters.
                    </small>
                </div>

                <div class="cs-divider-or">or by ID</div>

                <form id="lookupForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="serviceId" class="form-label">WHMCS Service ID</label>
                            <input type="number" class="form-control" id="serviceId" placeholder="e.g. 12345">
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">Storage Username</label>
                            <input type="text" class="form-control" id="username" placeholder="e.g. user@example.com">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-danger" id="lookupBtn">
                            <i class="fas fa-search"></i> Preview Deprovision
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- TOOL 2: Reset Onboarding (QA) -->
        <section class="cs-tool-card cs-tool--qa">
            <div class="cs-tool-header">
                <h3><i class="fas fa-redo"></i> Reset Onboarding</h3>
                <span class="cs-tool-tag">Dev / QA</span>
            </div>
            <div class="cs-tool-body">
                <p class="cs-tool-summary">
                    Cancels the client's eazyBackup / e3 Object Storage / e3 Cloud Backup services, wipes
                    trial state and onboarding bookkeeping, and re-enables the Welcome-page password prompt
                    so the same WHMCS client can re-run the full sign-up &rarr; provision &rarr; enroll
                    &rarr; first-backup flow.
                    <strong>Does NOT</strong> touch s3_users / RGW buckets &mdash; use the Deprovision tool
                    for that.
                </p>

                <!-- Customer typeahead -->
                <div class="cs-typeahead" id="rstCustomerSearch">
                    <label class="form-label" for="rstCustomerInput">Find customer</label>
                    <div class="cs-typeahead-input-wrap">
                        <input type="text" class="form-control" id="rstCustomerInput"
                               placeholder="Search by name, company, email, or client ID..."
                               autocomplete="off">
                        <i class="fas fa-spinner fa-spin cs-typeahead-spinner text-muted"></i>
                    </div>
                    <div class="cs-typeahead-results" id="rstCustomerResults"></div>
                    <small class="text-muted">
                        Click a result to populate the Client ID. Type at least 2 characters.
                    </small>
                </div>

                <div class="cs-divider-or">or by ID</div>

                <form id="resetOnboardingForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="resetClientId" class="form-label">Client ID</label>
                            <input type="number" class="form-control" id="resetClientId" placeholder="e.g. 42">
                            <div id="resetSelectedCustomer" class="mt-2"></div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-redo"></i> Reset onboarding
                            </button>
                        </div>
                    </div>
                </form>
                <div id="resetOnboardingResult" class="mt-3"></div>
            </div>
        </section>

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

                    <!-- Object Lock Assessment Warnings -->
                    <div id="objectLockWarnings" class="mb-4"></div>

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

                            <div class="form-check mb-3" id="govBypassCheckWrap" style="display:none;">
                                <input class="form-check-input" type="checkbox" id="confirmBypassGov">
                                <label class="form-check-label" for="confirmBypassGov">
                                    I understand this may bypass <strong>GOVERNANCE</strong> retention for Object Lock buckets and permanently delete retained objects.
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
    var requiresGovBypassConfirm = false;

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
    document.getElementById('confirmBypassGov').addEventListener('change', validateConfirmation);

    // Queue button
    document.getElementById('queueBtn').addEventListener('click', queueDeprovision);

    // Reset onboarding (dev tool)
    var resetForm = document.getElementById('resetOnboardingForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performResetOnboarding();
        });
    }

    // Customer typeahead instances - independent per tool so a stale query in
    // one panel never affects the other.
    initCustomerTypeahead({
        rootId:        'depCustomerSearch',
        inputId:       'depCustomerInput',
        resultsId:     'depCustomerResults',
        mode:          'deprovision'
    });
    initCustomerTypeahead({
        rootId:        'rstCustomerSearch',
        inputId:       'rstCustomerInput',
        resultsId:     'rstCustomerResults',
        mode:          'reset'
    });
}

/**
 * Generic customer typeahead.
 *   mode='deprovision' -> clicking a service pill fills storage username +
 *                         service ID and runs the lookup.
 *   mode='reset'       -> clicking the customer row fills the Client ID field.
 */
function initCustomerTypeahead(opts) {
    var root      = document.getElementById(opts.rootId);
    var input     = document.getElementById(opts.inputId);
    var results   = document.getElementById(opts.resultsId);
    var wrap      = root ? root.querySelector('.cs-typeahead-input-wrap') : null;
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

    input.addEventListener('focus', function() {
        if (currentItems.length > 0) results.classList.add('is-open');
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
        if (!root.contains(e.target)) {
            results.classList.remove('is-open');
        }
    });

    function applyActive(nodes) {
        nodes.forEach(function(n, i) { n.classList.toggle('is-active', i === activeIndex); });
        if (activeIndex >= 0 && nodes[activeIndex] && nodes[activeIndex].scrollIntoView) {
            nodes[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function runSearch(q) {
        if (q === lastQuery) return;
        lastQuery = q;
        wrap.classList.add('is-loading');
        var params = new URLSearchParams();
        params.append('cs_action', 'customer_search');
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
            if (opts.mode === 'deprovision') {
                if (it.services && it.services.length) {
                    it.services.forEach(function(svc, sidx) {
                        var pillClass = 'cs-svc-pill';
                        var statusLabel = svc.domainstatus ? ' (' + escapeHtml(svc.domainstatus) + ')' : '';
                        if (svc.domainstatus && svc.domainstatus !== 'Active' && svc.domainstatus !== 'Suspended') {
                            pillClass += ' is-cancelled';
                        }
                        var label = escapeHtml(svc.product || ('Product #' + svc.packageid)) + ' #' + svc.id + statusLabel;
                        pills += '<span class="' + pillClass + '" data-item-index="' + idx + '" data-svc-index="' + sidx + '" title="Lookup this service">' + label + '</span>';
                    });
                } else {
                    pills = '<span class="text-muted" style="font-size:.72rem;">No cloudstorage services.</span>';
                }
            }

            html += '<div class="cs-typeahead-item" data-item-index="' + idx + '">';
            html += '<div class="cs-ti-line1">' + line1 + '</div>';
            html += '<div class="cs-ti-line2">' + line2 + '</div>';
            if (pills) html += '<div>' + pills + '</div>';
            html += '</div>';
        });
        results.innerHTML = html;
        results.classList.add('is-open');
        activeIndex = -1;

        results.querySelectorAll('.cs-typeahead-item').forEach(function(node) {
            node.addEventListener('mouseenter', function() {
                activeIndex = parseInt(node.dataset.itemIndex, 10);
                applyActive(Array.prototype.slice.call(results.querySelectorAll('.cs-typeahead-item')));
            });
            node.addEventListener('click', function(e) {
                var pill = e.target.closest('.cs-svc-pill');
                if (pill) {
                    var i = parseInt(pill.dataset.itemIndex, 10);
                    var s = parseInt(pill.dataset.svcIndex, 10);
                    var svc = currentItems[i] && currentItems[i].services ? currentItems[i].services[s] : null;
                    if (svc) pickService(currentItems[i], svc);
                    return;
                }
                var idx2 = parseInt(node.dataset.itemIndex, 10);
                pickItem(currentItems[idx2]);
            });
        });
    }

    function pickItem(item) {
        if (!item) return;
        if (opts.mode === 'reset') {
            document.getElementById('resetClientId').value = item.id;
            renderResetSelected(item);
            input.value = (item.name || item.email || ('Client #' + item.id));
        } else {
            // Deprovision: if there's exactly one cloudstorage service, auto-pick it.
            if (item.services && item.services.length === 1) {
                pickService(item, item.services[0]);
                return;
            }
            // Otherwise prompt the admin to click a specific service pill.
            input.value = (item.name || item.email || ('Client #' + item.id));
            // Keep results open so they can click a service pill.
            return;
        }
        results.classList.remove('is-open');
    }

    function pickService(item, svc) {
        if (opts.mode !== 'deprovision') return;
        document.getElementById('serviceId').value = svc.id || '';
        document.getElementById('username').value = svc.username || '';
        input.value = (item.name || item.email || ('Client #' + item.id)) + ' / ' + (svc.product || '') + ' #' + svc.id;
        results.classList.remove('is-open');
        // Auto-trigger the existing lookup flow.
        performLookup();
    }

    function renderResetSelected(item) {
        var host = document.getElementById('resetSelectedCustomer');
        if (!host) return;
        var label = '';
        if (item.name) label = item.name;
        else if (item.email) label = item.email;
        else label = 'Client #' + item.id;
        host.innerHTML = '<span class="cs-selected-pill"><i class="fas fa-user"></i> ' +
            escapeHtml(label) + ' <span class="cs-ti-id">#' + item.id + '</span>' +
            '<i class="fas fa-times cs-clear" title="Clear selection"></i></span>';
        var clear = host.querySelector('.cs-clear');
        if (clear) clear.addEventListener('click', function() {
            host.innerHTML = '';
            document.getElementById('resetClientId').value = '';
            input.value = '';
        });
    }
}

function performResetOnboarding() {
    var clientId = parseInt(document.getElementById('resetClientId').value || '0', 10);
    if (!clientId) {
        document.getElementById('resetOnboardingResult').innerHTML = '<div class="alert alert-danger">Please enter a Client ID.</div>';
        return;
    }
    if (!confirm('Reset onboarding for client #' + clientId + '? This cancels their services and clears trial state.')) {
        return;
    }
    var params = new URLSearchParams();
    params.append('cs_action', 'reset_onboarding');
    params.append('client_id', clientId);
    fetch(window.location.pathname + '?module=cloudstorage&action=deprovision&' + params.toString(), {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (j.status === 'success') {
                var rows = '';
                Object.keys(j.counts || {}).forEach(function(k){
                    rows += '<li>' + k + ': <strong>' + j.counts[k] + '</strong></li>';
                });
                document.getElementById('resetOnboardingResult').innerHTML = '<div class="alert alert-success"><strong>Reset complete for client #' + clientId + '.</strong><ul>' + rows + '</ul></div>';
            } else {
                document.getElementById('resetOnboardingResult').innerHTML = '<div class="alert alert-danger">' + (j.message || 'Unknown error') + '</div>';
            }
        })
        .catch(function(e){
            document.getElementById('resetOnboardingResult').innerHTML = '<div class="alert alert-danger">Request failed: ' + e + '</div>';
        });
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

    // Object Lock Assessment Warnings
    let olHtml = '';
    requiresGovBypassConfirm = false;
    try {
        const ola = data.object_lock_assessment || null;
        if (ola && ola.status === 'success') {
            const summary = ola.summary || {};
            const comp = summary.non_empty_compliance_buckets || [];
            const gov = summary.non_empty_governance_buckets || [];
            const unk = summary.non_empty_unknown_mode_buckets || [];
            requiresGovBypassConfirm = (comp.length === 0) && (gov.length > 0 || unk.length > 0);

            if (comp.length > 0) {
                olHtml += '<div class="alert alert-danger">';
                olHtml += '<h6><i class="fas fa-lock"></i> Compliance Object Lock detected</h6>';
                olHtml += '<p class="mb-2">One or more buckets have <strong>COMPLIANCE</strong> retention and are not empty. Deprovision can proceed, but deletion will remain blocked until retention allows removal. Access will be revoked.</p>';
                olHtml += '<ul class="mb-0">';
                comp.forEach(name => {
                    const b = (ola.buckets || {})[name] || {};
                    const days = b.default_retention_days;
                    const years = b.default_retention_years;
                    let r = '';
                    if (Number.isInteger(days) && days > 0) r = days + ' days';
                    else if (Number.isInteger(years) && years > 0) r = years + ' years';
                    else r = 'not configured';
                    olHtml += '<li><strong>' + escapeHtml(name) + '</strong> — default retention: ' + escapeHtml(r) + '</li>';
                });
                olHtml += '</ul>';
                olHtml += '</div>';
            } else if (gov.length > 0) {
                olHtml += '<div class="alert alert-warning">';
                olHtml += '<h6><i class="fas fa-lock"></i> Governance Object Lock detected</h6>';
                olHtml += '<p class="mb-2">One or more buckets have <strong>GOVERNANCE</strong> retention and are not empty. This may require an extra confirmation to bypass governance retention during deprovision.</p>';
                olHtml += '<ul class="mb-0">';
                gov.forEach(name => {
                    const b = (ola.buckets || {})[name] || {};
                    const days = b.default_retention_days;
                    const years = b.default_retention_years;
                    let r = '';
                    if (Number.isInteger(days) && days > 0) r = days + ' days';
                    else if (Number.isInteger(years) && years > 0) r = years + ' years';
                    else r = 'not configured';
                    olHtml += '<li><strong>' + escapeHtml(name) + '</strong> — default retention: ' + escapeHtml(r) + '</li>';
                });
                olHtml += '</ul>';
                olHtml += '</div>';
            } else if (unk.length > 0) {
                olHtml += '<div class="alert alert-warning">';
                olHtml += '<h6><i class="fas fa-lock"></i> Object Lock enabled (mode unknown)</h6>';
                olHtml += '<p class="mb-2">Some non-empty buckets have Object Lock enabled but no DefaultRetention mode was detected. Treat this as high risk.</p>';
                olHtml += '<ul class="mb-0">';
                unk.forEach(name => {
                    olHtml += '<li><strong>' + escapeHtml(name) + '</strong></li>';
                });
                olHtml += '</ul>';
                olHtml += '</div>';
            }
        } else if (ola && ola.status === 'fail') {
            olHtml = '<div class="alert alert-secondary"><strong>Object Lock status:</strong> ' + escapeHtml(ola.message || 'Unavailable') + '</div>';
        }
    } catch (e) {
        // ignore rendering errors
    }
    document.getElementById('objectLockWarnings').innerHTML = olHtml;

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
            // Object Lock assessment badges (if available)
            try {
                const ola = data.object_lock_assessment && data.object_lock_assessment.status === 'success' ? data.object_lock_assessment : null;
                const a = ola && ola.buckets ? ola.buckets[bucket.name] : null;
                if (a) {
                    if (a.object_lock_enabled) {
                        const mode = (a.default_mode || '').toUpperCase();
                        if (mode === 'COMPLIANCE') {
                            bucketsHtml += ' <span class="badge bg-dark">Compliance</span>';
                        } else if (mode === 'GOVERNANCE') {
                            bucketsHtml += ' <span class="badge bg-secondary">Governance</span>';
                        } else {
                            bucketsHtml += ' <span class="badge bg-secondary">Mode: Unknown</span>';
                        }
                    }
                    if (a.empty === true) {
                        bucketsHtml += ' <span class="badge bg-success">Empty</span>';
                    } else if (a.empty === false) {
                        bucketsHtml += ' <span class="badge bg-danger">Not empty</span>';
                    }
                }
            } catch (e) {}
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
        document.getElementById('confirmBypassGov').checked = false;
        document.getElementById('govBypassCheckWrap').style.display = requiresGovBypassConfirm ? 'block' : 'none';
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
    const govOk = !requiresGovBypassConfirm || document.getElementById('confirmBypassGov').checked;

    document.getElementById('queueBtn').disabled = !(checked && govOk && phrase === expected);
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
    params.append('confirm_bypass_governance', (requiresGovBypassConfirm && document.getElementById('confirmBypassGov').checked) ? '1' : '0');

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

