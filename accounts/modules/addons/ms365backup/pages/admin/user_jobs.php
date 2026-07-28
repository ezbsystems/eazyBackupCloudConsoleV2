<?php
declare(strict_types=1);

use Ms365Backup\Ms365AdminUsersRepository;

$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$backupUserId = (int) ($_GET['backup_user_id'] ?? 0);
$jobId = trim((string) ($_GET['job_id'] ?? ''));

$summary = Ms365AdminUsersRepository::getUserSummary($backupUserId);
$jobName = '';
if ($summary !== null && $jobId !== '') {
    foreach ($summary['jobs'] ?? [] as $job) {
        if (strcasecmp((string) ($job['job_id'] ?? ''), $jobId) === 0) {
            $jobName = (string) ($job['name'] ?? '');
            break;
        }
    }
}
if ($jobName === '' && $jobId !== '') {
    $jobName = $jobId;
}
?>
<div class="panel panel-default" style="margin-bottom:15px">
    <div class="panel-heading"><strong>User job history</strong></div>
    <div class="panel-body">
        <?php if ($summary === null): ?>
            <div class="alert alert-warning">Backup user #<?= $backupUserId ?> not found.</div>
        <?php else: ?>
            <p>
                <strong>Client:</strong> <?= $e($summary['client_name'] ?? '') ?>
                &nbsp; <strong>Username:</strong> <code><?= $e($summary['username'] ?? '') ?></code>
                &nbsp; <strong>Job:</strong> <?= $e($jobName) ?>
            </p>
            <p class="text-muted" style="margin-bottom:0">
                Showing batch runs for backup user #<?= (int) $backupUserId ?>
                <?php if ($jobId !== ''): ?> and job <code><?= $e($jobId) ?></code><?php endif; ?>.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($summary !== null): ?>
<script>
window.MS365_JOBS_FORCED_FILTERS = <?= json_encode([
    'backup_user_id' => $backupUserId > 0 ? (string) $backupUserId : '',
    'job_id' => $jobId,
]) ?>;
</script>
    <?php require __DIR__ . '/jobs.php'; ?>
<?php endif; ?>
