-- MS365 bucket-per-job storage and job-scoped delta state.

ALTER TABLE `ms365_delta_state`
  ADD COLUMN `e3_job_id` CHAR(36) NULL DEFAULT NULL AFTER `tenant_record_id`;

ALTER TABLE `ms365_delta_state`
  DROP INDEX `uniq_ms365_delta`;

ALTER TABLE `ms365_delta_state`
  ADD UNIQUE KEY `uniq_ms365_delta` (`tenant_record_id`, `e3_job_id`, `physical_key`, `workload`, `state_key`);

ALTER TABLE `ms365_delta_state`
  ADD KEY `idx_ms365_delta_job` (`e3_job_id`, `tenant_record_id`);
