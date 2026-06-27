<?php
declare(strict_types=1);

use Ms365Backup\BackupPlanner;
use Ms365Backup\BackupRunRepository;
use Ms365Backup\BackupScope;
use Ms365Backup\PhysicalBackupJob;
use Ms365Backup\DiscoveryService;
use Ms365Backup\InventoryService;
use Ms365Backup\TenantResource;
use Ms365Backup\WorkerProcess;
use Ms365Backup\WorkerSpawner;
use Ms365Backup\StoragePermissions;
use Ms365Backup\GraphClient;
use Ms365Backup\ProgressLogger;
use Ms365Backup\RegionEndpoints;
use Ms365Backup\ResourceAccessService;
use Ms365Backup\StorageLayout;
use Ms365Backup\TenantRepository;
use Ms365Backup\TokenProvider;
use Ms365Backup\Seeder\SeederConfigRepository;
use Ms365Backup\Seeder\SeederEntraConfig;
use Ms365Backup\Seeder\SeederGraphFactory;
use Ms365Backup\Seeder\SeederOAuthService;
use Ms365Backup\Seeder\SeederProgressWriter;
use Ms365Backup\Seeder\SeederRunRepository;
use Ms365Backup\Seeder\SeederTokenProvider;
use Ms365Backup\Seeder\SeederWorkerSpawner;
use Ms365Backup\Seeder\SeederProfileCatalog;

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['adminid']) || (int) $_SESSION['adminid'] <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

$op = (string) ($_GET['op'] ?? $_POST['op'] ?? $_REQUEST['op'] ?? '');

