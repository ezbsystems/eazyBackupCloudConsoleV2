<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use WHMCS\Database\Capsule;

class CloudBackupAdminController {

    /**
     * Get all jobs across all clients
     *
     * @param array $filters
     * @return array
     */
    public static function getAllJobs($filters = [])
    {
        try {
            $query = Capsule::table('s3_cloudbackup_jobs')
                ->join('tblclients', 's3_cloudbackup_jobs.client_id', '=', 'tblclients.id')
                ->select(
                    's3_cloudbackup_jobs.*',
                    'tblclients.firstname',
                    'tblclients.lastname',
                    'tblclients.email'
                );

            if (isset($filters['client_id']) && $filters['client_id']) {
                $query->where('s3_cloudbackup_jobs.client_id', $filters['client_id']);
            }

            if (isset($filters['status']) && $filters['status']) {
                $query->where('s3_cloudbackup_jobs.status', $filters['status']);
            }

            if (isset($filters['source_type']) && $filters['source_type']) {
                $query->where('s3_cloudbackup_jobs.source_type', $filters['source_type']);
            }

            if (isset($filters['job_name']) && $filters['job_name']) {
                $query->where('s3_cloudbackup_jobs.name', 'LIKE', '%' . $filters['job_name'] . '%');
            }

            $results = $query->orderBy('s3_cloudbackup_jobs.created_at', 'desc')->get();
            // Convert stdClass objects to arrays for Smarty compatibility
            return array_map(function($item) {
                return (array) $item;
            }, $results->toArray());
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'getAllJobs', $filters, $e->getMessage());
            return [];
        }
    }

    /**
     * Get all runs across all clients
     *
     * @param array $filters
     * @return array
     */
    public static function getAllRuns($filters = [])
    {
        try {
            $query = Capsule::table('s3_cloudbackup_runs')
                ->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.id')
                ->join('tblclients', 's3_cloudbackup_jobs.client_id', '=', 'tblclients.id')
                ->select(
                    's3_cloudbackup_runs.*',
                    's3_cloudbackup_jobs.name as job_name',
                    's3_cloudbackup_jobs.client_id',
                    'tblclients.firstname',
                    'tblclients.lastname',
                    'tblclients.email'
                );

            if (isset($filters['client_id']) && $filters['client_id']) {
                $query->where('s3_cloudbackup_jobs.client_id', $filters['client_id']);
            }

            if (isset($filters['job_id']) && $filters['job_id']) {
                $query->where('s3_cloudbackup_runs.job_id', $filters['job_id']);
            }

            if (isset($filters['status']) && $filters['status']) {
                $query->where('s3_cloudbackup_runs.status', $filters['status']);
            }

            if (isset($filters['date_from']) && $filters['date_from']) {
                $query->where('s3_cloudbackup_runs.started_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to']) && $filters['date_to']) {
                $query->where('s3_cloudbackup_runs.started_at', '<=', $filters['date_to'] . ' 23:59:59');
            }

            if (isset($filters['job_name']) && $filters['job_name']) {
                $query->where('s3_cloudbackup_jobs.name', 'LIKE', '%' . $filters['job_name'] . '%');
            }

            $results = $query->orderBy('s3_cloudbackup_runs.started_at', 'desc')->limit(500)->get();
            // Convert stdClass objects to arrays for Smarty compatibility
            return array_map(function($item) {
                return (array) $item;
            }, $results->toArray());
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'getAllRuns', $filters, $e->getMessage());
            return [];
        }
    }

    /**
     * Force cancel a run
     *
     * @param int $runId
     * @return array
     */
    public static function forceCancelRun($runId)
    {
        try {
            Capsule::table('s3_cloudbackup_runs')
                ->where('id', $runId)
                ->update([
                    'cancel_requested' => 1,
                ]);

            return ['status' => 'success'];
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'forceCancelRun', ['run_id' => $runId], $e->getMessage());
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }
}

