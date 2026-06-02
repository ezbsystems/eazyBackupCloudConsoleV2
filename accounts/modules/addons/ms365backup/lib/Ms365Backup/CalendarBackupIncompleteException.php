<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Raised when one or more calendars did not complete inventory (strict completeness policy).
 */
final class CalendarBackupIncompleteException extends \RuntimeException
{
    /**
     * @param list<array<string, mixed>> $incompleteCalendars
     * @param list<array<string, mixed>> $completedCalendars
     */
    public function __construct(
        string $message,
        public readonly array $incompleteCalendars = [],
        public readonly array $completedCalendars = [],
    ) {
        parent::__construct($message);
    }
}
