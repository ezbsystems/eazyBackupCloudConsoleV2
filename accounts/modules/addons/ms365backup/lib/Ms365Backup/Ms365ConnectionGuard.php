<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Detects revoked Entra consent / invalid app credentials and marks tenant records for reconnect.
 */
final class Ms365ConnectionGuard
{
    public const RECONNECT_MESSAGE = 'Microsoft 365 access was removed or expired. Reconnect your organization to continue.';

    public static function isReconnectRequired(\Throwable $e): bool
    {
        if ($e instanceof Ms365ReconnectRequiredException) {
            return true;
        }

        if ($e instanceof GraphApiException && $e->isAuthenticationFailure()) {
            return true;
        }

        $msg = strtolower($e->getMessage());

        if (preg_match('/\baadsts70001[16]\b/i', $msg)) {
            return true;
        }

        if (str_contains($msg, 'invalid_client')
            || str_contains($msg, 'unauthorized_client')
            || str_contains($msg, 'failed to obtain access token')
            || str_contains($msg, 'identity of the calling application could not be established')) {
            return true;
        }

        return false;
    }

    public static function markReconnectRequired(int $tenantRecordId, \Throwable $e): void
    {
        if ($tenantRecordId <= 0) {
            return;
        }

        TenantRecordRepository::updateHealth(
            $tenantRecordId,
            'action_required',
            self::RECONNECT_MESSAGE,
        );
        Ms365CustomerError::log('markReconnectRequired', $e);
    }

    public static function handleIfReconnectRequired(int $tenantRecordId, \Throwable $e): bool
    {
        if ($tenantRecordId <= 0 || !self::isReconnectRequired($e)) {
            return false;
        }

        self::markReconnectRequired($tenantRecordId, $e);

        return true;
    }

    /**
     * Mark reconnect state when applicable, then throw Ms365ReconnectRequiredException.
     *
     * @throws Ms365ReconnectRequiredException
     * @throws \Throwable
     */
    public static function throwIfReconnectRequired(int $tenantRecordId, \Throwable $e): void
    {
        if (self::handleIfReconnectRequired($tenantRecordId, $e)) {
            throw new Ms365ReconnectRequiredException(self::RECONNECT_MESSAGE, 0, $e);
        }

        throw $e;
    }

    public static function tenantRecordIdForBackupUser(int $clientId, int $backupUserId): int
    {
        $record = $backupUserId > 0
            ? TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
            : TenantRecordRepository::getPrimaryForClient($clientId);

        return $record !== null ? (int) $record['id'] : 0;
    }
}
