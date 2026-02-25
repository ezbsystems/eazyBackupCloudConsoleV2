<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$initPath = '/var/www/eazybackup.ca/accounts/init.php';
if (!is_file($initPath)) {
    fwrite(STDERR, "WHMCS init.php not found at {$initPath}\n");
    exit(1);
}

require_once $initPath;
require_once dirname(__DIR__) . '/cloudstorage.php';

if (!function_exists('cloudstorage_repair_agent_uuid_schema')) {
    fwrite(STDERR, "cloudstorage_repair_agent_uuid_schema() is missing.\n");
    exit(1);
}

echo "repair-agent-uuid-schema:start\n";
cloudstorage_repair_agent_uuid_schema('manual_repair_script');

$hasAgentUuid = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_uuid');
echo ($hasAgentUuid ? 'has-agent_uuid' : 'missing-agent_uuid') . PHP_EOL;

$emptyAgentUuid = (int) Capsule::table('s3_cloudbackup_agents')
    ->where(function ($q) {
        $q->whereNull('agent_uuid')->orWhere('agent_uuid', '');
    })
    ->count();
echo 'empty-agent_uuid=' . $emptyAgentUuid . PHP_EOL;
echo "repair-agent-uuid-schema:done\n";
