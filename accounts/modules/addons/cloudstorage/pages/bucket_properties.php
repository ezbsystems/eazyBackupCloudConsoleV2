<!DOCTYPE html>
<html lang="en">

<?php

use Aws\S3\Exception\S3Exception;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

$packageId = 48;
$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product)) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}


$pageTitle = 'Bucket Properties | eazyBackup e3 Dashboard';
include 'head.php';
require_once __DIR__ . '/../app/functions/s3.php';
require_once __DIR__ . '/../app/functions/bucket_functions.php';
require_once __DIR__ . '/../app/functions/db_connection.php';
require_once __DIR__ . '/../app/functions/session_check.php';

$username = $_SESSION['username'];

$userFirstName = $_SESSION['userFirstName'] ?? 'User';

$bucket = isset($_GET['bucket']) ? $_GET['bucket'] : '';
$bucket = htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8');

$conn = createDbConnection();

$bucketSettings = getBucketSettings($s3Client, $bucket);
$usageStats = getUserUsageStats($conn);
$currentBucketStats = getCurrentBucketStats($usageStats, $bucket);

$bucketName = $bucket; // Assuming $bucket contains the current bucket name from the URL
$bucketUsageStats = getUserBucketUsageStats($conn, $bucketName);
// var_dump($bucketSettings)

?>

<body>


<script>
    function copyToClipboard(elementId) {
        // Get the text from the element
        var text = document.getElementById(elementId).value; // Use .value for input fields
        // Create a temporary textarea element to hold the text to copy
        var tempElem = document.createElement('textarea');
        tempElem.value = text;
        document.body.appendChild(tempElem);
        tempElem.select(); // Select the text
        document.execCommand("copy"); // Copy the text
        document.body.removeChild(tempElem); // Remove the temporary element
    }

    function changeIcon(iconId) {
        // Change the icon to indicate copying is done, for example to a check icon
        var icon = document.getElementById(iconId);
        if (icon) {
            icon.className = "bi bi-check-circle"; // Change this class to whatever your 'copied' icon is
        }
    }
    </script>


    <?php include 'navbar.php'; ?>
    <div class="page-body">
        <div class="heading-row">
            <div class="navigation-horizontal">
                <div class="navigation-horizontal-title">
                    <span class="nav-title">
                            <i class="bi bi-columns"></i>
                            <span class="h2">Dashboard</span>
                    </span>
                </div>
                <div class="navigation-horizontal-text navigation-horizontal-btns">
                    <a style="text-decoration: none;" href="#">
                        <button class="nav-btn-sm loader-btn me-2" type="button" onclick="showLoaderAndRefresh()">
                            <i class="bi bi-arrow-clockwise reload"></i>
                        </button>
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                            <span><?php echo htmlspecialchars($userFirstName); ?></span>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item" href="logout.php">Log out</a>
                            <a class="dropdown-item" href="https://accounts.eazybackup.ca/">Client area</a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <div class="container">
            <!-- Bucket Settings Card -->
            <div class="card mb-4">
                <div class="card-header">
                    Bucket Settings
                </div>
                <ul class="list-group list-group-flush">
                    <!-- Dynamic PHP Content: Bucket Name -->
                    <li class="list-group-item">
                        <strong>Bucket Name:</strong> <?= htmlspecialchars($bucket) ?>
                    </li>
                    <!-- Service Endpoint -->
                    <li class="list-group-item">
                        <span><strong>Service Endpoint:</strong></span>
                        <div class="action-container">
                            <div class="input-container">
                                <input class="form-control" id="endpoint" type="text"
                                    value="s3.ca-central-1.eazybackup.com" readonly>
                            </div>
                            <i id="access-icon" class="bi bi-clipboard"
                                onclick="copyToClipboard('endpoint'); changeIcon('access-icon')"
                                style="font-size: 24px;"></i>
                            <span id="endpoint-copy-message"></span>
                        </div>
                    </li>
                    <li class="list-group-item">
                    <table class="table">
                        <tbody>
                        <!-- Bucket Properties Dynamic List -->
                        <?php foreach ($bucketSettings as $property => $value): ?>
                        <tr>
                            <td><?= htmlspecialchars($property, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($property === 'Policy'): ?>
                                    <!-- Make Policy clickable -->
                                    <a href="/api/togglePolicy.php?bucket=<?= urlencode($bucketName) ?>&policy=<?= urlencode($value) ?>" class="text-link">
                                        <?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php elseif (in_array($property, ['Logging', 'Encryption', 'Versioning'])): ?>
                                    <a href="#" class="text-link" data-bs-toggle="modal" data-bs-target="#<?= $property ?>Modal">
                                        <?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                            <!-- Other predefined rows can remain or be handled similarly -->
                        </tbody>
                    </table>
                    </li>
                </ul>
            </div>
            <!-- Bucket Usage Stats -->
            <div class="card mt-4">
                <div class="card-header">
                    Usage Stats
                </div>
                <?php if ($bucketUsageStats !== null): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th><th>Bytes Sent</th><th>Bytes Received</th><th>Ops</th><th>Successful Ops</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bucketUsageStats as $category): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category["category"], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($category["bytes_sent"], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($category["bytes_received"], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($category["ops"], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($category["successful_ops"], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="card-body">
                        No usage stats available for the current bucket at this time.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Logging Modal Trigger -->


        <!-- Logging Modal -->
        <div class="modal fade" id="loggingModal" tabindex="-1" aria-labelledby="loggingModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loggingModalLabel">Modify Logging</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="loggingForm" method="post" action="#">
                    <div class="mb-3">
                        <label for="targetBucket" class="form-label">Target Bucket for Logs</label>
                        <select class="form-select" id="targetBucket" name="targetBucket">
                        <?php
                        $buckets = getBucketsForUser($s3Client, $conn, $username);
                        foreach ($buckets as $bucket) {
                            echo "<option value=\"{$bucket['Name']}\">{$bucket['Name']}</option>";
                        }
                        ?>
                        </select>
                    </div>
                    <!-- Include any additional form fields here -->
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" form="loggingForm">Save changes</button>
                </div>
                </div>
            </div>
        </div>
</body>
</html>