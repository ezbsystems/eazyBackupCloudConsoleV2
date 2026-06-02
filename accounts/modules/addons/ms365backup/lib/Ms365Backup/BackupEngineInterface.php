<?php
declare(strict_types=1);

namespace Ms365Backup;

interface BackupEngineInterface
{
    public function name(): string;

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool;

    /**
     * @return array<string, mixed> stats for manifest engines section
     */
    public function run(BackupEngineContext $ctx): array;
}
