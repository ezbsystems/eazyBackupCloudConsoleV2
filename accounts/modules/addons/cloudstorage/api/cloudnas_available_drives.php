<?php
/**
 * Cloud NAS - Available Drive Letters
 *
 * Returns drive letters that are NOT in use on the selected agent's host.
 * Combines the agent's reported volumes with existing Cloud NAS mounts to
 * produce the set of letters the user can safely choose.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

$agentUuid = trim($_GET['agent_uuid'] ?? '');
if ($agentUuid === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'agent_uuid is required'], 200))->send();
    exit;
}

try {
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->where('client_id', $clientId)
        ->first();

    if (!$agent) {
        (new JsonResponse(['status' => 'error', 'message' => 'Agent not found'], 200))->send();
        exit;
    }

    // Letters used on the host PC (from agent's periodic volume report)
    $hostUsedLetters = [];
    $hasVolumesCol = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'volumes_json');
    if ($hasVolumesCol && !empty($agent->volumes_json)) {
        $volumes = json_decode($agent->volumes_json, true);
        if (is_array($volumes)) {
            foreach ($volumes as $vol) {
                $path = strtoupper(trim($vol['path'] ?? ''));
                if (preg_match('/^([A-Z]):/', $path, $m)) {
                    $hostUsedLetters[$m[1]] = true;
                }
            }
        }
    }

    // Letters used by existing Cloud NAS mounts for this agent
    $mountLetters = Capsule::table('s3_cloudnas_mounts')
        ->where('client_id', $clientId)
        ->where('agent_id', (int) $agent->id)
        ->pluck('drive_letter')
        ->toArray();

    foreach ($mountLetters as $letter) {
        $hostUsedLetters[strtoupper($letter)] = true;
    }

    // Build the available list (D-Z, skip A-C as they are almost always reserved)
    $allLetters = range('D', 'Z');
    $available = [];
    foreach (array_reverse($allLetters) as $letter) {
        if (!isset($hostUsedLetters[$letter])) {
            $available[] = $letter;
        }
    }

    (new JsonResponse([
        'status'    => 'success',
        'available' => $available,
        'host_used' => array_keys($hostUsedLetters),
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_available_drives error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to load available drive letters'], 200))->send();
}
exit;
