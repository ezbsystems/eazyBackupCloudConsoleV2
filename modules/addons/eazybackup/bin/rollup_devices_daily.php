<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();

// Registered devices right now (active only)
$registered = (int)$pdo->query("
    SELECT COUNT(*) AS c
    FROM eb_devices_registry
    WHERE status = 'active'
")->fetchColumn();

// Active in last 24h (based on last_seen)
$active24 = (int)$pdo->query("
    SELECT COUNT(*) AS c
    FROM eb_devices_registry
    WHERE status = 'active'
      AND last_seen >= UNIX_TIMESTAMP() - 86400
")->fetchColumn();

// Upsert today’s snapshot
$stmt = $pdo->prepare("
    REPLACE INTO eb_devices_daily (d, registered, active_24h)
    VALUES (CURRENT_DATE(), :registered, :active24)
");
$stmt->execute([':registered' => $registered, ':active24' => $active24]);

logLine("[rollup] devices_daily d=TODAY registered={$registered} active24h={$active24}");

// (Optional) Light housekeeping for recent jobs (keep 48h)
$pdo->exec("DELETE FROM eb_jobs_recent_24h WHERE ended_at < (UNIX_TIMESTAMP() - 172800)");
