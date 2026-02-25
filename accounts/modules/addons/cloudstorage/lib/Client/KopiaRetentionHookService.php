<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Hooks to decide when to enqueue retention/maintenance ops from run outcomes
 * and when to retire sources on job/agent deletion.
 */
class KopiaRetentionHookService
{
    private const KOPIA_ENGINES = ['kopia', 'disk_image', 'hyperv'];

    /**
     * Whether to enqueue retention/maintenance ops after a run completes successfully.
     * true: local_agent + kopia-family engine.
     *
     * @param string $status Run status (e.g. success, warning)
     * @param string $sourceType Job source_type (e.g. local_agent, aws)
     * @param string $engine Job engine (e.g. kopia, sync)
     */
    public static function shouldEnqueueFromRun(string $status, string $sourceType, string $engine): bool
    {
        if (!in_array(strtolower($status), ['success', 'warning'], true)) {
            return false;
        }
        if (strtolower(trim($sourceType)) !== 'local_agent') {
            return false;
        }
        return in_array(strtolower(trim($engine)), self::KOPIA_ENGINES, true);
    }

    /**
     * Whether to retire sources when a job is deleted.
     * true: local_agent + kopia-family engine.
     *
     * @param string $sourceType Job source_type
     * @param string $engine Job engine
     */
    public static function shouldRetireOnJobDelete(string $sourceType, string $engine): bool
    {
        if (strtolower(trim($sourceType)) !== 'local_agent') {
            return false;
        }
        return in_array(strtolower(trim($engine)), self::KOPIA_ENGINES, true);
    }
}
