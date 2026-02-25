<?php
$tpl = file_get_contents(__DIR__ . '/../templates/e3backup_agents.tpl');
$legacyAgentLabel = 'Agent ' . '#';
if (strpos($tpl, $legacyAgentLabel) !== false) { throw new RuntimeException('legacy Agent label still present'); }
if (strpos($tpl, 'agent_id') !== false) { throw new RuntimeException('agent_id token still present'); }
$obfuscatedAgentId = "agent' + '_id";
if (strpos($tpl, $obfuscatedAgentId) !== false) { throw new RuntimeException('obfuscated agent_id token still present'); }
if (strpos($tpl, '>Agent UUID<') === false) { throw new RuntimeException('agents header must show Agent UUID'); }
if (strpos($tpl, '>ID<') !== false) { throw new RuntimeException('legacy ID header still present on agents table'); }
if (strpos($tpl, 'x-text="agent.agent_uuid') === false) { throw new RuntimeException('agents identity cell must bind agent_uuid'); }
if (strpos($tpl, 'agent.id') !== false) { throw new RuntimeException('agents template still references agent.id'); }

$cloudnasTpl = file_get_contents(__DIR__ . '/../templates/e3backup_cloudnas.tpl');
if (strpos($cloudnasTpl, 'agent_id:') !== false) { throw new RuntimeException('cloudnas tpl still posts agent_id'); }
if (strpos($cloudnasTpl, 'selectedAgentId') !== false) { throw new RuntimeException('cloudnas tpl still tracks selectedAgentId'); }

$diskTpl = file_get_contents(__DIR__ . '/../templates/e3backup_disk_image_restore.tpl');
if (strpos($diskTpl, "\$a->id") !== false) { throw new RuntimeException('disk image restore dropdown still uses numeric id'); }
if (strpos($diskTpl, "params.set('agent_id'") !== false) { throw new RuntimeException('disk image restore still uses agent_id query param'); }
echo "ui-ok\n";
