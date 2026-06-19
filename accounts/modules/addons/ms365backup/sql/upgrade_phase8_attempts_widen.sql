-- Widen ms365_job_queue.attempts / max_attempts from TINYINT (max 255) to
-- SMALLINT so a thrashing run can no longer wrap the attempts counter.
-- Idempotent: re-running just re-applies the same column definition.
ALTER TABLE `ms365_job_queue`
  MODIFY COLUMN `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY COLUMN `max_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 3;
