<?php
declare(strict_types=1);

/**
 * Billing model analysis for a backup user (Protected Users + EXCEPTION-SM).
 *
 * Usage:
 *   php modules/addons/ms365backup/bin/analyze_billing_model.php --backup-user-id=N [--client-id=N]
 *   php ... --explicit-mailbox-ids=mailbox:receipts,mailbox:referrals
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\CustomerSelectionCodec;
use Ms365Backup\Ms365UsageMeter;
use Ms365Backup\ProtectedUserResolver;
use Ms365Backup\TenantResource;
use WHMCS\Database\Capsule;

$clientId = 0;
$backupUserId = 0;
$explicitMailboxIds = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--client-id=')) {
        $clientId = (int) substr($arg, 12);
    }
    if (str_starts_with($arg, '--backup-user-id=')) {
        $backupUserId = (int) substr($arg, 17);
    }
    if (str_starts_with($arg, '--explicit-mailbox-ids=')) {
        $explicitMailboxIds = CustomerSelectionCodec::normalizeIds(explode(',', substr($arg, 22)));
    }
}

if ($backupUserId <= 0) {
    fwrite(STDERR, "Usage: php analyze_billing_model.php --backup-user-id=N [--client-id=N] [--explicit-mailbox-ids=mailbox:a,mailbox:b]\n");
    exit(1);
}

if ($clientId <= 0) {
    $clientId = (int) Capsule::table('s3_backup_users')
        ->where('id', $backupUserId)
        ->value('client_id');
    if ($clientId <= 0 && Capsule::schema()->hasTable('ms365_tenant_records')) {
        $clientId = (int) Capsule::table('ms365_tenant_records')
            ->where('backup_user_id', $backupUserId)
            ->value('whmcs_client_id');
    }
    if ($clientId <= 0) {
        fwrite(STDERR, "Could not resolve client_id for backup_user_id={$backupUserId}\n");
        exit(1);
    }
}

$measure = Ms365UsageMeter::measureBackupUser($clientId, $backupUserId);
$liveCount = (int) ($measure['protected_users'] ?? 0);

$ref = new ReflectionClass(Ms365UsageMeter::class);
$loadInv = $ref->getMethod('loadInventorySafe');
$loadInv->setAccessible(true);
$mergeSel = $ref->getMethod('mergeJobSelections');
$mergeSel->setAccessible(true);
$discoveryFor = $ref->getMethod('discoveryForBackupUser');
$discoveryFor->setAccessible(true);

$inventory = $loadInv->invoke(null, $clientId, $backupUserId);
$selection = $mergeSel->invoke(null, $clientId, $backupUserId, $inventory);
$discovery = $discoveryFor->invoke(null, $clientId, $backupUserId);

$selectedIds = $selection['selected_ids'] ?? [];
$scopeOverrides = $selection['scope_overrides'] ?? [];
$billingExemptIds = $selection['billing_exempt_resource_ids'] ?? [];
$billingExemptKeyPresent = (bool) ($selection['billing_exempt_key_present'] ?? true);

$resolution = ProtectedUserResolver::resolve(
    $inventory,
    $selectedIds,
    $scopeOverrides,
    $discovery,
    $billingExemptIds,
    $billingExemptKeyPresent,
);
$protectedIds = $resolution['protected_azure_ids'];

$simExemptIds = array_values(array_diff($billingExemptIds, $explicitMailboxIds));
$simResolution = ProtectedUserResolver::resolve(
    $inventory,
    $selectedIds,
    $scopeOverrides,
    $discovery,
    $simExemptIds,
    true,
);
$simIds = $simResolution['protected_azure_ids'];

$legacyResolution = ProtectedUserResolver::resolve(
    $inventory,
    $selectedIds,
    $scopeOverrides,
    $discovery,
    [],
    false,
);
$legacyAbsentIds = $legacyResolution['protected_azure_ids'];

$userIndex = [];
foreach ($inventory['resources'] ?? [] as $resource) {
    if (!is_array($resource)) {
        continue;
    }
    $type = (string) ($resource['resource_type'] ?? '');
    if (!in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
        continue;
    }
    $graphId = (string) ($resource['graph_id'] ?? TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? '')));
    if ($graphId === '') {
        continue;
    }
    $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
    $userIndex[$graphId] = [
        'user_type' => strtolower((string) ($meta['user_type'] ?? '')),
        'upn' => strtolower((string) ($resource['email'] ?? $meta['mail'] ?? '')),
        'resource_type' => $type,
        'display_name' => (string) ($resource['display_name'] ?? ''),
    ];
}

$excludedMailboxIds = [];
$byId = [];
foreach ($inventory['resources'] ?? [] as $resource) {
    if (!is_array($resource)) {
        continue;
    }
    $id = (string) ($resource['id'] ?? '');
    if ($id !== '') {
        $byId[$id] = $resource;
    }
}
foreach ($selectedIds as $resourceId) {
    $resource = $byId[$resourceId] ?? null;
    if ($resource === null) {
        continue;
    }
    $type = (string) ($resource['resource_type'] ?? '');
    if ($type !== TenantResource::TYPE_MAILBOX) {
        continue;
    }
    $gid = (string) ($resource['graph_id'] ?? TenantResource::graphIdFromResourceId($resourceId));
    if ($gid !== '' && !in_array($gid, $protectedIds, true)) {
        $excludedMailboxIds[] = $gid;
    }
}

$typeCounts = [];
foreach ($inventory['resources'] ?? [] as $resource) {
    if (!is_array($resource)) {
        continue;
    }
    $t = (string) ($resource['resource_type'] ?? 'unknown');
    $typeCounts[$t] = ($typeCounts[$t] ?? 0) + 1;
}

$cometCount = null;
$cometUsername = Capsule::table('s3_backup_users')->where('id', $backupUserId)->value('username');
$whmcsSvc = Capsule::table('tblhosting')
    ->where('userid', $clientId)
    ->where('username', $cometUsername)
    ->first();
if ($whmcsSvc && Capsule::schema()->hasTable('comet_items')) {
    $items = Capsule::table('comet_items')
        ->where('client_id', $clientId)
        ->where('type', 'engine1/winmsofficemail')
        ->get();
    $cometTotal = 0;
    foreach ($items as $item) {
        $content = json_decode((string) ($item->content ?? ''), true);
        if (!is_array($content)) {
            continue;
        }
        $stats = $content['Statistics']['LastBackupJob']
            ?? $content['Statistics']['LastSuccessfulBackupJob']
            ?? [];
        $n = (int) ($stats['TotalAccountsCount'] ?? 0);
        if ($n > 0) {
            $cometTotal += $n;
        }
    }
    if ($cometTotal > 0) {
        $cometCount = $cometTotal;
    }
}

$recon = $resolution['reconciliation'] ?? [];

echo "=== Billing model analysis ===\n";
echo "Client ID: {$clientId}\n";
echo "Backup user ID: {$backupUserId} (username: {$cometUsername})\n";
echo "WHMCS service: " . ($whmcsSvc->id ?? 'n/a') . "\n\n";

echo "--- Counts ---\n";
echo sprintf("Live meter (Protected Users):              %d\n", $liveCount);
echo sprintf("Resolver (EXCEPTION-SM + job exempt):      %d\n", count($protectedIds));
if ($explicitMailboxIds !== []) {
    echo sprintf("Simulation (cleared explicit mailboxes):   %d\n", count($simIds));
}
echo sprintf("Legacy-absent exempt (all mailboxes off):  %d\n", count($legacyAbsentIds));
echo sprintf("Billing-exempt mailbox resource IDs:       %d\n", count($billingExemptIds));
echo sprintf("Comet protected users (if found):        %s\n", $cometCount !== null ? (string) $cometCount : 'n/a');
echo sprintf("DB snapshot latest:                        %d\n", (int) Capsule::table('ms365_billing_usage_snapshots')
    ->where('backup_user_id', $backupUserId)
    ->where('metric', 'protected_users')
    ->orderBy('id', 'desc')
    ->value('qty'));

echo "\n--- Reconciliation ---\n";
echo json_encode($recon, JSON_PRETTY_PRINT) . "\n";

echo "\n--- Selection summary ---\n";
echo "Selected resource IDs: " . count($selectedIds) . "\n";
echo "Inventory type counts: " . json_encode($typeCounts) . "\n";
echo "Excluded mailbox Azure IDs (not billed): " . count($excludedMailboxIds) . "\n";
echo "Membership sources in breakdown: " . count($resolution['breakdown'] ?? []) . "\n";

if ($excludedMailboxIds !== []) {
    echo "\n--- Sample non-billed mailboxes (up to 15) ---\n";
    $shown = 0;
    foreach ($excludedMailboxIds as $gid) {
        $row = $userIndex[$gid] ?? ['display_name' => $gid];
        echo '  ' . ($row['display_name'] ?? $gid) . " ({$gid})\n";
        if (++$shown >= 15) {
            echo '  ... and ' . (count($excludedMailboxIds) - 15) . " more\n";
            break;
        }
    }
}

$breakdown = $resolution['breakdown'] ?? [];
usort($breakdown, static fn ($a, $b) => ($b['member_count'] ?? 0) <=> ($a['member_count'] ?? 0));
echo "\n--- Top membership sources ---\n";
foreach (array_slice($breakdown, 0, 10) as $row) {
    echo sprintf("  %s: %d members\n", $row['label'] ?? $row['resource_id'], (int) ($row['member_count'] ?? 0));
}

if ($cometCount !== null) {
    echo "\n--- Gap vs Comet ({$cometCount}) ---\n";
    echo sprintf("  Protected Users - Comet:  %+d\n", count($protectedIds) - $cometCount);
}
