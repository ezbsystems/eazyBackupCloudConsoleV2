#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * One-time / ops CLI to scan and remediate orphaned e3 backup resources.
 *
 * Usage:
 *   php accounts/modules/addons/cloudstorage/bin/e3backup_orphan_remediate.php --dry-run
 *   php accounts/modules/addons/cloudstorage/bin/e3backup_orphan_remediate.php --apply
 *   php accounts/modules/addons/cloudstorage/bin/e3backup_orphan_remediate.php --apply --client-id=123
 */

$repoRoot = dirname(__DIR__, 4);
require_once $repoRoot . '/init.php';
require_once __DIR__ . '/../lib/Admin/E3BackupOrphanRemediation.php';

use WHMCS\Module\Addon\CloudStorage\Admin\E3BackupOrphanRemediation;

$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;
$clientId = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--client-id=') === 0) {
        $clientId = (int) substr($arg, strlen('--client-id='));
    }
}

$scan = E3BackupOrphanRemediation::scan($clientId !== null && $clientId > 0 ? $clientId : null);
$total = 0;
foreach ($scan as $type => $items) {
    $count = count($items);
    $total += $count;
    echo strtoupper($type) . ': ' . $count . PHP_EOL;
}

if ($total === 0) {
    echo "No orphans detected.\n";
    exit(0);
}

echo ($dryRun ? 'Dry-run' : 'Apply') . " remediation for {$total} item(s)...\n";
$ok = 0;
$fail = 0;
foreach ($scan as $items) {
    foreach ($items as $item) {
        $result = E3BackupOrphanRemediation::remediate($item, $dryRun);
        $status = (string) ($result['status'] ?? 'fail');
        $label = (string) ($item['type'] ?? 'item');
        if ($status === 'success') {
            $ok++;
            echo "[OK] {$label}\n";
        } else {
            $fail++;
            echo "[FAIL] {$label}: " . (string) ($result['message'] ?? 'unknown') . "\n";
        }
    }
}

echo "Done. ok={$ok} fail={$fail}\n";
exit($fail > 0 ? 1 : 0);
