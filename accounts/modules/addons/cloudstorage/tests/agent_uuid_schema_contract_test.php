<?php
$src = file_get_contents(__DIR__ . '/../cloudstorage.php');
if ($src === false) {
    throw new RuntimeException('failed to read cloudstorage.php');
}

if (strpos($src, "create('s3_cloudbackup_agents'") === false) {
    throw new RuntimeException('agents schema create block missing');
}
if (strpos($src, "'agent_uuid'") === false) {
    throw new RuntimeException('agent_uuid column contract missing');
}
if (strpos($src, "hasColumn('s3_cloudbackup_agents', 'agent_uuid')") === false) {
    throw new RuntimeException('agent_uuid migration guard for existing installs missing');
}
if (strpos($src, 'cloudstorage_repair_agent_uuid_schema') === false) {
    throw new RuntimeException('agent_uuid repair helper missing');
}
if (strpos($src, "unsignedInteger('agent_id'") !== false || strpos($src, "unsignedBigInteger('agent_id'") !== false) {
    throw new RuntimeException('legacy agent_id schema contract still present');
}
echo "schema-contract-ok\n";
