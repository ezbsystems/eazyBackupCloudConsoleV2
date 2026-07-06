<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Centralized addon settings and cb_settings key-value store.
 */
class Settings
{
    private const MODULE = 'cometbilling';

    /** WHMCS password-type addon fields that must be decrypted. */
    private const ENCRYPTED_FIELDS = ['PortalToken'];

    /**
     * Load addon module settings from tbladdonmodules with decrypt() for password fields.
     *
     * @return array<string, string>
     */
    public static function getAddonSettings(): array
    {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', self::MODULE)
            ->pluck('value', 'setting')
            ->toArray();

        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (!empty($settings[$field])) {
                $settings[$field] = self::maybeDecrypt((string) $settings[$field]);
            }
        }

        return $settings;
    }

    /**
     * Decrypt WHMCS-encrypted values; keep plaintext credentials unchanged.
     * decrypt() on a plaintext token does not throw — it returns binary garbage.
     */
    private static function maybeDecrypt(string $value): string
    {
        if ($value === '' || !function_exists('decrypt')) {
            return $value;
        }

        try {
            $decrypted = (string) decrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }

        if (self::isUsableCredential($decrypted)) {
            return $decrypted;
        }

        return $value;
    }

    /**
     * True when a string looks like a real API token/password, not decrypt garbage.
     */
    private static function isUsableCredential(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            return false;
        }
        // Reject control chars (invalid in HTTP Authorization headers)
        if (preg_match('/[\x00-\x08\x0E-\x1F\x7F]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Get a single addon setting value.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $settings = self::getAddonSettings();
        return $settings[$key] ?? $default;
    }

    /**
     * Read a value from cb_settings.
     */
    public static function getKv(string $key, ?string $default = null): ?string
    {
        self::ensureSettingsTable();

        $row = Capsule::table('cb_settings')->where('k', $key)->first();
        return $row ? (string) $row->v : $default;
    }

    /**
     * Write a value to cb_settings.
     */
    public static function setKv(string $key, string $value): void
    {
        self::ensureSettingsTable();

        Capsule::table('cb_settings')->updateOrInsert(
            ['k' => $key],
            ['v' => self::sanitizeForDb($value)]
        );
    }

    /**
     * Strip invalid UTF-8 so cb_settings writes do not fail on collation conversion.
     */
    private static function sanitizeForDb(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($clean === false) {
            return preg_replace('/[^\x20-\x7E\r\n\t]/', '?', $value) ?? $value;
        }

        return $clean;
    }

    /**
     * Get portal client configuration as an array.
     *
     * @return array{baseUrl: string, authType: string, token: string, timeout: int}
     */
    public static function getPortalConfig(): array
    {
        $settings = self::getAddonSettings();

        $timeout = (int) ($settings['HttpTimeoutSeconds'] ?? 180);
        if ($timeout < 60) {
            $timeout = 60;
        }

        return [
            'baseUrl' => $settings['PortalBaseUrl'] ?? 'https://account.cometbackup.com',
            'authType' => $settings['PortalAuthType'] ?? 'token',
            'token' => $settings['PortalToken'] ?? '',
            'timeout' => $timeout,
        ];
    }

    /**
     * Mark a background sync job as running.
     */
    public static function markJobRunning(string $job): void
    {
        self::setKv($job . '_running', '1');
        self::setKv($job . '_started_at', gmdate('Y-m-d H:i:s'));
    }

    /**
     * Mark a background sync job as finished and store result.
     */
    public static function markJobFinished(string $job, string $status, string $message): void
    {
        self::setKv($job . '_running', '0');
        self::setKv('last_' . $job . '_at', gmdate('Y-m-d H:i:s'));
        self::setKv('last_' . $job . '_status', $status);
        self::setKv('last_' . $job . '_message', $message);
    }

    public static function isJobRunning(string $job): bool
    {
        if (self::getKv($job . '_running') !== '1') {
            return false;
        }

        $started = self::getJobStartedAt($job);
        if ($started) {
            $startedTs = strtotime($started . ' UTC');
            if ($startedTs !== false && (time() - $startedTs) > 1200) {
                // Stale after 20 minutes — job likely crashed without finishing
                self::setKv($job . '_running', '0');
                return false;
            }
        }

        return true;
    }

    public static function getJobStartedAt(string $job): ?string
    {
        return self::getKv($job . '_started_at');
    }

    private static function ensureSettingsTable(): void
    {
        if (!Capsule::schema()->hasTable('cb_settings')) {
            Capsule::schema()->create('cb_settings', function ($table) {
                $table->string('k', 64)->primary();
                $table->text('v');
            });
        }
    }
}
