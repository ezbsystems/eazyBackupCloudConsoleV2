<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\Ms365EngineConfig;
use Ms365Backup\WorkerClaimService;
use Ms365Backup\WorkerNodeRepository;
use WHMCS\Database\Capsule;

final class WorkerConfigService
{
  private const FORBIDDEN_KEYS = [
    'worker' => ['token'],
    'api' => ['base_url'],
  ];

  private const STRIP_WORKER_KEYS = ['node_id', 'hostname', 'proxmox_vmid'];

  public static function templateYaml(): string
  {
    $paths = [
      FleetSettings::repoPath() . '/deploy/proxmox/config.yaml.template',
      dirname(FleetSettings::repoPath()) . '/ms365-backup-worker/deploy/proxmox/config.yaml.template',
      dirname(__DIR__, 7) . '/ms365-backup-worker/deploy/proxmox/config.yaml.template',
    ];
    foreach ($paths as $path) {
      if (is_file($path)) {
        return (string) file_get_contents($path);
      }
    }

    return "worker:\n  node_id: \"\"\n  hostname: \"\"\n  max_concurrent_runs: 16\nkopia:\n  repo_config_dir: /var/lib/ms365-backup-worker/kopia\ngraph:\n  global_max_concurrency: 48\n";
  }

  /** @return array<string, mixed>|null */
  public static function getVersion(int $version): ?array
  {
    if (!Capsule::schema()->hasTable('ms365_worker_config') || $version <= 0) {
      return null;
    }
    $row = Capsule::table('ms365_worker_config')->where('version', $version)->first();

    return $row ? (array) $row : null;
  }

  /** @return array<string, mixed>|null */
  public static function current(): ?array
  {
    if (!Capsule::schema()->hasTable('ms365_worker_config')) {
      return null;
    }
    $row = Capsule::table('ms365_worker_config')->orderByDesc('version')->first();

    return $row ? (array) $row : null;
  }

  /** @return list<array<string, mixed>> */
  public static function versionHistory(int $limit = 20): array
  {
    if (!Capsule::schema()->hasTable('ms365_worker_config')) {
      return [];
    }

    return Capsule::table('ms365_worker_config')
      ->orderByDesc('version')
      ->limit($limit)
      ->get()
      ->map(static fn ($r) => (array) $r)
      ->all();
  }

  /** @return array{yaml: string, errors: list<string>} */
  public static function validateYaml(string $yaml): array
  {
    $errors = [];
    $yaml = str_replace("\r\n", "\n", trim($yaml));
    if ($yaml === '') {
      return ['yaml' => '', 'errors' => ['Config YAML is empty']];
    }

    foreach (self::findForbiddenKeys($yaml) as $key) {
      $errors[] = 'Forbidden key (use environment.conf): ' . $key;
    }

    foreach (['worker', 'kopia', 'graph'] as $section) {
      if (!preg_match('/^' . preg_quote($section, '/') . ':\s*$/m', $yaml)) {
        $errors[] = 'Missing required section: ' . $section;
      }
    }

    $clean = self::stripApiSection(self::stripPerNodeIdentity($yaml));
    if (function_exists('yaml_parse')) {
      $parsed = @yaml_parse($clean);
      if ($parsed === false) {
        $errors[] = 'YAML parse error';
      } elseif (!is_array($parsed)) {
        $errors[] = 'YAML must be a mapping';
      }
    }

    return ['yaml' => $clean, 'errors' => $errors];
  }

  public static function saveNewVersion(string $yaml, ?int $adminId): array
  {
    if (!Capsule::schema()->hasTable('ms365_worker_config')) {
      throw new \RuntimeException('Worker config table not installed — upgrade the module');
    }
    $validated = self::validateYaml($yaml);
    if ($validated['errors'] !== []) {
      throw new \RuntimeException(implode('; ', $validated['errors']));
    }
    $clean = $validated['yaml'];
    $sha256 = hash('sha256', $clean);
    $latest = self::current();
    $nextVersion = $latest ? ((int) $latest['version'] + 1) : 1;
    $now = time();
    Capsule::table('ms365_worker_config')->insert([
      'version' => $nextVersion,
      'yaml' => $clean,
      'sha256' => $sha256,
      'created_by_admin_id' => $adminId > 0 ? $adminId : null,
      'created_at' => $now,
    ]);
    FleetAuditLog::write('config_saved', 'Saved worker config v' . $nextVersion, 'worker_config', (string) $nextVersion);

    return [
      'version' => $nextVersion,
      'sha256' => $sha256,
      'created_at' => $now,
    ];
  }

