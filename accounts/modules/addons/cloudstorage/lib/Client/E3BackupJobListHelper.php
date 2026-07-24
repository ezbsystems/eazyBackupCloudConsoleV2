<?php

declare(strict_types=1);

/**
 * ROW_NUMBER() ORDER BY for last_run: prefer unfinished active runs over newer terminal rows.
 */
function e3backup_last_run_row_number_order_clause(bool $uuidPk = true): string
{
    $idCol = $uuidPk ? 'run_id' : 'id';

    return 'CASE WHEN finished_at IS NULL AND status IN (\'queued\',\'starting\',\'running\') THEN 0 ELSE 1 END, started_at DESC, '
        . $idCol . ' DESC';
}
