<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();

/** Safe JSON decode to array */
function j($v): array {
    if ($v === null || $v === '') return [];
    if (is_array($v)) return $v;
    $d = json_decode((string)$v, true);
    return is_array($d) ? $d : [];
}

/** From an item content blob, pull the “best” stats block */
function stats(array $content): array {
    // Prefer last job; fall back to last successful
    if (isset($content['Statistics']['LastBackupJob']) && is_array($content['Statistics']['LastBackupJob'])) {
        return $content['Statistics']['LastBackupJob'];
    }
    if (isset($content['Statistics']['LastSuccessfulBackupJob']) && is_array($content['Statistics']['LastSuccessfulBackupJob'])) {
        return $content['Statistics']['LastSuccessfulBackupJob'];
    }
    return [];
}

/** Count Microsoft 365 users: prefer stats.TotalAccountsCount; else CUSTOM_SETTINGV2 user GUIDs */
function countM365Users(array $content): int {
    $s = stats($content);
    if (!empty($s['TotalAccountsCount']) && is_numeric($s['TotalAccountsCount'])) {
        return (int)$s['TotalAccountsCount'];
    }

    // Fallback: EngineProps.CUSTOM_SETTINGV2 is a JSON string; count user GUID keys (no commas)
    $props = $content['EngineProps'] ?? [];
    $cfg   = isset($props['CUSTOM_SETTINGV2']) ? j($props['CUSTOM_SETTINGV2']) : [];
    $users = [];

    // BackupOptions: keys are identifiers; user GUIDs have no commas, SharePoint site identifiers contain commas.
    if (!empty($cfg['BackupOptions']) && is_array($cfg['BackupOptions'])) {
        foreach (array_keys($cfg['BackupOptions']) as $k) {
            if (strpos($k, ',') === false) { // user-like id
                $users[$k] = true;
            }
        }
    }
    // MemberBackupOptions: also user GUIDs
    if (!empty($cfg['MemberBackupOptions']) && is_array($cfg['MemberBackupOptions'])) {
        foreach (array_keys($cfg['MemberBackupOptions']) as $k) {
            if (strpos($k, ',') === false) {
                $users[$k] = true;
            }
        }
    }
    // If nothing found, bill at least 1 per item to avoid silent undercount
    return max(1, count($users));
}

/** Count VMs for Hyper-V / VMware: prefer stats.TotalVmCount; else fallback 1 */
function countVMs(array $content): int {
    $s = stats($content);
    if (isset($s['TotalVmCount']) && is_numeric($s['TotalVmCount'])) {
        $n = (int)$s['TotalVmCount'];
        if ($n > 0) return $n;
    }
    return 1;
}

// -----------------------------------------------------------------------------
// Tally across all items (global). If you want per-client rows, loop DISTINCT client_id.
// -----------------------------------------------------------------------------

$sql = "SELECT type, comet_device_id, content FROM comet_items";
$rows = $pdo->query($sql)->fetchAll();

$di_devices_set = [];
$hv_vms     = 0;
$vw_vms     = 0;
$m365_users = 0;
$ff_items   = 0;

foreach ($rows as $r) {
    $type    = (string)$r['type'];
    $content = j($r['content']);

    switch ($type) {
        case 'engine1/windisk': // Disk Image — billed per device
            $did = (string)($r['comet_device_id'] ?? '');
            $di_devices_set[$did !== '' ? $did : uniqid('nodev_', true)] = true;
            break;

        case 'engine1/hyperv': // Microsoft Hyper-V — billed per VM
            $hv_vms += countVMs($content);
            break;

        case 'engine1/vmware': // VMware vSphere — billed per VM
            $vw_vms += countVMs($content);
            break;

        case 'engine1/winmsofficemail': // Microsoft 365 — billed per user
            $m365_users += countM365Users($content);
            break;

        case 'engine1/file': // Files and Folders — included
            $ff_items += 1;
            break;

        default:
            // Other engines currently not billed in your scheme; ignore or add as needed.
            break;
    }
}

$di_devices = count($di_devices_set);

// Upsert today’s snapshot
$stmt = $pdo->prepare("
    REPLACE INTO eb_items_daily (d, di_devices, hv_vms, vw_vms, m365_users, ff_items)
    VALUES (CURRENT_DATE(), :di, :hv, :vw, :m365, :ff)
");
$stmt->execute([
    ':di'   => $di_devices,
    ':hv'   => $hv_vms,
    ':vw'   => $vw_vms,
    ':m365' => $m365_users,
    ':ff'   => $ff_items,
]);

logLine(sprintf(
    "[rollup] items_daily d=TODAY di=%d hv=%d vw=%d m365=%d ff=%d",
    $di_devices, $hv_vms, $vw_vms, $m365_users, $ff_items
));