  /** @param list<string> $nodeIds */
  public static function rollout(int $configVersion, array $nodeIds, string $strategy = 'explicit'): array
  {
    if (!Capsule::schema()->hasColumn('ms365_worker_nodes', 'target_config_version')) {
      throw new \RuntimeException('Worker config columns not installed — upgrade the module');
    }
    $config = self::getVersion($configVersion);
    if ($config === null) {
      throw new \RuntimeException('Config version not found');
    }

    $eligible = WorkerNodeRepository::listNodes(['active', 'draining', 'offline', 'registering']);
    $eligible = array_values(array_filter($eligible, static fn ($n) => ($n['status'] ?? '') !== 'retired'));

    if ($strategy === 'all') {
      $targets = $eligible;
    } elseif ($strategy === 'idle') {
      $targets = array_values(array_filter($eligible, static function ($n) {
        $nodeId = (string) ($n['node_id'] ?? '');

        return WorkerClaimService::effectiveReportedLoad($nodeId, (int) ($n['current_load'] ?? 0)) === 0;
      }));
    } elseif ($strategy === 'canary') {
      $state = FleetStateRepository::get();
      $canaryId = (string) ($state['canary_node_id'] ?? '');
      if ($canaryId === '' && $eligible !== []) {
        $canaryId = (string) ($eligible[0]['node_id'] ?? '');
      }
      $targets = array_values(array_filter($eligible, static fn ($n) => (string) ($n['node_id'] ?? '') === $canaryId));
    } else {
      $idSet = array_flip(array_map('strval', $nodeIds));
      $targets = array_values(array_filter($eligible, static fn ($n) => isset($idSet[(string) ($n['node_id'] ?? '')])));
    }

    if ($targets === []) {
      throw new \RuntimeException('No eligible nodes selected for rollout');
    }

    $now = time();
    $updated = 0;
    foreach ($targets as $node) {
      $nodeId = (string) ($node['node_id'] ?? '');
      $applied = (int) ($node['config_version'] ?? 0);
      if ($applied >= $configVersion) {
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
          'config_status' => 'current',
          'target_config_version' => null,
          'config_error' => '',
          'config_updated_at' => $now,
          'updated_at' => $now,
        ]);
        continue;
      }
      Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
        'target_config_version' => $configVersion,
        'config_status' => 'pending',
        'config_error' => '',
        'config_updated_at' => $now,
        'updated_at' => $now,
      ]);
      $updated++;
    }

    FleetAuditLog::write('config_rollout', 'Rollout config v' . $configVersion . ' to ' . count($targets) . ' node(s)', 'worker_config', (string) $configVersion, [
      'strategy' => $strategy,
      'nodes_targeted' => count($targets),
      'nodes_pending' => $updated,
    ]);

    return [
      'config_version' => $configVersion,
      'nodes_targeted' => count($targets),
      'nodes_pending' => $updated,
    ];
  }

  public static function reconcileFromHeartbeat(string $nodeId, int $configVersion, string $configError): void
  {
    if (!Capsule::schema()->hasColumn('ms365_worker_nodes', 'config_version')) {
      return;
    }
    $node = WorkerNodeRepository::get($nodeId);
    if ($node === null) {
      return;
    }
    $now = time();
    $update = ['updated_at' => $now];

    if ($configError !== '') {
      $update['config_status'] = 'failed';
      $update['config_error'] = mb_substr($configError, 0, 500);
      $update['config_updated_at'] = $now;
      Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update($update);

      return;
    }

    if ($configVersion > 0) {
      $update['config_version'] = $configVersion;
      $target = (int) ($node['target_config_version'] ?? 0);
      if ($target > 0 && $configVersion >= $target) {
        $update['config_status'] = 'current';
        $update['target_config_version'] = null;
        $update['config_error'] = '';
      } elseif ($target > 0 && $configVersion < $target) {
        $status = (string) ($node['config_status'] ?? 'current');
        if ($status === 'pending') {
          $update['config_status'] = 'applying';
        }
      }
      $update['config_updated_at'] = $now;
      Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update($update);
    }
  }

    /** @param array<string, mixed> $node */
    public static function configInstructionForNode(array $node): ?array
    {
        if (!Capsule::schema()->hasColumn('ms365_worker_nodes', 'target_config_version')) {
            return null;
        }
        $nodeId = (string) ($node['node_id'] ?? '');
        $applied = (int) ($node['config_version'] ?? 0);
        $target = (int) ($node['target_config_version'] ?? 0);
        if ($target <= 0 || $target <= $applied) {
            return null;
        }
        $config = self::getVersion($target);
        if ($config === null) {
            return null;
        }

        // Config apply restarts the worker — defer until the node is fully idle so
        // active backups are not cancelled mid-run (same policy as baseline deploy).
        $reportedLoad = (int) ($node['current_load'] ?? 0);
        $effectiveLoad = WorkerClaimService::effectiveReportedLoad($nodeId, $reportedLoad);
        $queueClaims = WorkerClaimService::runningClaimCountForNode($nodeId);
        if ($reportedLoad > 0 || $effectiveLoad > 0 || $queueClaims > 0) {
            return null;
        }

        $status = (string) ($node['config_status'] ?? 'current');
    if ($status !== 'applying') {
      Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
        'config_status' => 'applying',
        'config_updated_at' => time(),
        'updated_at' => time(),
      ]);
    }

    return [
      'version' => $target,
      'sha256' => (string) $config['sha256'],
      'download_url' => self::configDownloadUrl($target, $nodeId),
    ];
  }

  /** @return array<string, mixed> */
  public static function statusSummary(): array
  {
    if (!Capsule::schema()->hasColumn('ms365_worker_nodes', 'config_status')) {
      return ['current_version' => 0, 'nodes' => []];
    }
    $current = self::current();
    $nodes = WorkerNodeRepository::listNodes();
    $byStatus = ['current' => 0, 'pending' => 0, 'applying' => 0, 'failed' => 0];
    $nodeStates = [];
    foreach ($nodes as $n) {
      if (($n['status'] ?? '') === 'retired') {
        continue;
      }
      $st = (string) ($n['config_status'] ?? 'current');
      if (isset($byStatus[$st])) {
        $byStatus[$st]++;
      }
      $nodeStates[] = [
        'node_id' => $n['node_id'],
        'hostname' => $n['hostname'],
        'status' => $n['status'],
        'config_version' => (int) ($n['config_version'] ?? 0),
        'target_config_version' => $n['target_config_version'] !== null ? (int) $n['target_config_version'] : null,
        'config_status' => $st,
        'config_error' => (string) ($n['config_error'] ?? ''),
      ];
    }

    return [
      'current_version' => $current ? (int) $current['version'] : 0,
      'current_sha256' => $current ? (string) $current['sha256'] : '',
      'status_counts' => $byStatus,
      'nodes' => $nodeStates,
      'history' => self::versionHistory(10),
    ];
  }

  public static function issueConfigNonce(int $version, string $nodeId): string
  {
    $ttl = FleetSettings::artifactNonceTtlSeconds();
    $expires = time() + $ttl;
    $payload = $version . '|' . $nodeId . '|' . $expires;
    $sig = hash_hmac('sha256', $payload, self::signingKey());
    $nonce = base64_encode($payload . '|' . $sig);

    return rtrim(strtr($nonce, '+/', '-_'), '=');
  }

  /** @return array{version: int, node_id: string}|null */
  public static function verifyConfigNonce(string $nonce): ?array
  {
    $decoded = base64_decode(strtr($nonce, '-_', '+/') . str_repeat('=', (4 - strlen($nonce) % 4) % 4), true);
    if ($decoded === false) {
      return null;
    }
    $parts = explode('|', $decoded);
    if (count($parts) !== 4) {
      return null;
    }
    [$version, $nodeId, $expires, $sig] = $parts;
    if ((int) $expires < time()) {
      return null;
    }
    $payload = $version . '|' . $nodeId . '|' . $expires;
    $expected = hash_hmac('sha256', $payload, self::signingKey());
    if (!hash_equals($expected, $sig)) {
      return null;
    }

    return ['version' => (int) $version, 'node_id' => $nodeId];
  }

  public static function configDownloadUrl(int $version, string $nodeId): string
  {
    $nonce = self::issueConfigNonce($version, $nodeId);

    return FleetSettings::workerApiBaseUrl() . '/ms365_worker_config.php?version=' . $version . '&node=' . rawurlencode($nodeId) . '&nonce=' . rawurlencode($nonce);
  }

  public static function logConfigDownload(int $version, string $nodeId, string $nonce, string $ip): void
  {
    // Optional audit — reuse artifact downloads table pattern if needed later.
    unset($version, $nodeId, $nonce, $ip);
  }

  /** @return list<string> */
  private static function findForbiddenKeys(string $yaml): array
  {
    $found = [];
    $section = '';
    foreach (explode("\n", $yaml) as $line) {
      if (preg_match('/^([a-z_]+):\s*$/', $line, $m)) {
        $section = $m[1];
        continue;
      }
      if (!preg_match('/^(\s*)([a-z0-9_]+):\s*(.*)$/i', $line, $m)) {
        continue;
      }
      $indent = strlen($m[1]);
      $key = strtolower($m[2]);
      $ctx = $indent <= 2 ? $section : '';
      $value = self::yamlScalarValue($m[3]);
      if ($ctx === 'worker' && in_array($key, self::FORBIDDEN_KEYS['worker'], true) && $value !== '') {
        $found[] = 'worker.' . $key;
      }
      if ($ctx === 'api' && in_array($key, self::FORBIDDEN_KEYS['api'], true) && $value !== '') {
        $found[] = 'api.' . $key;
      }
      if ($key === 'token' && $section === 'worker' && $value !== '') {
        $found[] = 'worker.token';
      }
      if ($key === 'base_url' && $section === 'api' && $value !== '') {
        $found[] = 'api.base_url';
      }
    }

    return array_values(array_unique($found));
  }

  private static function stripPerNodeIdentity(string $yaml): string
  {
    $lines = explode("\n", $yaml);
    $out = [];
    $inWorker = false;
    foreach ($lines as $line) {
      if (preg_match('/^worker:\s*$/', $line)) {
        $inWorker = true;
        $out[] = $line;
        continue;
      }
      if ($inWorker && preg_match('/^[a-z_]+:\s*$/', $line) && !preg_match('/^\s/', $line)) {
        $inWorker = false;
      }
      if ($inWorker && preg_match('/^\s+(node_id|hostname|proxmox_vmid):\s*/', $line, $m)) {
        $key = $m[1];
        if ($key === 'proxmox_vmid') {
          $out[] = '  proxmox_vmid: 0';
        } else {
          $out[] = '  ' . $key . ': ""';
        }
        continue;
      }
      $out[] = $line;
    }

    return implode("\n", $out);
  }

  private static function stripApiSection(string $yaml): string
  {
    $lines = explode("\n", $yaml);
    $out = [];
    $inApi = false;
    foreach ($lines as $line) {
      if (preg_match('/^api:\s*$/', $line)) {
        $inApi = true;
        continue;
      }
      if ($inApi) {
        if (preg_match('/^[a-z_]+:\s*$/', $line) && !preg_match('/^\s/', $line)) {
          $inApi = false;
          $out[] = $line;
        }
        continue;
      }
      $out[] = $line;
    }

    return implode("\n", $out);
  }

  private static function yamlScalarValue(string $raw): string
  {
    $value = trim($raw);
    $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
    $value = trim($value, " \t\"'");

    return $value;
  }

  private static function signingKey(): string
  {
    $token = Ms365EngineConfig::workerToken();

    return hash('sha256', 'ms365-config|' . $token);
  }
}
