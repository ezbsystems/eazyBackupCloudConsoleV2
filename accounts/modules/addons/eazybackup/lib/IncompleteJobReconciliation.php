<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Eazybackup;

use RuntimeException;

/**
 * Pure normalization and decision policy for incomplete Comet backup jobs.
 */
final class IncompleteJobReconciliation
{
    public const STATUS_ACTIVE = 6001;
    public const STATUS_REVIVED = 6002;
    public const BACKUP_CLASSIFICATION = 4001;

    /**
     * @return array{
     *   eligible:bool,
     *   reason:string,
     *   job:?array{
     *     job_id:string,
     *     username:string,
     *     device:string,
     *     job_type:int,
     *     status:int,
     *     started_at:int,
     *     bytes:int,
     *     heartbeat_ts:int,
     *     cancellation_id:string
     *   }
     * }
     */
    public static function classify(array $job): array
    {
        $jobId = trim((string)($job['GUID'] ?? ''));
        $username = trim((string)($job['Username'] ?? ''));
        $startedAt = (int)($job['StartTime'] ?? 0);

        if ($jobId === '' || $username === '' || $startedAt <= 0) {
            return self::excluded('malformed');
        }

        $classification = (int)($job['Classification'] ?? 0);
        if ($classification !== self::BACKUP_CLASSIFICATION) {
            return self::excluded('non_backup_classification');
        }

        $status = (int)($job['Status'] ?? 0);
        if (!self::isRunningStatus($status)) {
            return self::excluded('terminal_status');
        }

        $progress = is_array($job['Progress'] ?? null) ? $job['Progress'] : [];
        $bytes = max(
            0,
            (int)($job['UploadSize'] ?? 0),
            (int)($progress['BytesDone'] ?? 0)
        );

        return [
            'eligible' => true,
            'reason' => 'eligible',
            'job' => [
                'job_id' => $jobId,
                'username' => $username,
                'device' => trim((string)($job['DeviceID'] ?? '')),
                'job_type' => self::BACKUP_CLASSIFICATION,
                'status' => $status,
                'started_at' => $startedAt,
                'bytes' => $bytes,
                'heartbeat_ts' => LiveJobState::progressHeartbeatTs($job),
                'cancellation_id' => trim((string)($job['CancellationID'] ?? '')),
            ],
        ];
    }

    /**
     * @param mixed $response
     * @return array{
     *   jobs:list<array<string,int|string>>,
     *   counts:array<string,int>,
     *   total:int
     * }
     */
    public static function classifyResponse($response): array
    {
        if (!is_array($response) || !array_is_list($response)) {
            throw new RuntimeException('malformed incomplete-jobs response');
        }

        $jobs = [];
        $counts = [
            'eligible' => 0,
            'terminal_status' => 0,
            'non_backup_classification' => 0,
            'malformed' => 0,
        ];

        foreach ($response as $row) {
            $classified = is_array($row)
                ? self::classify($row)
                : self::excluded('malformed');
            $reason = (string)$classified['reason'];
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
            if ($classified['eligible'] && is_array($classified['job'])) {
                $jobs[] = $classified['job'];
            }
        }

        return [
            'jobs' => $jobs,
            'counts' => $counts,
            'total' => count($response),
        ];
    }

