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

$bucketController = new BucketController(null, null, null, null, $vars['s3_region'] ?? 'ca-central-1');

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

$displayedPeriod = [
    'start' => $displayPeriod['start'],
    'end' => $displayPeriod['end']
];

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
$rangePreset = $_GET['range'] ?? '';

$startDate = null;
$endDate = null;

if ($action === 'current_period') {
    // Use display period for header, but end at today for queries
    $startDate = $displayPeriod['start'];
    $endDate = $displayPeriod['end_for_queries'];
    $displayedPeriod = [
        'start' => $displayPeriod['start'],
        'end' => $displayPeriod['end']
    ];
} elseif ($action === 'prev_period' && $refStartDateForNav) {
    try {
        new \DateTime($refStartDateForNav); // Validate date format
        $prevData = $billingObject->getPreviousBillingPeriod($loggedInUserId, $packageId, $refStartDateForNav);
        $startDate = $prevData['start'];
        $endDate = $prevData['end'];
        if (!is_null($startDate) && !is_null($endDate)) {
            $displayedPeriod = [
                'start' => $startDate,
                'end' => $endDate
            ];
        }
    } catch (\Exception $e) {
        debugLog('Date Navigation Error', ['action' => 'prev_period', 'ref_start' => $refStartDateForNav, 'error' => $e->getMessage()], 'Error processing prev_period, falling back.');
    }
} elseif ($action === 'next_period' && $refStartDateForNav) {
    try {
        new \DateTime($refStartDateForNav); // Validate date format
        $nextData = $billingObject->getNextBillingPeriod($loggedInUserId, $packageId, $refStartDateForNav);

        if (!empty($nextData['start']) && !empty($nextData['end'])) {
            $nextStartDt = new \DateTime($nextData['start']);
            $currentStartDt = new \DateTime($displayPeriod['start']);

            // Prevent navigating beyond the actual current billing period
            if ($nextStartDt > $currentStartDt) {
                $startDate = $displayPeriod['start'];
                $endDate = $displayPeriod['end_for_queries'];
                $displayedPeriod = [
                    'start' => $displayPeriod['start'],
                    'end' => $displayPeriod['end']
                ];
            } else {
                $startDate = $nextData['start'];
                $endDate = $nextData['end'];
                $displayedPeriod = [
                    'start' => $startDate,
                    'end' => $endDate
                ];
            }
        }
    } catch (\Exception $e) {
        debugLog('Date Navigation Error', ['action' => 'next_period', 'ref_start' => $refStartDateForNav, 'error' => $e->getMessage()], 'Error processing next_period, falling back.');
    }
} elseif (!is_null($requestedStartDate) && !is_null($requestedEndDate)) {
    try {
        new \DateTime($requestedStartDate); // Validate
        new \DateTime($requestedEndDate);   // Validate
        $startDate = $requestedStartDate;
        $endDate = $requestedEndDate;
        if ($startDate === $displayPeriod['start'] && $endDate === $displayPeriod['end_for_queries']) {
            $displayedPeriod = [
                'start' => $displayPeriod['start'],
                'end' => $displayPeriod['end']
            ];
        } else {
            $displayedPeriod = [
                'start' => $startDate,
                'end' => $endDate
            ];
        }
    } catch (\Exception $e) {
        debugLog('Invalid Custom Date Range', ['start' => $requestedStartDate, 'end' => $requestedEndDate, 'error' => $e->getMessage()], 'Invalid custom dates provided, falling back.');
    }
}

// Fallback if $startDate or $endDate are still null (e.g., error in nav logic, or no action/custom dates)
if (is_null($startDate) || is_null($endDate)) {
    $startDate = $displayPeriod['start'];
    $endDate = $displayPeriod['end_for_queries'];
    debugLog('Date Fallback Applied', ['reason' => 'Primary logic did not set dates', 'applied_start' => $startDate, 'applied_end' => $endDate], 'Fell back to user actual current billing period.');
    $displayedPeriod = [
        'start' => $displayPeriod['start'],
        'end' => $displayPeriod['end']
    ];
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
    $displayedPeriod = [
        'start' => $startDate,
        'end' => $endDate
    ];
}

if ($rangePreset === '') {
    if (!is_null($requestedStartDate) && !is_null($requestedEndDate)) {
        $rangePreset = 'custom';
    } else {
        $rangePreset = 'billing_period';
    }
}

