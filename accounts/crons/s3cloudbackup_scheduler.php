<?php

/**
 * Cloud Backup Scheduler Cron
 *
 * Evaluates scheduled jobs and enqueues runs via CloudBackupController::startRun()
 *
 * Recommended: run every 5 minutes.
 */

require_once __DIR__ . '/../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

function parseScheduleJson($raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }
    $candidates = [
        $raw,
        stripslashes($raw),
        html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    ];
    foreach ($candidates as $cand) {
        $decoded = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }
    return [];
}

function resolveScheduleType($job): array
{
    $type = strtolower(trim((string)($job->schedule_type ?? '')));
    $json = parseScheduleJson($job->schedule_json ?? null);
    if ($type === '' || $type === 'manual') {
        $jsonType = strtolower(trim((string)($json['type'] ?? '')));
        if ($jsonType !== '') {
            $type = $jsonType;
        }
    }

    // Extract weekday - can be array (multi-day) or single value
    $weekday = $job->schedule_weekday ?? null;
    $weekdayArray = [];
    if (isset($json['weekday'])) {
        if (is_array($json['weekday'])) {
            $weekdayArray = array_map('intval', $json['weekday']);
        } elseif (is_string($json['weekday']) && strpos($json['weekday'], ',') !== false) {
            $weekdayArray = array_map('intval', explode(',', $json['weekday']));
        } elseif ($json['weekday'] !== '') {
            $weekdayArray = [(int)$json['weekday']];
        }
    } elseif ($weekday !== null && $weekday !== '') {
        if (is_string($weekday) && strpos($weekday, ',') !== false) {
            $weekdayArray = array_map('intval', explode(',', $weekday));
        } else {
            $weekdayArray = [(int)$weekday];
        }
    }
    // Filter valid weekdays (1-7)
    $weekdayArray = array_filter($weekdayArray, fn($d) => $d >= 1 && $d <= 7);
    
    // Extract minute for hourly schedules
    $hourlyMinute = null;
    if (isset($json['minute']) && is_numeric($json['minute'])) {
        $hourlyMinute = (int)$json['minute'];
    } else {
        // Try to extract from time field (format "MM:00" for hourly)
        $time = (string)($job->schedule_time ?? ($json['time'] ?? ''));
        if ($time !== '' && preg_match('/^(\d{1,2}):/', $time, $m)) {
            $hourlyMinute = (int)$m[1];
        }
    }
    if ($hourlyMinute !== null && ($hourlyMinute < 0 || $hourlyMinute > 59)) {
        $hourlyMinute = 0;
    }

    return [
        'type' => $type,
        'time' => (string)($job->schedule_time ?? ($json['time'] ?? '')),
        'weekday' => count($weekdayArray) > 0 ? (string)$weekdayArray[0] : '',
        'weekday_array' => $weekdayArray,
        'hourly_minute' => $hourlyMinute,
        'cron' => (string)($job->schedule_cron ?? ($json['cron'] ?? '')),
    ];
}

function resolveTimezone($job, array $settingsMap, DateTimeZone $fallback): DateTimeZone
{
    $tz = trim((string)($job->timezone ?? ''));
    if ($tz === '' && isset($settingsMap[$job->client_id])) {
        $tz = trim((string)$settingsMap[$job->client_id]);
    }
    if ($tz === '') {
        return $fallback;
    }
    try {
        return new DateTimeZone($tz);
    } catch (\Throwable $e) {
        return $fallback;
    }
}

function parseCronField(string $field, int $min, int $max): array
{
    $field = trim($field);
    if ($field === '') {
        return [];
    }

    $values = [];
    foreach (explode(',', $field) as $segment) {
        $segment = trim($segment);
        if ($segment === '') {
            return [];
        }

        $step = 1;
        if (strpos($segment, '/') !== false) {
            [$base, $stepRaw] = explode('/', $segment, 2);
            $base = trim($base);
            $stepRaw = trim($stepRaw);
            if ($stepRaw === '' || !ctype_digit($stepRaw) || (int)$stepRaw <= 0) {
                return [];
            }
            $step = (int)$stepRaw;
            $segment = $base;
        }

        if ($segment === '*') {
            $start = $min;
            $end = $max;
        } elseif (preg_match('/^(\d+)-(\d+)$/', $segment, $m)) {
            $start = (int)$m[1];
            $end = (int)$m[2];
            if ($start > $end) {
                return [];
            }
        } elseif (preg_match('/^\d+$/', $segment)) {
            if ($step !== 1) {
                return [];
            }
            $start = (int)$segment;
            $end = (int)$segment;
        } else {
            return [];
        }

        if ($start < $min || $end > $max) {
            return [];
        }

        for ($value = $start; $value <= $end; $value += $step) {
            $values[$value] = true;
        }
    }

    $values = array_keys($values);
    sort($values);

    return $values;
}

