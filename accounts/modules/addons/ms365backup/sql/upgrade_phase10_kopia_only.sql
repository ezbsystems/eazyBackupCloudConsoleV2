-- Kopia-only engine: backfill legacy php/kopia_shadow settings.
UPDATE `tbladdonmodules`
SET `value` = 'kopia'
WHERE `module` = 'ms365backup' AND `setting` = 'ms365_engine_mode';
