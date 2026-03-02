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

function eb_ph_signup_approvals_require_csrf_or_redirect(array $vars, string $token): void
{
    if (!function_exists('check_token')) {
        eb_ph_signup_approvals_redirect($vars, 'error=csrf');
    }
    try {
        $valid = (bool)check_token('plain', $token);
    } catch (\Throwable $__) {
        eb_ph_signup_approvals_redirect($vars, 'error=csrf');
    }
    if (!$valid) {
        eb_ph_signup_approvals_redirect($vars, 'error=csrf');
    }
}

function eb_ph_signup_approvals_get_order_snapshot(int $orderId, string $adminUser): ?array
{
    if ($orderId <= 0) {
        return null;
    }
    try {
        $res = localAPI('GetOrders', ['id' => $orderId], $adminUser);
    } catch (\Throwable $__) {
        return null;
    }
    if (($res['result'] ?? '') !== 'success') {
        return null;
    }

    $orders = $res['orders']['order'] ?? [];
    if (!is_array($orders)) {
        return null;
    }
    if (isset($orders['id'])) {
        $orders = [$orders];
    }
    if (!isset($orders[0]) || !is_array($orders[0])) {
        return null;
    }
    $order = $orders[0];

    return [
        'id' => (int)($order['id'] ?? 0),
        'userid' => (int)($order['userid'] ?? 0),
        'invoiceid' => (int)($order['invoiceid'] ?? 0),
        'status' => (string)($order['status'] ?? ''),
    ];
}

function eb_ph_signup_approvals_is_order_cancelled(?array $order): bool
{
    $status = strtolower(trim((string)($order['status'] ?? '')));
    return $status === 'cancelled';
}

function eb_ph_signup_approvals_is_order_accepted_like(?array $order): bool
{
    $status = strtolower(trim((string)($order['status'] ?? '')));
    return in_array($status, ['active', 'accepted', 'completed'], true);
}

function eb_ph_signup_approvals_is_invoice_cancelled(int $invoiceId, string $adminUser): bool
{
    if ($invoiceId <= 0) {
        return true;
    }
    try {
        $res = localAPI('GetInvoices', ['invoiceid' => $invoiceId], $adminUser);
    } catch (\Throwable $__) {
        return false;
    }
    if (($res['result'] ?? '') !== 'success') {
        return false;
    }

    $invoices = $res['invoices']['invoice'] ?? [];
    if (!is_array($invoices)) {
        return false;
    }
    if (isset($invoices['id'])) {
        $invoices = [$invoices];
    }
    if (!isset($invoices[0]) || !is_array($invoices[0])) {
        return false;
    }
    $status = strtolower(trim((string)($invoices[0]['status'] ?? '')));

    return $status === 'cancelled';
}

