<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Delta token expired or invalid; caller should clear sync state and run a full resync.
 */
final class GraphDeltaResetException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 410,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
