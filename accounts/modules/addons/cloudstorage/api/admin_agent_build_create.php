<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['adminid'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required']))->send();
    exit;
}

$platform = (string) ($_POST['platform'] ?? 'both');
if (!in_array($platform, ['linux', 'windows', 'both', 'recovery_iso'], true)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'invalid platform']))->send();
    exit;
}
$gitRef = trim((string) ($_POST['git_ref'] ?? 'main'));
$version = trim((string) ($_POST['version_label'] ?? ''));
if ($version === '') {
    $version = date('Y.m.d-His');
}
$flags = [
    'run_tests'        => !empty($_POST['run_tests']),
    'sign'             => !empty($_POST['sign']),
    'publish'          => !empty($_POST['publish']),
    'include_recovery' => !empty($_POST['include_recovery']),
];

try {
    $jobId = JobStore::createJob([
        'admin_id'      => (int) $_SESSION['adminid'],
        'platform'      => $platform,
        'git_ref'       => $gitRef ?: 'main',
        'version_label' => $version,
        'flags'         => $flags,
    ]);
    (new JsonResponse(['status' => 'success', 'job_id' => $jobId]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => $e->getMessage()]))->send();
}
