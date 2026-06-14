<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/** Logs restore progress lines to ms365_backup_log_lines (shared table). */
final class RestoreProgressLogger
{
    public function __construct(private readonly string $restoreRunId)
    {
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        if (!Capsule::schema()->hasTable('ms365_backup_log_lines')) {
            return;
        }
        Capsule::table('ms365_backup_log_lines')->insert([
            'run_id' => $this->restoreRunId,
            'level' => 'info',
            'message' => $message,
            'context_json' => $context !== [] ? json_encode($context) : null,
            'created_at' => time(),
        ]);
    }
}
