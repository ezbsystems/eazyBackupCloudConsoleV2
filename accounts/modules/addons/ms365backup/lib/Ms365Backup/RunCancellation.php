<?php
declare(strict_types=1);

namespace Ms365Backup;

final class RunCancellation
{
    public function __construct(private readonly string $runId)
    {
    }

    public function check(): void
    {
        if (BackupRunRepository::isCancelled($this->runId)) {
            throw new RunCancelledException('Backup cancelled by administrator');
        }
    }
}
