<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Per-client onboarding state for the e3 Cloud Backup first-run experience.
 *
 * Public surface:
 *   - compute($clientId): array          one-shot status payload (used by API + shell pill)
 *   - recordEvent($clientId, $event)     persist a click event (download_clicked / tour_*)
 *   - touchVisit($clientId)              update last_visited_getting_started_at
 *
 * Three of the four step completion booleans are derived live from the
 * authoritative tables (`s3_cloudbackup_agents`, `s3_cloudbackup_jobs`,
 * `s3_cloudbackup_runs`). Only the Download step needs explicit tracking
 * via the `s3_e3backup_onboarding_state` row.
 */
class OnboardingState
{
    public const EVENT_DOWNLOAD_CLICKED          = 'download_clicked';
    public const EVENT_TOUR_STARTED              = 'tour_started';
    public const EVENT_TOUR_COMPLETED            = 'tour_completed';
    public const EVENT_TOUR_DISMISSED            = 'tour_dismissed';
    public const EVENT_FIRST_JOB_TOUR_STARTED    = 'first_job_tour_started';
    public const EVENT_FIRST_JOB_TOUR_COMPLETED  = 'first_job_tour_completed';
    public const EVENT_FIRST_JOB_TOUR_DISMISSED  = 'first_job_tour_dismissed';

    /**
     * Build the full status payload for a single client.
     *
     * @return array<string,mixed>
     */
    public static function compute(int $clientId): array
    {
        $row = self::loadRow($clientId);
        $agentCount = self::agentCount($clientId);
        $jobCount   = self::jobCount($clientId);
        $runCount   = self::runSuccessCount($clientId);

        $downloadComplete = $row && !empty($row->download_clicked_at);
        $agentOnline      = $agentCount > 0;
        $firstJob         = $jobCount > 0;
        $firstRun         = $runCount > 0;

        $steps = [
            'download' => [
                'complete'     => (bool) $downloadComplete,
                'completed_at' => $downloadComplete ? (string) $row->download_clicked_at : null,
            ],
            'agent_online' => [
                'complete'     => $agentOnline,
                'completed_at' => null,
                'agent_count'  => $agentCount,
            ],
            'first_job' => [
                'complete'     => $firstJob,
                'completed_at' => null,
                'job_count'    => $jobCount,
            ],
            'first_run' => [
                'complete'     => $firstRun,
                'completed_at' => null,
                'run_count'    => $runCount,
            ],
        ];

        $completedCount = (int) $downloadComplete + (int) $agentOnline + (int) $firstJob + (int) $firstRun;
        $totalCount = 4;

        return [
            'client_id'        => $clientId,
            'steps'            => $steps,
            'completed_count'  => $completedCount,
            'total_count'      => $totalCount,
            'all_complete'     => $completedCount >= $totalCount,
            'tour_started'              => $row && !empty($row->tour_started_at),
            'tour_completed'            => $row && !empty($row->tour_completed_at),
            'tour_dismissed'            => $row && !empty($row->tour_dismissed_at),
            'first_job_tour_started'    => $row && !empty($row->first_job_tour_started_at ?? null),
            'first_job_tour_completed'  => $row && !empty($row->first_job_tour_completed_at ?? null),
            'first_job_tour_dismissed'  => $row && !empty($row->first_job_tour_dismissed_at ?? null),
            'last_visited_at'  => $row && !empty($row->last_visited_getting_started_at)
                ? (string) $row->last_visited_getting_started_at
                : null,
        ];
    }

    /**
     * Persist an explicit event. Idempotent for the *_at columns - they are
     * stamped once and never overwritten so we can tell when the customer
     * first crossed each threshold.
     */
    public static function recordEvent(int $clientId, string $event): bool
    {
        if ($clientId <= 0) {
            return false;
        }
        $column = self::columnForEvent($event);
        if ($column === null) {
            return false;
        }
        try {
            self::ensureRow($clientId);
            // Only stamp if not already set; preserve the very first event time.
            $row = self::loadRow($clientId);
            if ($row && !empty($row->{$column})) {
                return true;
            }
            $now = date('Y-m-d H:i:s');
            Capsule::table('s3_e3backup_onboarding_state')
                ->where('client_id', $clientId)
                ->update([
                    $column      => $now,
                    'updated_at' => $now,
                ]);
            return true;
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'onboarding_record_event_fail', ['client_id' => $clientId, 'event' => $event], $e->getMessage(), [], []); } catch (\Throwable $_) {}
            return false;
        }
    }

    /**
     * Update last_visited_getting_started_at. Cheap, called from the page handler.
     */
    public static function touchVisit(int $clientId): void
    {
        if ($clientId <= 0) {
            return;
        }
        try {
            self::ensureRow($clientId);
            $now = date('Y-m-d H:i:s');
            Capsule::table('s3_e3backup_onboarding_state')
                ->where('client_id', $clientId)
                ->update([
                    'last_visited_getting_started_at' => $now,
                    'updated_at'                       => $now,
                ]);
        } catch (\Throwable $e) {
        }
    }

    private static function columnForEvent(string $event): ?string
    {
        switch ($event) {
            case self::EVENT_DOWNLOAD_CLICKED:         return 'download_clicked_at';
            case self::EVENT_TOUR_STARTED:             return 'tour_started_at';
            case self::EVENT_TOUR_COMPLETED:           return 'tour_completed_at';
            case self::EVENT_TOUR_DISMISSED:           return 'tour_dismissed_at';
            case self::EVENT_FIRST_JOB_TOUR_STARTED:   return 'first_job_tour_started_at';
            case self::EVENT_FIRST_JOB_TOUR_COMPLETED: return 'first_job_tour_completed_at';
            case self::EVENT_FIRST_JOB_TOUR_DISMISSED: return 'first_job_tour_dismissed_at';
        }
        return null;
    }

    private static function loadRow(int $clientId)
    {
        try {
            if (!Capsule::schema()->hasTable('s3_e3backup_onboarding_state')) {
                return null;
            }
            return Capsule::table('s3_e3backup_onboarding_state')->where('client_id', $clientId)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function ensureRow(int $clientId): void
    {
        try {
            if (!Capsule::schema()->hasTable('s3_e3backup_onboarding_state')) {
                return;
            }
            $exists = Capsule::table('s3_e3backup_onboarding_state')->where('client_id', $clientId)->exists();
            if (!$exists) {
                Capsule::table('s3_e3backup_onboarding_state')->insert([
                    'client_id'  => $clientId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
        }
    }

    private static function agentCount(int $clientId): int
    {
        try {
            if (!Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
                return 0;
            }
            return (int) Capsule::table('s3_cloudbackup_agents')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->whereNotNull('last_seen_at')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function jobCount(int $clientId): int
    {
        try {
            if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
                return 0;
            }
            return (int) Capsule::table('s3_cloudbackup_jobs')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function runSuccessCount(int $clientId): int
    {
        try {
            if (!Capsule::schema()->hasTable('s3_cloudbackup_runs') || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
                return 0;
            }
            return (int) Capsule::table('s3_cloudbackup_runs as r')
                ->join('s3_cloudbackup_jobs as j', 'j.job_id', '=', 'r.job_id')
                ->where('j.client_id', $clientId)
                ->where('r.status', 'success')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
