<?php

use WHMCS\Database\Capsule;
use Comet\CometItem;

require_once __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . "/../../../../../modules/servers/comet/summary_functions.php";
require_once __DIR__ . "/../../../../../modules/servers/comet/comet.php";
require_once __DIR__ . "/../../../../../modules/servers/comet/functions.php";
require_once __DIR__ . "/../../../../../modules/servers/comet/CometItem.php";

// Define the function that processes the dashboard logic
function eazybackup_dashboard(array $vars = []) {
    // Ensure the user is authenticated—if not, redirect to login.
    if (!isset($_SESSION['uid'])) {
        header("Location: login.php");
        exit;
    }

    $clientId = $_SESSION['uid'];

    // Retrieve summary data for the backup dashboard
    $summaryData = getBackupSummary($clientId);

    // Retrieve detailed job logs
    // $jobDetails = getJobDetails($clientId);

    // Retrieve protected items with error handling
    // try {
    //     $protectedItems = CometItem::getProtectedItems($clientId);
    // } catch (Exception $e) {
    //     error_log("Error fetching protected items: " . $e->getMessage());
    //     $protectedItems = []; // Fallback to empty array if there's an error
    // }

    // Define the page title for display in the template
    $pageTitle = 'eazyBackup Management Console Dashboard';

    // Return the data as an associative array for the calling function
    // return [
    //     'summaryData'    => $summaryData,
    //     'jobDetails'     => $jobDetails,
    //     'protectedItems' => $protectedItems,
    //     'pageTitle'      => $pageTitle,
    // ];

    return [
        'summaryData'    => $summaryData,
        'jobDetails'     => [],
        'protectedItems' => [],
        'pageTitle'      => $pageTitle,
    ];
}
