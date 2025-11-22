<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\BillingController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Custom debug logging function
 */
function debugLog($context, $data, $message = '')
{
    // Log to WHMCS module log
    logModuleCall(
        'cloudstorage',
        $context,
        $data,
        $message
    );

    // Log to file
    $logDir = dirname(dirname(__FILE__)) . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/history_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$context}: {$message}\n";
    $logMessage .= "Data: " . print_r($data, true) . "\n";
    $logMessage .= "----------------------------------------\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

$bucketController = new BucketController(null, null, null, null, $vars['s3_region'] ?? 'us-east-1');

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);

if (is_null($product) || is_null($product->username)) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}

$username = $product->username;
$user = DBController::getUser($username);
$client = DBController::getRow('tblclients', [
    ['id', '=', $loggedInUserId]
]);

$tenants = DBController::getResult('s3_users', [
    ['parent_id', '=', $user->id]
], [
    'id', 'username'
])->pluck('id', 'username')->toArray();

$userIds = array_merge(array_values($tenants), [$user->id]);

$billingObject = new BillingController();
$displayPeriod = $billingObject->calculateDisplayPeriod($loggedInUserId, $packageId);
$overdueNotice = $billingObject->getOverdueNotice($loggedInUserId, $packageId);

debugLog('Display Period', [
    'period' => $displayPeriod,
    'loggedInUserId' => $loggedInUserId,
    'packageId' => $packageId
], 'Rolling display period calculated for UI.');

// Determine startDate and endDate for data fetching
$action = $_GET['action'] ?? null;
$requestedStartDate = $_GET['start_date'] ?? null;
$requestedEndDate = $_GET['end_date'] ?? null;

$refStartDateForNav = $_GET['ref_start'] ?? null;
$refEndDateForNav = $_GET['ref_end'] ?? null;

$startDate = null;
$endDate = null;

if ($action === 'current_period') {
    // Use display period for header, but end at today for queries
    $startDate = $displayPeriod['start'];
    $endDate = $displayPeriod['end_for_queries'];
} elseif ($action === 'prev_period' && $refStartDateForNav) {
    try {
        new \DateTime($refStartDateForNav); // Validate date format
        $prevData = $billingObject->getPreviousBillingPeriod($loggedInUserId, $packageId, $refStartDateForNav);
        $startDate = $prevData['start'];
        $endDate = $prevData['end'];
    } catch (\Exception $e) {
        debugLog('Date Navigation Error', ['action' => 'prev_period', 'ref_start' => $refStartDateForNav, 'error' => $e->getMessage()], 'Error processing prev_period, falling back.');
    }
} elseif (!is_null($requestedStartDate) && !is_null($requestedEndDate)) {
    try {
        new \DateTime($requestedStartDate); // Validate
        new \DateTime($requestedEndDate);   // Validate
        $startDate = $requestedStartDate;
        $endDate = $requestedEndDate;
    } catch (\Exception $e) {
        debugLog('Invalid Custom Date Range', ['start' => $requestedStartDate, 'end' => $requestedEndDate, 'error' => $e->getMessage()], 'Invalid custom dates provided, falling back.');
    }
}

// Fallback if $startDate or $endDate are still null (e.g., error in nav logic, or no action/custom dates)
if (is_null($startDate) || is_null($endDate)) {
    $startDate = $displayPeriod['start'];
    $endDate = $displayPeriod['end_for_queries'];
    debugLog('Date Fallback Applied', ['reason' => 'Primary logic did not set dates', 'applied_start' => $startDate, 'applied_end' => $endDate], 'Fell back to user actual current billing period.');
}

// Critical fallback: if $userActualCurrentBillingPeriod was also null (e.g. no active product)
if (is_null($startDate) || is_null($endDate)) {
    // This case should ideally be caught by the $product check earlier, which exits.
    // If somehow reached, default to a safe range to prevent query errors.
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-29 days')); // Last 30 days
    debugLog('Critical Date Fallback', [
        'loggedInUserId' => $loggedInUserId, 'packageId' => $packageId
    ], "All date determination failed (even userActualCurrentBillingPeriod was null). Defaulting to {$startDate} - {$endDate}.");
}

