<?php
$tpl = file_get_contents(__DIR__ . '/../templates/e3backup_agents.tpl');
if (strpos($tpl, 'Agent #') !== false) { throw new RuntimeException('legacy Agent # label still present'); }
if (strpos($tpl, 'agent_id') !== false) { throw new RuntimeException('agent_id token still present'); }
echo "ui-ok\n";
