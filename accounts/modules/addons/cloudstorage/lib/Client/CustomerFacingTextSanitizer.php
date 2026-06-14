<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Removes internal engine/vendor identifiers (e.g. Kopia) from customer-facing text.
 */
final class CustomerFacingTextSanitizer
{
    /** @var array<int, string> */
    private const PHRASE_PATTERNS = [
        '/\bkopia\s+backup\s+completed\b/i',
        '/\beazybackup\s+backup\s+completed\b/i',
        '/\bbackup\s+backup\s+completed\b/i',
        '/\bkopia\s+backup\s+failed\b/i',
        '/\bkopia\s+upload\b/i',
        '/\bkopia\s+snapshot\b/i',
        '/\bkopia\s+error\b/i',
        '/\bkopia\s+engine\b/i',
    ];

    /** @var array<int, string> */
    private const PHRASE_REPLACEMENTS = [
        'Backup completed',
        'Backup completed',
        'Backup completed',
        'Backup failed',
        'Upload in progress',
        'Creating snapshot',
        'Backup error',
        'Backup engine',
    ];

    /** @var array<string, string> */
    private const PHASE_LABELS = [
        'upload' => 'Upload in progress',
        'complete' => 'Backup completed',
        'completed' => 'Backup completed',
        'manifest' => 'Recording backup manifest',
        'snapshot' => 'Creating snapshot',
        'hash' => 'Processing data',
        'hashing' => 'Processing data',
        'discover' => 'Discovering items',
        'discovery' => 'Discovering items',
        'queued' => 'Queued',
        'running' => 'Running',
        'cancelled' => 'Cancelled',
    ];

    public static function scrub(?string $text): string
    {
        $text = trim(html_entity_decode((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        if ($text === '') {
            return '';
        }

        $text = preg_replace(self::PHRASE_PATTERNS, self::PHRASE_REPLACEMENTS, $text) ?? $text;

        // Phase codes such as kopia_upload → upload
        $text = preg_replace('/\bkopia_([a-z0-9_]+)/i', '$1', $text) ?? $text;

        // Remove any remaining standalone "kopia" word
        $text = preg_replace('/\bkopia\b/i', '', $text) ?? $text;

        // Drop redundant vendor prefix before generic backup wording
        $text = preg_replace('/\beazybackup\s+(?=backup\b)/i', '', $text) ?? $text;
        $text = preg_replace('/\bbackup\s+backup\b/i', 'backup', $text) ?? $text;

        return preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
    }

    /**
     * Scrub and humanize short progress/log lines (including single phase tokens).
     */
    public static function scrubLogMessage(?string $text): string
    {
        $text = self::scrub($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $text)) {
            return self::humanizePhaseToken($text);
        }

        return $text;
    }

    public static function humanizePhaseToken(string $token): string
    {
        $key = strtolower(trim($token));
        if ($key === '') {
            return '';
        }

        return self::PHASE_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
