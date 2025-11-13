<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Eazybackup\Nfr;

if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
    return [
        'pagetitle'   => 'NFR Application',
        'templatefile'=> 'templates/error',
        'requirelogin'=> true,
        'vars'        => ['error' => 'Not authenticated'],
    ];
}

$clientId = (int)$_SESSION['uid'];

// Flash submitted state (Post/Redirect/Get)
$submitted = false;
if (!empty($_SESSION['eb_nfr_submitted'])) {
    $submitted = true;
    unset($_SESSION['eb_nfr_submitted']);
}

if (!Nfr::enabled()) {
    return [
        'pagetitle'   => 'NFR Application',
        'templatefile'=> 'templates/error',
        'requirelogin'=> true,
        'vars'        => ['error' => 'NFR applications are currently closed.'],
    ];
}

$hasActive = Nfr::hasActiveGrant($clientId);
$activeGrant = $hasActive ? Nfr::activeGrant($clientId) : null;

$errors = [];
$form = [];

// Build product list from config
$nfrProducts = [];
try {
    foreach (Nfr::productIds() as $pid) {
        $name = Capsule::table('tblproducts')->where('id', $pid)->value('name');
        if ($name) { $nfrProducts[] = ['id' => (int)$pid, 'name' => (string)$name]; }
    }
} catch (\Throwable $_) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasActive) {
    $company = trim((string)($_POST['company_name'] ?? ''));
    $contact = trim((string)($_POST['contact_name'] ?? ''));
    $title   = trim((string)($_POST['job_title'] ?? ''));
    $email   = trim((string)($_POST['work_email'] ?? ''));
    $phone   = trim((string)($_POST['phone'] ?? ''));
    $username= trim((string)($_POST['requested_username'] ?? ''));
    $markets = trim((string)($_POST['markets'] ?? ''));
    $useCases= trim((string)($_POST['use_cases'] ?? ''));
    $platforms = trim((string)($_POST['platforms'] ?? ''));
    $virt    = trim((string)($_POST['virtualization'] ?? ''));
    $diskImg = isset($_POST['disk_image']) && (int)$_POST['disk_image'] === 1 ? 1 : 0;
    $quotaGiB = (int)($_POST['requested_quota_gib'] ?? 0);
    $overage = in_array(($_POST['overage'] ?? 'block'), ['block','allow_notice'], true) ? ($_POST['overage']) : 'block';
    $deviceCap = isset($_POST['device_cap']) && $_POST['device_cap'] !== '' ? max(0, (int)$_POST['device_cap']) : null;
    $agree = isset($_POST['agree_terms']) ? 1 : 0;
    $selectedPid = (int)($_POST['product_id'] ?? 0);

    // Persist form values for template on error
    $form = [
        'requested_username' => $username,
        'company_name' => $company,
        'contact_name' => $contact,
        'job_title' => $title,
        'work_email' => $email,
        'phone' => $phone,
        'markets' => $markets,
        'use_cases' => $useCases,
        'platforms' => $platforms,
        'virtualization' => $virt,
        'disk_image' => (string)$diskImg,
        'requested_quota_gib' => ($quotaGiB > 0 ? (string)$quotaGiB : ''),
        'overage' => $overage,
        'device_cap' => ($deviceCap !== null ? (string)$deviceCap : ''),
        'product_id' => ($selectedPid > 0 ? (string)$selectedPid : ''),
        'agree_terms' => ($agree ? '1' : ''),
    ];

    if ($company === '') { $errors['company_name'] = 'Company name is required.'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['work_email'] = 'Valid work email is required.'; }
    if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]{3,}$/', $username)) { $errors['requested_username'] = 'Username must be 3+ chars; letters, numbers, dot, underscore, or hyphen.'; }
    if ($selectedPid <= 0 || !in_array($selectedPid, array_map(function($p){return (int)$p;}, Nfr::productIds()), true)) { $errors['product_id'] = 'Please select a valid product.'; }
    if ($agree !== 1) { $errors['agree_terms'] = 'You must agree to the NFR terms.'; }

    if (Nfr::captchaEnabled()) {
        $token = (string)($_POST['cf-turnstile-response'] ?? '');
        $secret = (string)\WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module','eazybackup')->where('setting','turnstilesecret')->value('value');
        if ($token === '' || !function_exists('validateTurnstile') || !validateTurnstile($token, (string)$secret, $_SERVER['REMOTE_ADDR'] ?? null)) {
            $errors['turnstile'] = 'Please complete the verification.';
        }
    }

    // Enforce per-client active limit
    try {
        $maxActive = Nfr::maxActivePerClient();
        $activeCount = Capsule::table('eb_nfr')
            ->where('client_id', $clientId)
            ->whereIn('status',['approved','provisioned'])
            ->count();
        if ($activeCount >= $maxActive) {
            $errors['limit'] = 'You already have an active NFR grant.';
        }
    } catch (\Throwable $_) {}

    if (empty($errors)) {
        try {
            Capsule::table('eb_nfr')->insert([
                'client_id' => $clientId,
                'product_id'=> $selectedPid ?: null,
                'service_id'=> null,
                'service_username' => ($username !== '' ? $username : null),
                'requested_username' => $username ?: null,
                'status'    => 'pending',
                'company_name' => $company,
                'contact_name' => $contact,
                'job_title' => $title,
                'work_email' => $email,
                'phone' => $phone,
                'markets' => $markets,
                'use_cases' => $useCases,
                'platforms' => $platforms,
                'virtualization' => $virt,
                'disk_image' => $diskImg,
                'requested_quota_gib' => $quotaGiB,
                'approved_quota_gib' => null,
                'overage' => $overage,
                'device_cap' => $deviceCap,
                'duration_days' => null,
                'start_date' => null,
                'end_date' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            // Admin notification
            $adminTo = Nfr::adminEmail();
            if ($adminTo !== '') {
                // Resolve product label (optional)
                $pidLabel = (string)$selectedPid;
                foreach ($nfrProducts as $p) { if ((int)$p['id'] === $selectedPid) { $pidLabel = $p['name'] . " (PID {$selectedPid})"; break; } }

                $summary = "A new NFR application was submitted.\n\n"
                  . "Client ID: {$clientId}\n"
                  . "Company: {$company}\n"
                  . "Contact: {$contact}\n"
                  . "Title: {$title}\n"
                  . "Email: {$email}\n"
                  . "Phone: {$phone}\n"
                  . "Requested Username: {$username}\n"
                  . "Selected Product: {$pidLabel}\n\n"
                  . "Requested Quota (GiB): {$quotaGiB}\n"
                  . "Overage Handling: {$overage}\n"
                  . "Device Cap: " . ($deviceCap === null ? '—' : (string)$deviceCap) . "\n"
                  . "Disk Image: " . ($diskImg ? 'Yes' : 'No') . "\n"
                  . "Platforms: " . ($platforms !== '' ? $platforms : '—') . "\n"
                  . "Virtualization: " . ($virt !== '' ? $virt : '—') . "\n"
                  . "Markets: " . ($markets !== '' ? $markets : '—') . "\n"
                  . "Use cases: " . ($useCases !== '' ? $useCases : '—') . "\n\n"
                  . "Review in: Addons → eazyBackup → NFR → Applications";
                // Prefer Admin email API (does not require related id)
                $resp = @localAPI('SendAdminEmail', [
                    'customsubject' => 'New NFR Application from ' . $company,
                    'custommessage' => $summary,
                    'to' => $adminTo,
                ]);
                if (!is_array($resp) || ($resp['result'] ?? '') !== 'success') {
                    // Fallback log and attempt via SendEmail
                    try { logModuleCall('eazybackup','nfr_admin_mail', ['to'=>$adminTo], $summary, $resp); } catch (\Throwable $__) {}
                    $resp2 = @localAPI('SendEmail', [
                        'customtype' => 'general',
                        'customsubject' => 'New NFR Application from ' . $company,
                        'custommessage' => $summary,
                        'to' => $adminTo,
                    ]);
                    try { if (!is_array($resp2) || ($resp2['result'] ?? '') !== 'success') { logModuleCall('eazybackup','nfr_admin_mail_fallback', ['to'=>$adminTo], $summary, $resp2); } } catch (\Throwable $___) {}
                }
            }

            // Client acknowledgement (optional simple copy)
            @localAPI('SendEmail', [
                'customtype' => 'general',
                'customsubject' => 'We received your NFR application',
                'custommessage' => "Thanks — your application was submitted. We will review it and email you shortly.",
                'to' => $email,
            ]);

            // PRG: avoid resubmission on refresh
            $_SESSION['eb_nfr_submitted'] = 1;
            header('Location: index.php?m=eazybackup&a=nfr-apply');
            exit;
        } catch (\Throwable $e) {
            $errors['server'] = 'Could not submit application. Please try again later.';
            try { logModuleCall('eazybackup','nfr_submit_error', ['client_id'=>$clientId,'username'=>$username,'pid'=>$selectedPid], ['error'=>$e->getMessage()]); } catch (\Throwable $__) {}
        }
    }
}

return [
    'pagetitle'   => 'NFR Application',
    'templatefile'=> 'templates/clientarea/nfr-apply',
    'requirelogin'=> true,
    'forcessl'    => true,
    'vars'        => [
        'modulelink' => 'index.php?m=eazybackup',
        'csrfToken' => function_exists('generate_token') ? generate_token('plain') : '',
        'submitted' => $submitted,
        'hasActiveGrant' => $hasActive,
        'activeGrant' => $activeGrant,
        'captchaEnabled' => Nfr::captchaEnabled(),
        'turnstile_site_key' => (string)Capsule::table('tbladdonmodules')->where('module','eazybackup')->where('setting','turnstilesitekey')->value('value'),
        'errors' => $errors,
        'form' => $form,
        'nfrProducts' => $nfrProducts,
    ],
];