// #region agent log
$__ms365_dbg_op = $op;
$__ms365_dbg_start = microtime(true);
@file_put_contents('/var/www/eazybackup.ca/.cursor/debug-e8409d.log', json_encode([
    'sessionId' => 'e8409d', 'runId' => 'nodes-tab', 'hypothesisId' => 'B',
    'location' => 'admin/api.php:start', 'message' => 'admin api op start',
    'data' => ['op' => $op, 'method' => $_SERVER['REQUEST_METHOD'] ?? '', 'tab' => $_GET['tab'] ?? ''],
    'timestamp' => (int) round($__ms365_dbg_start * 1000),
], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
register_shutdown_function(static function () use (&$__ms365_dbg_op, &$__ms365_dbg_start): void {
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-e8409d.log', json_encode([
        'sessionId' => 'e8409d', 'runId' => 'nodes-tab', 'hypothesisId' => 'B',
        'location' => 'admin/api.php:end', 'message' => 'admin api op end',
        'data' => ['op' => $__ms365_dbg_op, 'elapsed_ms' => (int) round((microtime(true) - $__ms365_dbg_start) * 1000)],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
});
// #endregion

/** Resolve fleet target from request (development|production). */
function ms365backup_fleet_target(): string
{
    $fleet = trim((string) ($_GET['fleet'] ?? $_POST['fleet'] ?? ''));

    return \Ms365Backup\Fleet\FleetFacade::resolveFleetFromRequest($fleet !== '' ? $fleet : null);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !check_token('WHMCS.admin.default')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

try {
    switch ($op) {
        case 'save_config':
            TenantRepository::save([
                'region' => (string) ($_POST['region'] ?? 'GlobalPublicCloud'),
                'tenant_id' => (string) ($_POST['tenant_id'] ?? ''),
                'client_id' => (string) ($_POST['client_id'] ?? ''),
                'app_secret' => (string) ($_POST['app_secret'] ?? ''),
            ]);
            echo json_encode(['ok' => true, 'message' => 'Credentials saved']);
            break;

        case 'test_auth':
            $creds = TenantRepository::credentials();
            $tokens = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);
            $graph = new GraphClient($tokens, $creds['region']);
            $org = $graph->get('organization', ['$top' => '1']);
            $name = $org['value'][0]['displayName'] ?? 'Connected';
            echo json_encode(['ok' => true, 'organization' => $name]);
            break;

        case 'get_config':
            $row = TenantRepository::get();
            echo json_encode([
                'ok' => true,
                'config' => [
                    'region' => $row['region'] ?? 'GlobalPublicCloud',
                    'tenant_id' => $row['tenant_id'] ?? '',
                    'client_id' => $row['client_id'] ?? '',
                    'has_secret' => !empty($row['app_secret_enc']),
                ],
                'regions' => RegionEndpoints::allowedRegions(),
            ]);
            break;

        case 'discover_users':
        case 'discover_sites':
        case 'discover_teams':
            $creds = TenantRepository::credentials();
            $tokens = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);
            $graph = new GraphClient($tokens, $creds['region']);
            $storage = new StorageLayout($creds['tenant_id']);
            $discovery = new DiscoveryService($graph, $storage);
            $type = match ($op) {
                'discover_sites' => 'sites',
                'discover_teams' => 'teams',
                default => 'users',
            };
            $items = match ($type) {
                'sites' => $discovery->listSites(),
                'teams' => $discovery->listTeams(),
                default => $discovery->listUsers(),
            };
            echo json_encode(['ok' => true, 'type' => $type, 'count' => count($items), 'value' => $items]);
            break;

        case 'load_cached':
            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $type = (string) ($_GET['type'] ?? 'users');
            if ($type === 'inventory') {
                $inventory = new InventoryService(
                    ms365backup_graph_client(),
                    $storage,
                    ms365backup_discovery_service($storage),
                );
                echo json_encode(['ok' => true, 'data' => $inventory->load()]);
                break;
            }
            $file = $storage->discoveryDir() . '/' . $type . '.json';
            $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            echo json_encode(['ok' => true, 'data' => is_array($cached) ? $cached : null]);
            break;

        case 'discover_inventory':
            set_time_limit(600);
            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $inventory = new InventoryService(
                ms365backup_graph_client(),
                $storage,
                ms365backup_discovery_service($storage),
            );
            $data = $inventory->refresh();
            echo json_encode([
                'ok' => true,
                'fetched_at' => $data['fetched_at'] ?? '',
                'counts' => $data['counts'] ?? [],
                'resource_count' => count($data['resources'] ?? []),
            ]);
            break;

        case 'load_inventory':
            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $inventory = new InventoryService(
                ms365backup_graph_client(),
                $storage,
                ms365backup_discovery_service($storage),
            );
            $data = $inventory->load();
            $sections = trim((string) ($_GET['sections'] ?? ''));
            if ($data !== null && $sections !== '') {
                $allowed = array_map('trim', explode(',', $sections));
                $data['resources'] = array_values(array_filter(
                    $data['resources'] ?? [],
                    static fn (array $r): bool => in_array((string) ($r['resource_type'] ?? ''), $allowed, true),
                ));
            }
            echo json_encode(['ok' => true, 'inventory' => $data]);
            break;

        case 'plan_backup':
            $selectedRaw = (string) ($_POST['selected_ids_json'] ?? $_POST['selected_ids'] ?? '[]');
            $decoded = json_decode($selectedRaw, true);
            $selectedIds = is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
            $plan = ms365backup_build_plan_response(
                $selectedIds,
                ms365backup_parse_scope_from_request(),
                ms365backup_parse_scope_overrides_from_request(),
            );
            echo json_encode(['ok' => true, 'plan' => $plan]);
            break;

        case 'start_backup_plan':
            $selectedRaw = (string) ($_POST['selected_ids_json'] ?? $_POST['selected_ids'] ?? '[]');
            $decoded = json_decode($selectedRaw, true);
            $selectedIds = is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
            $defaultScope = ms365backup_parse_scope_from_request();
            if (!$defaultScope->hasAnyEnabled()) {
                throw new \RuntimeException('Select at least one backup capability in scope');
            }
            $result = ms365backup_queue_physical_jobs($selectedIds, $defaultScope, ms365backup_parse_scope_overrides_from_request());
            echo json_encode($result);
            break;

        case 'check_access':
            set_time_limit(120);
            $type = (string) ($_POST['type'] ?? 'users');
            if (!in_array($type, ['users', 'sites', 'inventory_users', 'inventory_sites', 'inventory_onedrive', 'inventory_teams'], true)) {
                throw new \RuntimeException('type must be users, sites, inventory_users, inventory_sites, inventory_onedrive, or inventory_teams');
            }
            $offset = max(0, (int) ($_POST['offset'] ?? 0));
            $limit = max(1, min(50, (int) ($_POST['limit'] ?? 25)));
            $creds = TenantRepository::credentials();
            $tokens = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);
            $graph = new GraphClient($tokens, $creds['region']);
            $storage = new StorageLayout($creds['tenant_id']);
            $access = new ResourceAccessService($graph, $storage);

            if (in_array($type, ['inventory_users', 'inventory_sites', 'inventory_onedrive', 'inventory_teams'], true)) {
                $inventory = new InventoryService($graph, $storage, ms365backup_discovery_service($storage));
                $result = match ($type) {
                    'inventory_sites' => $inventory->checkInventorySiteAccessBatch($offset, $limit, $access),
                    'inventory_onedrive' => $inventory->checkInventoryOneDriveAccessBatch($offset, $limit, $access),
                    'inventory_teams' => $inventory->checkInventoryTeamAccessBatch($offset, $limit, $access),
                    default => $inventory->checkInventoryUserAccessBatch($offset, $limit, $access),
                };
                echo json_encode([
                    'ok' => true,
                    'type' => $type,
                    'offset' => $offset,
                    'limit' => $limit,
                    'total' => $result['total'],
                    'processed' => $result['processed'],
                    'done' => $result['done'],
                    'unavailable_count' => $result['unavailable_count'],
                ]);
                break;
            }

            $legacyType = $type === 'sites' ? 'sites' : 'users';
            $result = $legacyType === 'sites'
                ? $access->checkSitesBatch($offset, $limit)
                : $access->checkUsersBatch($offset, $limit);
            echo json_encode([
                'ok' => true,
                'type' => $legacyType,
                'offset' => $offset,
                'limit' => $limit,
                'total' => $result['total'],
                'processed' => $result['processed'],
                'done' => $result['done'],
                'unavailable_count' => $result['unavailable_count'],
            ]);
            break;

        case 'start_backup':
            $userId = trim((string) ($_POST['user_id'] ?? ''));
            $userUpn = trim((string) ($_POST['user_upn'] ?? ''));
            $displayName = trim((string) ($_POST['user_display_name'] ?? ''));
            $backupMail = filter_var($_POST['backup_mail'] ?? '1', FILTER_VALIDATE_BOOLEAN);
            $backupCalendar = filter_var($_POST['backup_calendar'] ?? '1', FILTER_VALIDATE_BOOLEAN);
            if ($userId === '') {
                throw new \RuntimeException('user_id is required');
            }
            if (!$backupMail && !$backupCalendar) {
                throw new \RuntimeException('Select at least one of Mail or Calendar events');
            }
            StoragePermissions::ensureWritableBase();
            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $runId = BackupRunRepository::create($userId, $userUpn, $displayName, '', $backupMail, $backupCalendar);
            $run = BackupRunRepository::get($runId);
            $actualRunDir = (string) ($run['backup_path'] ?? $storage->runDir($userId, $runId));

            $logger = new ProgressLogger($runId, $actualRunDir . '/run.log');
            $logger->info('Backup queued, starting background worker');
            WorkerSpawner::spawn($runId, $logger);

            echo json_encode(['ok' => true, 'run_id' => $runId]);
            break;

        case 'start_backup_batch':
            $backupMail = filter_var($_POST['backup_mail'] ?? '1', FILTER_VALIDATE_BOOLEAN);
            $backupCalendar = filter_var($_POST['backup_calendar'] ?? '1', FILTER_VALIDATE_BOOLEAN);
            if (!$backupMail && !$backupCalendar) {
                throw new \RuntimeException('Select at least one of Mail or Calendar events');
            }
            $users = ms365backup_resolve_runnable_batch_users();
            if ($users === []) {
                throw new \RuntimeException('Select at least one user or mailbox with mail/calendar backup enabled');
            }
            StoragePermissions::ensureWritableBase();
            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $runs = [];
            foreach ($users as $user) {
                if (!is_array($user)) {
                    continue;
                }
                $userId = trim((string) ($user['id'] ?? $user['user_id'] ?? ''));
                if ($userId === '') {
                    continue;
                }
                $userUpn = trim((string) ($user['upn'] ?? $user['user_upn'] ?? ''));
                $displayName = trim((string) ($user['name'] ?? $user['display_name'] ?? $user['user_display_name'] ?? ''));
                $runId = BackupRunRepository::create($userId, $userUpn, $displayName, '', $backupMail, $backupCalendar);
                $actualRunDir = $storage->runDir($userId, $runId);
                BackupRunRepository::update($runId, ['backup_path' => $actualRunDir]);
                $logger = new ProgressLogger($runId, $actualRunDir . '/run.log');
                $logger->info('Backup queued, starting background worker');
                WorkerSpawner::spawn($runId, $logger);
                $runs[] = [
                    'run_id' => $runId,
                    'user_id' => $userId,
                    'user_upn' => $userUpn,
                    'user_display_name' => $displayName,
                ];
            }
            if ($runs === []) {
                throw new \RuntimeException('No valid users in batch');
            }
            echo json_encode(['ok' => true, 'count' => count($runs), 'runs' => $runs]);
            break;

        case 'progress':
            $runId = (string) ($_GET['run_id'] ?? '');
            $run = BackupRunRepository::get($runId);
            if (!$run) {
                throw new \RuntimeException('Run not found');
            }
            echo json_encode(['ok' => true, 'run' => $run]);
            break;

        case 'logs':
            $runId = (string) ($_GET['run_id'] ?? '');
            $sinceId = (int) ($_GET['since_id'] ?? 0);
            $lines = ProgressLogger::tail($runId, $sinceId);
            $lastId = $sinceId;
            foreach ($lines as $line) {
                $lastId = max($lastId, (int) ($line['id'] ?? 0));
            }
            echo json_encode(['ok' => true, 'lines' => $lines, 'last_id' => $lastId]);
            break;

        case 'list_runs':
            $runs = BackupRunRepository::listRecent((int) ($_GET['limit'] ?? 25));
            echo json_encode(['ok' => true, 'runs' => $runs]);
            break;

        case 'restart_worker':
            $runId = trim((string) ($_POST['run_id'] ?? ''));
            $run = BackupRunRepository::get($runId);
            if (!$run) {
                throw new \RuntimeException('Run not found');
            }
            if (!in_array($run['status'] ?? '', ['queued', 'running'], true)) {
                throw new \RuntimeException('Run is not active');
            }
            StoragePermissions::ensureWritableBase();
            $logPath = (string) ($run['backup_path'] ?? '');
            if ($logPath === '') {
                $creds = TenantRepository::credentials();
                $storage = new StorageLayout($creds['tenant_id']);
                $logPath = $storage->runDir((string) $run['user_id'], $runId);
                BackupRunRepository::update($runId, ['backup_path' => $logPath]);
            }
            $logger = new ProgressLogger($runId, $logPath . '/run.log');
            $logger->info('Restarting background worker');
            WorkerSpawner::spawn($runId, $logger);
            echo json_encode(['ok' => true, 'message' => 'Worker restarted']);
            break;

        case 'storage_check':
            StoragePermissions::ensureWritableBase();
            echo json_encode([
                'ok' => true,
                'base' => StorageLayout::BASE_PATH,
                'writable' => is_writable(StorageLayout::BASE_PATH),
                'web_user' => StoragePermissions::webUser(),
                'engine_mode' => 'kopia',
                'worker_fleet' => 'kopia',
            ]);
            break;

        case 'cancel_run':
            $runId = trim((string) ($_POST['run_id'] ?? ''));
            if ($runId === '') {
                throw new \RuntimeException('run_id is required');
            }
            $run = BackupRunRepository::get($runId);
            if (!$run) {
                throw new \RuntimeException('Run not found');
            }
            if (!BackupRunRepository::requestCancel($runId)) {
                throw new \RuntimeException('Run cannot be cancelled (status: ' . ($run['status'] ?? 'unknown') . ')');
            }
            $backupPath = (string) ($run['backup_path'] ?? '');
            if ($backupPath !== '' && is_dir($backupPath)) {
                try {
                    WorkerProcess::terminate($backupPath);
                } catch (\Throwable $killEx) {
                    // Cancellation is recorded even if the worker process cannot be signalled.
                }
            }
            $logPath = $backupPath !== '' ? $backupPath . '/run.log' : null;
            $logger = new ProgressLogger($runId, $logPath);
            $logger->info('Cancellation requested by administrator');
            echo json_encode(['ok' => true, 'message' => 'Backup cancelled']);
            break;

        case 'seeder_save_config':
            SeederConfigRepository::save([
                'region' => (string) ($_POST['region'] ?? 'GlobalPublicCloud'),
                'tenant_id' => (string) ($_POST['tenant_id'] ?? ''),
                'client_id' => (string) ($_POST['client_id'] ?? ''),
                'app_secret' => (string) ($_POST['app_secret'] ?? ''),
            ]);
            echo json_encode(['ok' => true, 'message' => 'Seeder credentials saved']);
            break;

        case 'seeder_test_auth':
            $creds = SeederConfigRepository::credentials();
            $tokens = SeederTokenProvider::fromConfig();
            $graph = new GraphClient($tokens, $creds['region']);
            $org = $graph->get('organization', ['$top' => '1']);
            $graph->get('users', ['$top' => '1', '$select' => 'id']);
            $name = $org['value'][0]['displayName'] ?? 'Connected';
            echo json_encode(['ok' => true, 'organization' => $name]);
            break;

        case 'seeder_build_oauth_url':
            echo json_encode(['ok' => true, 'url' => SeederOAuthService::buildAuthorizeUrl()]);
            break;

        case 'seeder_disconnect_user':
            SeederConfigRepository::clearDelegatedUser();
            echo json_encode(['ok' => true, 'message' => 'Seed user disconnected']);
            break;

        case 'seeder_status':
            $row = SeederConfigRepository::get() ?? [];
            $runs = SeederRunRepository::listRecent(1);
            echo json_encode([
                'ok' => true,
                'configured' => ($row['client_id'] ?? '') !== '' && ($row['app_secret_enc'] ?? '') !== '',
                'seed_user_upn' => (string) ($row['seed_user_upn'] ?? ''),
                'has_seed_user' => SeederConfigRepository::hasDelegatedUser(),
                'redirect_uri' => SeederEntraConfig::redirectUri(),
                'profiles' => SeederProfileCatalog::profileKeys(),
                'last_run' => $runs[0] ?? null,
            ]);
            break;

        case 'seeder_discover_targets':
            $creds = SeederConfigRepository::credentials();
            $graph = SeederGraphFactory::appClient();
            $storage = new StorageLayout($creds['tenant_id']);
            $discovery = new DiscoveryService($graph, $storage);
            $users = $discovery->listUsers();
            $sites = [];
            $teams = [];
            try {
                $sites = $discovery->listSites();
            } catch (\Throwable $_) {
            }
            try {
                $teams = $discovery->listTeams();
            } catch (\Throwable $_) {
            }
            echo json_encode([
                'ok' => true,
                'users' => count($users),
                'sites' => count($sites),
                'teams' => count($teams),
            ]);
            break;

        case 'seeder_start':
            StoragePermissions::ensureWritableBase();
            $profile = (string) ($_POST['profile'] ?? 'light');
            if (!in_array($profile, SeederProfileCatalog::profileKeys(), true)) {
                throw new \RuntimeException('Invalid profile');
            }
            $workloadsRaw = (string) ($_POST['workloads_json'] ?? '{}');
            $workloads = json_decode($workloadsRaw, true);
            if (!is_array($workloads)) {
                $workloads = [];
            }
            $options = [
                'profile' => $profile,
                'workloads' => $workloads,
                'all_users' => filter_var($_POST['all_users'] ?? '1', FILTER_VALIDATE_BOOLEAN),
                'all_sites' => filter_var($_POST['all_sites'] ?? '1', FILTER_VALIDATE_BOOLEAN),
                'all_teams' => filter_var($_POST['all_teams'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            ];
            $runId = SeederRunRepository::create($profile, $options);
            SeederWorkerSpawner::spawn($runId);
            echo json_encode(['ok' => true, 'run_id' => $runId]);
            break;

        case 'seeder_progress':
            $runId = (string) ($_GET['run_id'] ?? '');
            $run = SeederRunRepository::get($runId);
            if (!$run) {
                throw new \RuntimeException('Run not found');
            }
            echo json_encode([
                'ok' => true,
                'run' => $run,
                'progress' => SeederProgressWriter::read($runId),
            ]);
            break;

        case 'seeder_cancel':
            $runId = trim((string) ($_POST['run_id'] ?? ''));
            if ($runId === '') {
                throw new \RuntimeException('run_id is required');
            }
            if (!SeederRunRepository::requestCancel($runId)) {
                throw new \RuntimeException('Run cannot be cancelled');
            }
            echo json_encode(['ok' => true, 'message' => 'Cancellation requested']);
            break;

        case 'seeder_list_runs':
            $runs = SeederRunRepository::listRecent((int) ($_GET['limit'] ?? 25));
            echo json_encode(['ok' => true, 'runs' => $runs]);
            break;

        case 'fleet_meta':
            echo json_encode(['ok' => true, 'meta' => \Ms365Backup\Fleet\FleetContext::uiMeta()]);
            break;

        case 'fleet_set_target':
            if (!\Ms365Backup\Fleet\FleetContext::isDevelopmentServer()) {
                throw new \RuntimeException('Fleet target selection is only available on the development server');
            }
            $target = trim((string) ($_POST['fleet'] ?? ''));
            if ($target === '') {
                throw new \RuntimeException('fleet required');
            }
            \Ms365Backup\Fleet\FleetContext::setActiveFleet($target);
            echo json_encode(['ok' => true, 'meta' => \Ms365Backup\Fleet\FleetContext::uiMeta()]);
            break;

        case 'fleet_summary':
            echo json_encode(['ok' => true, 'summary' => \Ms365Backup\Fleet\FleetFacade::summary(ms365backup_fleet_target())]);
            break;

        case 'fleet_nodes':
            $status = trim((string) ($_GET['status'] ?? ''));
            echo json_encode(['ok' => true, 'nodes' => \Ms365Backup\Fleet\FleetFacade::nodes($status, ms365backup_fleet_target())]);
            break;

        case 'fleet_node_drain':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            \Ms365Backup\Fleet\FleetFacade::nodeDrain($nodeId, ms365backup_fleet_target());
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_activate':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            \Ms365Backup\Fleet\FleetFacade::nodeActivate($nodeId, ms365backup_fleet_target());
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_retire':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            \Ms365Backup\Fleet\FleetFacade::nodeRetire($nodeId, ms365backup_fleet_target());
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_delete':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            \Ms365Backup\Fleet\FleetFacade::nodeDelete($nodeId, ms365backup_fleet_target());
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
            \Ms365Backup\Fleet\FleetFacade::nodeSetVmid($nodeId, $vmid, ms365backup_fleet_target());
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_release_leases':
            $result = \Ms365Backup\Fleet\FleetFacade::releaseLeases(ms365backup_fleet_target());
            echo json_encode(['ok' => true] + $result);
            break;

        case 'fleet_settings_get':
            echo json_encode(['ok' => true, 'settings' => \Ms365Backup\Fleet\FleetFacade::settingsGet(ms365backup_fleet_target())]);
            break;

        case 'fleet_audit':
            echo json_encode(['ok' => true, 'entries' => \Ms365Backup\Fleet\FleetFacade::audit((int) ($_GET['limit'] ?? 50), ms365backup_fleet_target())]);
            break;

        case 'fleet_node_telemetry':
            $nodeId = trim((string) ($_GET['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            $limit = max(1, min(500, (int) ($_GET['limit'] ?? 96)));
            $payload = \Ms365Backup\Fleet\FleetFacade::nodeTelemetry($nodeId, $limit, ms365backup_fleet_target());
            echo json_encode(['ok' => true] + $payload);
            break;

        case 'fleet_proxmox_nodes':
            echo json_encode(['ok' => true, 'nodes' => \Ms365Backup\ProxmoxProvisioner::clusterNodes()]);
            break;

        case 'fleet_scale_up':
            $proxmoxNode = trim((string) ($_POST['proxmox_node'] ?? ''));
            $count = max(1, min(20, (int) ($_POST['count'] ?? 1)));
            if ($proxmoxNode === '') {
                throw new \RuntimeException('proxmox_node required');
            }
            $fleet = ms365backup_fleet_target();
            $result = \Ms365Backup\Fleet\FleetFacade::scaleUp($proxmoxNode, $count, $fleet);
            \Ms365Backup\Fleet\FleetAuditLog::write('fleet_scale_up', 'Scaled up ' . $count . ' worker(s) on ' . $proxmoxNode . ' (' . $fleet . ' fleet)', 'proxmox_node', $proxmoxNode, [
                'count' => $count,
                'fleet' => $fleet,
                'created' => count($result['created'] ?? []),
                'failed' => $result['failed'] ?? [],
                'errors' => $result['errors'] ?? [],
            ]);
            echo json_encode(['ok' => true, 'result' => $result]);
            break;

        case 'fleet_node_stop':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            \Ms365Backup\Fleet\FleetFacade::nodeStop($nodeId, ms365backup_fleet_target());
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_node_start':
            $nodeId = trim((string) ($_POST['node_id'] ?? ''));
            if ($nodeId === '') {
                throw new \RuntimeException('node_id required');
            }
            \Ms365Backup\Fleet\FleetFacade::nodeStart($nodeId, ms365backup_fleet_target());
            echo json_encode(['ok' => true]);
            break;

        case 'fleet_config_get':
            $payload = \Ms365Backup\Fleet\FleetFacade::configGet(ms365backup_fleet_target());
            echo json_encode(['ok' => true] + $payload);
            break;

        case 'fleet_config_save':
            $yaml = (string) ($_POST['yaml'] ?? '');
            $validateOnly = (string) ($_POST['validate_only'] ?? '') === '1';
            $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : null;
            $result = \Ms365Backup\Fleet\FleetFacade::configSave($yaml, $validateOnly, $adminId, ms365backup_fleet_target());
            echo json_encode($result);
            break;

        case 'fleet_config_rollout':
            $version = (int) ($_POST['config_version'] ?? 0);
            if ($version <= 0) {
                throw new \RuntimeException('config_version required');
            }
            $strategy = trim((string) ($_POST['strategy'] ?? 'explicit'));
            $nodeIdsRaw = trim((string) ($_POST['node_ids'] ?? ''));
            $nodeIds = $nodeIdsRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $nodeIdsRaw)))) : [];
            $result = \Ms365Backup\Fleet\FleetFacade::configRollout($version, $nodeIds, $strategy, ms365backup_fleet_target());
            echo json_encode($result);
            break;

        case 'fleet_config_status':
            echo json_encode(['ok' => true, 'status' => \Ms365Backup\Fleet\FleetFacade::configStatus(ms365backup_fleet_target())]);
            break;

        case 'fleet_release_sync':
            $releaseId = (int) ($_POST['release_id'] ?? 0);
            if ($releaseId <= 0) {
                throw new \RuntimeException('release_id required');
            }
            $result = \Ms365Backup\Fleet\FleetFacade::releaseSyncToProduction($releaseId);
            echo json_encode(['ok' => true] + $result);
            break;

        case 'jobs_list':
            $filters = [
                'client_id' => $_GET['client_id'] ?? null,
                'client_name' => $_GET['client_name'] ?? null,
                'job_name' => $_GET['job_name'] ?? null,
                'status' => $_GET['status'] ?? null,
                'type' => $_GET['type'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'run_id' => $_GET['run_id'] ?? null,
            ];
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
            $result = \Ms365Backup\Ms365AdminJobsRepository::listJobs($filters, $page, $perPage);
            echo json_encode(['ok' => true] + $result);
            break;

        case 'jobs_batch_logs':
            $batchRunId = trim((string) ($_GET['batch_run_id'] ?? ''));
            if ($batchRunId === '') {
                throw new \RuntimeException('batch_run_id required');
            }
            $payload = \Ms365Backup\Ms365AdminJobsService::aggregateJobLogs($batchRunId);
            ms365backup_echo_json(['ok' => true] + $payload);
            break;

        case 'jobs_batch_detail':
            $batchRunId = trim((string) ($_GET['batch_run_id'] ?? ''));
            if ($batchRunId === '') {
                throw new \RuntimeException('batch_run_id required');
            }
            ms365backup_echo_json([
                'ok' => true,
                'parent' => \Ms365Backup\Ms365AdminJobsService::parentForApi($batchRunId),
                'children' => \Ms365Backup\Ms365AdminJobsRepository::getBatchChildrenDetail($batchRunId),
            ]);
            break;

        case 'jobs_worker_logs':
            $batchRunId = trim((string) ($_GET['batch_run_id'] ?? ''));
            if ($batchRunId === '') {
                throw new \RuntimeException('batch_run_id required');
            }
            $payload = \Ms365Backup\Ms365AdminJobsService::aggregateWorkerLogs($batchRunId, true);
            echo json_encode(['ok' => true] + $payload);
            break;

        case 'worker_build_create':
            $version = trim((string) ($_POST['version_label'] ?? ''));
            if ($version === '') {
                throw new \RuntimeException('version_label required');
            }
            \Ms365Backup\Fleet\ReleaseRepository::assertVersionAvailable($version);
            $jobId = \Ms365Backup\Fleet\BuildJobStore::createJob([
                'admin_id' => (int) $_SESSION['adminid'],
                'git_ref' => trim((string) ($_POST['git_ref'] ?? 'main')),
                'version_label' => $version,
                'flags' => [
                    'run_tests' => !empty($_POST['run_tests']),
                    'git_sync' => !empty($_POST['git_sync']),
                ],
            ]);
            \Ms365Backup\Fleet\FleetAuditLog::write('build_queued', 'Queued worker build ' . $version, 'build_job', (string) $jobId);
            echo json_encode(['ok' => true, 'job_id' => $jobId]);
            break;

        case 'worker_build_list':
            ms365backup_echo_json(['ok' => true, 'jobs' => \Ms365Backup\Fleet\BuildJobStore::listRecent(25)]);
            break;

        case 'worker_build_status':
            $jobId = (int) ($_GET['job_id'] ?? 0);
            $job = $jobId > 0 ? \Ms365Backup\Fleet\BuildJobStore::getJob($jobId) : null;
            if ($job === null) {
                throw new \RuntimeException('job not found');
            }
            echo json_encode([
                'ok' => true,
                'job' => $job,
                'steps' => \Ms365Backup\Fleet\BuildJobStore::steps($jobId),
            ]);
            break;

        case 'worker_build_log':
            $jobId = (int) ($_GET['job_id'] ?? 0);
            $step = trim((string) ($_GET['step'] ?? ''));
            $offset = (int) ($_GET['offset'] ?? 0);
            echo json_encode([
                'ok' => true,
                'log' => \Ms365Backup\Fleet\BuildJobStore::tailLog($jobId, $step, $offset),
            ]);
            break;

        case 'worker_release_list':
            echo json_encode(['ok' => true, 'releases' => \Ms365Backup\Fleet\FleetFacade::releaseList(ms365backup_fleet_target())]);
            break;

        case 'worker_deploy_create':
            $releaseId = (int) ($_POST['release_id'] ?? 0);
            if ($releaseId <= 0) {
                throw new \RuntimeException('release_id required');
            }
            $strategy = trim((string) ($_POST['strategy'] ?? 'rolling'));
            $force = !empty($_POST['force_deploy']);
            $canary = trim((string) ($_POST['canary_node_id'] ?? ''));
            $result = \Ms365Backup\Fleet\FleetFacade::deployCreate(
                $releaseId,
                $strategy,
                $force,
                $canary !== '' ? $canary : null,
                (int) $_SESSION['adminid'],
                ms365backup_fleet_target()
            );
            echo json_encode($result);
            break;

        case 'worker_deploy_list':
            echo json_encode(['ok' => true, 'jobs' => \Ms365Backup\Fleet\FleetFacade::deployList(ms365backup_fleet_target())]);
            break;

        case 'worker_build_and_deploy':
            $version = trim((string) ($_POST['version_label'] ?? ''));
            if ($version === '') {
                throw new \RuntimeException('version_label required');
            }
            \Ms365Backup\Fleet\ReleaseRepository::assertVersionAvailable($version);
            $jobId = \Ms365Backup\Fleet\BuildJobStore::createJob([
                'admin_id' => (int) $_SESSION['adminid'],
                'git_ref' => trim((string) ($_POST['git_ref'] ?? 'main')),
                'version_label' => $version,
                'flags' => [
                    'run_tests' => !empty($_POST['run_tests']),
                    'git_sync' => !empty($_POST['git_sync']),
                    'auto_deploy' => true,
                    'deploy_strategy' => trim((string) ($_POST['strategy'] ?? 'rolling')),
                ],
            ]);
            echo json_encode(['ok' => true, 'job_id' => $jobId, 'message' => 'Build queued; deploy after publish can be triggered manually or via cron hook']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown op']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    ms365backup_echo_json(['ok' => false, 'error' => $e->getMessage()]);
}

/**
 * @param array<string, mixed> $data
 */
function ms365backup_echo_json(array $data): void
{
    $flags = JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($data, $flags);
    if ($json === false) {
        echo json_encode(['ok' => false, 'error' => 'JSON encode failed: ' . json_last_error_msg()]);
        return;
    }
    echo $json;
}

/**
 * Request params (WHMCS may not populate $_POST for multipart; $_REQUEST is safer).
 *
 * @return array<string, mixed>
 */
function ms365backup_request_params(): array
{
    return array_merge($_GET, $_POST, $_REQUEST);
}

/**
 * @return list<array{id: string, upn: string, name: string}>
 */
function ms365backup_parse_batch_users_from_request(): array
{
    $params = ms365backup_request_params();
    $count = (int) ($params['batch_user_count'] ?? 0);
    if ($count > 0 && $count <= 500) {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $id = trim((string) ($params['batch_user_' . $i . '_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $users[] = [
                'id' => $id,
                'upn' => trim((string) ($params['batch_user_' . $i . '_upn'] ?? '')),
                'name' => trim((string) ($params['batch_user_' . $i . '_name'] ?? '')),
            ];
        }
        if ($users !== []) {
            return $users;
        }
    }

    $indexed = [];
    foreach ($params as $key => $value) {
        if (!is_string($key) || preg_match('/^batch_user_(\d+)_id$/', $key, $m) !== 1) {
            continue;
        }
        $i = (int) $m[1];
        $indexed[$i] = [
            'id' => trim((string) $value),
            'upn' => trim((string) ($params['batch_user_' . $i . '_upn'] ?? '')),
            'name' => trim((string) ($params['batch_user_' . $i . '_name'] ?? '')),
        ];
    }
    if ($indexed !== []) {
        ksort($indexed);
        return array_values(array_filter($indexed, static fn (array $u): bool => $u['id'] !== ''));
    }

    $usersRaw = (string) ($params['batch_users_json'] ?? $params['users_json'] ?? '');
    if ($usersRaw !== '') {
        $decoded = json_decode(stripslashes($usersRaw), true);
        if (is_array($decoded)) {
            $out = [];
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? $row['user_id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $out[] = [
                    'id' => $id,
                    'upn' => trim((string) ($row['upn'] ?? $row['user_upn'] ?? '')),
                    'name' => trim((string) ($row['name'] ?? $row['display_name'] ?? $row['user_display_name'] ?? '')),
                ];
            }
            if ($out !== []) {
                return $out;
            }
        }
    }

    return [];
}

function ms365backup_graph_client(): GraphClient
{
    $creds = TenantRepository::credentials();
    $tokens = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);

    return new GraphClient($tokens, $creds['region']);
}

function ms365backup_discovery_service(StorageLayout $storage): DiscoveryService
{
    return new DiscoveryService(ms365backup_graph_client(), $storage);
}

/**
 * Resolve batch users from explicit list or resource selection via BackupPlanner.
 *
 * @return list<array{id: string, upn: string, name: string}>
 */
function ms365backup_resolve_runnable_batch_users(): array
{
    $users = ms365backup_parse_batch_users_from_request();
    if ($users !== []) {
        return $users;
    }

    $params = ms365backup_request_params();
    $selectedRaw = (string) ($params['selected_ids_json'] ?? '[]');
    $decoded = json_decode($selectedRaw, true);
    if (!is_array($decoded) || $decoded === []) {
        return [];
    }

    $scope = ms365backup_parse_scope_from_request();
    $queue = ms365backup_build_physical_queue(
        array_values(array_filter(array_map('strval', $decoded))),
        $scope,
        ms365backup_parse_scope_overrides_from_request(),
    );

    $planner = new BackupPlanner();
    $users = $planner->runnableJobsToBatchUsers($queue['physical_jobs']);
    if ($users === []) {
        throw new \RuntimeException('Selected resources are not runnable (mail/calendar users only)');
    }

    return $users;
}

function ms365backup_scope_has_runnable_user_engines(BackupScope $scope): bool
{
    return $scope->isEnabled(BackupScope::MAIL)
        || $scope->isEnabled(BackupScope::CALENDAR)
        || $scope->isEnabled(BackupScope::CONTACTS)
        || $scope->isEnabled(BackupScope::TASKS);
}

function ms365backup_can_queue_physical_job(PhysicalBackupJob $job): bool
{
    if (in_array($job->resourceType(), [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
        return ms365backup_scope_has_runnable_user_engines($job->scope);
    }
    if ($job->resourceType() === TenantResource::TYPE_USER_ONEDRIVE) {
        return $job->scope->isEnabled(BackupScope::ONEDRIVE);
    }
    if ($job->resourceType() === TenantResource::TYPE_SHAREPOINT_SITE) {
        return $job->scope->isEnabled(BackupScope::FILES) || $job->scope->isEnabled(BackupScope::LISTS);
    }
    if (in_array($job->resourceType(), [TenantResource::TYPE_TEAM, TenantResource::TYPE_TEAM_CHANNEL], true)) {
        return $job->scope->isEnabled(BackupScope::TEAMS_METADATA)
            || $job->scope->isEnabled(BackupScope::TEAMS_MESSAGES);
    }
    if ($job->resourceType() === TenantResource::TYPE_M365_GROUP) {
        return $job->scope->isEnabled(BackupScope::MAIL) || $job->scope->isEnabled(BackupScope::CALENDAR);
    }
    if ($job->resourceType() === TenantResource::TYPE_PLANNER_PLAN) {
        return $job->scope->isEnabled(BackupScope::PLANNER);
    }
    if ($job->resourceType() === TenantResource::TYPE_ONENOTE_NOTEBOOK) {
        return $job->scope->isEnabled(BackupScope::ONENOTE);
    }
    if ($job->resourceType() === TenantResource::TYPE_DIRECTORY_BASELINE) {
        return true;
    }

    return false;
}

function ms365backup_parse_scope_from_request(): BackupScope
{
    $params = ms365backup_request_params();
    $raw = (string) ($params['scope_json'] ?? '');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return BackupScope::fromJson($decoded);
        }
    }

    return new BackupScope([
        BackupScope::MAIL => filter_var($params['backup_mail'] ?? '1', FILTER_VALIDATE_BOOLEAN),
        BackupScope::CALENDAR => filter_var($params['backup_calendar'] ?? '1', FILTER_VALIDATE_BOOLEAN),
    ]);
}

/** @return array<string, array<string, bool>> */
function ms365backup_parse_scope_overrides_from_request(): array
{
    $params = ms365backup_request_params();
    $raw = (string) ($params['scope_overrides_json'] ?? '{}');
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param list<string> $selectedIds
 * @return array<string, mixed>
 */
function ms365backup_build_plan_response(array $selectedIds, BackupScope $defaultScope, array $scopeOverrides): array
{
    $queue = ms365backup_build_physical_queue($selectedIds, $defaultScope, $scopeOverrides);
    $runnable = [];
    $deferred = [];
    foreach ($queue['physical_jobs'] as $job) {
        if ($job->isRunnable()) {
            $runnable[] = $job->primaryResource;
        } else {
            $deferred[] = array_merge($job->primaryResource, ['reason' => $job->deferReason]);
        }
    }

    return [
        'runnable' => $runnable,
        'deferred' => $deferred,
        'dedup_groups' => $queue['dedup_groups'],
        'warnings' => $queue['warnings'],
        'physical_jobs' => array_map(static fn (PhysicalBackupJob $j) => $j->toArray(), $queue['physical_jobs']),
        'summary' => $queue['summary'],
    ];
}

/**
 * @param list<string> $selectedIds
 * @return array{physical_jobs: list<PhysicalBackupJob>, dedup_groups: list<array<string, mixed>>, warnings: list<string>, summary: array{runnable: int, deferred: int}}
 */
function ms365backup_build_physical_queue(array $selectedIds, BackupScope $defaultScope, array $scopeOverrides): array
{
    $creds = TenantRepository::credentials();
    $storage = new StorageLayout($creds['tenant_id']);
    $inventoryService = new InventoryService(
        ms365backup_graph_client(),
        $storage,
        ms365backup_discovery_service($storage),
    );
    $inventory = $inventoryService->load();
    if ($inventory !== null && $selectedIds !== []) {
        $inventoryService->enrichResourcesForPlanning($inventory, $selectedIds);
    }
    $planner = new BackupPlanner();

    return $planner->buildPhysicalQueue($selectedIds, $inventory, $defaultScope, $scopeOverrides);
}

/**
 * @param list<string> $selectedIds
 * @return array<string, mixed>
 */
function ms365backup_queue_physical_jobs(array $selectedIds, BackupScope $defaultScope, array $scopeOverrides): array
{
    StoragePermissions::ensureWritableBase();
    $creds = TenantRepository::credentials();
    $storage = new StorageLayout($creds['tenant_id']);
    $queue = ms365backup_build_physical_queue($selectedIds, $defaultScope, $scopeOverrides);
    $plan = ms365backup_build_plan_response($selectedIds, $defaultScope, $scopeOverrides);

    $runs = [];
    $deferredOut = [];
    foreach ($queue['physical_jobs'] as $job) {
        if (!$job->isRunnable()) {
            $deferredOut[] = $job->toArray();
            continue;
        }
        if (!ms365backup_can_queue_physical_job($job)) {
            $deferredOut[] = $job->toArray();
            continue;
        }
        $runId = BackupRunRepository::createFromPhysicalJob($job, $storage);
        $runDir = $storage->runDirForJob($job->physicalKey, $runId);
        $logger = new ProgressLogger($runId, $runDir . '/run.log');
        $logger->info('Backup queued, starting background worker');
        WorkerSpawner::spawn($runId, $logger);
        $runs[] = [
            'run_id' => $runId,
            'resource_id' => $job->resourceId(),
            'resource_type' => $job->resourceType(),
            'physical_key' => $job->physicalKey,
            'user_id' => $job->graphId(),
            'user_display_name' => $job->displayName(),
        ];
    }

    if ($runs === [] && $deferredOut === []) {
        throw new \RuntimeException('No resources selected');
    }
    if ($runs === []) {
        throw new \RuntimeException('No runnable backups in selection (all deferred or inventory-only)');
    }

    return [
        'ok' => true,
        'count' => count($runs),
        'runs' => $runs,
        'deferred' => $deferredOut,
        'physical_jobs' => array_map(static fn (PhysicalBackupJob $j) => $j->toArray(), $queue['physical_jobs']),
        'plan' => $plan,
    ];
}