function cronMatchesNow(string $cronExpr, DateTime $now): bool
{
    $parts = preg_split('/\s+/', trim($cronExpr));
    if (!is_array($parts) || count($parts) !== 5) {
        return false;
    }

    [$minuteField, $hourField, $dayOfMonthField, $monthField, $dayOfWeekField] = $parts;

    $minutes = parseCronField($minuteField, 0, 59);
    $hours = parseCronField($hourField, 0, 23);
    $daysOfMonth = parseCronField($dayOfMonthField, 1, 31);
    $months = parseCronField($monthField, 1, 12);
    $daysOfWeek = parseCronField($dayOfWeekField, 0, 7);
    if (empty($minutes) || empty($hours) || empty($daysOfMonth) || empty($months) || empty($daysOfWeek)) {
        return false;
    }

    $daysOfWeek = array_values(array_unique(array_map(static function ($value) {
        return $value === 7 ? 0 : $value;
    }, $daysOfWeek)));

    $minute = (int)$now->format('i');
    $hour = (int)$now->format('G');
    $dayOfMonth = (int)$now->format('j');
    $month = (int)$now->format('n');
    $dayOfWeek = (int)$now->format('w');

    if (!in_array($minute, $minutes, true) || !in_array($hour, $hours, true) || !in_array($month, $months, true)) {
        return false;
    }

    $dayOfMonthMatches = in_array($dayOfMonth, $daysOfMonth, true);
    $dayOfWeekMatches = in_array($dayOfWeek, $daysOfWeek, true);
    $dayOfMonthIsWildcard = trim($dayOfMonthField) === '*';
    $dayOfWeekIsWildcard = trim($dayOfWeekField) === '*';

    if ($dayOfMonthIsWildcard && $dayOfWeekIsWildcard) {
        return true;
    }
    if ($dayOfMonthIsWildcard) {
        return $dayOfWeekMatches;
    }
    if ($dayOfWeekIsWildcard) {
        return $dayOfMonthMatches;
    }

    return $dayOfMonthMatches || $dayOfWeekMatches;
}

function computeSlot(DateTime $now, array $schedule): ?array
{
    $type = $schedule['type'] ?? '';
    if ($type === '' || $type === 'manual') {
        return null;
    }

    $slotStart = clone $now;
    
    if ($type === 'hourly') {
        // Use the minute offset from schedule (default to 0 if not set)
        $minute = $schedule['hourly_minute'] ?? 0;
        if (!is_numeric($minute) || $minute < 0 || $minute > 59) {
            $minute = 0;
        }
        $slotStart->setTime((int)$now->format('H'), (int)$minute, 0);
        $slotEnd = (clone $slotStart)->modify('+1 hour');
        return [$slotStart, $slotEnd];
    }

    if ($type === 'daily') {
        $time = trim((string)($schedule['time'] ?? '00:00'));
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return null;
        }
        $hour = (int)$m[1];
        $minute = (int)$m[2];
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }
        $slotStart->setTime($hour, $minute, 0);
        $slotEnd = (clone $slotStart)->modify('+1 day');
        return [$slotStart, $slotEnd];
    }

    if ($type === 'weekly') {
        // Support multi-day weekly: check if today is in the weekday array
        $weekdayArray = $schedule['weekday_array'] ?? [];
        if (empty($weekdayArray)) {
            // Fall back to single weekday value
            $weekday = (int)($schedule['weekday'] ?? 0);
            if ($weekday >= 1 && $weekday <= 7) {
                $weekdayArray = [$weekday];
            }
        }
        if (empty($weekdayArray)) {
            return null;
        }
        
        $time = trim((string)($schedule['time'] ?? '00:00'));
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return null;
        }
        $hour = (int)$m[1];
        $minute = (int)$m[2];
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }
        
        $today = (int)$now->format('N'); // 1=Monday, 7=Sunday
        
        // Check if today is one of the scheduled days
        if (!in_array($today, $weekdayArray, true)) {
            return null; // Today is not a scheduled day
        }
        
        // Today is a scheduled day - compute the slot for today
        $slotStart->setTime($hour, $minute, 0);
        // Slot end is tomorrow at the same time (ensures one run per scheduled day)
        $slotEnd = (clone $slotStart)->modify('+1 day');
        return [$slotStart, $slotEnd];
    }

    if ($type === 'cron') {
        $cronExpr = trim((string)($schedule['cron'] ?? ''));
        if ($cronExpr === '' || !cronMatchesNow($cronExpr, $now)) {
            return null;
        }
        $slotStart = (clone $now)->setTime(
            (int)$now->format('H'),
            (int)$now->format('i'),
            0
        );
        $slotEnd = (clone $slotStart)->modify('+5 minutes');
        return [$slotStart, $slotEnd];
    }

    return null;
}

