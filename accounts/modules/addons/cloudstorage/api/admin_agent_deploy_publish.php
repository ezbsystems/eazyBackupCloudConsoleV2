<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeployPublisher;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['adminid'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required']))->send();
    exit;
}

$mode = trim((string) ($_POST['mode'] ?? 'latest'));
$jobId = (int) ($_POST['job_id'] ?? 0);
$adminId = (int) $_SESSION['adminid'];

try {
    if ($mode === 'job' && $jobId > 0) {
        $result = DeployPublisher::publishJob($jobId, $adminId);
    } else {
        $result = DeployPublisher::publishLatest($adminId);
    }
    (new JsonResponse(['status' => 'success'] + $result))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => $e->getMessage()]))->send();
}