// $startDate and $endDate are now set for data fetching.
// $displayPeriod is used for display of 'Current Service Period: X to Y' in template.

// Debug log the final selected date range
debugLog('Final Date Selection for Data', [
    'startDate' => $startDate,
    'endDate' => $endDate,
    'userIds' => $userIds,
    'displayPeriod' => $displayPeriod
], 'Data range selected for fetching');

// Get the selected username from URL parameters
$selectedUsername = isset($_GET['username']) ? $_GET['username'] : '';

// If a specific username is selected, filter the user IDs
if (!empty($selectedUsername)) {
    $selectedUser = DBController::getUser($selectedUsername);
    if ($selectedUser) {
        $userIds = [$selectedUser->id];
    } else {
        debugLog('User Selection Warning', ['selectedUsername' => $selectedUsername], 'Selected username not found in s3_users, using parent/all tenants.');
    }
}

$bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $vars['s3_region'] ?? 'us-east-1');

// Get historical usage data using the determined $startDate and $endDate
$historicalData = $bucketController->getHistoricalUsage($userIds, $startDate, $endDate);

debugLog('Raw Historical Data from getHistoricalUsage', [
    'historicalData' => $historicalData,
    'userIds' => $userIds,
    'startDate' => $startDate,
    'endDate' => $endDate
], 'Data fetched from s3_historical_stats');

// Populate variables for the template from $historicalData

// Peak Usage
$peakUsageDate = 'N/A';
$peakUsageSizeFormatted = '0 Bytes';
if (isset($historicalData['peak_usage']) && $historicalData['peak_usage']) {
    $peakUsageDate = $historicalData['peak_usage']->date ?? 'N/A';
    $peakUsageSizeFormatted = HelperController::formatSizeUnits($historicalData['peak_usage']->size ?? 0);
}
$peakUsageForTemplate = [
    'date' => $peakUsageDate,
    'size' => $peakUsageSizeFormatted
];

// Ingress, Egress, Operations from summary
$totalIngressFormatted = '0 Bytes';
$totalEgressFormatted = '0 Bytes';
$totalOpsFormatted = '0';

if (isset($historicalData['summary'])) {
    $totalIngressFormatted = HelperController::formatSizeUnits($historicalData['summary']->total_bytes_received ?? 0);
    $totalEgressFormatted = HelperController::formatSizeUnits($historicalData['summary']->total_bytes_sent ?? 0);
    $totalOpsFormatted = number_format($historicalData['summary']->total_operations ?? 0);
}

// Get bucket stats for the period (for charts/other displays, not summary cards)
$bucketStats = $bucketObject->getUserBucketSummary($userIds, $startDate, $endDate); // Used for daily usage chart if #sizeChart is present

// Get all usernames for the dropdown
$usernames = array_keys($tenants);
array_unshift($usernames, $username);

// Debug log the date range
debugLog('Template Data Population', [
    'startDate' => $startDate,
    'endDate' => $endDate,
    'userIds' => $userIds,
    'displayPeriod' => $displayPeriod,
    'peakUsageForTemplate' => $peakUsageForTemplate,
    'totalIngressFormatted' => $totalIngressFormatted,
    'totalEgressFormatted' => $totalEgressFormatted,
    'totalOpsFormatted' => $totalOpsFormatted
], 'Data prepared for history.tpl');

// Prepare data for charts (daily_usage and transfer_data from historicalData)
$dailyUsageData = [];
$dailyUsageDates = [];
$transferSentData = [];
$transferReceivedData = [];
$transferOpsData = []; 
$transferDates = [];

// Aggregation for Daily Usage (Storage) with last-value carry-forward to avoid drops on missing days
$aggregatedDailyUsage = [];
if (isset($historicalData['daily_usage']) && is_array($historicalData['daily_usage'])) {
    foreach ($historicalData['daily_usage'] as $usage) {
        if (is_object($usage) && isset($usage->date)) { 
            $date = $usage->date;
            if (!isset($aggregatedDailyUsage[$date])) {
                $aggregatedDailyUsage[$date] = 0;
            }
            $aggregatedDailyUsage[$date] += (float)($usage->total_storage ?? 0);
        }
    }
}

