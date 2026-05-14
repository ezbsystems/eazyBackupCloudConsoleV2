<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['adminid'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required']))->send();
    exit;
}

$releaseId = (int) ($_POST['release_id'] ?? 0);
if ($releaseId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'release_id required']))->send();
    exit;
}
$row = Capsule::table('s3_agent_releases')->where('id', $releaseId)->first();
if (!$row) {
    (new JsonResponse(['status' => 'fail', 'message' => 'release not found']))->send();
    exit;
}

// Flip latest flag
Capsule::table('s3_agent_releases')
    ->where('platform', $row->platform)
    ->where('artifact_filename', $row->artifact_filename)
    ->update(['is_latest' => 0]);
Capsule::table('s3_agent_releases')->where('id', $releaseId)->update(['is_latest' => 1]);

// Repoint the "latest" alias on disk if both files exist
$publishDir = (string) Settings::get('agent_build_publish_dir', '/var/www/eazybackup.ca/accounts/client_installer');
$ext = pathinfo($row->artifact_filename, PATHINFO_EXTENSION);
$base = pathinfo($row->artifact_filename, PATHINFO_FILENAME);
$candidate = $publishDir . '/' . $base . '-' . $row->version_label . ($ext ? '.' . $ext : '');
$latest = $publishDir . '/' . $row->artifact_filename;
if (file_exists($candidate)) {
    @copy($candidate, $latest);
    @chmod($latest, 0644);
}

(new JsonResponse(['status' => 'success']))->send();
