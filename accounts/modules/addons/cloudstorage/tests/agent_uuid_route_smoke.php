<?php

$apiChecks = [
    'cloudbackup_create_job.php' => ['agent_id'],
    'cloudbackup_start_restore.php' => ['agent_id'],
    'agent_browse_filesystem.php' => ['agent_id'],
    'e3backup_agent_list.php' => ['a.id'],
    'e3backup_agent_toggle.php' => ["\$_POST['agent_id']"],
    'agent_delete.php' => ["\$_POST['agent_id']", "'agent_id' =>", 'agent_id is required'],
    'cloudnas_create_mount.php' => ["input['agent_id']", 'agent ID are required'],
    'cloudnas_mount_snapshot.php' => ["input['agent_id']", 'agent ID are required'],
    'cloudnas_unmount_snapshot.php' => ["input['agent_id']", 'agent ID are required'],
];

foreach ($apiChecks as $file => $forbiddenTokens) {
    $src = file_get_contents(__DIR__ . '/../api/' . $file);
    foreach ($forbiddenTokens as $token) {
        if (strpos($src, $token) !== false) {
            throw new RuntimeException("$file still contains forbidden token: $token");
        }
    }
}

$task13AuthChecks = [
    'agent_fetch_jobs.php' => ['HTTP_X_AGENT_UUID', "where('agent_uuid'"],
    'agent_poll_commands.php' => ['HTTP_X_AGENT_UUID', "where('agent_uuid'"],
    'agent_start_run.php' => ['HTTP_X_AGENT_UUID', "where('agent_uuid'"],
    'agent_report_browse.php' => ['HTTP_X_AGENT_UUID', "where('agent_uuid'"],
    'agent_poll_repo_operations.php' => ['HTTP_X_AGENT_UUID', "where('agent_uuid'"],
    'agent_complete_repo_operation.php' => ['HTTP_X_AGENT_UUID', "where('agent_uuid'"],
    'cloudnas_update_status.php' => ['HTTP_X_AGENT_UUID', "where('agent_uuid'"],
    'agent_disable.php' => ["\$_POST['agent_uuid']", "where('agent_uuid'"],
];
foreach ($task13AuthChecks as $file => $requiredTokens) {
    $src = file_get_contents(__DIR__ . '/../api/' . $file);
    if (strpos($src, 'HTTP_X_AGENT_ID') !== false || strpos($src, "\$_SERVER['HTTP_X_AGENT_ID']") !== false) {
        throw new RuntimeException("$file still accepts legacy HTTP_X_AGENT_ID");
    }
    if (strpos($src, "\$_POST['agent_id']") !== false) {
        throw new RuntimeException("$file still accepts legacy \$_POST['agent_id']");
    }
    if (preg_match('/where\(\'id\',\s*\$agentId\)/', $src) === 1) {
        throw new RuntimeException("$file still performs direct auth lookup by numeric agent id");
    }
    foreach ($requiredTokens as $token) {
        if (strpos($src, $token) === false) {
            throw new RuntimeException("$file missing UUID auth token: $token");
        }
    }
}

$dashboardSrc = file_get_contents(__DIR__ . '/../pages/e3backup_dashboard.php');
if (strpos($dashboardSrc, "j.agent_id") !== false || strpos($dashboardSrc, "a.id") !== false) {
    throw new RuntimeException('e3backup_dashboard.php still contains numeric job->agent join');
}

$cutoverSrc = file_get_contents(__DIR__ . '/../scripts/agent_uuid_bigbang_cutover.php');
if (strpos($cutoverSrc, 'agent_id') !== false) {
    throw new RuntimeException('agent_uuid_bigbang_cutover.php still contains legacy agent_id compatibility bridge');
}

$pageChecks = [
    'e3backup_jobs.php',
    'e3backup_restores.php',
    'e3backup_disk_image_restore.php',
];
foreach ($pageChecks as $page) {
    $src = file_get_contents(__DIR__ . '/../pages/' . $page);
    if (strpos($src, 'agent_uuid') === false) {
        throw new RuntimeException("$page missing agent_uuid loader contract");
    }
    if (strpos($src, "->get(['id'") !== false) {
        throw new RuntimeException("$page still selects numeric id for agents dropdown/filter");
    }
}

$diskTpl = file_get_contents(__DIR__ . '/../templates/e3backup_disk_image_restore.tpl');
if (strpos($diskTpl, "params.set('agent_id'") !== false || strpos($diskTpl, "\$a->id") !== false) {
    throw new RuntimeException('disk image restore template still uses numeric agent identity');
}

echo "route-ok\n";
