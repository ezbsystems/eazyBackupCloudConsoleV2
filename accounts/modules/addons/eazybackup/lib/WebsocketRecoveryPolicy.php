<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Eazybackup;

/**
 * Pure policy for Comet websocket worker reconnect, recovery verification, and alerting.
 */
final class WebsocketRecoveryPolicy
{
    public const ALERT_IDLE_RECYCLE = 'idle-recycle';
    public const ALERT_CONNECT_FAILURE = 'connect-failure';
    public const ALERT_RECOVERY_FAILED = 'recovery-failed';

    /**
     * @return array{
     *   idle_alert_after:int,
     *   recovery_deadline_secs:int,
     *   connect_backoff_base:int,
     *   connect_backoff_max:int,
     *   idle_reconnect_delay:int,
     *   connect_alert_after:int
     * }
     */
    public static function configFromEnv(): array
    {
        return [
            'idle_alert_after' => max(1, (int)(getenv('EB_WS_IDLE_ALERT_AFTER') ?: 2)),
            'recovery_deadline_secs' => max(10, (int)(getenv('EB_WS_RECOVERY_DEADLINE') ?: 60)),
            'connect_backoff_base' => max(1, (int)(getenv('EB_WS_CONNECT_BACKOFF_BASE') ?: 2)),
            'connect_backoff_max' => max(2, (int)(getenv('EB_WS_CONNECT_BACKOFF_MAX') ?: 32)),
            'idle_reconnect_delay' => max(1, (int)(getenv('EB_WS_IDLE_RECONNECT_DELAY') ?: 2)),
            'connect_alert_after' => max(1, (int)(getenv('EB_WS_CONNECT_ALERT_AFTER') ?: 3)),
        ];
    }

    /**
     * @return array{
     *   consecutive_idle_recycles:int,
     *   consecutive_connect_failures:int,
     *   session_healthy:bool,
     *   authenticated_at:int,
     *   last_message_at:int,
     *   last_recycle_at:int,
     *   last_recovery_at:int,
     *   total_idle_recycles:int,
     *   last_disconnect_reason:string
     * }
     */
    public static function initialState(): array
    {
        return [
            'consecutive_idle_recycles' => 0,
            'consecutive_connect_failures' => 0,
            'session_healthy' => false,
            'authenticated_at' => 0,
            'last_message_at' => 0,
            'last_recycle_at' => 0,
            'last_recovery_at' => 0,
            'total_idle_recycles' => 0,
            'last_disconnect_reason' => '',
        ];
    }

    public static function onAuthenticated(array &$state, int $now): void
    {
        $state['authenticated_at'] = $now;
        $state['session_healthy'] = false;
    }

    /**
     * @return array{recovery_complete:bool}
     */
    public static function onStreamEvent(array &$state, string $typeString, int $now): array
    {
        $state['last_message_at'] = $now;
        if ($state['session_healthy']) {
            return ['recovery_complete' => false];
        }

        if ($typeString === '' || str_starts_with($typeString, 'SEVT_META_')) {
            $state['session_healthy'] = true;
            $state['consecutive_idle_recycles'] = 0;
            $state['consecutive_connect_failures'] = 0;
            $state['last_recovery_at'] = $now;
            return ['recovery_complete' => true];
        }

        if (str_starts_with($typeString, 'SEVT_')) {
            $state['session_healthy'] = true;
            $state['consecutive_idle_recycles'] = 0;
            $state['consecutive_connect_failures'] = 0;
            $state['last_recovery_at'] = $now;
            return ['recovery_complete' => true];
        }

        return ['recovery_complete' => false];
    }

    /**
     * @return array{
     *   log:string,
     *   should_alert:bool,
     *   alert_kind:?string,
     *   alert_reason:?string,
     *   reconnect_delay:int
     * }
     */
    public static function onIdleRecycle(array &$state, int $now, int $idleSecs, array $config): array
    {
        $state['total_idle_recycles']++;
        $state['last_recycle_at'] = $now;
        $state['consecutive_idle_recycles']++;
        $state['session_healthy'] = false;
        $state['last_disconnect_reason'] = "No application messages received for {$idleSecs}s";

        $shouldAlert = $state['consecutive_idle_recycles'] >= $config['idle_alert_after'];

        return [
            'log' => 'idle-recycle',
            'should_alert' => $shouldAlert,
            'alert_kind' => $shouldAlert ? self::ALERT_IDLE_RECYCLE : null,
            'alert_reason' => $shouldAlert ? $state['last_disconnect_reason'] : null,
            'reconnect_delay' => $config['idle_reconnect_delay'],
        ];
    }

