<?php
declare(strict_types=1);

namespace Ms365Backup;

enum PaginationDuplicatePageMode
{
    /** Throw GraphPaginationException when a page contains only duplicate item IDs. */
    case Strict;

    /** Stop pagination and set PaginationOutcome::stoppedOnDuplicatePage (calendar normal scan). */
    case DetectDuplicateOnly;
}