// Fill forward across the requested date range
try {
    $rangeStartDt = new \DateTime($startDate);
    $rangeEndDt = new \DateTime($endDate);
    $period = new \DatePeriod($rangeStartDt, new \DateInterval('P1D'), (clone $rangeEndDt)->modify('+1 day'));
    $lastValue = 0;
    foreach ($period as $dt) {
        $d = $dt->format('Y-m-d');
        if (isset($aggregatedDailyUsage[$d])) {
            $lastValue = (float)$aggregatedDailyUsage[$d];
        }
        $dailyUsageDates[] = $d;
        $dailyUsageData[] = $lastValue;
    }
} catch (\Exception $e) {
    // Fallback to simple series if date parsing fails
    if (!empty($aggregatedDailyUsage)) {
        ksort($aggregatedDailyUsage);
        $dailyUsageDates = array_keys($aggregatedDailyUsage);
        $dailyUsageData = array_values($aggregatedDailyUsage);
    }
}

// Aggregation for Transfer Data (Ingress, Egress, Ops)
$aggregatedTransferData = [];
if (isset($historicalData['transfer_data']) && is_array($historicalData['transfer_data'])) {
    foreach ($historicalData['transfer_data'] as $transfer) {
        if (is_object($transfer) && isset($transfer->date)) { 
            $date = $transfer->date;
            if (!isset($aggregatedTransferData[$date])) {
                $aggregatedTransferData[$date] = [
                    'sent' => 0,
                    'received' => 0,
                    'ops' => 0
                ];
            }
            $aggregatedTransferData[$date]['sent'] += (float)($transfer->bytes_sent ?? 0);
            $aggregatedTransferData[$date]['received'] += (float)($transfer->bytes_received ?? 0);
            $aggregatedTransferData[$date]['ops'] += (int)($transfer->operations ?? 0);
        }
    }
}

if (!empty($aggregatedTransferData)) {
    ksort($aggregatedTransferData); // Sort by date key
    foreach ($aggregatedTransferData as $date => $data) {
        $transferDates[] = $date;
        $transferSentData[] = $data['sent'];
        $transferReceivedData[] = $data['received'];
        $transferOpsData[] = $data['ops'];
    }
}

debugLog('Aggregated Chart Data for Template', [
    'dailyUsageDates' => $dailyUsageDates,
    'dailyUsageData' => $dailyUsageData,
    'transferDates' => $transferDates,
    'transferSentData' => $transferSentData,
    'transferReceivedData' => $transferReceivedData,
    'transferOpsData' => $transferOpsData
], 'Chart data after aggregation logic.');

// Determine active button state
$currentPeriodActive = false;
$prevPeriodActive = false;

if ($action === 'prev_period') {
    $prevPeriodActive = true;
} else {
    // Covers $action === 'current_period' or $action being null (default)
    $currentPeriodActive = true;
}

// Return variables for the template
return [
    'billingPeriod' => $displayPeriod, // Rolling display period for header label
    'overdueNotice' => $overdueNotice,
    'bucketStats' => $bucketStats, // For daily storage chart #sizeChart, from getUserBucketSummary
    
    'totalIngress' => $totalIngressFormatted,
    'totalEgress' => $totalEgressFormatted,
    'totalOps' => $totalOpsFormatted,
    
    'usernames' => $usernames,
    'startDate' => $startDate, // Currently active start date for data range
    'endDate' => $endDate,   // Currently active end date for data range
    
    'peakUsage' => $peakUsageForTemplate, // Peak usage {date, size} from historical
    
    // Chart data from s3_historical_stats
    'dailyUsageData' => $dailyUsageData,       // array of total_storage values
    'dailyUsageDates' => $dailyUsageDates,     // array of dates for dailyUsageData
    'transferSentData' => $transferSentData,     // array of bytes_sent values
    'transferReceivedData' => $transferReceivedData, // array of bytes_received values
    'transferOpsData' => $transferOpsData,         // array of operations values
    'transferDates' => $transferDates,          // array of dates for transfer data
    'currentPeriodActive' => $currentPeriodActive,
    'prevPeriodActive' => $prevPeriodActive
]; 