<?php
declare(strict_types=1);

namespace Ms365Backup;

/** Temporary agent debug logging (session 7861d7). */
final class Ms365AgentDebugLog
{
    private const PATH = '/var/www/eazybackup.ca/.cursor/debug-7861d7.log';

    /** @param array<string, mixed> $data */
    public static function write(string $location, string $message, array $data = [], string $hypothesisId = ''): void
    {
        $entry = [
            'sessionId' => '7861d7',
            'timestamp' => (int) round(microtime(true) * 1000),
            'location' => $location,
            'message' => $message,
            'data' => $data,
        ];
        if ($hypothesisId !== '') {
            $entry['hypothesisId'] = $hypothesisId;
        }
        @file_put_contents(self::PATH, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }
}
