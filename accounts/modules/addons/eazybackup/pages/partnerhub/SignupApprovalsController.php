<?php

use WHMCS\Database\Capsule;

function eb_ph_signup_approvals_base_link(array $vars): string
{
    return (string)($vars['modulelink'] ?? 'index.php?m=eazybackup');
}

function eb_ph_signup_approvals_redirect(array $vars, string $query = ''): void
{
    $url = eb_ph_signup_approvals_base_link($vars) . '&a=ph-signup-approvals';
    if ($query !== '') {
        $url .= '&' . $query;
    }
    header('Location: ' . $url);
    exit;
}

function eb_ph_signup_approvals_context_or_redirect(array $vars): array
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
        header('Location: clientarea.php');
        exit;
    }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) {
        header('Location: ' . eb_ph_signup_approvals_base_link($vars) . '&a=ph-clients');
        exit;
    }

    return [$clientId, $msp];
}

function eb_ph_signup_approvals_resolve_admin_user(): string
{
    $adminUser = 'API';
    try {
        $configuredAdmin = (string)(Capsule::table('tbladdonmodules')
            ->where('module', 'eazybackup')
            ->where('setting', 'adminuser')
            ->value('value') ?? '');
        if ($configuredAdmin !== '') {
            $adminUser = $configuredAdmin;
        } else {
            $firstAdmin = Capsule::table('tbladmins')
                ->where('disabled', 0)
                ->orderBy('id', 'asc')
                ->value('username');
            if ($firstAdmin) {
                $adminUser = (string)$firstAdmin;
            }
        }
    } catch (\Throwable $__) {
        // Keep API fallback.
    }

    return $adminUser;
}

function eb_ph_signup_approvals_get_event_for_client(int $eventId, int $clientId): ?object
{
    return Capsule::table('eb_whitelabel_signup_events as e')
        ->join('eb_whitelabel_tenants as t', 't.id', '=', 'e.tenant_id')
        ->where('e.id', $eventId)
        ->where('t.client_id', $clientId)
        ->first([
            'e.id',
            'e.tenant_id',
            'e.email',
            'e.status',
            'e.whmcs_client_id',
            'e.whmcs_order_id',
            'e.comet_username',
            'e.approval_notes',
            't.subdomain',
            't.fqdn',
        ]);
}

function eb_ph_signup_approvals_index(array $vars)
{
    [$clientId, $msp] = eb_ph_signup_approvals_context_or_redirect($vars);

    $rowsCol = Capsule::table('eb_whitelabel_signup_events as e')
        ->join('eb_whitelabel_tenants as t', 't.id', '=', 'e.tenant_id')
        ->leftJoin('tblclients as wc', 'wc.id', '=', 'e.whmcs_client_id')
        ->where('t.client_id', $clientId)
        ->where('e.status', 'pending_approval')
        ->orderBy('e.created_at', 'asc')
        ->get([
            'e.id',
            'e.email',
            'e.status',
            'e.created_at',
            'e.whmcs_client_id',
            'e.whmcs_order_id',
            't.id as tenant_id',
            't.subdomain',
            't.fqdn',
            'wc.firstname',
            'wc.lastname',
            'wc.companyname',
        ]);

    $rows = [];
    foreach ($rowsCol as $row) {
        $rows[] = (array)$row;
    }

    return [
        'pagetitle' => 'Partner Hub - Signup Approvals',
        'templatefile' => 'whitelabel/signup-approvals',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => eb_ph_signup_approvals_base_link($vars),
            'msp' => (array)$msp,
            'rows' => $rows,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'notice' => (string)($_GET['notice'] ?? ''),
            'error' => (string)($_GET['error'] ?? ''),
        ],
    ];
}

