<?php
$files = [
  'cloudbackup_create_job.php',
  'cloudbackup_start_restore.php',
  'agent_browse_filesystem.php',
];
foreach ($files as $f) {
  $src = file_get_contents(__DIR__ . '/../api/' . $f);
  if (strpos($src, 'agent_id') !== false) {
    throw new RuntimeException("$f still contains agent_id");
  }
}
echo "route-ok\n";
