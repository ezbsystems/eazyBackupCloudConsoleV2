<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();

// Sum active Comet vaults (types 1000 and 1003) per user and keep the day's maxima
$sql = "INSERT INTO eb_storage_daily (d, client_id, username, bytes_total, bytes_t1000, bytes_t1003)
SELECT CURRENT_DATE(), COALESCE(client_id,0) AS client_id, username,
       COALESCE(SUM(total_bytes),0) AS bytes_total,
       COALESCE(SUM(CASE WHEN type=1000 THEN total_bytes ELSE 0 END),0) AS bytes_t1000,
       COALESCE(SUM(CASE WHEN type=1003 THEN total_bytes ELSE 0 END),0) AS bytes_t1003
FROM comet_vaults
WHERE is_active = 1 AND type IN (1000, 1003)
GROUP BY COALESCE(client_id,0), username
ON DUPLICATE KEY UPDATE
  bytes_total = GREATEST(eb_storage_daily.bytes_total, VALUES(bytes_total)),
  bytes_t1000 = GREATEST(eb_storage_daily.bytes_t1000, VALUES(bytes_t1000)),
  bytes_t1003 = GREATEST(eb_storage_daily.bytes_t1003, VALUES(bytes_t1003)),
  updated_at = NOW()";

$pdo->exec($sql);

// Optional: log a compact line
try {
    $rc = (int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn();
    logLine(sprintf('[rollup] storage_daily d=TODAY rc=%d', $rc));
} catch (Throwable $e) {
    logLine('[rollup] storage_daily completed');
}

 
