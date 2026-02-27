<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();

// Per-client daily snapshot for dashboard device growth trends.
$sql = "INSERT INTO eb_devices_client_daily (d, client_id, registered, online)
SELECT CURRENT_DATE() AS d,
       d.client_id AS client_id,
       COUNT(*) AS registered,
       SUM(CASE WHEN d.is_active = 1 THEN 1 ELSE 0 END) AS online
FROM comet_devices d
WHERE d.client_id > 0
  AND d.revoked_at IS NULL
  AND EXISTS (
      SELECT 1
      FROM tblhosting h
      WHERE h.userid = d.client_id
        AND BINARY h.username = BINARY d.username
        AND h.domainstatus = 'Active'
  )
GROUP BY d.client_id
ON DUPLICATE KEY UPDATE
  registered = VALUES(registered),
  online = VALUES(online),
  updated_at = NOW()";

$pdo->exec($sql);

try {
    $rc = (int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn();
    logLine(sprintf('[rollup] devices_client_daily d=TODAY rc=%d', $rc));
} catch (Throwable $e) {
    logLine('[rollup] devices_client_daily completed');
}
