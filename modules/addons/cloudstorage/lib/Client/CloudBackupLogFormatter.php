<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

class CloudBackupLogFormatter
{
    /**
     * Format raw rclone JSON logs into user-friendly readable format
     *
     * @param string|null $rawLogJson Raw JSON log excerpt from database
     * @return string Formatted, user-friendly log output
     */
    public static function formatRcloneLogs($rawLogJson)
    {
        if (empty($rawLogJson)) {
            return "No log data available for this backup run.";
        }

        // Try to parse as JSON array first (if stored as JSON array)
        $logLines = json_decode($rawLogJson, true);
        
        // Handle case where log is stored as JSON array of JSON strings (double-encoded)
        if (is_array($logLines) && !empty($logLines) && is_string($logLines[0])) {
            // It's an array of JSON strings, decode each one
            $decodedLines = [];
            foreach ($logLines as $line) {
                if (is_string($line)) {
                    $decoded = json_decode($line, true);
                    if ($decoded !== null) {
                        $decodedLines[] = $decoded;
                    }
                } elseif (is_array($line)) {
                    $decodedLines[] = $line;
                }
            }
            $logLines = $decodedLines;
        }
        
        // If not a JSON array, try parsing line by line
        if (!is_array($logLines)) {
            $lines = explode("\n", trim($rawLogJson));
            $logLines = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    $logLines[] = $decoded;
                } else {
                    // If line is not JSON, treat as plain text
                    $logLines[] = ['msg' => $line, 'level' => 'info'];
                }
            }
        }

        if (empty($logLines)) {
            return "Log data is empty or could not be parsed.";
        }

        $formatted = [];
        $lastStats = null;
        $startTime = null;
        $lastProgressUpdate = null;
        $errors = [];
        $warnings = [];
        $nothingToTransfer = false;

        foreach ($logLines as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $level = strtolower($entry['level'] ?? 'info');
            $msg = $entry['msg'] ?? '';
            $time = $entry['time'] ?? null;
            $stats = $entry['stats'] ?? null;

            // Extract timestamp
            if ($time && !$startTime) {
                $startTime = $time;
            }

            // Check for "nothing to transfer" message
            if (stripos($msg, 'nothing to transfer') !== false) {
                $nothingToTransfer = true;
            }

            // Handle stats updates - but also check for "nothing to transfer" in stats
            if ($stats && is_array($stats)) {
                $lastStats = $stats;
                $lastProgressUpdate = $time;
                
                // Check if stats indicate nothing to transfer
                $totalBytes = isset($stats['totalBytes']) ? (int)$stats['totalBytes'] : 0;
                $bytes = isset($stats['bytes']) ? (int)$stats['bytes'] : 0;
                $totalTransfers = isset($stats['totalTransfers']) ? (int)$stats['totalTransfers'] : 0;
                $transfers = isset($stats['transfers']) ? (int)$stats['transfers'] : 0;
                
                if ($totalBytes == 0 && $bytes == 0 && $totalTransfers == 0 && $transfers == 0) {
                    $nothingToTransfer = true;
                }
                
                continue; // We'll format stats separately
            }

            // Format message based on type
            $formattedMsg = self::formatLogMessage($msg, $level, $entry);
            
            if ($formattedMsg) {
                $timestamp = $time ? self::formatTimestamp($time) : '';
                $formatted[] = [
                    'time' => $timestamp,
                    'level' => $level,
                    'message' => $formattedMsg
                ];

                if ($level === 'error') {
                    $errors[] = $formattedMsg;
                } elseif ($level === 'warning') {
                    $warnings[] = $formattedMsg;
                }
            }
        }

        // Build final formatted output
        $output = [];

        // Add header
        if ($startTime) {
            $output[] = "═══════════════════════════════════════════════════════════";
            $output[] = "BACKUP RUN DETAILS";
            $output[] = "Started: " . self::formatTimestamp($startTime);
            $output[] = "═══════════════════════════════════════════════════════════";
            $output[] = "";
        }

        // Add initial status
        $output[] = "📋 Backup Process";
        $output[] = "";

        // If nothing to transfer, add special header
        if ($nothingToTransfer) {
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "✅ Backup Completed - No Changes Needed";
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "";
            $output[] = "The backup process completed successfully, but there were no files to transfer.";
            $output[] = "This means your source and destination are already synchronized - all files are up to date.";
            $output[] = "";
        }

        // Add formatted log entries
        $currentSection = null;
        foreach ($formatted as $entry) {
            $section = self::detectSection($entry['message']);
            if ($section && $section !== $currentSection) {
                if ($currentSection !== null) {
                    $output[] = "";
                }
                $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
                $output[] = $section;
                $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
                $output[] = "";
                $currentSection = $section;
            }

            $line = "";
            if ($entry['time']) {
                $line .= "[" . $entry['time'] . "] ";
            }
            $line .= $entry['message'];
            $output[] = $line;
        }
        
        // If no log entries but we detected nothing to transfer, add a note
        if (empty($formatted) && $nothingToTransfer) {
            $output[] = "No additional log entries - backup completed with no files to transfer.";
        }

        // Add final stats if available
        if ($lastStats) {
            $output[] = "";
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "📊 Backup Summary";
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "";
            $summary = self::formatStatsSummary($lastStats, $nothingToTransfer);
            $output[] = $summary;
            
            // Add special message if nothing to transfer
            if ($nothingToTransfer) {
                $output[] = "";
                $output[] = "ℹ️  No files were transferred because source and destination are already synchronized.";
            }
        }

        // Add errors section if any
        if (!empty($errors)) {
            $output[] = "";
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "❌ Errors Encountered";
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "";
            foreach ($errors as $error) {
                $output[] = "• " . $error;
            }
        }

        // Add warnings section if any
        if (!empty($warnings)) {
            $output[] = "";
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "⚠️  Warnings";
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "";
            foreach ($warnings as $warning) {
                $output[] = "• " . $warning;
            }
        }

        return implode("\n", $output);
    }

    /**
     * Format validation logs into user-friendly format
     *
     * @param string|null $rawLogJson Raw JSON validation log excerpt
     * @return string Formatted, user-friendly validation log output
     */
    public static function formatValidationLogs($rawLogJson)
    {
        if (empty($rawLogJson)) {
            return "No validation log data available.";
        }

        // Similar parsing to formatRcloneLogs but tailored for validation
        $logLines = json_decode($rawLogJson, true);
        
        if (!is_array($logLines)) {
            $lines = explode("\n", trim($rawLogJson));
            $logLines = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    $logLines[] = $decoded;
                } else {
                    $logLines[] = ['msg' => $line, 'level' => 'info'];
                }
            }
        }

        if (empty($logLines)) {
            return "Validation log data is empty or could not be parsed.";
        }

        $output = [];
        $output[] = "═══════════════════════════════════════════════════════════";
        $output[] = "VALIDATION CHECK DETAILS";
        $output[] = "═══════════════════════════════════════════════════════════";
        $output[] = "";

        $errors = [];
        $mismatches = [];

        foreach ($logLines as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $level = strtolower($entry['level'] ?? 'info');
            $msg = $entry['msg'] ?? '';
            $time = $entry['time'] ?? null;

            $formattedMsg = self::formatValidationMessage($msg, $level, $entry);
            
            if ($formattedMsg) {
                $timestamp = $time ? self::formatTimestamp($time) : '';
                $line = "";
                if ($timestamp) {
                    $line .= "[" . $timestamp . "] ";
                }
                $line .= $formattedMsg;
                $output[] = $line;

                if (stripos($msg, 'error') !== false || stripos($msg, 'mismatch') !== false) {
                    $errors[] = $formattedMsg;
                }
            }
        }

        if (!empty($errors)) {
            $output[] = "";
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "❌ Validation Issues Found";
            $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $output[] = "";
            foreach ($errors as $error) {
                $output[] = "• " . $error;
            }
        } else {
            $output[] = "";
            $output[] = "✅ Validation completed successfully - all files verified.";
        }

        return implode("\n", $output);
    }

    /**
     * Format a log message to be user-friendly
     *
     * @param string $msg Original message
     * @param string $level Log level
     * @param array $entry Full log entry
     * @return string Formatted message
     */
    private static function formatLogMessage($msg, $level, $entry)
    {
        // Replace vendor name
        $msg = preg_replace('/rclone/i', 'eazyBackup', $msg);

        // Convert common rclone messages to user-friendly format
        $replacements = [
            // Starting messages
            '/Starting sync/i' => '🔄 Starting backup process',
            '/Starting copy/i' => '🔄 Starting file copy',
            '/Starting move/i' => '🔄 Starting file move',
            
            // Progress messages
            '/Transferred:/i' => '📤 Transferred:',
            '/Checks:/i' => '✓ Checks:',
            '/Transferred:\s*(\d+)\s*\/\s*(\d+)/i' => 'Transferred: $1 of $2 files',
            
            // Completion messages
            '/Completed sync/i' => '✅ Backup completed successfully',
            '/Completed copy/i' => '✅ File copy completed',
            '/Completed move/i' => '✅ File move completed',
            
            // Nothing to transfer messages
            '/nothing to transfer/i' => '✅ No files to transfer - source and destination are synchronized',
            '/There was nothing to transfer/i' => '✅ No files to transfer - source and destination are synchronized',
            
            // Error messages
            '/error/i' => '❌ Error',
            '/failed/i' => '❌ Failed',
            '/permission denied/i' => '❌ Permission denied - Unable to access files',
            '/no such file/i' => '❌ File not found',
            '/connection refused/i' => '❌ Connection refused - Unable to connect to source',
            '/timeout/i' => '❌ Connection timeout - Source did not respond',
            '/authentication failed/i' => '❌ Authentication failed - Invalid credentials',
            
            // File operations
            '/Copying/i' => '📋 Copying',
            '/Moving/i' => '📦 Moving',
            '/Deleting/i' => '🗑️  Deleting',
            '/Checking/i' => '🔍 Checking',
            
            // Source/dest references
            '/source:/i' => 'Source',
            '/dest:/i' => 'Destination',
        ];

        $formatted = $msg;
        foreach ($replacements as $pattern => $replacement) {
            $formatted = preg_replace($pattern, $replacement, $formatted);
        }

        // Extract file paths and make them readable
        if (isset($entry['object'])) {
            $formatted .= " - File: " . basename($entry['object']);
        }

        // Handle specific error types
        if ($level === 'error') {
            if (stripos($msg, 'permission') !== false) {
                $formatted = "❌ Permission Error: Unable to access the requested file or directory. Please check your source credentials have read access.";
            } elseif (stripos($msg, 'not found') !== false) {
                $formatted = "❌ File Not Found: The requested file or directory does not exist at the source location.";
            } elseif (stripos($msg, 'connection') !== false) {
                $formatted = "❌ Connection Error: Unable to connect to the source storage. Please check your network connection and source settings.";
            } elseif (stripos($msg, 'authentication') !== false || stripos($msg, 'auth') !== false) {
                $formatted = "❌ Authentication Error: Invalid credentials provided. Please verify your access keys are correct and have read permissions.";
            }
        }

        return $formatted;
    }

    /**
     * Format validation-specific messages
     *
     * @param string $msg Original message
     * @param string $level Log level
     * @param array $entry Full log entry
     * @return string Formatted message
     */
    private static function formatValidationMessage($msg, $level, $entry)
    {
        $replacements = [
            '/Starting check/i' => '🔍 Starting validation check',
            '/mismatch/i' => '❌ Data mismatch detected',
            '/error/i' => '❌ Validation error',
            '/differences found/i' => '⚠️  Differences found between source and backup',
            '/identical/i' => '✅ Files are identical',
            '/OK/i' => '✅ Verified',
        ];

        $formatted = $msg;
        foreach ($replacements as $pattern => $replacement) {
            $formatted = preg_replace($pattern, $replacement, $formatted);
        }

        return $formatted;
    }

    /**
     * Format timestamp to readable format
     *
     * @param string $timestamp ISO 8601 timestamp
     * @return string Formatted timestamp
     */
    private static function formatTimestamp($timestamp)
    {
        try {
            $dt = new \DateTime($timestamp);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Detect section header from message
     *
     * @param string $message
     * @return string|null Section name or null
     */
    private static function detectSection($message)
    {
        if (stripos($message, 'Starting') !== false || stripos($message, '🔄 Starting') !== false) {
            return "🚀 Starting Backup";
        }
        if (stripos($message, 'Transferring') !== false || stripos($message, 'Copying') !== false || stripos($message, '📤') !== false) {
            return "📤 Transferring Files";
        }
        if (stripos($message, 'Completed') !== false || stripos($message, '✅') !== false) {
            return "✅ Completing Backup";
        }
        if (stripos($message, 'Error') !== false || stripos($message, '❌') !== false) {
            return "❌ Errors";
        }
        return null;
    }

    /**
     * Format stats summary in user-friendly way
     *
     * @param array $stats Stats array from rclone
     * @param bool $nothingToTransfer Whether there was nothing to transfer
     * @return string Formatted summary
     */
    private static function formatStatsSummary($stats, $nothingToTransfer = false)
    {
        $output = [];

        // Check for nothing to transfer in stats
        $totalBytes = isset($stats['totalBytes']) ? (int)$stats['totalBytes'] : (isset($stats['bytesTotal']) ? (int)$stats['bytesTotal'] : 0);
        $bytes = isset($stats['bytes']) ? (int)$stats['bytes'] : 0;
        $totalTransfers = isset($stats['totalTransfers']) ? (int)$stats['totalTransfers'] : 0;
        $transfers = isset($stats['transfers']) ? (int)$stats['transfers'] : 0;
        
        if ($nothingToTransfer || ($totalBytes == 0 && $bytes == 0 && $totalTransfers == 0 && $transfers == 0)) {
            $output[] = "Status: ✅ No files to transfer";
            $output[] = "Reason: Source and destination are already synchronized";
        }

        if (isset($stats['bytes']) || isset($stats['totalBytes'])) {
            $bytes = isset($stats['bytes']) ? (int)$stats['bytes'] : 0;
            $bytesTotal = isset($stats['bytesTotal']) ? (int)$stats['bytesTotal'] : (isset($stats['totalBytes']) ? (int)$stats['totalBytes'] : $bytes);
            
            if ($bytesTotal > 0 || $bytes > 0) {
                $bytesFormatted = HelperController::formatSizeUnits($bytes);
                $bytesTotalFormatted = HelperController::formatSizeUnits($bytesTotal);
                
                $output[] = "Data Transferred: " . strip_tags($bytesFormatted) . " of " . strip_tags($bytesTotalFormatted);
                
                if ($bytesTotal > 0) {
                    $percent = round(($bytes / $bytesTotal) * 100, 2);
                    $output[] = "Progress: {$percent}%";
                }
            } else {
                $output[] = "Data Transferred: 0 Bytes";
            }
        }

        // Handle files/transfers
        if (isset($stats['transfers']) || isset($stats['files'])) {
            $transfers = isset($stats['transfers']) ? (int)$stats['transfers'] : (isset($stats['files']) ? (int)$stats['files'] : 0);
            $totalTransfers = isset($stats['totalTransfers']) ? (int)$stats['totalTransfers'] : (isset($stats['filesTotal']) ? (int)$stats['filesTotal'] : $transfers);
            $output[] = "Files Processed: {$transfers} of {$totalTransfers}";
        }

        // Handle checks
        if (isset($stats['checks']) || isset($stats['totalChecks'])) {
            $checks = isset($stats['checks']) ? (int)$stats['checks'] : 0;
            $totalChecks = isset($stats['totalChecks']) ? (int)$stats['totalChecks'] : $checks;
            if ($totalChecks > 0) {
                $output[] = "Files Checked: {$checks} of {$totalChecks}";
            }
        }

        if (isset($stats['speed']) && (int)$stats['speed'] > 0) {
            $speed = (int)$stats['speed'];
            $speedFormatted = HelperController::formatSizeUnits($speed);
            $output[] = "Average Speed: " . strip_tags($speedFormatted) . "/s";
        }

        if (isset($stats['elapsedTime'])) {
            $elapsed = (float)$stats['elapsedTime'];
            $output[] = "Duration: " . self::formatDuration($elapsed);
        }

        return implode("\n", $output);
    }

    /**
     * Format duration in seconds to human-readable format
     *
     * @param float $seconds
     * @return string
     */
    private static function formatDuration($seconds)
    {
        if ($seconds < 60) {
            return round($seconds, 2) . " seconds";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = round($seconds % 60, 2);
            return "{$minutes} minute" . ($minutes != 1 ? 's' : '') . " {$secs} second" . ($secs != 1 ? 's' : '');
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = round($seconds % 60, 2);
            return "{$hours} hour" . ($hours != 1 ? 's' : '') . " {$minutes} minute" . ($minutes != 1 ? 's' : '') . " {$secs} second" . ($secs != 1 ? 's' : '');
        }
    }
}

