<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

use Illuminate\Database\Capsule\Manager as Capsule;

class DeployStore
{
  /** @var array<string, array{platform: string, latest: string, versioned_prefix: string, versioned_ext: string}> */
  public const ARTIFACT_DEFS = [
    'linux' => [
      'platform' => 'linux',
      'latest' => 'e3-backup-agent-linux',
      'versioned_prefix' => 'e3-backup-agent-linux-',
      'versioned_ext' => '',
    ],
    'windows' => [
      'platform' => 'windows',
      'latest' => 'e3-backup-agent-setup.exe',
      'versioned_prefix' => 'e3-backup-agent-setup-',
      'versioned_ext' => '.exe',
    ],
    'recovery' => [
      'platform' => 'recovery_iso',
      'latest' => 'e3-recovery-agent.exe',
      'versioned_prefix' => 'e3-recovery-agent-',
      'versioned_ext' => '.exe',
    ],
    'recovery_media' => [
      'platform' => 'recovery_media',
      'latest' => 'e3-recovery-media-creator.exe',
      'versioned_prefix' => 'e3-recovery-media-creator-',
      'versioned_ext' => '.exe',
    ],
  ];

  public static function versionedFilename(string $artifactKey, string $version): string
  {
    $def = self::ARTIFACT_DEFS[$artifactKey] ?? null;
    if (!$def) {
      return '';
    }
    return $def['versioned_prefix'] . $version . $def['versioned_ext'];
  }

  public static function activeDeployment(): ?array
  {
    if (!Capsule::schema()->hasTable('s3_agent_deployments')) {
      return null;
    }
    $row = Capsule::table('s3_agent_deployments')
      ->where('status', 'active')
      ->orderByDesc('id')
      ->first();
    return $row ? (array) $row : null;
  }

  public static function getDeployment(int $id): ?array
  {
    $row = Capsule::table('s3_agent_deployments')->where('id', $id)->first();
    return $row ? (array) $row : null;
  }

  /** @return list<array> */
  public static function listDeployments(int $limit = 25): array
  {
    return array_map(
      static fn($r) => (array) $r,
      Capsule::table('s3_agent_deployments')->orderByDesc('id')->limit($limit)->get()->all()
    );
  }

  /** @return list<array> */
  public static function artifactsForDeployment(int $deploymentId): array
  {
    return array_map(
      static fn($r) => (array) $r,
      Capsule::table('s3_agent_deploy_artifacts')
        ->where('deployment_id', $deploymentId)
        ->orderBy('id')
        ->get()
        ->all()
    );
  }

  public static function supersedeActive(): void
  {
    Capsule::table('s3_agent_deployments')
      ->where('status', 'active')
      ->update(['status' => 'superseded']);
  }

  /**
   * @param list<array> $artifacts
   */
  public static function createDeployment(array $data, array $artifacts): int
  {
    $now = date('Y-m-d H:i:s');
    return (int) Capsule::connection()->transaction(function () use ($data, $artifacts, $now) {
      self::supersedeActive();
      $deploymentId = (int) Capsule::table('s3_agent_deployments')->insertGetId([
        'job_id' => $data['job_id'] ?? null,
        'version_label' => $data['version_label'] ?? null,
        'git_commit' => $data['git_commit'] ?? null,
        'status' => 'active',
        'artifacts_json' => json_encode($artifacts),
        'created_by_admin_id' => $data['created_by_admin_id'] ?? null,
        'activated_at' => $now,
        'created_at' => $now,
      ]);
      foreach ($artifacts as $art) {
        Capsule::table('s3_agent_deploy_artifacts')->insert([
          'deployment_id' => $deploymentId,
          'artifact_key' => $art['artifact_key'],
          'platform' => $art['platform'],
          'latest_filename' => $art['latest_filename'],
          'versioned_filename' => $art['versioned_filename'],
          'sha256' => $art['sha256'] ?? null,
          'size_bytes' => $art['size_bytes'] ?? null,
          'signed_subject' => $art['signed_subject'] ?? null,
          'signed_at' => $art['signed_at'] ?? null,
          'created_at' => $now,
        ]);
      }
      return $deploymentId;
    });
  }

  public static function logDownload(int $deploymentId, string $artifactKey, string $nonce, string $ip): void
  {
    if (!Capsule::schema()->hasTable('s3_agent_deploy_downloads')) {
      return;
    }
    Capsule::table('s3_agent_deploy_downloads')->insert([
      'deployment_id' => $deploymentId,
      'artifact_key' => $artifactKey,
      'nonce' => mb_substr($nonce, 0, 128),
      'ip_address' => mb_substr($ip, 0, 45),
      'downloaded_at' => date('Y-m-d H:i:s'),
      'created_at' => date('Y-m-d H:i:s'),
    ]);
  }

  public static function startSyncRun(int $deploymentId): int
  {
    $now = date('Y-m-d H:i:s');
    return (int) Capsule::table('s3_agent_deploy_sync_runs')->insertGetId([
      'deployment_id' => $deploymentId,
      'status' => 'running',
      'started_at' => $now,
      'created_at' => $now,
    ]);
  }

  public static function finishSyncRun(int $runId, string $status, string $detail = ''): void
  {
    Capsule::table('s3_agent_deploy_sync_runs')->where('id', $runId)->update([
      'status' => $status,
      'detail' => $detail,
      'ended_at' => date('Y-m-d H:i:s'),
    ]);
  }

  /** Record a completed sync attempt (including skipped/failed before install). */
  public static function recordSyncOutcome(?int $deploymentId, string $status, string $detail = ''): void
  {
    if (!Capsule::schema()->hasTable('s3_agent_deploy_sync_runs')) {
      return;
    }
    $now = date('Y-m-d H:i:s');
    Capsule::table('s3_agent_deploy_sync_runs')->insert([
      'deployment_id' => $deploymentId,
      'status' => $status,
      'detail' => mb_substr($detail, 0, 2000),
      'started_at' => $now,
      'ended_at' => $now,
      'created_at' => $now,
    ]);
  }

  /** @return list<array> */
  public static function listSyncRuns(int $limit = 10): array
  {
    if (!Capsule::schema()->hasTable('s3_agent_deploy_sync_runs')) {
      return [];
    }
    return array_map(
      static fn($r) => (array) $r,
      Capsule::table('s3_agent_deploy_sync_runs')->orderByDesc('id')->limit($limit)->get()->all()
    );
  }

  public static function lastSyncId(): int
  {
    return (int) Settings::get('agent_deploy_last_sync_id', '0');
  }

  public static function setLastSyncId(int $deploymentId): void
  {
    Capsule::table('tbladdonmodules')
      ->where('module', 'cloudstorage')
      ->where('setting', 'agent_deploy_last_sync_id')
      ->delete();
    Capsule::table('tbladdonmodules')->insert([
      'module' => 'cloudstorage',
      'setting' => 'agent_deploy_last_sync_id',
      'value' => (string) $deploymentId,
    ]);
    Settings::clearCache();
  }
}