    /**
     * Determine the next state-machine action for a confirmed Comet job.
     *
     * @param array{
     *   now:int,
     *   activity_ts:int,
     *   stale_secs:int,
     *   last_checked_ts:int,
     *   recheck_secs:int,
     *   stale_observations:int,
     *   action_stage:string,
     *   next_action_ts:int,
     *   action_attempts:int,
     *   max_attempts:int,
     *   has_cancellation_id:bool
     * } $state
     * @return array{action:string,reason:string}
     */
    public static function decide(array $state): array
    {
        $now = (int)$state['now'];
        $activityTs = (int)$state['activity_ts'];
        $staleSecs = max(1, (int)$state['stale_secs']);
        $lastCheckedTs = (int)$state['last_checked_ts'];
        $recheckSecs = max(1, (int)$state['recheck_secs']);
        $staleObservations = max(0, (int)$state['stale_observations']);
        $stage = (string)$state['action_stage'];
        $nextActionTs = (int)$state['next_action_ts'];
        $attempts = max(0, (int)$state['action_attempts']);
        $maxAttempts = max(1, (int)$state['max_attempts']);
        $hasCancellationId = (bool)$state['has_cancellation_id'];

        if ($activityTs > 0 && ($now - $activityTs) <= $staleSecs) {
            return self::decision('fresh', 'activity_within_stale_window');
        }

        if ($stage === 'exhausted') {
            return $nextActionTs > $now
                ? self::decision('defer', 'exhausted_cooldown')
                : self::decision('cooldown_reset', 'exhausted_cooldown_elapsed');
        }

        if ($nextActionTs > $now) {
            return self::decision('defer', 'next_action_not_due');
        }

        if ($lastCheckedTs > 0 && ($now - $lastCheckedTs) < $recheckSecs) {
            return self::decision('defer', 'recheck_interval');
        }

        if ($attempts >= $maxAttempts) {
            return self::decision('exhaust', 'maximum_action_attempts');
        }

        if ($staleObservations < 1) {
            return self::decision('strike', 'first_stale_observation');
        }

        if (in_array($stage, ['cancel_requested', 'cancel_unavailable', 'abandon_requested'], true)) {
            return self::decision('abandon', 'running_after_prior_action');
        }

        return $hasCancellationId
            ? self::decision('cancel', 'confirmed_stale_with_cancellation_id')
            : self::decision('abandon', 'confirmed_stale_without_cancellation_id');
    }

    /**
     * @param array{Username?:mixed,Password?:mixed,SessionKey?:mixed} $auth
     */
    public static function validateAuth(array $auth): void
    {
        $username = trim((string)($auth['Username'] ?? ''));
        $password = (string)($auth['Password'] ?? '');
        $sessionKey = (string)($auth['SessionKey'] ?? '');

        if ($username === '' || ($password === '' && $sessionKey === '')) {
            throw new RuntimeException('Comet API credentials missing');
        }
    }

    public static function assertNoApiError(array $response): void
    {
        if (
            array_key_exists('Status', $response)
            && array_key_exists('Message', $response)
            && is_numeric($response['Status'])
            && (int)$response['Status'] >= 400
        ) {
            $status = (int)$response['Status'];
            $message = self::sanitizeError((string)$response['Message']);
            throw new RuntimeException("Comet API error {$status}: {$message}", $status);
        }
    }

    public static function validateMutationResponse(array $response): void
    {
        self::assertNoApiError($response);
        $status = isset($response['Status']) && is_numeric($response['Status'])
            ? (int)$response['Status']
            : 0;
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Comet mutation returned an invalid status');
        }
    }

    public static function isRunningStatus(int $status): bool
    {
        return in_array($status, [self::STATUS_ACTIVE, self::STATUS_REVIVED], true);
    }

    public static function isKnownTerminalStatus(int $status): bool
    {
        return $status === 5000 || ($status >= 7000 && $status <= 7007);
    }

    public static function sanitizeError(string $message): string
    {
        $sanitized = preg_replace(
            '/(["\']?)(Password|SessionKey|TOTP)\1\s*(?:=|:)\s*(?:"[^"]*"|\'[^\']*\'|[^&\s,}]+)/i',
            '$2=[redacted]',
            $message
        );
        $sanitized = is_string($sanitized) ? $sanitized : 'sanitized error';

        return substr($sanitized, 0, 255);
    }

    /**
     * @return array{eligible:false,reason:string,job:null}
     */
    private static function excluded(string $reason): array
    {
        return [
            'eligible' => false,
            'reason' => $reason,
            'job' => null,
        ];
    }

    /**
     * @return array{action:string,reason:string}
     */
    private static function decision(string $action, string $reason): array
    {
        return [
            'action' => $action,
            'reason' => $reason,
        ];
    }
}
