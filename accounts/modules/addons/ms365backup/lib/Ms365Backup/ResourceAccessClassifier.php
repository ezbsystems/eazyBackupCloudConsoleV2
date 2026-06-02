<?php
declare(strict_types=1);

namespace Ms365Backup;

final class ResourceAccessClassifier
{
    public static function classify(GraphApiException $e): AccessResult
    {
        $msg = strtolower($e->getMessage());
        $inner = strtolower($e->innerErrorCode);
        $code = strtolower($e->errorCode);

        if ($e->statusCode === 423 || $inner === 'resourcelocked') {
            return new AccessResult(
                AccessResult::STATUS_LOCKED,
                $e->getMessage(),
                true,
            );
        }

        if ($code === 'notallowed' && (
            str_contains($msg, 'blocked')
            || str_contains($msg, 'access to this site')
            || $inner === 'resourcelocked'
        )) {
            return new AccessResult(
                AccessResult::STATUS_LOCKED,
                $e->getMessage(),
                true,
            );
        }

        if ($e->statusCode === 404 && (
            str_contains($msg, 'mailbox is either inactive')
            || str_contains($msg, 'soft-deleted')
            || str_contains($msg, 'hosted on-premise')
            || str_contains($msg, 'mailbox')
        )) {
            return new AccessResult(
                AccessResult::STATUS_UNAVAILABLE,
                $e->getMessage(),
                true,
            );
        }

        if ($e->statusCode === 404 && str_contains($msg, 'user was not found')) {
            return new AccessResult(
                AccessResult::STATUS_UNAVAILABLE,
                $e->getMessage(),
                true,
            );
        }

        return new AccessResult(
            AccessResult::STATUS_ERROR,
            $e->getMessage(),
            false,
        );
    }

    public static function fromThrowable(\Throwable $e): ?AccessResult
    {
        if ($e instanceof ResourceUnavailableException) {
            return $e->accessResult;
        }
        if ($e instanceof GraphApiException) {
            $result = self::classify($e);
            return $result->skippable ? $result : null;
        }
        if ($e->getPrevious() instanceof GraphApiException) {
            return self::fromThrowable($e->getPrevious());
        }
        return null;
    }

    public static function available(): AccessResult
    {
        return new AccessResult(AccessResult::STATUS_AVAILABLE, '', false);
    }
}