// $startDate and $endDate are now set for data fetching.
// $displayPeriod is used for display of 'Current Service Period: X to Y' in template.

// Debug log the final selected date range
debugLog('Final Date Selection for Data', [
    'startDate' => $startDate,
    'endDate' => $endDate,
    'userIds' => $userIds,
    'displayPeriod' => $displayPeriod,
    'displayedPeriod' => $displayedPeriod
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

$bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $vars['s3_region'] ?? 'ca-central-1');

// Get historical usage data using the determined $startDate and $endDate
$historicalData = $bucketController->getHistoricalUsage($userIds, $startDate, $endDate);

debugLog('Raw Historical Data from getHistoricalUsage', [
    'historicalData' => $historicalData,
    'userIds' => $userIds,
    'startDate' => $startDate,
    'endDate' => $endDate
], 'Data fetched from s3_historical_stats');

// Populate variables for the template from $historicalData

// Peak Usage (billed instantaneous) from s3_prices
$peakFromPrices = $bucketController->findPeakBillableUsageFromPrices((int)$user->id, [
    'start' => $startDate,
    'end' => $endDate
]);
$peakUsageForTemplate = [
    'date' => ($peakFromPrices && $peakFromPrices->exact_timestamp) ? date('Y-m-d', strtotime($peakFromPrices->exact_timestamp)) : 'N/A',
    'size' => HelperController::formatSizeUnits($peakFromPrices->total_size ?? 0)
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

// Get bucket stats for the period (legacy, kept for compatibility if referenced elsewhere)
$bucketStats = $bucketObject->getUserBucketSummary($userIds, $startDate, $endDate); // Daily usage chart uses dailyUsageData below

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

// Aggregation for Daily Usage (Storage) from billed instantaneous snapshots (s3_prices)
$aggregatedDailyUsage = [];
$pricesDaily = $bucketController->getDailyBillableUsageFromPrices((int)$user->id, $startDate, $endDate);
if (is_array($pricesDaily)) {
    foreach ($pricesDaily as $row) {
        if (is_array($row) && isset($row['period'])) {
            $aggregatedDailyUsage[$row['period']] = (float)($row['total_usage'] ?? 0);
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

$hasStorageUsage = false;
foreach ($dailyUsageData as $value) {
    if ((float)$value > 0) {
        $hasStorageUsage = true;
        break;
    }
}

$hasTransferUsage = false;
foreach ($aggregatedTransferData as $data) {
    if (
        (float)($data['sent'] ?? 0) > 0
        || (float)($data['received'] ?? 0) > 0
        || (int)($data['ops'] ?? 0) > 0
    ) {
        $hasTransferUsage = true;
        break;
    }
}

$hasUsageData = $hasStorageUsage || $hasTransferUsage;

$exportRequested = isset($_GET['export']) && $_GET['export'] === 'csv';
if ($exportRequested) {
    $filename = 'usage_history_' . $startDate . '_to_' . $endDate . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Date', 'Storage', 'Ingress', 'Egress']);

    $transferMap = [];
    foreach ($aggregatedTransferData as $date => $data) {
        $transferMap[$date] = $data;
    }

    foreach ($dailyUsageDates as $index => $date) {
        $storageBytes = $dailyUsageData[$index] ?? 0;
        $ingressBytes = $transferMap[$date]['received'] ?? 0;
        $egressBytes = $transferMap[$date]['sent'] ?? 0;

        fputcsv($output, [
            $date,
            HelperController::formatSizeUnits($storageBytes),
            HelperController::formatSizeUnits($ingressBytes),
            HelperController::formatSizeUnits($egressBytes)
        ]);
    }

    fclose($output);
    exit;
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
$isCurrentDisplayPeriod = (
    $displayedPeriod['start'] === $displayPeriod['start']
    && $displayedPeriod['end'] === $displayPeriod['end']
);
$currentPeriodActive = $isCurrentDisplayPeriod;
$canNavigateForward = !$isCurrentDisplayPeriod;

// Return variables for the template
return [
    'billingPeriod' => $displayPeriod, // Rolling display period for header label
    'displayedPeriod' => $displayedPeriod,
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
    'canNavigateForward' => $canNavigateForward,
    'rangePreset' => $rangePreset,
    'hasUsageData' => $hasUsageData
]; 