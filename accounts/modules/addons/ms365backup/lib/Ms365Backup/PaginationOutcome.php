<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Records how a Graph paginate() session ended.
 */
final class PaginationOutcome
{
    public bool $completedNaturally = false;

    public bool $stoppedOnDuplicatePage = false;

    public int $pages = 0;

    public int $totalItems = 0;

    public function isCleanCompletion(): bool
    {
        return $this->completedNaturally;
    }

    public function needsFallbackInventory(): bool
    {
        return $this->stoppedOnDuplicatePage;
    }
}
