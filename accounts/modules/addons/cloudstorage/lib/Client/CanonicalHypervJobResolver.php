<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

class CanonicalHypervJobResolver
{
    public static function normalizeSignatureValue($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '';
            }
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded);
            }
            return $trimmed;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        return trim((string) $value);
    }

    public static function resolveCanonicalJobId(string $jobId, int $clientId): ?string
    {
        if (!UuidBinary::isUuid($jobId)) {
            return null;
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        if (!$hasJobIdPk) {
            return null;
        }

        $columns = Capsule::schema()->getColumnListing('s3_cloudbackup_jobs');
        $hasHypervEnabled = in_array('hyperv_enabled', $columns, true);
        $hasHypervConfig = in_array('hyperv_config', $columns, true);
        $hasSourcePathsJson = in_array('source_paths_json', $columns, true);

        $select = [
            Capsule::raw('BIN_TO_UUID(job_id) as job_id'),
            'client_id',
            'status',
            'engine',
            'source_type',
            'agent_uuid',
            'source_path',
            'dest_bucket_id',
            'dest_prefix',
            'created_at',
        ];
        $select[] = $hasHypervEnabled ? 'hyperv_enabled' : Capsule::raw('0 as hyperv_enabled');
        $select[] = $hasHypervConfig ? 'hyperv_config' : Capsule::raw('NULL as hyperv_config');
        $select[] = $hasSourcePathsJson ? 'source_paths_json' : Capsule::raw('NULL as source_paths_json');

        $current = Capsule::table('s3_cloudbackup_jobs')
            ->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobId)))
            ->first($select);

        if (!$current || (int) ($current->client_id ?? 0) !== $clientId) {
            return null;
        }

        $currentEngine = strtolower(trim((string) ($current->engine ?? '')));
        $isHypervLike = $currentEngine === 'hyperv'
            || (int) ($current->hyperv_enabled ?? 0) === 1
            || trim((string) ($current->hyperv_config ?? '')) !== '';
        if (!$isHypervLike || $currentEngine === 'hyperv') {
            return null;
        }

        $sourcePathsSignature = self::normalizeSignatureValue($current->source_paths_json ?? null);
        $hypervConfigSignature = self::normalizeSignatureValue($current->hyperv_config ?? null);
        $sourcePath = trim((string) ($current->source_path ?? ''));
        $agentUuid = trim((string) ($current->agent_uuid ?? ''));
        $destBucketId = (string) ($current->dest_bucket_id ?? '');
        $destPrefix = trim((string) ($current->dest_prefix ?? ''));

        $candidateQuery = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('status', '!=', 'deleted')
            ->whereRaw('job_id != ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobId)))
            ->where('source_type', 'local_agent')
            ->where('engine', 'hyperv');

        if ($agentUuid !== '') {
            $candidateQuery->where('agent_uuid', $agentUuid);
        } else {
            $candidateQuery->whereNull('agent_uuid');
        }
        if ($sourcePath !== '') {
            $candidateQuery->where('source_path', $sourcePath);
        } else {
            $candidateQuery->where(function ($q) {
                $q->whereNull('source_path')->orWhere('source_path', '');
            });
        }
        if ($destBucketId !== '') {
            $candidateQuery->where('dest_bucket_id', $destBucketId);
        } else {
            $candidateQuery->whereNull('dest_bucket_id');
        }
        if ($destPrefix !== '') {
            $candidateQuery->where('dest_prefix', $destPrefix);
        } else {
            $candidateQuery->where(function ($q) {
                $q->whereNull('dest_prefix')->orWhere('dest_prefix', '');
            });
        }
        if ($hasSourcePathsJson) {
            if ($sourcePathsSignature !== '') {
                $candidateQuery->where('source_paths_json', $sourcePathsSignature);
            } else {
                $candidateQuery->where(function ($q) {
                    $q->whereNull('source_paths_json')->orWhere('source_paths_json', '');
                });
            }
        }
        if ($hasHypervConfig) {
            if ($hypervConfigSignature !== '') {
                $candidateQuery->where('hyperv_config', $hypervConfigSignature);
            } else {
                $candidateQuery->where(function ($q) {
                    $q->whereNull('hyperv_config')->orWhere('hyperv_config', '');
                });
            }
        }

        $candidate = $candidateQuery
            ->orderByDesc('created_at')
            ->first([Capsule::raw('BIN_TO_UUID(job_id) as job_id')]);

        return $candidate && !empty($candidate->job_id) ? (string) $candidate->job_id : null;
    }
}
