<?php
declare(strict_types=1);

namespace Ms365Backup;

final class DeltaPaginationOutcome
{
    public string $deltaLink = '';
    public int $pages = 0;
    public int $totalItems = 0;
}
