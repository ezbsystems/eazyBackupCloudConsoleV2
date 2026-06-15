<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Production-side pull sync: fetch manifest from dev, download artifacts, install locally.
 */
class DeploySync
{
  /**
   * @return array{status: string, message: string, deployment_id?: int}
   */
  public static function runOnce(): array
  {
    if (!Settings::getBool('agent_deploy_sync_enabled', false)) {
      $r = ['status' => 'skipped', 'message' => 'Sync disabled'];
      DeployStore::recordSyncOutcome(null, 'skipped', $r['message']);
      return $r;
    }

    $manifestUrl = trim((string) Settings::get('agent_deploy_manifest_url', ''));
    if ($manifestUrl === '') {
      $r = ['status' => 'skipped', 'message' => 'Manifest URL not configured'];
      DeployStore::recordSyncOutcome(null, 'skipped', $r['message']);
      return $r;
    }

    $token = DeployAuth::sharedToken();
    if ($token === '') {
      $r = ['status' => 'failed', 'message' => 'Deploy shared secret not configured'];
      DeployStore::recordSyncOutcome(null, 'failed', $r['message']);
      return $r;
    }

    try {
      $manifest = self::fetchManifest($manifestUrl, $token);
    } catch (\Throwable $e) {
      $r = ['status' => 'failed', 'message' => $e->getMessage()];
      DeployStore::recordSyncOutcome(null, 'failed', $r['message']);
      return $r;
    }
    if ($manifest === null) {
      $r = ['status' => 'failed', 'message' => 'Unable to fetch deployment manifest (network or invalid response)'];
      DeployStore::recordSyncOutcome(null, 'failed', $r['message']);
      return $r;
    }
    if ($manifest === []) {
      $r = ['status' => 'skipped', 'message' => 'No active deployment on publisher'];
      DeployStore::recordSyncOutcome(null, 'skipped', $r['message']);
      return $r;
    }

    $deploymentId = (int) ($manifest['deployment_id'] ?? 0);
    if ($deploymentId <= 0) {
      $r = ['status' => 'skipped', 'message' => 'No active deployment on publisher'];
      DeployStore::recordSyncOutcome(null, 'skipped', $r['message']);
      return $r;
    }

    if ($deploymentId === DeployStore::lastSyncId()) {
      $r = ['status' => 'skipped', 'message' => 'Already synced deployment ' . $deploymentId];
      DeployStore::recordSyncOutcome($deploymentId, 'skipped', $r['message']);
      return $r;
    }

    $runId = DeployStore::startSyncRun($deploymentId);
    try {
      $count = self::installManifest($manifest);
      DeployStore::setLastSyncId($deploymentId);
      $detail = 'Installed ' . $count . ' artifact(s) for deployment ' . $deploymentId;
      DeployStore::finishSyncRun($runId, 'succeeded', $detail);
      return ['status' => 'succeeded', 'message' => $detail, 'deployment_id' => $deploymentId];
    } catch (\Throwable $e) {
      DeployStore::finishSyncRun($runId, 'failed', $e->getMessage());
      return ['status' => 'failed', 'message' => $e->getMessage()];
    }
  }

  /**
   * @return array<string, mixed>|null null = hard failure; [] = auth ok but no deployment
   */
  private static function fetchManifest(string $url, string $token): ?array
  {
    $raw = self::httpGet($url, $token);
    if ($raw === null) {
      return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      return null;
    }
    $status = (string) ($decoded['status'] ?? '');
    if ($status === 'error') {
      $msg = (string) ($decoded['message'] ?? 'unknown error');
      if (stripos($msg, 'unauthorized') !== false) {
        throw new \RuntimeException('Manifest auth failed: check shared deploy secret on consumer matches publisher');
      }
      throw new \RuntimeException('Manifest endpoint error: ' . $msg);
    }
    if ($status !== 'success') {
      return null;
    }
    $manifest = $decoded['manifest'] ?? null;
    if ($manifest === null) {
      return [];
    }
    return is_array($manifest) ? $manifest : null;
  }