    /**
     * @return array{
     *   log:string,
     *   should_alert:bool,
     *   alert_kind:?string,
     *   alert_reason:string,
     *   reconnect_delay:int
     * }
     */
    public static function onConnectFailure(array &$state, int $now, string $reason, array $config): array
    {
        $state['consecutive_connect_failures']++;
        $state['session_healthy'] = false;
        $state['last_disconnect_reason'] = $reason;

        $failures = $state['consecutive_connect_failures'];
        $delay = min(
            $config['connect_backoff_max'],
            (int)($config['connect_backoff_base'] ** min($failures, 6))
        );
        $shouldAlert = $failures >= $config['connect_alert_after'];

        return [
            'log' => 'connect-failure',
            'should_alert' => $shouldAlert,
            'alert_kind' => $shouldAlert ? self::ALERT_CONNECT_FAILURE : null,
            'alert_reason' => $reason,
            'reconnect_delay' => max(1, $delay),
        ];
    }

    /**
     * @return array{should_alert:bool,alert_kind:string,alert_reason:string}|null
     */
    public static function recoveryDeadlineExceeded(array $state, int $now, array $config): ?array
    {
        if ($state['session_healthy'] || $state['authenticated_at'] <= 0) {
            return null;
        }

        // A previous healthy session that later idle-recycles must not look like
        // a failed post-auth recovery: last_message_at is still >= authenticated_at.
        if ((int)$state['last_message_at'] >= (int)$state['authenticated_at']) {
            return null;
        }

        $elapsed = $now - $state['authenticated_at'];
        if ($elapsed < $config['recovery_deadline_secs']) {
            return null;
        }

        return [
            'should_alert' => true,
            'alert_kind' => self::ALERT_RECOVERY_FAILED,
            'alert_reason' => 'No stream event received within '
                . $config['recovery_deadline_secs']
                . 's after authentication',
        ];
    }

    public static function isApplicationMessageIdleTimeout(\Throwable $e): bool
    {
        $cur = $e;
        while ($cur !== null) {
            if ($cur instanceof \Amp\TimeoutException) {
                $msg = $cur->getMessage();
                if (str_contains($msg, 'No application messages received for')
                    || str_contains($msg, 'No websocket frames received for')) {
                    return true;
                }
            }
            $msg = $cur->getMessage();
            if (str_contains($msg, 'No application messages received for')
                || str_contains($msg, 'No websocket frames received for')) {
                return true;
            }
            $cur = $cur->getPrevious();
        }

        return false;
    }

    public static function isAuthTimeout(\Throwable $e): bool
    {
        $cur = $e;
        while ($cur !== null) {
            $msg = $cur->getMessage();
            if (str_contains($msg, 'Timed out waiting for auth response')) {
                return true;
            }
            $cur = $cur->getPrevious();
        }

        return false;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function persistencePayload(string $profile, array $state, int $now): array
    {
        return [
            'profile' => $profile,
            'updated_at' => $now,
            'updated_at_utc' => gmdate('Y-m-d H:i:s', $now) . ' UTC',
            'session_healthy' => (bool)$state['session_healthy'],
            'last_message_at' => (int)$state['last_message_at'],
            'last_message_at_utc' => ((int)$state['last_message_at'] > 0)
                ? gmdate('Y-m-d H:i:s', (int)$state['last_message_at']) . ' UTC'
                : null,
            'last_recovery_at' => (int)$state['last_recovery_at'],
            'last_recycle_at' => (int)$state['last_recycle_at'],
            'consecutive_idle_recycles' => (int)$state['consecutive_idle_recycles'],
            'consecutive_connect_failures' => (int)$state['consecutive_connect_failures'],
            'total_idle_recycles' => (int)$state['total_idle_recycles'],
            'last_disconnect_reason' => (string)$state['last_disconnect_reason'],
        ];
    }

    /**
     * @param array<string,int|float|string|null> $stats
     */
    public static function formatDiagnostics(array $stats): string
    {
        $parts = [];
        foreach ($stats as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key . '=' . $value;
        }

        return implode(', ', $parts);
    }
}
