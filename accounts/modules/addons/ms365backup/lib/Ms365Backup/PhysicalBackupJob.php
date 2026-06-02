<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * One physical backup target after dedup (maps to one ms365_backup_runs row).
 */
final class PhysicalBackupJob
{
    public const STATUS_RUNNABLE = 'runnable';
    public const STATUS_DEFERRED = 'deferred';

    /**
     * @param array<string, mixed> $primaryResource
     * @param list<array<string, mixed>> $logicalSources
     */
    public function __construct(
        public readonly string $physicalKey,
        public readonly array $primaryResource,
        public readonly array $logicalSources,
        public readonly BackupScope $scope,
        public readonly string $engineStatus,
        public readonly string $deferReason = '',
    ) {
    }

    public function resourceId(): string
    {
        return (string) ($this->primaryResource['id'] ?? '');
    }

    public function resourceType(): string
    {
        return (string) ($this->primaryResource['resource_type'] ?? '');
    }

    public function graphId(): string
    {
        return (string) ($this->primaryResource['graph_id'] ?? '');
    }

    public function displayName(): string
    {
        return (string) ($this->primaryResource['display_name'] ?? $this->resourceId());
    }

    public function email(): string
    {
        return (string) ($this->primaryResource['email'] ?? '');
    }

    public function isRunnable(): bool
    {
        return $this->engineStatus === self::STATUS_RUNNABLE;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'physical_key' => $this->physicalKey,
            'primary_resource' => $this->primaryResource,
            'logical_sources' => $this->logicalSources,
            'scope' => $this->scope->toArray(),
            'engine_status' => $this->engineStatus,
            'defer_reason' => $this->deferReason,
        ];
    }

    /**
     * @param array<string, mixed> $run
     */
    public static function fromRunRow(array $run): self
    {
        $logicalRaw = $run['logical_sources_json'] ?? '[]';
        $logical = is_string($logicalRaw) ? json_decode($logicalRaw, true) : $logicalRaw;
        if (!is_array($logical)) {
            $logical = [];
        }

        $resourceType = (string) ($run['resource_type'] ?? TenantResource::TYPE_USER);
        $graphId = (string) ($run['graph_id'] ?? $run['user_id'] ?? '');
        $resourceId = (string) ($run['resource_id'] ?? '');
        if ($resourceId === '' && $graphId !== '') {
            $resourceId = TenantResource::makeId($resourceType, $graphId);
        }

        $primary = [
            'id' => $resourceId,
            'resource_type' => $resourceType,
            'graph_id' => $graphId,
            'display_name' => (string) ($run['user_display_name'] ?? ''),
            'email' => (string) ($run['user_upn'] ?? ''),
        ];

        $physicalKey = (string) ($run['physical_key'] ?? '');
        if ($physicalKey === '' && $graphId !== '') {
            $physicalKey = 'user:' . $graphId;
        }

        return new self(
            $physicalKey,
            $primary,
            $logical,
            BackupScope::fromLegacyRun($run),
            self::STATUS_RUNNABLE,
        );
    }
}
