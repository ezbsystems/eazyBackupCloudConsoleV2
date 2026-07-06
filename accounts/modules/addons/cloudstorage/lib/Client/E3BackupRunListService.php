<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Paginated run list for the global / scoped Job Logs page.
 */
final class E3BackupRunListService
{
  public const WORKLOAD_MS365 = 'ms365';
  public const WORKLOAD_LOCAL_AGENT = 'local_agent';
  public const WORKLOAD_CLOUD_TO_CLOUD = 'cloud_to_cloud';

  /** @var list<string> */
  public const VALID_WORKLOADS = [
    self::WORKLOAD_MS365,
    self::WORKLOAD_LOCAL_AGENT,
    self::WORKLOAD_CLOUD_TO_CLOUD,
  ];

  /**
   * @param array<string, mixed> $filters
   * @return array{total: int, rows: list<array<string, mixed>>, facets: array{statusCounts: array<string, int>}}
   */
  public static function listRuns(int $clientId, array $filters): array
  {
    $schema = Capsule::schema();
    $hasRunIdCol = $schema->hasColumn('s3_cloudbackup_runs', 'run_id');
    $hasRunTypeCol = $schema->hasColumn('s3_cloudbackup_runs', 'run_type');
    $hasStatsJsonCol = $schema->hasColumn('s3_cloudbackup_runs', 'stats_json');
    $hasErrorSummaryCol = $schema->hasColumn('s3_cloudbackup_runs', 'error_summary');
    $hasRunCreatedAtCol = $schema->hasColumn('s3_cloudbackup_runs', 'created_at');
    $hasJobIdPk = $schema->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $hasJobTenant = $schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id');
    $hasJobBackupUser = $schema->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
    $hasJobSourceType = $schema->hasColumn('s3_cloudbackup_jobs', 'source_type');
    $hasJobSourceDisplay = $schema->hasColumn('s3_cloudbackup_jobs', 'source_display_name');
    $hasJobAgentUuid = $schema->hasColumn('s3_cloudbackup_jobs', 'agent_uuid');
    $hasAgentBackupUser = $schema->hasTable('s3_cloudbackup_agents')
      && $schema->hasColumn('s3_cloudbackup_agents', 'backup_user_id');

    $rangeHours = (int) ($filters['range_hours'] ?? 24);
    if (!in_array($rangeHours, [24, 48, 60, 72], true)) {
      $rangeHours = 24;
    }
    $statuses = is_array($filters['statuses'] ?? null) ? array_values($filters['statuses']) : [];
    $workloads = is_array($filters['workloads'] ?? null) ? array_values($filters['workloads']) : [];
    $workloads = array_values(array_intersect($workloads, self::VALID_WORKLOADS));
    $agentFilter = trim((string) ($filters['agent_uuid'] ?? ''));
    $q = trim((string) ($filters['q'] ?? ''));
    $page = max(1, (int) ($filters['page'] ?? 1));
    $pageSize = (int) ($filters['pageSize'] ?? 25);
    if (!in_array($pageSize, [10, 25, 50, 100], true)) {
      $pageSize = 25;
    }
    $sortBy = strtolower((string) ($filters['sortBy'] ?? 'started'));
    $sortDir = strtolower((string) ($filters['sortDir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
    $tenantFilterRaw = $filters['tenant_id'] ?? null;
    $tenantFilter = isset($filters['tenant_filter_id']) ? (int) $filters['tenant_filter_id'] : null;
    $scopeUserActive = !empty($filters['scope_user_active']);
    $userScopeId = (int) ($filters['user_scope_id'] ?? 0);
    $scopeStorageTenantId = $filters['scope_storage_tenant_id'] ?? null;
    $jobFilterRaw = trim((string) ($filters['job_id'] ?? ''));

    $jobRunJoin = $hasJobIdPk ? ['r.job_id', '=', 'j.job_id'] : ['r.job_id', '=', 'j.id'];
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . $rangeHours . ' hours'));
    $effectiveStartedExpr = $hasRunCreatedAtCol
      ? 'COALESCE(r.started_at, r.created_at)'
      : 'r.started_at';

    $applyScopeFilters = function ($query) use (
      $hasJobTenant,
      $tenantFilterRaw,
      $tenantFilter,
      $agentFilter,
      $q,
      $scopeUserActive,
      $userScopeId,
      $hasJobBackupUser,
      $hasAgentBackupUser,
      $scopeStorageTenantId,
      $jobFilterRaw,
      $hasJobIdPk,
      $hasJobSourceDisplay,
      $workloads,
      $hasJobSourceType,
      $hasJobAgentUuid
    ) {
      if ($hasJobTenant && $tenantFilterRaw !== null) {
        if ($tenantFilterRaw === 'direct') {
          $query->whereNull('j.tenant_id');
        } elseif ($tenantFilter !== null) {
          $query->where('j.tenant_id', $tenantFilter);
        }
      }
      if ($agentFilter !== '') {
        $query->where('j.agent_uuid', $agentFilter);
      }
      if ($scopeUserActive) {
        if ($hasJobBackupUser) {
          if ($hasAgentBackupUser) {
            $query->where(function ($scoped) use ($userScopeId) {
              $scoped->where('j.backup_user_id', $userScopeId)
                ->orWhere(function ($legacy) use ($userScopeId) {
                  $legacy->whereNull('j.backup_user_id')
                    ->where('a.backup_user_id', $userScopeId);
                });
            });
          } else {
            $query->where('j.backup_user_id', $userScopeId);
          }
        } elseif ($hasAgentBackupUser) {
          if ($scopeStorageTenantId === null) {
            $query->whereNull('a.tenant_id');
          } else {
            $query->where('a.tenant_id', $scopeStorageTenantId);
          }
        }
      }
      if ($jobFilterRaw !== '') {
        if ($hasJobIdPk && UuidBinary::isUuid($jobFilterRaw)) {
          $query->whereRaw('j.job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobFilterRaw)));
        } elseif (!$hasJobIdPk && ctype_digit($jobFilterRaw)) {
          $query->where('j.id', (int) $jobFilterRaw);
        }
      }
      if (!empty($workloads)) {
        $query->where(function ($w) use ($workloads, $hasJobSourceType, $hasJobAgentUuid) {
          foreach ($workloads as $workload) {
            $w->orWhere(function ($inner) use ($workload, $hasJobSourceType, $hasJobAgentUuid) {
              self::applyWorkloadSqlFilter($inner, $workload, $hasJobSourceType, $hasJobAgentUuid);
            });
          }
        });
      }
      if ($q !== '') {
        $like = '%' . $q . '%';
        $query->where(function ($w) use ($like, $hasJobSourceDisplay) {
          $w->where('j.name', 'like', $like)
            ->orWhere('a.hostname', 'like', $like)
            ->orWhereExists(function ($sub) use ($like) {
              $sub->select(Capsule::raw('1'))
                ->from('s3_backup_users as bu')
                ->whereRaw('bu.id = COALESCE(j.backup_user_id, a.backup_user_id)')
                ->where('bu.username', 'like', $like);
            });
          if ($hasJobSourceDisplay) {
            $w->orWhere('j.source_display_name', 'like', $like);
          }
        });
      }

      return $query;
    };

    $applyTimeCutoff = function ($query) use ($cutoff, $effectiveStartedExpr) {
      $query->whereRaw($effectiveStartedExpr . ' >= ?', [$cutoff]);
    };

    $buildBase = function () use (
      $jobRunJoin,
      $clientId,
      $statuses,
      $applyScopeFilters,
      $applyTimeCutoff
    ) {
      $base = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
        ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted');

      $applyTimeCutoff($base);
      $applyScopeFilters($base);
      if (!empty($statuses)) {
        $base->whereIn('r.status', $statuses);
      }

      return $base;
    };

    $facetQuery = Capsule::table('s3_cloudbackup_runs as r')
      ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
      ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
      ->where('j.client_id', $clientId)
      ->where('j.status', '!=', 'deleted');
    $applyTimeCutoff($facetQuery);
    $applyScopeFilters($facetQuery);

    $statusCounts = [];
    foreach ($facetQuery->groupBy('r.status')->get([Capsule::raw('r.status'), Capsule::raw('COUNT(*) as cnt')]) as $row) {
      $statusCounts[strtolower((string) $row->status)] = (int) $row->cnt;
    }

    $total = (int) $buildBase()->count();

    $sortColMap = [
      'started' => Capsule::raw($effectiveStartedExpr),
      'finished' => 'r.finished_at',
      'status' => 'r.status',
      'job' => 'j.name',
      'agent' => 'a.hostname',
      'source' => 'a.hostname',
    ];
    $sortCol = $sortColMap[$sortBy] ?? Capsule::raw($effectiveStartedExpr);

    $runIdSelect = $hasRunIdCol
      ? Capsule::raw('BIN_TO_UUID(r.run_id) as run_id')
      : Capsule::raw('r.id as run_id');
    $jobIdSelect = $hasJobIdPk
      ? Capsule::raw('BIN_TO_UUID(j.job_id) as job_id')
      : Capsule::raw('j.id as job_id');

    $userExpr = 'NULL as backup_user_id';
    if ($hasJobBackupUser && $hasAgentBackupUser) {
      $userExpr = 'COALESCE(j.backup_user_id, a.backup_user_id) as backup_user_id';
    } elseif ($hasJobBackupUser) {
      $userExpr = 'j.backup_user_id as backup_user_id';
    } elseif ($hasAgentBackupUser) {
      $userExpr = 'a.backup_user_id as backup_user_id';
    }

    $rows = $buildBase()
      ->orderBy($sortCol, $sortDir)
      ->offset(($page - 1) * $pageSize)
      ->limit($pageSize)
      ->get(array_values(array_filter([
        $runIdSelect,
        $jobIdSelect,
        'r.status',
        'r.started_at',
        $hasRunCreatedAtCol ? 'r.created_at' : null,
        'r.finished_at',
        'r.trigger_type',
        'r.engine',
        'r.bytes_processed',
        'r.bytes_transferred',
        $hasStatsJsonCol ? 'r.stats_json' : null,
        $hasRunTypeCol ? 'r.run_type' : null,
        $hasErrorSummaryCol ? 'r.error_summary' : null,
        'j.name as job_name',
        $hasJobSourceType ? 'j.source_type' : null,
        $hasJobSourceDisplay ? 'j.source_display_name' : null,
        $hasJobAgentUuid ? 'j.agent_uuid as job_agent_uuid' : null,
        'a.hostname as agent_hostname',
        'a.agent_uuid',
        Capsule::raw($userExpr),
      ])));

    $userIds = [];
    foreach ($rows as $r) {
      if (!empty($r->backup_user_id)) {
        $userIds[] = (int) $r->backup_user_id;
      }
    }
    $usernameById = [];
    if (!empty($userIds)) {
      foreach (Capsule::table('s3_backup_users')->whereIn('id', array_unique($userIds))->get(['id', 'username']) as $u) {
        $usernameById[(int) $u->id] = (string) $u->username;
      }
    }

    $out = [];
    foreach ($rows as $r) {
      $sourceType = $hasJobSourceType ? (string) ($r->source_type ?? '') : '';
      $engine = (string) ($r->engine ?? 'sync');
      $agentHostname = (string) ($r->agent_hostname ?? '');
      $sourceDisplayName = $hasJobSourceDisplay ? (string) ($r->source_display_name ?? '') : '';
      $jobAgentUuid = $hasJobAgentUuid ? (string) ($r->job_agent_uuid ?? '') : (string) ($r->agent_uuid ?? '');
      $workloadCategory = self::categorizeWorkload($sourceType, $engine, $jobAgentUuid);
      $workloadLabel = self::workloadLabel($workloadCategory, $sourceType, $sourceDisplayName, $agentHostname);

      $startedAt = (string) ($r->started_at ?? '');
      if ($startedAt === '' && $hasRunCreatedAtCol && !empty($r->created_at)) {
        $startedAt = (string) $r->created_at;
      }

      $duration = '-';
      if ($startedAt !== '' && !empty($r->finished_at)) {
        $diff = max(0, strtotime((string) $r->finished_at) - strtotime($startedAt));
        if ($diff >= 3600) {
          $duration = floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
        } elseif ($diff >= 60) {
          $duration = floor($diff / 60) . 'm ' . ($diff % 60) . 's';
        } else {
          $duration = $diff . 's';
        }
      }

      $bytes = max((int) ($r->bytes_processed ?? 0), (int) ($r->bytes_transferred ?? 0));
      $scheduleSkipped = false;
      if ($hasStatsJsonCol && isset($r->stats_json)) {
        $decoded = json_decode((string) $r->stats_json, true);
        $scheduleSkipped = is_array($decoded) && !empty($decoded['ms365_schedule_skip']);
      }

      $operationType = 'Backup';
      $restoreTypes = ['restore', 'hyperv_restore', 'disk_restore'];
      if ($hasRunTypeCol && !empty($r->run_type) && in_array((string) $r->run_type, $restoreTypes, true)) {
        $operationType = 'Restore';
      } elseif ($hasStatsJsonCol && isset($r->stats_json)) {
        $decoded = json_decode((string) $r->stats_json, true);
        if (is_array($decoded)) {
          $stype = (string) ($decoded['type'] ?? '');
          if (in_array($stype, $restoreTypes, true)) {
            $operationType = 'Restore';
          }
        }
      }

      $out[] = [
        'run_id' => (string) ($r->run_id ?? ''),
        'job_id' => (string) ($r->job_id ?? ''),
        'status' => (string) $r->status,
        'schedule_skipped' => $scheduleSkipped,
        'error_summary' => $hasErrorSummaryCol ? (string) ($r->error_summary ?? '') : '',
        'started_at' => $startedAt,
        'finished_at' => (string) ($r->finished_at ?? ''),
        'trigger_type' => (string) ($r->trigger_type ?? ''),
        'engine' => $engine,
        'operation_type' => $operationType,
        'job_name' => (string) ($r->job_name ?? ''),
        'source_type' => $sourceType,
        'source_display_name' => $sourceDisplayName,
        'workload_category' => $workloadCategory,
        'workload_label' => $workloadLabel,
        'agent_hostname' => $agentHostname,
        'agent_uuid' => (string) ($r->agent_uuid ?? ''),
        'username' => !empty($r->backup_user_id) ? ($usernameById[(int) $r->backup_user_id] ?? '') : '',
        'duration' => $duration,
        'size_formatted' => $bytes > 0 ? HelperController::formatSizeUnitsPlain($bytes) : '-',
      ];
    }

    return [
      'total' => $total,
      'rows' => $out,
      'facets' => ['statusCounts' => $statusCounts],
    ];
  }

  public static function categorizeWorkload(string $sourceType, string $engine, string $agentUuid): string
  {
    $sourceType = strtolower(trim($sourceType));
    $engine = strtolower(trim($engine));
    if ($sourceType === self::WORKLOAD_MS365 || $engine === self::WORKLOAD_MS365) {
      return self::WORKLOAD_MS365;
    }
    if ($sourceType === self::WORKLOAD_LOCAL_AGENT || $agentUuid !== '') {
      return self::WORKLOAD_LOCAL_AGENT;
    }

    return self::WORKLOAD_CLOUD_TO_CLOUD;
  }

  public static function workloadLabel(
    string $workloadCategory,
    string $sourceType,
    string $sourceDisplayName,
    string $agentHostname
  ): string {
    switch ($workloadCategory) {
      case self::WORKLOAD_MS365:
        return 'Microsoft 365';
      case self::WORKLOAD_LOCAL_AGENT:
        return $agentHostname !== '' ? $agentHostname : 'Local agent';
      case self::WORKLOAD_CLOUD_TO_CLOUD:
        if ($sourceDisplayName !== '') {
          return $sourceDisplayName;
        }

        return self::sourceTypeLabel($sourceType);
      default:
        return '-';
    }
  }

  public static function sourceTypeLabel(string $sourceType): string
  {
    $map = [
      's3_compatible' => 'S3-compatible',
      'aws' => 'Amazon S3',
      'sftp' => 'SFTP',
      'google_drive' => 'Google Drive',
      'dropbox' => 'Dropbox',
      'smb' => 'SMB',
      'nas' => 'NAS',
      'local_agent' => 'Local agent',
      'ms365' => 'Microsoft 365',
    ];
    $key = strtolower(trim($sourceType));

    return $map[$key] ?? ($key !== '' ? ucwords(str_replace('_', ' ', $key)) : 'Cloud source');
  }

  /**
   * @param mixed $query
   */
  private static function applyWorkloadSqlFilter($query, string $workload, bool $hasJobSourceType, bool $hasJobAgentUuid): void
  {
    switch ($workload) {
      case self::WORKLOAD_MS365:
        $query->where(function ($w) use ($hasJobSourceType) {
          $w->where('r.engine', self::WORKLOAD_MS365);
          if ($hasJobSourceType) {
            $w->orWhere('j.source_type', self::WORKLOAD_MS365)
              ->orWhere('j.engine', self::WORKLOAD_MS365);
          } else {
            $w->orWhere('j.engine', self::WORKLOAD_MS365);
          }
        });
        break;
      case self::WORKLOAD_LOCAL_AGENT:
        $query->where(function ($w) use ($hasJobSourceType, $hasJobAgentUuid) {
          if ($hasJobSourceType) {
            $w->where('j.source_type', self::WORKLOAD_LOCAL_AGENT);
          }
          if ($hasJobAgentUuid) {
            $w->orWhere(function ($agentScoped) {
              $agentScoped->whereNotNull('j.agent_uuid')
                ->where('j.agent_uuid', '!=', '');
            });
          }
          if (!$hasJobSourceType && !$hasJobAgentUuid) {
            $w->whereRaw('1 = 0');
          }
        });
        break;
      case self::WORKLOAD_CLOUD_TO_CLOUD:
        $query->where(function ($w) use ($hasJobSourceType, $hasJobAgentUuid) {
          $w->where(function ($excludeMs365) use ($hasJobSourceType) {
            $excludeMs365->where('r.engine', '!=', self::WORKLOAD_MS365);
            if ($hasJobSourceType) {
              $excludeMs365->where(function ($notMs365) {
                $notMs365->whereNull('j.source_type')
                  ->orWhere('j.source_type', '!=', self::WORKLOAD_MS365);
              })->where(function ($notMs365Engine) {
                $notMs365Engine->whereNull('j.engine')
                  ->orWhere('j.engine', '!=', self::WORKLOAD_MS365);
              });
            }
          });
          if ($hasJobSourceType) {
            $w->where(function ($notLocal) {
              $notLocal->whereNull('j.source_type')
                ->orWhere('j.source_type', '!=', self::WORKLOAD_LOCAL_AGENT);
            });
          }
          if ($hasJobAgentUuid) {
            $w->where(function ($noAgent) {
              $noAgent->whereNull('j.agent_uuid')
                ->orWhere('j.agent_uuid', '=', '');
            });
          }
        });
        break;
    }
  }
}
