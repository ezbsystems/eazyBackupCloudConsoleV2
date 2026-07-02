<?php
declare(strict_types=1);

/**
 * Workload report table + error formatting for MS365 backup report emails.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_backup_report_email_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Ms365BackupReportEmailService;

$failures = 0;

function assert_true(bool $cond, string $message): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

function assert_contains(string $haystack, string $needle, string $message): void
{
    assert_true(str_contains($haystack, $needle), $message);
}

$childWithSkip = [
    'workload_label' => 'user: Adele Vance',
    'status' => 'success',
    'attempts' => 1,
    'max_attempts' => 3,
    'queue_status' => 'done',
    'error_message' => '',
    'queue_error' => '',
    'workload_skipped' => ['tasks' => 'mailbox_not_enabled'],
];

$errorText = Ms365BackupReportEmailService::formatChildError($childWithSkip);
assert_contains($errorText, 'Skipped: tasks=mailbox_not_enabled', 'skipped workload in error column');

$childWithQueueError = [
    'error_message' => 'Graph timeout',
    'queue_error' => 'lease expired',
    'workload_skipped' => [],
];
$errorMixed = Ms365BackupReportEmailService::formatChildError($childWithQueueError);
assert_contains($errorMixed, 'Graph timeout', 'run error present');
assert_contains($errorMixed, 'Queue: lease expired', 'queue error present');

$attempts = Ms365BackupReportEmailService::formatAttempts([
    'attempts' => 2,
    'max_attempts' => 3,
    'queue_status' => 'running',
]);
assert_true($attempts === '2/3 (running)', 'attempts column format');

$reports = Ms365BackupReportEmailService::buildWorkloadReports([
    $childWithSkip,
    [
        'workload_label' => 'sharepoint_site: Team Site',
        'status' => 'failed',
        'attempts' => 3,
        'max_attempts' => 3,
        'queue_status' => '',
        'error_message' => 'upload stalled',
        'queue_error' => '',
        'workload_skipped' => [],
    ],
]);
assert_contains($reports['html'], '<th', 'html table headers');
assert_contains($reports['html'], 'Adele Vance', 'html workload label');
assert_contains($reports['html'], 'Skipped: tasks=mailbox_not_enabled', 'html skipped error');
assert_contains($reports['text'], 'user: Adele Vance', 'plain text workload row');
assert_contains($reports['text'], 'sharepoint_site: Team Site', 'plain text second row');

$rendered = Ms365BackupReportEmailService::renderTemplateContent(
    '[{$run_status}] {$job_name} for {$backup_username}',
    '<p>Hi {$client_first_name}</p>{$workload_report_html}',
    [
        'run_status' => 'Success',
        'job_name' => 'Microsoft 365 Backup',
        'backup_username' => 'AcmeCorp',
        'client_first_name' => 'Brian',
        'workload_report_html' => '<table></table>',
    ],
);
assert_true($rendered['subject'] === '[Success] Microsoft 365 Backup for AcmeCorp', 'template subject merge');
assert_contains($rendered['message'], 'Hi Brian', 'template body merge');

$normalized = Ms365BackupReportEmailService::normalizeRecipients([
    'Support@EazyBackup.ca',
    'support@eazybackup.ca',
    'brian@eazybackup.ca',
    'not-an-email',
]);
assert_true($normalized === ['support@eazybackup.ca', 'brian@eazybackup.ca'], 'recipient list deduped and validated');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
