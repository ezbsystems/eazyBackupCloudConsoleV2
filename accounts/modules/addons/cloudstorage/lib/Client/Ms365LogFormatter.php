<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Maps ms365_backup_log_lines rows into e3 client-area log structures.
 */
final class Ms365LogFormatter
{
    /**
     * @param array<string, mixed> $line
     * @param array<string, mixed> $childRun
     */
    public static function workloadLabel(array $childRun): string
    {
        $type = (string) ($childRun['resource_type'] ?? 'workload');
        $name = trim((string) ($childRun['user_display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($childRun['physical_key'] ?? ''));
        }

        return $name !== '' ? $type . ': ' . $name : $type;
    }

    /**
     * @param array<string, mixed> $line
     * @param array<string, mixed> $childRun
     */
    public static function formatMessage(array $line, array $childRun): string
    {
        $prefix = self::workloadLabel($childRun);
        $message = CustomerFacingTextSanitizer::scrubLogMessage(trim((string) ($line['message'] ?? '')));
        if ($message === '') {
            return '[' . $prefix . ']';
        }

        return '[' . $prefix . '] ' . $message;
    }

    /**
     * @param array<string, mixed> $line
     * @param array<string, mixed> $childRun
     * @return array<string, mixed>
     */
    public static function toStructuredLog(array $line, array $childRun, ?\DateTimeZone $userTz = null): array
    {
        $createdAt = (int) ($line['created_at'] ?? 0);
        $ts = '';
        if ($createdAt > 0 && $userTz !== null) {
            $ts = TimezoneHelper::formatTimestamp(gmdate('Y-m-d H:i:s', $createdAt), $userTz);
        } elseif ($createdAt > 0) {
            $ts = gmdate('Y-m-d H:i:s', $createdAt);
        }

        $details = null;
        if (!empty($line['context_json'])) {
            $decoded = json_decode((string) $line['context_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $details = $decoded;
            }
        }

        return [
            'ts' => $ts,
            'level' => (string) ($line['level'] ?? 'info'),
            'code' => '',
            'message' => self::formatMessage($line, $childRun),
            'details' => $details,
        ];
    }

    /**
     * @param array<string, mixed> $line
     * @param array<string, mixed> $childRun
     * @return array<string, mixed>
     */
    public static function toEvent(array $line, array $childRun, ?\DateTimeZone $userTz = null): array
    {
        $createdAt = (int) ($line['created_at'] ?? 0);
        $ts = '';
        if ($createdAt > 0 && $userTz !== null) {
            $ts = TimezoneHelper::formatTimestamp(gmdate('Y-m-d H:i:s', $createdAt), $userTz);
        } elseif ($createdAt > 0) {
            $ts = gmdate('Y-m-d H:i:s', $createdAt);
        }

        return [
            'id' => (int) ($line['id'] ?? 0),
            'ts' => $ts,
            'type' => 'log',
            'level' => (string) ($line['level'] ?? 'info'),
            'code' => '',
            'message_id' => '',
            'params' => [],
            'message' => self::formatMessage($line, $childRun),
        ];
    }
}