function eb_ph_signup_approvals_claim_processing(int $eventId, string $processingStatus, ?int $adminId): bool
{
    if (!in_array($processingStatus, ['approving', 'rejecting'], true)) {
        return false;
    }
    $update = [
        'status' => $processingStatus,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($adminId !== null && $adminId > 0) {
        $update['approved_by_admin_id'] = $adminId;
    }

    $affected = Capsule::table('eb_whitelabel_signup_events')
        ->where('id', $eventId)
        ->where('status', 'pending_approval')
        ->update($update);

    return (int)$affected === 1;
}

function eb_ph_signup_approvals_finalize_from_processing(int $eventId, string $processingStatus, string $finalStatus, array $update): bool
{
    if (!in_array($processingStatus, ['approving', 'rejecting'], true)) {
        return false;
    }
    if (!in_array($finalStatus, ['approved', 'rejected'], true)) {
        return false;
    }

    $update['status'] = $finalStatus;
    $update['updated_at'] = date('Y-m-d H:i:s');

    $affected = Capsule::table('eb_whitelabel_signup_events')
        ->where('id', $eventId)
        ->where('status', $processingStatus)
        ->update($update);

    return (int)$affected === 1;
}

function eb_ph_signup_approvals_rollback_to_pending(int $eventId, string $processingStatus, string $reason): bool
{
    if (!in_array($processingStatus, ['approving', 'rejecting'], true)) {
        return false;
    }

    $affected = Capsule::table('eb_whitelabel_signup_events')
        ->where('id', $eventId)
        ->where('status', $processingStatus)
        ->update([
            'status' => 'pending_approval',
            'approval_notes' => substr($reason, 0, 65535),
            'approved_at' => null,
            'approved_by_admin_id' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    return (int)$affected === 1;
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
        ->whereIn('e.status', ['pending_approval', 'approving', 'rejecting'])
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
    eb_ph_signup_approvals_require_csrf_or_redirect($vars, $token);

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
    $order = eb_ph_signup_approvals_get_order_snapshot($orderId, $adminUser);
    if (!$order) {
        eb_ph_signup_approvals_redirect($vars, 'error=order_lookup');
    }
    $expectedClientId = (int)($event->whmcs_client_id ?? 0);
    $orderUserId = (int)($order['userid'] ?? 0);
    if ($expectedClientId <= 0 || $orderUserId !== $expectedClientId) {
        eb_ph_signup_approvals_redirect($vars, 'error=order_owner');
    }
    $adminId = isset($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : null;
    if (!eb_ph_signup_approvals_claim_processing($eventId, 'approving', $adminId)) {
        eb_ph_signup_approvals_redirect($vars, 'error=race');
    }

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
        $accept = ['result' => 'error', 'message' => 'exception'];
    }

    $approvalNotes = trim((string)($_POST['approval_notes'] ?? ''));
    if (($accept['result'] ?? '') !== 'success') {
        $latestOrder = eb_ph_signup_approvals_get_order_snapshot($orderId, $adminUser);
        if (!eb_ph_signup_approvals_is_order_accepted_like($latestOrder)) {
            $acceptReason = (($accept['message'] ?? '') === 'exception') ? 'Approve failed: accept exception' : 'Approve failed: accept failed';
            $rolledBack = eb_ph_signup_approvals_rollback_to_pending($eventId, 'approving', $acceptReason);
            if (!$rolledBack) {
                eb_ph_signup_approvals_redirect($vars, 'error=race');
            }
            $redirectError = (($accept['message'] ?? '') === 'exception') ? 'error=accept_exception' : 'error=accept_failed';
            eb_ph_signup_approvals_redirect($vars, $redirectError);
        }

        $reconciliationNote = 'Approved via reconciliation: AcceptOrder reported failure';
        if ($approvalNotes !== '') {
            $approvalNotes .= ' | ' . $reconciliationNote;
        } else {
            $approvalNotes = $reconciliationNote;
        }
    }

    $update = [
        'status' => 'approved',
        'approved_by_admin_id' => ($adminId && $adminId > 0) ? $adminId : null,
        'approved_at' => date('Y-m-d H:i:s'),
    ];
    if ($approvalNotes !== '') {
        $update['approval_notes'] = substr($approvalNotes, 0, 65535);
    }
    if (!eb_ph_signup_approvals_finalize_from_processing($eventId, 'approving', 'approved', $update)) {
        eb_ph_signup_approvals_redirect($vars, 'error=race');
    }

    eb_ph_signup_approvals_redirect($vars, 'notice=approved');
}

function eb_ph_signup_reject(array $vars): void
{
    [$clientId] = eb_ph_signup_approvals_context_or_redirect($vars);

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        eb_ph_signup_approvals_redirect($vars, 'error=method');
    }

    $token = (string)($_POST['token'] ?? '');
    eb_ph_signup_approvals_require_csrf_or_redirect($vars, $token);

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
    $order = eb_ph_signup_approvals_get_order_snapshot($orderId, $adminUser);
    if (!$order) {
        eb_ph_signup_approvals_redirect($vars, 'error=order_lookup');
    }
    $expectedClientId = (int)($event->whmcs_client_id ?? 0);
    $orderUserId = (int)($order['userid'] ?? 0);
    if ($expectedClientId <= 0 || $orderUserId !== $expectedClientId) {
        eb_ph_signup_approvals_redirect($vars, 'error=order_owner');
    }
    $adminId = isset($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : null;
    if (!eb_ph_signup_approvals_claim_processing($eventId, 'rejecting', $adminId)) {
        eb_ph_signup_approvals_redirect($vars, 'error=race');
    }

    $cancelPathSucceeded = false;
    try {
        $cancelRes = localAPI('CancelOrder', [
            'orderid' => $orderId,
            'cancellationreason' => 'Rejected by MSP signup approvals',
        ], $adminUser);
        $cancelPathSucceeded = (($cancelRes['result'] ?? '') === 'success');
    } catch (\Throwable $__) {
        $cancelPathSucceeded = false;
    }
    if (!$cancelPathSucceeded) {
        $latestOrder = eb_ph_signup_approvals_get_order_snapshot($orderId, $adminUser);
        if (eb_ph_signup_approvals_is_order_cancelled($latestOrder)) {
            $cancelPathSucceeded = true;
            $order = $latestOrder;
        }
    }
    if (!$cancelPathSucceeded) {
        $rolledBack = eb_ph_signup_approvals_rollback_to_pending($eventId, 'rejecting', 'Reject failed: cancel path did not succeed');
        if (!$rolledBack) {
            eb_ph_signup_approvals_redirect($vars, 'error=race');
        }
        eb_ph_signup_approvals_redirect($vars, 'error=cancel_failed');
    }

    $invoiceId = (int)($order['invoiceid'] ?? 0);
    $voidPathSucceeded = true;
    $voidFollowupWarning = '';
    if ($invoiceId > 0) {
        $voidPathSucceeded = false;
        try {
            $voidRes = localAPI('UpdateInvoice', [
                'invoiceid' => $invoiceId,
                'status' => 'Cancelled',
            ], $adminUser);
            $voidPathSucceeded = (($voidRes['result'] ?? '') === 'success');
        } catch (\Throwable $__) {
            $voidPathSucceeded = false;
        }
        if (!$voidPathSucceeded) {
            $voidPathSucceeded = eb_ph_signup_approvals_is_invoice_cancelled($invoiceId, $adminUser);
        }
    }
    if (!$voidPathSucceeded) {
        $voidFollowupWarning = 'invoice void follow-up needed';
    }

    $notes = trim((string)($_POST['approval_notes'] ?? ''));
    if ($notes === '') {
        $notes = 'Rejected by MSP signup approvals';
    }
    if ($voidFollowupWarning !== '') {
        $notes .= ' [warning: ' . $voidFollowupWarning . ']';
    }

    $update = [
        'status' => 'rejected',
        'approval_notes' => substr($notes, 0, 65535),
        'approved_by_admin_id' => ($adminId && $adminId > 0) ? $adminId : null,
        'approved_at' => date('Y-m-d H:i:s'),
    ];
    if (!eb_ph_signup_approvals_finalize_from_processing($eventId, 'rejecting', 'rejected', $update)) {
        eb_ph_signup_approvals_redirect($vars, 'error=race');
    }

    $redirectQuery = ($voidFollowupWarning !== '') ? 'notice=rejected&error=void_followup' : 'notice=rejected';
    eb_ph_signup_approvals_redirect($vars, $redirectQuery);
}
