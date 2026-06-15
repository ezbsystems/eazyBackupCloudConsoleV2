<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Creates a production deployment manifest from published artifacts on the dev server.
 */
class DeployPublisher
{
  /**
   * Deploy the current is_latest releases (or all artifacts from a specific job).
   *
   * @return array{deployment_id: int, version_label: string, artifact_count: int}
   */
  public static function publishLatest(?int $adminId = null): array
  {
    return self::publishFromReleases(self::gatherLatestReleases(), null, $adminId);
  }

  /**
   * @return array{deployment_id: int, version_label: string, artifact_count: int}
   */
  public static function publishJob(int $jobId, ?int $adminId = null): array
  {
    $job = JobStore::getJob($jobId);
    if (!$job) {
      throw new \RuntimeException('Build job not found');
    }
    if (($job['status'] ?? '') !== 'succeeded') {
      throw new \RuntimeException('Build job must have succeeded before deployment');
    }
    $releases = self::gatherReleasesForJob($jobId);
    if ($releases === []) {
      throw new \RuntimeException('No published releases found for this build job');
    }

    return self::publishFromReleases($releases, $jobId, $adminId);
  }

  /**
   * @param list<array> $releases
   * @return array{deployment_id: int, version_label: string, artifact_count: int}
   */
  private static function publishFromReleases(array $releases, ?int $jobId, ?int $adminId): array
  {
    if ($releases === []) {
      throw new \RuntimeException('No published releases available to deploy');
    }

    $publishDir = (string) Settings::get('agent_build_publish_dir', '/var/www/eazybackup.ca/accounts/client_installer');
    $artifacts = [];
    $versionLabel = '';
    $gitCommit = '';

    foreach ($releases as $release) {
      $key = self::artifactKeyForRelease($release);
      if ($key === '') {
        continue;
      }
      $def = DeployStore::ARTIFACT_DEFS[$key];
      $version = (string) ($release['version_label'] ?? '');
      if ($versionLabel === '' && $version !== '') {
        $versionLabel = $version;
      }
      if ($gitCommit === '' && !empty($release['git_commit'])) {
        $gitCommit = (string) $release['git_commit'];
      }

      $versioned = DeployStore::versionedFilename($key, $version);
      $verPath = $publishDir . '/' . $versioned;
      $latestPath = $publishDir . '/' . $def['latest'];

      $srcPath = file_exists($verPath) ? $verPath : (file_exists($latestPath) ? $latestPath : '');
      if ($srcPath === '') {
        throw new \RuntimeException('Artifact file missing on disk: ' . $def['latest']);
      }

      $sha = hash_file('sha256', $srcPath) ?: (string) ($release['sha256'] ?? '');
      if ($sha === '') {
        throw new \RuntimeException('Unable to compute SHA-256 for ' . $def['latest']);
      }

      $artifacts[] = [
        'artifact_key' => $key,
        'platform' => $def['platform'],
        'latest_filename' => $def['latest'],
        'versioned_filename' => $versioned !== '' ? $versioned : basename($srcPath),
        'sha256' => $sha,
        'size_bytes' => (int) filesize($srcPath),
        'signed_subject' => $release['signed_subject'] ?? null,
        'signed_at' => $release['signed_at'] ?? null,
      ];
    }

    if ($artifacts === []) {
      throw new \RuntimeException('No deployable artifacts found');
    }

    $deploymentId = DeployStore::createDeployment([
      'job_id' => $jobId,
      'version_label' => $versionLabel,
      'git_commit' => $gitCommit,
      'created_by_admin_id' => $adminId,
    ], $artifacts);

    return [
      'deployment_id' => $deploymentId,
      'version_label' => $versionLabel,
      'artifact_count' => count($artifacts),
    ];
  }

  /** @return list<array> */
  private static function gatherLatestReleases(): array
  {
    $rows = Capsule::table('s3_agent_releases')
      ->where('is_latest', 1)
      ->orderByDesc('id')
      ->get()
      ->all();

    return array_map(static fn($r) => (array) $r, $rows);
  }

  /** @return list<array> */
  private static function gatherReleasesForJob(int $jobId): array
  {
    $rows = Capsule::table('s3_agent_releases')
      ->where('job_id', $jobId)
      ->orderByDesc('id')
      ->get()
      ->all();

    return array_map(static fn($r) => (array) $r, $rows);
  }

  /** @param array $release */
  private static function artifactKeyForRelease(array $release): string
  {
    $filename = (string) ($release['artifact_filename'] ?? '');
    foreach (DeployStore::ARTIFACT_DEFS as $key => $def) {
      if ($def['latest'] === $filename) {
        return $key;
      }
    }
    return '';
  }

  /** @return array<string, mixed>|null */
  public static function activeManifestPayload(): ?array
  {
    $deployment = DeployStore::activeDeployment();
    if (!$deployment) {
      return null;
    }
    $deploymentId = (int) $deployment['id'];
    $artifacts = DeployStore::artifactsForDeployment($deploymentId);
    $out = [];
    foreach ($artifacts as $art) {
      $key = (string) $art['artifact_key'];
      $out[] = [
        'key' => $key,
        'platform' => $art['platform'],
        'latest_filename' => $art['latest_filename'],
        'versioned_filename' => $art['versioned_filename'],
        'sha256' => $art['sha256'],
        'size_bytes' => (int) ($art['size_bytes'] ?? 0),
        'download_url' => DeployAuth::artifactApiUrl($deploymentId, $key),
      ];
    }

    return [
      'deployment_id' => $deploymentId,
      'version_label' => $deployment['version_label'] ?? '',
      'git_commit' => $deployment['git_commit'] ?? '',
      'activated_at' => $deployment['activated_at'] ?? '',
      'artifacts' => $out,
    ];
  }
}
