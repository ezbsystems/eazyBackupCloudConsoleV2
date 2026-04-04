<?php

declare(strict_types=1);

/**
 * Contract test: semantic e3 shell migration markers for `e3backup_users.tpl`.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/cloudstorage_users_semantic_migration_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);
$templateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/templates/e3backup_users.tpl';

$source = @file_get_contents($templateFile);
if ($source === false) {
    echo 'FAIL: unable to read e3backup users template' . PHP_EOL;
    exit(1);
}

$requiredMarkers = [
    'e3 shell include marker' => 'modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl',
    'users sidebar marker' => "ebE3SidebarPage='users'",
    'page header include marker' => '$template/includes/ui/page-header.tpl',
    'table toolbar marker' => 'eb-table-toolbar',
    'table shell marker' => 'eb-table-shell',
    'table marker' => 'class="eb-table"',
    'table sort button marker' => 'eb-table-sort-button',
    'table pagination marker' => 'eb-table-pagination',
    'empty state marker' => 'eb-app-empty',
];

$forbiddenMarkers = [
    'legacy page wrapper marker' => 'min-h-screen bg-slate-950',
    'legacy surfaced panel marker' => 'rounded-3xl border border-slate-800/80 bg-slate-950',
    'legacy rounded search marker' => 'rounded-full bg-slate-900/70 border border-slate-700',
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

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "cloudstorage-users-semantic-migration-contract-ok\n";
exit(0);
