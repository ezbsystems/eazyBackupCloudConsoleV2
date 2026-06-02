<?php
declare(strict_types=1);

namespace Ms365Backup;

final class AccessResult
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_ERROR = 'error';
    public const STATUS_UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $status,
        public readonly string $reason,
        public readonly bool $skippable,
    ) {
    }

    public function isProblematic(): bool
    {
        return $this->status === self::STATUS_UNAVAILABLE
            || $this->status === self::STATUS_LOCKED
            || $this->status === self::STATUS_ERROR;
    }
}
