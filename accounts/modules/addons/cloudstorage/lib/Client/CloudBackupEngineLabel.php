<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Maps internal backup engine identifiers to customer-facing labels.
 * "kopia" must never appear in client UI or support ticket text.
 */
class CloudBackupEngineLabel
{
    /**
     * @param string|null $engine Internal engine id (kopia, sync, disk_image, hyperv, …)
     */
    public static function label(?string $engine): string
    {
        switch (strtolower(trim((string) $engine))) {
            case 'kopia':
            case 'sync':
                return 'File/Folder';
            case 'disk_image':
                return 'Disk Image';
            case 'hyperv':
                return 'Hyper-V';
            default:
                $engine = trim((string) $engine);
                if ($engine === '') {
                    return 'File/Folder';
                }
                return ucfirst($engine);
        }
    }

    /**
     * Best-effort scrub of internal engine names from free-form customer-facing text.
     */
    public static function sanitizeText(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $patterns = [
            '/\bEngine:\s*kopia\b/i' => 'Engine: File/Folder',
            '/\bengine:\s*kopia\b/i' => 'engine: File/Folder',
            '/\bkopia\s+engine\b/i' => 'File/Folder backup',
            '/\bKopia\b/' => 'File/Folder',
            '/\bkopia\b/' => 'File/Folder',
        ];
        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        return $text;
    }
}
