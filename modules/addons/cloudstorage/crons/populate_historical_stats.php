<?php


require __DIR__ . '/../../../../init.php';

use WHMCS\Database\Capsule;

// Get all users
$users = Capsule::table('s3_users')->get();

echo "Starting historical stats population for " . count($users) . " users...\n";

$totalRecordsCreated = 0;

foreach ($users as $user) {
    // Get the earliest usage_day from s3_bucket_stats_summary
    $earliestDate = Capsule::table('s3_bucket_stats_summary')
        ->where('user_id', $user->id)
        ->min('usage_day');

    if (!$earliestDate) {
        echo "No data found for user {$user->id} ({$user->username}), skipping...\n";
        continue; // Skip if no data exists
    }

    echo "Processing user {$user->id} ({$user->username}) from {$earliestDate}...\n";

    // Convert to DateTime
    $startDate = new DateTime($earliestDate);
    $endDate = new DateTime();
    $interval = new DateInterval('P1D'); // 1 day interval
    $dateRange = new DatePeriod($startDate, $interval, $endDate);

    $userRecordsCreated = 0;

    foreach ($dateRange as $date) {
        $dateStr = $date->format('Y-m-d');
        
        // Get total storage for this date
        $storageStats = Capsule::table('s3_bucket_stats_summary')
            ->where('user_id', $user->id)
            ->where('usage_day', $dateStr)
            ->sum('total_usage');

        // Get transfer stats for this date
        $transferStats = Capsule::table('s3_transfer_stats_summary')
            ->where('user_id', $user->id)
            ->whereDate('created_at', $dateStr)
            ->select(
                Capsule::raw('SUM(bytes_sent) as total_bytes_sent'),
                Capsule::raw('SUM(bytes_received) as total_bytes_received'),
                Capsule::raw('SUM(ops) as total_ops')
            )
            ->first();

        // Check if record already exists
        $existingRecord = Capsule::table('s3_historical_stats')
            ->where('user_id', $user->id)
            ->where('date', $dateStr)
            ->first();

        if (!$existingRecord) {
            // Insert new record
            Capsule::table('s3_historical_stats')->insert([
                'user_id' => $user->id,
                'date' => $dateStr,
                'total_storage' => $storageStats ?? 0,
                'bytes_sent' => $transferStats->total_bytes_sent ?? 0,
                'bytes_received' => $transferStats->total_bytes_received ?? 0,
                'operations' => $transferStats->total_ops ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $userRecordsCreated++;
            $totalRecordsCreated++;
        }
    }

    echo "Created {$userRecordsCreated} historical records for user {$user->id}\n";
}

echo "Historical stats population completed. Total records created: {$totalRecordsCreated}\n"; 

// Log the completion to WHMCS module log
logModuleCall(
    'cloudstorage',
    'populate_historical_stats',
    ['total_users' => count($users), 'records_created' => $totalRecordsCreated],
    "Historical stats population completed. Created {$totalRecordsCreated} records for " . count($users) . " users."
); 