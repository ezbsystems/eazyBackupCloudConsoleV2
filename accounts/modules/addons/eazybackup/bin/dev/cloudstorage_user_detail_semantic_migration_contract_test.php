<?php

declare(strict_types=1);

/**
 * Contract test: semantic e3 shell migration markers for `e3backup_user_detail.tpl`.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/cloudstorage_user_detail_semantic_migration_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);
$templateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/templates/e3backup_user_detail.tpl';

$source = @file_get_contents($templateFile);
if ($source === false) {
    echo 'FAIL: unable to read e3backup user detail template' . PHP_EOL;
    exit(1);
}

$requiredMarkers = [
    'e3 shell include marker' => 'modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl',
    'users sidebar marker' => "ebE3SidebarPage='users'",
    'page title assign marker' => 'ebE3Title=',
    'page description assign marker' => 'ebE3Description=',
    'page header include marker' => '$template/includes/ui/page-header.tpl',
    'card shell marker' => 'eb-card',
    'alert shell marker' => 'eb-alert',
];

$forbiddenMarkers = [
    'legacy page wrapper marker' => 'min-h-screen bg-slate-950',
    'legacy nav partial marker' => 'modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl',
    'legacy surfaced panel marker' => 'rounded-3xl border border-slate-800/80 bg-slate-950/80',
];

$failures = [];

foreach ($requiredMarkers as $label => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = 'FAIL: missing ' . $label;
    }
}

foreach ($forbiddenMarkers as $label => $needle) {
    if (strpos($source, $needle) !== false) {
        $failures[] = 'FAIL: found forbidden ' . $label;
    }
}

$hasDangerButton = strpos($source, 'eb-btn-danger') !== false
    || strpos($source, 'eb-btn-danger-solid') !== false;
if (!$hasDangerButton) {
    $failures[] = 'FAIL: missing destructive action button marker (eb-btn-danger or eb-btn-danger-solid)';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "cloudstorage-user-detail-semantic-migration-contract-ok\n";
exit(0);