$serverTz = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
$settingsMap = [];
try {
    $settingsMap = Capsule::table('s3_cloudbackup_settings')->pluck('default_timezone', 'client_id')->toArray();
} catch (\Throwable $e) {}

$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$jobIdSelect = $hasJobIdPk
    ? Capsule::raw('BIN_TO_UUID(job_id) as job_id')
    : 'id';

$jobs = Capsule::table('s3_cloudbackup_jobs')
    ->where('status', 'active')
    ->select([
        $jobIdSelect,
        'client_id',
        'schedule_type',
        'schedule_time',
        'schedule_weekday',
        'schedule_cron',
        'schedule_json',
        'timezone',
    ])
    ->get();

$queued = 0;
$skipped = 0;

foreach ($jobs as $job) {
    $jobIdentifier = $hasJobIdPk ? $job->job_id : $job->id;

    $schedule = resolveScheduleType($job);
    if (empty($schedule['type']) || $schedule['type'] === 'manual') {
        $skipped++;
        continue;
    }

    $jobTz = resolveTimezone($job, $settingsMap, $serverTz);
    $now = new DateTime('now', $jobTz);
    $slot = computeSlot($now, $schedule);
    if (!$slot) {
        $skipped++;
        continue;
    }
    [$slotStartJob, $slotEndJob] = $slot;

    if ($now < $slotStartJob) {
        $skipped++;
        continue;
    }

    $slotStartServer = (clone $slotStartJob)->setTimezone($serverTz);
    $slotEndServer = (clone $slotEndJob)->setTimezone($serverTz);

    $dupeQuery = Capsule::table('s3_cloudbackup_runs');
    if ($hasJobIdPk && UuidBinary::isUuid($jobIdentifier)) {
        $dupeQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobIdentifier)));
    } else {
        $dupeQuery->where('job_id', $jobIdentifier);
    }
    $existingScheduled = $dupeQuery
        ->where('trigger_type', 'schedule')
        ->whereBetween('created_at', [$slotStartServer->format('Y-m-d H:i:s'), $slotEndServer->format('Y-m-d H:i:s')])
        ->count();
    if ($existingScheduled > 0) {
        $skipped++;
        continue;
    }

    $flightQuery = Capsule::table('s3_cloudbackup_runs');
    if ($hasJobIdPk && UuidBinary::isUuid($jobIdentifier)) {
        $flightQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobIdentifier)));
    } else {
        $flightQuery->where('job_id', $jobIdentifier);
    }
    $inFlight = $flightQuery
        ->whereIn('status', ['queued', 'starting', 'running'])
        ->whereNull('finished_at')
        ->count();
    if ($inFlight > 0) {
        $skipped++;
        continue;
    }

    $res = CloudBackupController::startRun($jobIdentifier, (int)$job->client_id, 'schedule');
    logModuleCall('cloudstorage', 'scheduler_queue', [
        'job_id' => $jobIdentifier,
        'client_id' => $job->client_id,
        'schedule_type' => $schedule['type'],
        'slot_start' => $slotStartJob->format('Y-m-d H:i:s'),
        'slot_end' => $slotEndJob->format('Y-m-d H:i:s'),
    ], $res);
    if (($res['status'] ?? 'fail') === 'success') {
        $queued++;
    }
}

echo "Scheduler complete. queued={$queued}, skipped={$skipped}\n";
