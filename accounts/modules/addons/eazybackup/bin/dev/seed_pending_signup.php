<?php

declare(strict_types=1);

/**
 * Dev helper: seed a pending_approval signup event for a given MSP (WHMCS
 * client) so the Partner Hub bell, slide-over badge, and signup-approvals
 * queue page can be exercised end-to-end without running the public signup
 * form.
 *
 * Usage:
 *   php accounts/modules/addons/eazybackup/bin/dev/seed_pending_signup.php --client-id=1231
 *   php accounts/modules/addons/eazybackup/bin/dev/seed_pending_signup.php --client-id=1231 --tenant-id=42 --email=alice@example.com
 *   php accounts/modules/addons/eazybackup/bin/dev/seed_pending_signup.php --client-id=1231 --list
 *
 * Notes:
 * - The script does NOT create a real WHMCS client/order. It only writes a
 *   row into eb_whitelabel_signup_events with status='pending_approval'.
 *   Approve from the queue UI will fail (no underlying order); use Reject or
 *   delete the row by id afterwards.
 * - It also fires the new MSP "Pending Signup Notice" email so you can
 *   verify the notification system end-to-end.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

require_once __DIR__ . '/../../tests/bootstrap.php';

use WHMCS\Database\Capsule;

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9_-]+)(?:=(.*))?$/i', $a, $m)) {
        $args[$m[1]] = $m[2] ?? '1';
    }
}

$clientId = (int)($args['client-id'] ?? 0);
if ($clientId <= 0) {
    fwrite(STDERR, "Usage: --client-id=<whmcs uid> [--tenant-id=N] [--email=foo@bar] [--list]\n");
    exit(2);
}

$tenants = Capsule::table('eb_whitelabel_tenants')
    ->where('client_id', $clientId)
    ->orderBy('id')
    ->get(['id','public_id','fqdn','subdomain','status']);

if ($tenants->isEmpty()) {
    fwrite(STDERR, "No eb_whitelabel_tenants rows for client_id={$clientId}.\n");
    exit(1);
}

if (!empty($args['list'])) {
    echo "Tenants for client {$clientId}:\n";
    foreach ($tenants as $t) {
        printf("  id=%d  public_id=%s  fqdn=%s  status=%s\n",
            $t->id, $t->public_id, $t->fqdn, $t->status);
    }
    echo "\nExisting events (any status) for these tenants:\n";
    $events = Capsule::table('eb_whitelabel_signup_events as e')
        ->join('eb_whitelabel_tenants as t','t.id','=','e.tenant_id')
        ->where('t.client_id', $clientId)
        ->orderBy('e.id','desc')
        ->limit(20)
        ->get(['e.id','e.tenant_id','e.email','e.status','e.created_at']);
    if ($events->isEmpty()) {
        echo "  (none)\n";
    } else {
        foreach ($events as $e) {
            printf("  id=%d  tenant=%d  status=%s  email=%s  created=%s\n",
                $e->id, $e->tenant_id, $e->status, $e->email, $e->created_at);
        }
    }
    exit(0);
}

$tenantId = (int)($args['tenant-id'] ?? 0);
if ($tenantId <= 0) {
    $tenantId = (int)$tenants->first()->id;
}
$tenant = $tenants->firstWhere('id', $tenantId);
if (!$tenant) {
    fwrite(STDERR, "tenant-id {$tenantId} does not belong to client {$clientId}.\n");
    exit(1);
}

$email = (string)($args['email'] ?? ('qa+pending+' . substr(bin2hex(random_bytes(3)),0,6) . '@example.com'));
$now = date('Y-m-d H:i:s');

// Idempotency: the events table has UNIQUE(tenant_id, email).
$existing = Capsule::table('eb_whitelabel_signup_events')
    ->where('tenant_id', (int)$tenant->id)
    ->where('email', $email)
    ->first();

if ($existing) {
    Capsule::table('eb_whitelabel_signup_events')
        ->where('id', (int)$existing->id)
        ->update([
            'status' => 'pending_approval',
            'host_header' => (string)$tenant->fqdn,
            'updated_at' => $now,
            'error' => null,
        ]);
    $eventId = (int)$existing->id;
    echo "Reused existing event id={$eventId} (reset to pending_approval).\n";
} else {
    $eventId = (int)Capsule::table('eb_whitelabel_signup_events')->insertGetId([
        'tenant_id' => (int)$tenant->id,
        'host_header' => (string)$tenant->fqdn,
        'email' => $email,
        'status' => 'pending_approval',
        'whmcs_client_id' => null,
        'whmcs_order_id' => null,
        'comet_username' => null,
        'ip' => '127.0.0.1',
        'user_agent' => 'seed_pending_signup.php',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    echo "Inserted event id={$eventId}.\n";
}

printf("  tenant_id=%d  public_id=%s  fqdn=%s\n  email=%s\n",
    $tenant->id, $tenant->public_id, $tenant->fqdn, $email);

// Fire the MSP notice through the same helper PublicSignupController uses.
$noticeFile = __DIR__ . '/../../pages/whitelabel/PublicSignupController.php';
require_once $noticeFile;
if (function_exists('eb_signup_send_msp_pending_notice')) {
    try {
        eb_signup_send_msp_pending_notice($tenant, $email, 0);
        echo "MSP notice helper invoked (check tblemails / module log).\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "MSP notice failed: " . $e->getMessage() . "\n");
    }
} else {
    fwrite(STDERR, "eb_signup_send_msp_pending_notice() not found.\n");
}

// Show the resulting count summary so you can confirm the bell will render.
require_once __DIR__ . '/../../lib/Whitelabel/SignupApprovalsCount.php';
$summary = eb_ph_pending_signups_summary_for_client($clientId);
echo "Bell summary for client {$clientId}: total={$summary['total']}\n";
foreach ($summary['by_tenant_tid'] as $tid => $cnt) {
    echo "  tid={$tid}  count={$cnt}\n";
}

exit(0);
