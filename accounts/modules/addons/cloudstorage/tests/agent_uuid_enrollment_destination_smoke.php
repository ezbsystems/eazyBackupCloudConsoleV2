<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/CloudBackupBootstrapService.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupBootstrapService;

if (!defined('WHMCS')) {
    throw new RuntimeException('WHMCS runtime is required');
}

function smokeUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

$schema = Capsule::schema();
if (!$schema->hasTable('s3_cloudbackup_agents') || !$schema->hasTable('s3_cloudbackup_agent_destinations')) {
    throw new RuntimeException('required cloud backup tables are missing');
}
if (!$schema->hasColumn('s3_cloudbackup_agents', 'agent_uuid')) {
    throw new RuntimeException('s3_cloudbackup_agents.agent_uuid is missing');
}
if (!$schema->hasColumn('s3_cloudbackup_agent_destinations', 'agent_uuid')) {
    throw new RuntimeException('s3_cloudbackup_agent_destinations.agent_uuid is missing');
}

$agentUuid = smokeUuid();
$agentId = 0;
$destInserted = false;

try {
    $agentId = (int) Capsule::table('s3_cloudbackup_agents')->insertGetId([
        'agent_uuid' => $agentUuid,
        'client_id' => 1,
        'tenant_id' => null,
        'tenant_user_id' => null,
        'agent_token' => bin2hex(random_bytes(20)),
        'hostname' => 'uuid-smoke',
        'device_id' => 'uuid-smoke-device',
        'status' => 'active',
        'agent_type' => 'workstation',
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

    Capsule::statement('SET FOREIGN_KEY_CHECKS=0');
    $payload = [
        'agent_uuid' => $agentUuid,
        'client_id' => 1,
        'tenant_id' => null,
        's3_user_id' => 0,
        'dest_bucket_id' => 0,
        'root_prefix' => 'uuid-smoke-prefix',
        'is_locked' => 1,
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ];
    if ($schema->hasColumn('s3_cloudbackup_agent_destinations', 'agent_id')) {
        $payload['agent_id'] = $agentId;
    }
    Capsule::table('s3_cloudbackup_agent_destinations')->insert($payload);
    $destInserted = true;
    Capsule::statement('SET FOREIGN_KEY_CHECKS=1');

    $result = CloudBackupBootstrapService::ensureAgentDestination($agentUuid);
    if (($result['status'] ?? 'fail') !== 'success') {
        throw new RuntimeException('ensureAgentDestination failed for UUID smoke identity');
    }
    $dest = $result['destination'] ?? null;
    if (!$dest || (string) ($dest->agent_uuid ?? '') !== $agentUuid) {
        throw new RuntimeException('destination bootstrap did not resolve by agent_uuid');
    }
} finally {
    try {
        Capsule::statement('SET FOREIGN_KEY_CHECKS=0');
        if ($destInserted) {
            Capsule::table('s3_cloudbackup_agent_destinations')->where('agent_uuid', $agentUuid)->delete();
        }
        if ($agentId > 0) {
            Capsule::table('s3_cloudbackup_agents')->where('id', $agentId)->delete();
        }
        Capsule::statement('SET FOREIGN_KEY_CHECKS=1');
    } catch (\Throwable $cleanupError) {
        // Ignore cleanup failures in smoke mode.
    }
}

echo "agent-uuid-enrollment-destination-smoke-ok\n";
