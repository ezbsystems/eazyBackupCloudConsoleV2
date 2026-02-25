<?php
$tpl = file_get_contents(__DIR__ . '/../templates/e3backup_agents.tpl');
$legacyAgentLabel = 'Agent ' . '#';
if (strpos($tpl, $legacyAgentLabel) !== false) { throw new RuntimeException('legacy Agent label still present'); }
if (strpos($tpl, 'agent_id') !== false) { throw new RuntimeException('agent_id token still present'); }
$obfuscatedAgentId = "agent' + '_id";
if (strpos($tpl, $obfuscatedAgentId) !== false) { throw new RuntimeException('obfuscated agent_id token still present'); }

$cloudnasTpl = file_get_contents(__DIR__ . '/../templates/e3backup_cloudnas.tpl');
if (strpos($cloudnasTpl, 'agent_id:') !== false) { throw new RuntimeException('cloudnas tpl still posts agent_id'); }
if (strpos($cloudnasTpl, 'selectedAgentId') !== false) { throw new RuntimeException('cloudnas tpl still tracks selectedAgentId'); }
echo "ui-ok\n";
