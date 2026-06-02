<?php
declare(strict_types=1);

namespace Ms365Backup;

final class ResourceUnavailableException extends \RuntimeException
{
    public function __construct(
        public readonly string $resourceType,
        public readonly AccessResult $accessResult,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($accessResult->reason !== ''
            ? $accessResult->reason
            : 'Resource unavailable: ' . $resourceType, 0, $previous);
    }

    public static function fromGraph(string $resourceType, GraphApiException $e): self
    {
        return new self($resourceType, ResourceAccessClassifier::classify($e), $e);
    }
}
