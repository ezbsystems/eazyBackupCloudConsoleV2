<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['adminid'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required']))->send();
    exit;
}

$jobId = (int) ($_POST['job_id'] ?? 0);
if ($jobId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'job_id required']))->send();
    exit;
}
$job = JobStore::getJob($jobId);
if (!$job) {
    (new JsonResponse(['status' => 'fail', 'message' => 'job not found']))->send();
    exit;
}
if (in_array($job['status'], ['queued', 'running'], true)) {
    JobStore::updateJob($jobId, ['status' => 'cancelled', 'ended_at' => date('Y-m-d H:i:s')]);
}
(new JsonResponse(['status' => 'success']))->send();
