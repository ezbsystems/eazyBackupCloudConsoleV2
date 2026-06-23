<?php
declare(strict_types=1);

namespace Ms365Backup;

use PDO;
use WHMCS\Database\Capsule;

final class ProgressLogger
{
    private ?string $filePath = null;

    public function __construct(
        private readonly string $runId,
        ?string $filePath = null,
    ) {
        $this->filePath = $filePath;
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warn(string $message, array $context = []): void
    {
        $this->log('warn', $message, $context);
    }

    /** Alias for warn() — some engine paths call warning(). */
    public function warning(string $message, array $context = []): void
    {
        $this->warn($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $now = time();
        $contextJson = $context !== [] ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : null;

        if (class_exists(Capsule::class)) {
            Capsule::table('ms365_backup_log_lines')->insert([
                'run_id' => $this->runId,
                'level' => $level,
                'message' => $message,
                'context_json' => $contextJson,
                'created_at' => $now,
            ]);
        }

        if ($this->filePath !== null) {
            $line = strtoupper($level) . ': ' . $message;
            if ($contextJson) {
                $line .= ' ' . $contextJson;
            }
            StorageLayout::ensureBase();
            @file_put_contents($this->filePath, '[' . gmdate('c') . '] ' . $line . PHP_EOL, FILE_APPEND);
        }
    }

    public static function tail(string $runId, int $sinceId = 0, int $limit = 200): array
    {
        $q = Capsule::table('ms365_backup_log_lines')
            ->where('run_id', $runId)
            ->orderBy('id', 'asc');
        if ($sinceId > 0) {
            $q->where('id', '>', $sinceId);
        }
        return $q->limit($limit)->get()->map(static fn ($r) => (array) $r)->all();
    }

    public static function logPdo(PDO $pdo, string $runId, string $level, string $message, ?string $filePath = null): void
    {
        $now = time();
        $pdo->prepare(
            'INSERT INTO ms365_backup_log_lines (run_id, level, message, context_json, created_at) VALUES (?,?,?,?,?)'
        )->execute([$runId, $level, $message, null, $now]);
        if ($filePath) {
            @file_put_contents($filePath, '[' . gmdate('c') . '] ' . strtoupper($level) . ': ' . $message . PHP_EOL, FILE_APPEND);
        }
    }
}
