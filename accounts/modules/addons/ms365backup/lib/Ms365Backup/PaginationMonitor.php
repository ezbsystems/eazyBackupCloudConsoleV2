<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Controls logging and safety limits for Graph API pagination.
 */
final class PaginationMonitor
{
    public const DEFAULT_MAX_PAGES = 500;

    public const DEFAULT_MAX_EMPTY_PAGES_WITH_NEXT = 3;

    public function __construct(
        public readonly ?ProgressLogger $logger = null,
        public readonly string $context = '',
        public readonly int $maxPages = self::DEFAULT_MAX_PAGES,
        public readonly int $maxEmptyPagesWithNext = self::DEFAULT_MAX_EMPTY_PAGES_WITH_NEXT,
        public readonly PaginationDuplicatePageMode $duplicatePageMode = PaginationDuplicatePageMode::Strict,
    ) {
    }

    public static function forBackup(ProgressLogger $logger, string $context): self
    {
        return new self($logger, $context, self::DEFAULT_MAX_PAGES, self::DEFAULT_MAX_EMPTY_PAGES_WITH_NEXT);
    }

    /** Normal calendar /events inventory pass — detect duplicate page without throwing. */
    public static function forCalendarNormalScan(ProgressLogger $logger, string $context): self
    {
        return new self(
            $logger,
            $context,
            self::DEFAULT_MAX_PAGES,
            self::DEFAULT_MAX_EMPTY_PAGES_WITH_NEXT,
            PaginationDuplicatePageMode::DetectDuplicateOnly,
        );
    }

    /** Partitioned calendar scan — duplicate page is handled by subdividing the partition. */
    public static function forCalendarPartitionScan(ProgressLogger $logger, string $context): self
    {
        return new self($logger, $context, self::DEFAULT_MAX_PAGES, self::DEFAULT_MAX_EMPTY_PAGES_WITH_NEXT);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }
        $context['pagination_context'] = $this->context;
        $this->logger->log($level, $message, $context);
    }
}