function eb_ph_signup_approve(array $vars): void
{
    [$clientId] = eb_ph_signup_approvals_context_or_redirect($vars);

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        eb_ph_signup_approvals_redirect($vars, 'error=method');
    }

    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { eb_ph_signup_approvals_redirect($vars, 'error=csrf'); } } catch (\Throwable $__) {} }

    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($eventId <= 0) {
        eb_ph_signup_approvals_redirect($vars, 'error=event');
    }

    $event = eb_ph_signup_approvals_get_event_for_client($eventId, $clientId);
    if (!$event || (string)($event->status ?? '') !== 'pending_approval') {
        eb_ph_signup_approvals_redirect($vars, 'error=notfound');
    }

    $orderId = (int)($event->whmcs_order_id ?? 0);
    if ($orderId <= 0) {
        eb_ph_signup_approvals_redirect($vars, 'error=order');
    }

    $adminUser = eb_ph_signup_approvals_resolve_admin_user();
    $acceptData = [
        'orderid' => $orderId,
        'autosetup' => true,
        'sendemail' => true,
    ];
    $requestedUsername = trim((string)($event->comet_username ?? ''));
    if ($requestedUsername !== '') {
        $acceptData['serviceusername'] = $requestedUsername;
    }

    try {
        $accept = localAPI('AcceptOrder', $acceptData, $adminUser);
    } catch (\Throwable $e) {
        eb_ph_signup_approvals_redirect($vars, 'error=accept_exception');
    }
    if (($accept['result'] ?? '') !== 'success') {
        eb_ph_signup_approvals_redirect($vars, 'error=accept_failed');
    }

    $adminId = isset($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : null;
    Capsule::table('eb_whitelabel_signup_events')->where('id', $eventId)->update([
        'status' => 'approved',
        'approved_by_admin_id' => ($adminId && $adminId > 0) ? $adminId : null,
        'approved_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    eb_ph_signup_approvals_redirect($vars, 'notice=approved');
}

function eb_ph_signup_reject(array $vars): void
{
    [$clientId] = eb_ph_signup_approvals_context_or_redirect($vars);

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        eb_ph_signup_approvals_redirect($vars, 'error=method');
    }

    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { eb_ph_signup_approvals_redirect($vars, 'error=csrf'); } } catch (\Throwable $__) {} }

    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($eventId <= 0) {
        eb_ph_signup_approvals_redirect($vars, 'error=event');
    }

    $event = eb_ph_signup_approvals_get_event_for_client($eventId, $clientId);
    if (!$event || (string)($event->status ?? '') !== 'pending_approval') {
        eb_ph_signup_approvals_redirect($vars, 'error=notfound');
    }

    $adminUser = eb_ph_signup_approvals_resolve_admin_user();
    $orderId = (int)($event->whmcs_order_id ?? 0);
    $cancelIssues = [];

    if ($orderId > 0) {
        try {
            $cancelRes = localAPI('CancelOrder', [
                'orderid' => $orderId,
                'cancellationreason' => 'Rejected by MSP signup approvals',
            ], $adminUser);
            if (($cancelRes['result'] ?? '') !== 'success') {
                $cancelIssues[] = 'cancel_order_failed';
            }
        } catch (\Throwable $__) {
            $cancelIssues[] = 'cancel_order_exception';
        }

        $invoiceId = 0;
        try {
            $invoiceId = (int)(Capsule::table('tblorders')->where('id', $orderId)->value('invoiceid') ?? 0);
        } catch (\Throwable $__) {
            $invoiceId = 0;
        }
        if ($invoiceId > 0) {
            try {
                $voidRes = localAPI('UpdateInvoice', [
                    'invoiceid' => $invoiceId,
                    'status' => 'Cancelled',
                ], $adminUser);
                if (($voidRes['result'] ?? '') !== 'success') {
                    $cancelIssues[] = 'void_invoice_failed';
                }
            } catch (\Throwable $__) {
                $cancelIssues[] = 'void_invoice_exception';
            }
            try {
                Capsule::table('tblinvoices')->where('id', $invoiceId)->update(['status' => 'Cancelled']);
            } catch (\Throwable $__) {
                // Best effort.
            }
        }

        try {
            Capsule::table('tblorders')->where('id', $orderId)->update(['status' => 'Cancelled']);
        } catch (\Throwable $__) {
            // Best effort.
        }
    }

    $adminId = isset($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : null;
    $notes = trim((string)($_POST['approval_notes'] ?? ''));
    if ($notes === '') {
        $notes = 'Rejected by MSP signup approvals';
    }
    if (!empty($cancelIssues)) {
        $notes .= ' [' . implode(',', $cancelIssues) . ']';
    }

    Capsule::table('eb_whitelabel_signup_events')->where('id', $eventId)->update([
        'status' => 'rejected',
        'approval_notes' => substr($notes, 0, 65535),
        'approved_by_admin_id' => ($adminId && $adminId > 0) ? $adminId : null,
        'approved_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $redirectQuery = !empty($cancelIssues) ? 'notice=rejected&error=cancel' : 'notice=rejected';
    eb_ph_signup_approvals_redirect($vars, $redirectQuery);
}
