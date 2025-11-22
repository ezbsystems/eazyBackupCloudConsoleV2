<?php

require_once __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/../../../../../modules/servers/comet/functions.php';  

use WHMCS\Database\Capsule;

header('Content-Type: text/html');

if (!isset($_SESSION['uid'])) {
    echo '<div class="alert alert-danger">You must be logged in to view this content.</div>';
    exit;
}

if (!isset($_GET['jobid']) || !isset($_GET['username'])) {
    echo '<div class="alert alert-danger">No job ID or username specified.</div>';
    exit;
}

$clientId = $_SESSION['uid'];
$jobId = $_GET['jobid'];
$username = $_GET['username'];

$jobDetail = getJobDetail($clientId, $jobId);
$jobReport = getJobReport($clientId, $jobId);

if (!$jobDetail || !$jobReport) {
    echo '<div class="alert alert-danger">Could not retrieve job detail or job report.</div>';
    exit;
}

// Parse the job report
$reportEntries = explode("\n", trim($jobReport));

function formatReportEntry($entry) {
    list($timestamp, $status, $message) = explode('|', $entry, 3);
    $time = date('Y-m-d H:i:s', $timestamp);
    return [$time, $status, $message];
}

function getStatusText($status) {
    switch ($status) {
        case 'I':
            return 'Info';
        case 'W':
            return 'Warning';
        case 'E':
            return 'Error';
        default:
            return $status;
    }
}

$parsedEntries = array_map('formatReportEntry', $reportEntries);

?>

<div class="job-report">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="#" onclick="loadJobLogs('<?php echo $username; ?>')">Job Logs</a></li>
            <li class="breadcrumb-item active" aria-current="page">Job Report</li>
        </ol>
    </nav>
    <div class="row mb-3">
        <div class="col-md-6">
            <h4>Job Details</h4>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($jobDetail->Username); ?></p>
            <p><strong>Type:</strong> <?php echo htmlspecialchars($jobDetail->FriendlyJobType); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($jobDetail->FriendlyStatus); ?></p>
            <p><strong>Started:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i:s', $jobDetail->StartTime)); ?></p>
            <p><strong>Stopped:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i:s', $jobDetail->EndTime)); ?></p>
            <p><strong>Duration:</strong> <?php echo htmlspecialchars(($jobDetail->EndTime - $jobDetail->StartTime) . ' seconds'); ?></p>
            <p><strong>Protected Item:</strong> <?php echo htmlspecialchars($jobDetail->SourceGUID); ?></p>
            <p><strong>Storage Vault:</strong> <?php echo htmlspecialchars($jobDetail->DestinationGUID); ?></p>
        </div>
        <div class="col-md-6">
            <h4>Additional Details</h4>
            <p><strong>Account Name:</strong> <?php echo htmlspecialchars($jobDetail->AccountName); ?></p>
            <p><strong>Device:</strong> <?php echo htmlspecialchars($jobDetail->DeviceID); ?></p>
            <p><strong>Total Size:</strong> <?php echo htmlspecialchars(comet_HumanFileSize($jobDetail->TotalSize)); ?></p>
            <p><strong>Files:</strong> <?php echo htmlspecialchars($jobDetail->TotalFiles); ?></p>
            <p><strong>Directories:</strong> <?php echo htmlspecialchars($jobDetail->TotalDirectories); ?></p>
            <p><strong>Uploaded:</strong> <?php echo htmlspecialchars(comet_HumanFileSize($jobDetail->UploadSize)); ?></p>
            <p><strong>Downloaded:</strong> <?php echo htmlspecialchars(comet_HumanFileSize($jobDetail->DownloadSize)); ?></p>
            <p><strong>Version:</strong> <?php echo htmlspecialchars($jobDetail->ClientVersion); ?></p>
        </div>
    </div>
    <h4>Job Report</h4>
    <table id="job-report-table" class="table table-striped">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Status</th>
                <th>Message</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($parsedEntries as $entry): ?>
                <?php
                list($time, $status, $message) = $entry;
                $rowClass = '';
                if ($status == 'W') {
                    $rowClass = 'table-warning';
                } elseif ($status == 'E') {
                    $rowClass = 'table-danger';
                }
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td><?php echo htmlspecialchars($time); ?></td>
                    <td><?php echo htmlspecialchars(getStatusText($status)); ?></td>
                    <td><?php echo htmlspecialchars($message); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button class="btn btn-primary" onclick="loadJobLogs('<?php echo $username; ?>')">Back to Job Logs</button>
</div>

<script>
function loadJobLogs(username) {
    $.ajax({
        url: 'includes/GetAccountJobs.php',
        type: 'GET',
        data: { username: username },
        success: function(data) {
            $('#job-logs-content').html(data);
            initializeJobLogTable();
        },
        error: function(xhr, status, error) {
            $('#job-logs-content').html('<div class="alert alert-danger">Failed to load job logs. Please try again later.</div>');
        }
    });
}

function initializeJobLogTable() {
    if ($.fn.DataTable.isDataTable('#job-report-table')) {
        $('#job-report-table').DataTable().destroy();
    }

    $('#job-report-table').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'colvis',
                text: 'Column Visibility',
                columns: ':gt(0)', // Allow column visibility control for all columns except the first one
                className: 'btn btn-secondary', // Custom class for styling
                postfixButtons: ['colvisRestore'] // Optionally, add a restore button
            }
        ]
    });
}

// Initialize the job report table when the document is ready
$(document).ready(function() {
    initializeJobLogTable();
});
</script>
