<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shared-secret auth for dev→prod agent deployment manifest and artifact APIs.
 */
class DeployAuth
{
  public const HEADER = 'X-Agent-Deploy-Token';

  public static function authenticate(Request $request): ?JsonResponse
  {
    $expected = self::sharedToken();
    if ($expected === '') {
      return new JsonResponse(['status' => 'error', 'message' => 'Deploy token not configured'], 503);
    }
    $provided = trim((string) $request->headers->get(self::HEADER, ''));
    if ($provided === '') {
      $provided = trim((string) $request->query->get('token', ''));
    }
    if ($provided === '' || !hash_equals($expected, $provided)) {
      return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    return null;
  }

  public static function sharedToken(): string
  {
    return trim((string) Settings::decryptedSecret('agent_deploy_shared_secret'));
  }

  public static function nonceTtlSeconds(): int
  {
    $v = (int) Settings::get('agent_deploy_nonce_ttl', '600');
    return $v > 60 ? $v : 600;
  }

  public static function issueNonce(int $deploymentId, string $artifactKey): string
  {
    $expires = time() + self::nonceTtlSeconds();
    $payload = $deploymentId . '|' . $artifactKey . '|' . $expires;
    $sig = hash_hmac('sha256', $payload, self::signingKey());
    $nonce = base64_encode($payload . '|' . $sig);

    return rtrim(strtr($nonce, '+/', '-_'), '=');
  }

  /** @return array{deployment_id: int, artifact_key: string}|null */
  public static function verifyNonce(string $nonce): ?array
  {
    $pad = (4 - strlen($nonce) % 4) % 4;
    $decoded = base64_decode(strtr($nonce, '-_', '+/') . str_repeat('=', $pad), true);
    if ($decoded === false) {
      return null;
    }
    $parts = explode('|', $decoded);
    if (count($parts) !== 4) {
      return null;
    }
    [$deploymentId, $artifactKey, $expires, $sig] = $parts;
    if ((int) $expires < time()) {
      return null;
    }
    $payload = $deploymentId . '|' . $artifactKey . '|' . $expires;
    $expected = hash_hmac('sha256', $payload, self::signingKey());
    if (!hash_equals($expected, $sig)) {
      return null;
    }

    return ['deployment_id' => (int) $deploymentId, 'artifact_key' => $artifactKey];
  }

  public static function manifestApiUrl(): string
  {
    $base = rtrim((string) \WHMCS\Config\Setting::getValue('SystemURL'), '/');
    return $base . '/modules/addons/cloudstorage/api/agent_deploy_manifest.php';
  }

  public static function artifactApiUrl(int $deploymentId, string $artifactKey): string
  {
    $nonce = self::issueNonce($deploymentId, $artifactKey);
    $base = rtrim((string) \WHMCS\Config\Setting::getValue('SystemURL'), '/');

    return $base . '/modules/addons/cloudstorage/api/agent_deploy_artifact.php'
      . '?deployment_id=' . $deploymentId
      . '&artifact_key=' . rawurlencode($artifactKey)
      . '&nonce=' . rawurlencode($nonce);
  }

  private static function signingKey(): string
  {
    return hash('sha256', 'agent-deploy-artifact|' . self::sharedToken());
  }
}
