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

$jobId = (int) ($_GET['job_id'] ?? 0);
$step  = (string) ($_GET['step'] ?? '');
$offset = (int) ($_GET['offset'] ?? 0);
$maxBytes = 256 * 1024;

if ($jobId <= 0 || $step === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'job_id and step required']))->send();
    exit;
}

$logPath = JobStore::jobLogDir($jobId) . '/' . preg_replace('/[^a-z0-9_]/i', '', $step) . '.log';
$size = file_exists($logPath) ? (int) filesize($logPath) : 0;
$chunk = '';
if ($size > 0 && $offset < $size) {
    $fh = @fopen($logPath, 'rb');
    if ($fh) {
        @fseek($fh, $offset);
        $chunk = (string) @fread($fh, $maxBytes);
        @fclose($fh);
    }
}
(new JsonResponse([
    'status'   => 'success',
    'size'     => $size,
    'offset'   => $offset,
    'next'     => $offset + strlen($chunk),
    'chunk'    => $chunk,
    'truncated'=> ($offset + strlen($chunk)) < $size,
]))->send();