  private static function httpGet(string $url, string $token): ?string
  {
    $headers = [
      DeployAuth::HEADER . ': ' . $token,
      'Accept: application/json',
      'User-Agent: e3-agent-deploy-sync/1.0',
    ];

    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
      ]);
      $raw = curl_exec($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $err = curl_error($ch);
      curl_close($ch);
      if ($raw === false || $raw === '') {
        throw new \RuntimeException('Manifest HTTP request failed' . ($err !== '' ? ': ' . $err : ''));
      }
      if ($code === 401 || $code === 403) {
        throw new \RuntimeException('Manifest auth failed (HTTP ' . $code . '): shared deploy secret on consumer does not match publisher');
      }
      if ($code >= 400 && stripos((string) $raw, '{') !== 0) {
        throw new \RuntimeException('Manifest HTTP ' . $code . ': non-JSON response (possible WAF block)');
      }
      return (string) $raw;
    }

    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers) . "\r\n",
        'timeout' => 120,
        'ignore_errors' => true,
      ],
      'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
      ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
      return null;
    }
    return (string) $raw;
  }

  /**
   * @param array<string, mixed> $manifest
   */
  private static function installManifest(array $manifest): int
  {
    $publishDir = (string) Settings::get(
      'agent_deploy_publish_dir',
      (string) Settings::get('agent_build_publish_dir', '/var/www/eazybackup.ca/accounts/client_installer')
    );
    if (!is_dir($publishDir)) {
      @mkdir($publishDir, 0755, true);
    }
    $stagingDir = $publishDir . '/.staging';
    if (!is_dir($stagingDir)) {
      @mkdir($stagingDir, 0750, true);
    }

    $artifacts = $manifest['artifacts'] ?? [];
    if (!is_array($artifacts) || $artifacts === []) {
      throw new \RuntimeException('Manifest contains no artifacts');
    }

    $versionLabel = (string) ($manifest['version_label'] ?? '');
    $gitCommit = (string) ($manifest['git_commit'] ?? '');
    $deploymentId = (int) ($manifest['deployment_id'] ?? 0);
    $installed = 0;

    foreach ($artifacts as $art) {
      if (!is_array($art)) {
        continue;
      }
      $downloadUrl = (string) ($art['download_url'] ?? '');
      $expectedSha = strtolower((string) ($art['sha256'] ?? ''));
      $latestName = (string) ($art['latest_filename'] ?? '');
      $versionedName = (string) ($art['versioned_filename'] ?? '');
      $platform = (string) ($art['platform'] ?? '');
      $key = (string) ($art['key'] ?? '');

      if ($downloadUrl === '' || $latestName === '' || $expectedSha === '') {
        throw new \RuntimeException('Invalid artifact entry in manifest: ' . $key);
      }

      $tmpPath = $stagingDir . '/' . $latestName . '.tmp';
      self::downloadFile($downloadUrl, $tmpPath);

      $actualSha = strtolower(hash_file('sha256', $tmpPath) ?: '');
      if ($actualSha !== $expectedSha) {
        @unlink($tmpPath);
        throw new \RuntimeException('SHA-256 mismatch for ' . $latestName . ' (expected ' . $expectedSha . ', got ' . $actualSha . ')');
      }

      $verPath = $publishDir . '/' . ($versionedName !== '' ? $versionedName : $latestName);
      $latestPath = $publishDir . '/' . $latestName;

      if (!@rename($tmpPath, $verPath)) {
        if (!@copy($tmpPath, $verPath)) {
          @unlink($tmpPath);
          throw new \RuntimeException('Failed to write versioned artifact: ' . $verPath);
        }
        @unlink($tmpPath);
      }
      @chmod($verPath, 0644);

      if ($verPath !== $latestPath) {
        if (!@copy($verPath, $latestPath)) {
          throw new \RuntimeException('Failed to update latest alias: ' . $latestPath);
        }
        @chmod($latestPath, 0644);
      }

      self::upsertRelease([
        'deployment_id' => $deploymentId,
        'platform' => $platform,
        'artifact_filename' => $latestName,
        'version_label' => $versionLabel,
        'git_commit' => $gitCommit,
        'sha256' => $actualSha,
        'size_bytes' => (int) filesize($latestPath),
      ]);

      $installed++;
    }

    return $installed;
  }

  private static function downloadFile(string $url, string $destPath): void
  {
    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => 600,
        'ignore_errors' => true,
      ],
      'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
      ],
    ]);
    $in = @fopen($url, 'rb', false, $ctx);
    if ($in === false) {
      throw new \RuntimeException('Unable to open download URL: ' . $url);
    }
    $out = @fopen($destPath, 'wb');
    if ($out === false) {
      fclose($in);
      throw new \RuntimeException('Unable to write staging file: ' . $destPath);
    }
    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);
    if (!is_file($destPath) || filesize($destPath) === 0) {
      @unlink($destPath);
      throw new \RuntimeException('Downloaded file is empty: ' . $url);
    }
  }

  /** @param array<string, mixed> $data */
  private static function upsertRelease(array $data): void
  {
    if (!Capsule::schema()->hasTable('s3_agent_releases')) {
      return;
    }
    $platform = (string) $data['platform'];
    $filename = (string) $data['artifact_filename'];

    Capsule::table('s3_agent_releases')
      ->where('platform', $platform)
      ->where('artifact_filename', $filename)
      ->update(['is_latest' => 0]);

    Capsule::table('s3_agent_releases')->insert([
      'job_id' => null,
      'platform' => $platform,
      'artifact_filename' => $filename,
      'version_label' => $data['version_label'] ?? null,
      'git_commit' => $data['git_commit'] ?? null,
      'sha256' => $data['sha256'] ?? null,
      'size_bytes' => $data['size_bytes'] ?? null,
      'is_latest' => 1,
      'download_url' => '/client_installer/' . $filename,
      'published_at' => date('Y-m-d H:i:s'),
      'created_at' => date('Y-m-d H:i:s'),
    ]);
  }
}
