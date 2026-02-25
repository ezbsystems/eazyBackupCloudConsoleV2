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

$dashboardSrc = file_get_contents(__DIR__ . '/../pages/e3backup_dashboard.php');
if (strpos($dashboardSrc, "j.agent_id") !== false || strpos($dashboardSrc, "a.id") !== false) {
    throw new RuntimeException('e3backup_dashboard.php still contains numeric job->agent join');
}

$cutoverSrc = file_get_contents(__DIR__ . '/../scripts/agent_uuid_bigbang_cutover.php');
if (strpos($cutoverSrc, 'agent_id') !== false) {
    throw new RuntimeException('agent_uuid_bigbang_cutover.php still contains legacy agent_id compatibility bridge');
}

echo "route-ok\n";
