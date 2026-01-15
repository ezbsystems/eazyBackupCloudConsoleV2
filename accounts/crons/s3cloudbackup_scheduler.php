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

    return [
        'type' => $type,
        'time' => (string)($job->schedule_time ?? ($json['time'] ?? '')),
        'weekday' => (string)($job->schedule_weekday ?? ($json['weekday'] ?? '')),
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

function computeSlot(DateTime $now, array $schedule): ?array
{
    $type = $schedule['type'] ?? '';
    if ($type === '' || $type === 'manual') {
        return null;
    }

    $slotStart = clone $now;
    if ($type === 'hourly') {
        $slotStart->setTime((int)$now->format('H'), 0, 0);
        $slotEnd = (clone $slotStart)->modify('+1 hour');
        return [$slotStart, $slotEnd];
    }

    if ($type === 'daily') {
        $time = trim((string)($schedule['time'] ?? '00:00'));
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            return null;
        }
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $slotStart->setTime($hour, $minute, 0);
        $slotEnd = (clone $slotStart)->modify('+1 day');
        return [$slotStart, $slotEnd];
    }

    if ($type === 'weekly') {
        $weekday = (int)($schedule['weekday'] ?? 0);
        if ($weekday < 1 || $weekday > 7) {
            return null;
        }
        $time = trim((string)($schedule['time'] ?? '00:00'));
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            return null;
        }
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $slotStart->setTime($hour, $minute, 0);
        $today = (int)$now->format('N');
        $delta = $weekday - $today;
        $slotStart->modify(($delta >= 0 ? '+' : '') . $delta . ' days');
        $slotEnd = (clone $slotStart)->modify('+7 days');
        return [$slotStart, $slotEnd];
    }

    if ($type === 'cron') {
        // Cron parsing is not implemented; skip safely
        return null;
    }

    return null;
}

$serverTz = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
$settingsMap = [];
try {
    $settingsMap = Capsule::table('s3_cloudbackup_settings')->pluck('default_timezone', 'client_id')->toArray();
} catch (\Throwable $e) {}

$jobs = Capsule::table('s3_cloudbackup_jobs')
    ->where('status', 'active')
    ->select([
        'id',
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

    $existingScheduled = Capsule::table('s3_cloudbackup_runs')
        ->where('job_id', $job->id)
        ->where('trigger_type', 'schedule')
        ->whereBetween('created_at', [$slotStartServer->format('Y-m-d H:i:s'), $slotEndServer->format('Y-m-d H:i:s')])
        ->count();
    if ($existingScheduled > 0) {
        $skipped++;
        continue;
    }

    $inFlight = Capsule::table('s3_cloudbackup_runs')
        ->where('job_id', $job->id)
        ->whereIn('status', ['queued', 'starting', 'running'])
        ->whereNull('finished_at')
        ->count();
    if ($inFlight > 0) {
        $skipped++;
        continue;
    }

    $res = CloudBackupController::startRun((int)$job->id, (int)$job->client_id, 'schedule');
    logModuleCall('cloudstorage', 'scheduler_queue', [
        'job_id' => $job->id,
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
