<?php
declare(strict_types=1);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/ms365backup_autoload.php';

use Ms365Backup\Fleet\DeployService;
use Ms365Backup\Fleet\FleetAuditLog;
use Ms365Backup\Fleet\FleetContext;
use Ms365Backup\Fleet\FleetProvisionService;
use Ms365Backup\Fleet\FleetRemoteAuth;
use Ms365Backup\Fleet\FleetSettings;
use Ms365Backup\Fleet\FleetSummaryService;
use Ms365Backup\Fleet\ReleaseRepository;
use Ms365Backup\Fleet\WorkerConfigService;
use Ms365Backup\Ms365BatchClaimRepository;
use Ms365Backup\WorkerNodeRepository;

$authError = FleetRemoteAuth::authenticate();
if ($authError !== null) {
    http_response_code((int) ($authError['code'] ?? 401));
    echo json_encode(['ok' => false, 'error' => (string) ($authError['error'] ?? 'Unauthorized')]);
    exit;
}

$op = (string) ($_GET['op'] ?? $_POST['op'] ?? '');

try {
    switch ($op) {
        case 'fleet_summary':
            echo json_encode(['ok' => true, 'summary' => FleetSummaryService::summary()]);
            break;

        case 'fleet_nodes':
            $status = trim((string) ($_GET['status'] ?? $_POST['status'] ?? ''));
            $statuses = $status !== '' ? array_map('trim', explode(',', $status)) : [];
            echo json_encode(['ok' => true, 'nodes' => WorkerNodeRepository::listNodes($statuses)]);
            break;

        case 'fleet_node_get':
            $nodeId = trim((string) ($_GET['node_id'] ?? $_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            echo json_encode(['ok' => true, 'node' => WorkerNodeRepository::get($nodeId)]);
            break;

        case 'fleet_node_drain':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            WorkerNodeRepository::drain($nodeId);
            FleetAuditLog::write('node_drain', 'Node set to draining', 'node', $nodeId);
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_activate':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            WorkerNodeRepository::activate($nodeId);
            FleetAuditLog::write('node_activate', 'Node reactivated from draining', 'node', $nodeId);
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_retire':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            WorkerNodeRepository::retire($nodeId);
            FleetAuditLog::write('node_retire', 'Node retired', 'node', $nodeId);
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_delete':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            if (!WorkerNodeRepository::deleteRetired($nodeId)) {
                throw new \RuntimeException('Only retired nodes can be deleted');
            }
            FleetAuditLog::write('node_delete', 'Retired node removed', 'node', $nodeId);
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_set_vmid':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            $vmid = (int) ($_POST['proxmox_vmid'] ?? 0);
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            if ($vmid <= 0) {
                throw new \RuntimeException('proxmox_vmid must be a positive integer');
            }
            WorkerNodeRepository::setProxmoxVmid($nodeId, $vmid);
            FleetAuditLog::write('node_set_vmid', 'Set Proxmox VMID to ' . $vmid, 'node', $nodeId);
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_stop':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            WorkerNodeRepository::stop($nodeId);
            FleetAuditLog::write('node_stop', 'Worker container stopped (DB)', 'node', $nodeId);
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_start':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            WorkerNodeRepository::start($nodeId);
            FleetAuditLog::write('node_start', 'Worker container started (DB)', 'node', $nodeId);
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_release_leases':
            $batchesReaped = Ms365BatchClaimRepository::reapStaleBatches();
            echo json_encode(['ok' => true, 'released' => 0, 'recovered' => $batchesReaped, 'orphans_requeued' => 0, 'batches_reaped' => $batchesReaped]);
            break;

        case 'fleet_settings_get':
            echo json_encode(['ok' => true, 'settings' => FleetSettings::publicConfig(FleetContext::FLEET_PRODUCTION)]);
            break;

        case 'fleet_audit':
            echo json_encode(['ok' => true, 'entries' => FleetAuditLog::recent((int) ($_GET['limit'] ?? 50))]);
            break;

        case 'fleet_node_telemetry':
            $nodeId = trim((string) ($_GET['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            $limit = max(1, min(500, (int) ($_GET['limit'] ?? 96)));
            echo json_encode([
                'ok' => true,
                'node' => WorkerNodeRepository::get($nodeId),
                'history' => WorkerNodeRepository::telemetryHistory($nodeId, $limit),
            ]);
            break;

        case 'fleet_provision_prepare':
            $proxmoxNode = trim((string) ($_POST['proxmox_node'] ?? ''));
            $count = max(1, min(20, (int) ($_POST['count'] ?? 1)));
            $prepared = FleetProvisionService::prepareSlots($proxmoxNode, $count, 'ms365-prod-worker-');
            FleetAuditLog::write('fleet_provision_prepare', 'Prepared ' . count($prepared) . ' production slot(s) on ' . $proxmoxNode, 'proxmox_node', $proxmoxNode);
            echo json_encode(['ok' => true, 'prepared' => $prepared]);
            break;

        case 'fleet_provision_abandon':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            $vmid = (int) ($_POST['proxmox_vmid'] ?? 0);
            FleetProvisionService::abandonSlot($nodeId !== '' ? $nodeId : null, $vmid);
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_config_get':
            $current = WorkerConfigService::current();
            $yaml = $current ? (string) ($current['yaml'] ?? '') : WorkerConfigService::templateYaml();
            echo json_encode([
                'ok' => true,
                'version' => $current ? (int) $current['version'] : 0,
                'sha256' => $current ? (string) ($current['sha256'] ?? '') : '',
                'yaml' => $yaml,
                'status' => WorkerConfigService::statusSummary(),
            ]);
            break;

        case 'fleet_config_save':
            $yaml = (string) ($_POST['yaml'] ?? '');
            $validateOnly = (string) ($_POST['validate_only'] ?? '') === '1';
            $validated = WorkerConfigService::validateYaml($yaml);
            if ($validated['errors'] !== []) {
                echo json_encode(['ok' => false, 'errors' => $validated['errors'], 'yaml' => $validated['yaml']]);
                break;
            }
            if ($validateOnly) {
                echo json_encode(['ok' => true, 'valid' => true, 'yaml' => $validated['yaml']]);
                break;
            }
            $adminId = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : null;
            $saved = WorkerConfigService::saveNewVersion($validated['yaml'], $adminId);
            echo json_encode(['ok' => true, 'version' => $saved['version'], 'sha256' => $saved['sha256']]);
            break;

        case 'fleet_config_rollout':
            $version = (int) ($_POST['config_version'] ?? 0);
            if ($version <= 0) {
                throw new \RuntimeException('config_version required');
            }
            $strategy = trim((string) ($_POST['strategy'] ?? 'explicit'));
            $nodeIdsRaw = trim((string) ($_POST['node_ids'] ?? ''));
            $nodeIds = $nodeIdsRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $nodeIdsRaw)))) : [];
            $result = WorkerConfigService::rollout($version, $nodeIds, $strategy);
            echo json_encode(['ok' => true] + $result);
            break;

        case 'fleet_config_status':
            echo json_encode(['ok' => true, 'status' => WorkerConfigService::statusSummary()]);
            break;

        case 'fleet_release_list':
            echo json_encode(['ok' => true, 'releases' => ReleaseRepository::listRecent(25)]);
            break;

        case 'fleet_release_manifest':
            $latest = ReleaseRepository::latest();
            echo json_encode(['ok' => true, 'release' => $latest ?? []]);
            break;

        case 'fleet_release_artifact':
            $releaseId = (int) ($_GET['release_id'] ?? 0);
            $release = $releaseId > 0 ? ReleaseRepository::get($releaseId) : null;
            if ($release === null) {
                throw new \RuntimeException('Release not found');
            }
            $path = (string) ($release['artifact_path'] ?? '');
            if ($path === '' || !is_file($path)) {
                throw new \RuntimeException('Artifact file missing');
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="ms365-backup-worker-' . rawurlencode((string) $release['version']) . '"');
            header('X-MS365-Release-Sha256: ' . (string) ($release['sha256'] ?? ''));
            readfile($path);
            exit;

        case 'fleet_release_upsert':
            $version = trim((string) ($_POST['version'] ?? ''));
            if ($version === '') {
                throw new \RuntimeException('version required');
            }
            ReleaseRepository::validateVersionLabel($version);
            $sha256 = trim((string) ($_POST['sha256'] ?? ''));
            $gitRef = trim((string) ($_POST['git_ref'] ?? ''));
            $artifactSize = (int) ($_POST['artifact_size'] ?? 0);

            if (!isset($_FILES['artifact']) || !is_uploaded_file((string) ($_FILES['artifact']['tmp_name'] ?? ''))) {
                throw new \RuntimeException('artifact upload required');
            }
            $tmp = (string) $_FILES['artifact']['tmp_name'];
            $uploadSha = hash_file('sha256', $tmp) ?: '';
            if ($sha256 !== '' && !hash_equals($sha256, $uploadSha)) {
                throw new \RuntimeException('Uploaded artifact sha256 mismatch');
            }
            $sha256 = $uploadSha;

            $artifactDir = FleetSettings::artifactRoot() . '/' . $version;
            if (!is_dir($artifactDir)) {
                @mkdir($artifactDir, 0755, true);
            }
            $dest = $artifactDir . '/ms365-backup-worker';
            if (!move_uploaded_file($tmp, $dest)) {
                if (!copy($tmp, $dest)) {
                    throw new \RuntimeException('Failed to store uploaded artifact');
                }
            }
            @chmod($dest, 0755);
            $size = (int) filesize($dest);

            $existing = ReleaseRepository::getByVersion($version);
            if ($existing !== null) {
                \WHMCS\Database\Capsule::table('ms365_worker_releases')->where('id', (int) $existing['id'])->update([
                    'git_ref' => $gitRef,
                    'sha256' => $sha256,
                    'artifact_path' => $dest,
                    'artifact_size' => $size,
                ]);
                $releaseId = (int) $existing['id'];
            } else {
                $releaseId = ReleaseRepository::create([
                    'version' => $version,
                    'git_ref' => $gitRef,
                    'sha256' => $sha256,
                    'artifact_path' => $dest,
                    'artifact_size' => $size,
                    'build_job_id' => (int) ($_POST['build_job_id'] ?? 0) ?: null,
                    'created_by_admin_id' => null,
                    'notes' => 'Synced from development release #' . (int) ($_POST['source_release_id'] ?? 0),
                ]);
            }
            FleetAuditLog::write('release_sync_upsert', 'Upserted release ' . $version, 'release', (string) $releaseId);
            echo json_encode(['ok' => true, 'release_id' => $releaseId, 'version' => $version]);
            break;

        case 'fleet_deploy_create':
            $releaseId = (int) ($_POST['release_id'] ?? 0);
            if ($releaseId <= 0) {
                throw new \RuntimeException('release_id required');
            }
            $strategy = trim((string) ($_POST['strategy'] ?? 'rolling'));
            $force = !empty($_POST['force_deploy']);
            $canary = trim((string) ($_POST['canary_node_id'] ?? ''));
            $adminId = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : null;
            $result = DeployService::startDeploy(
                $releaseId,
                $strategy,
                $force,
                $canary !== '' ? $canary : null,
                $adminId
            );
            echo json_encode(['ok' => true] + $result);
            break;

        case 'fleet_deploy_list':
            echo json_encode(['ok' => true, 'jobs' => DeployService::listDeployJobs(25)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown op']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
