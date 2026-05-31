<?php

/**
 * e3backup_run_ticket_context.php
 *
 * Builds a pre-filled support-ticket context for a single e3 Cloud Backup
 * run, mirroring the eazybackup (Comet) job-ticket flow but run-scoped.
 *
 * GET/POST params:
 *   run_uuid (or run_id)  - the run to file a ticket about
 *   action                - 'context' (default) or 'duplicate'
 *
 * Returns JSON:
 *   context:   { subject, bodyMarkdown, deptId, priority, customFieldId,
 *                kbHints[], runMeta{} }
 *   duplicate: { ticket: {id, tid} | null }
 *
 * The browser (e3backup_run_ticket.js) stashes the log + this payload into
 * sessionStorage['eb_ticket_<runId>'] and redirects to
 * submitticket.php?step=2&eb_job=<runId>, where the theme's ticket-prefill.js
 * drains it. We reuse the same eb_job query param + sessionStorage prefix so
 * no theme changes are required; only the custom field differs (eb_run_id).
 */

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\TimezoneHelper;

function e3RunTicketFail($message, $code = 200)
{
    (new JsonResponse(['status' => 'fail', 'message' => $message], $code))->send();
    exit();
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    e3RunTicketFail('Session timeout.');
}
$clientId = (int) $ca->getUserID();

$runIdentifier = $_REQUEST['run_uuid'] ?? ($_REQUEST['run_id'] ?? null);
if (!$runIdentifier) {
    e3RunTicketFail('Run ID is required.');
}

$run = CloudBackupController::getRun($runIdentifier, $clientId);
if (!$run) {
    e3RunTicketFail('Run not found or access denied.', 404);
}

$action = $_REQUEST['action'] ?? 'context';
$runId = (string) ($run['run_id'] ?? $runIdentifier);

// Resolve the eb_run_id support custom field id (bootstrapped on activate).
$customFieldId = (int) (Capsule::table('tblcustomfields')
    ->where('type', 'support')
    ->where('fieldname', 'eb_run_id')
    ->value('id') ?? 0);

// ── Duplicate check: open ticket filed against this run in the last 7 days ──
if ($action === 'duplicate') {
    $ticket = null;
    if ($customFieldId > 0) {
        try {
            $row = Capsule::table('tblcustomfieldsvalues as v')
                ->join('tbltickets as t', 't.id', '=', 'v.relid')
                ->where('v.fieldid', $customFieldId)
                ->where('v.value', $runId)
                ->where('t.userid', $clientId)
                ->where('t.status', '!=', 'Closed')
                ->where('t.date', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
                ->orderByDesc('t.id')
                ->first(['t.id', 't.tid']);
            if ($row) {
                $ticket = ['id' => (int) $row->id, 'tid' => (string) $row->tid];
            }
        } catch (\Throwable $e) {
            $ticket = null;
        }
    }
    (new JsonResponse(['status' => 'success', 'ticket' => $ticket], 200))->send();
    exit();
}

// ── Build the ticket context ──
$job = CloudBackupController::getJob($run['job_id'] ?? '', $clientId);
$jobName = $job['name'] ?? 'Backup job';
$engine = strtolower((string) ($run['engine'] ?? ($job['engine'] ?? 'sync')));

// Agent hostname (best-effort).
$agentHostname = '';
$agentUuid = $run['agent_uuid'] ?? ($job['agent_uuid'] ?? null);
if (!empty($agentUuid)) {
    $agentHostname = (string) (Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->where('client_id', $clientId)
        ->value('hostname') ?? '');
}

$userTz = TimezoneHelper::resolveUserTimezone($clientId, $run['job_id'] ?? null);
$startedFmt = !empty($run['started_at']) ? TimezoneHelper::formatTimestamp($run['started_at'], $userTz) : '';
$finishedFmt = !empty($run['finished_at']) ? TimezoneHelper::formatTimestamp($run['finished_at'], $userTz) : '';

$status = strtolower((string) ($run['status'] ?? ''));
$statusLabel = ucfirst(str_replace('_', ' ', $status));
$isError = in_array($status, ['failed'], true);
$priority = $isError ? 'High' : 'Medium';

$dateStr = date('Y-m-d');
$subjectParts = array_filter([$agentHostname, $jobName]);
$subject = 'Cloud Backup ' . $statusLabel . ': ' . implode(' - ', $subjectParts) . ' - ' . $dateStr;

$errorSummary = trim((string) ($run['error_summary'] ?? ''));

$bodyLines = [];
$bodyLines[] = 'Hello eazyBackup support,';
$bodyLines[] = '';
$bodyLines[] = 'I would like assistance with the following e3 Cloud Backup run:';
$bodyLines[] = '';
$bodyLines[] = '- Job: ' . $jobName;
if ($agentHostname !== '') {
    $bodyLines[] = '- Agent: ' . $agentHostname;
}
$bodyLines[] = '- Engine: ' . $engine;
$bodyLines[] = '- Status: ' . $statusLabel;
if ($startedFmt !== '') {
    $bodyLines[] = '- Started: ' . $startedFmt;
}
if ($finishedFmt !== '') {
    $bodyLines[] = '- Finished: ' . $finishedFmt;
}
$bodyLines[] = '- Run ID: ' . $runId;
if ($errorSummary !== '') {
    $bodyLines[] = '';
    $bodyLines[] = 'Error summary:';
    $bodyLines[] = $errorSummary;
}
$bodyLines[] = '';
$bodyLines[] = 'The run log is attached. Thank you.';
$bodyMarkdown = implode("\n", $bodyLines);

// ── KB hints (best-effort; KbSuggester lives in the eazybackup addon) ──
$kbHints = [];
try {
    $kbPath = __DIR__ . '/../../eazybackup/lib/KbSuggester.php';
    if (is_file($kbPath)) {
        require_once $kbPath;
        $kbClass = 'EazyBackup\\Lib\\KbSuggester';
        if (class_exists($kbClass)) {
            $suggester = new $kbClass();
            $logRows = [];
            if (!empty($run['log_excerpt'])) {
                foreach (preg_split('/\r?\n/', (string) $run['log_excerpt']) as $line) {
                    $logRows[] = ['Severity' => 'E', 'Message' => $line];
                }
            }
            $hints = $suggester->suggest($logRows, $engine, $status);
            if (is_array($hints)) {
                $kbHints = array_slice($hints, 0, 3);
            }
        }
    }
} catch (\Throwable $e) {
    $kbHints = [];
}

$runMeta = [
    'runId' => $runId,
    'jobName' => $jobName,
    'agent' => $agentHostname,
    'engine' => $engine,
    'status' => $statusLabel,
    'started' => $startedFmt,
    'finished' => $finishedFmt,
];

(new JsonResponse([
    'status' => 'success',
    'subject' => $subject,
    'bodyMarkdown' => $bodyMarkdown,
    'deptId' => 1,
    'priority' => $priority,
    'customFieldId' => $customFieldId,
    'kbHints' => $kbHints,
    'runMeta' => $runMeta,
], 200))->send();
exit();
