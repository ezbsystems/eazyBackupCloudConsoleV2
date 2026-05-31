<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Resolves WHMCS 8 ticket attachment storage path (tblfileassetsettings).
 */
class CloudBackupTicketAttachmentStorage
{
    private static ?string $resolvedPath = null;

    public static function path(): string
    {
        if (self::$resolvedPath !== null) {
            return self::$resolvedPath;
        }

        $path = '';
        try {
            if (Capsule::schema()->hasTable('tblfileassetsettings')
                && Capsule::schema()->hasTable('tblstorageconfigurations')) {
                $configId = Capsule::table('tblfileassetsettings')
                    ->where('asset_type', 'ticket_attachments')
                    ->value('storageconfiguration_id');
                if ($configId) {
                    $settings = Capsule::table('tblstorageconfigurations')
                        ->where('id', (int) $configId)
                        ->value('settings');
                    if (is_string($settings) && $settings !== '') {
                        $decoded = json_decode($settings, true);
                        if (is_array($decoded) && !empty($decoded['local_path'])) {
                            $path = (string) $decoded['local_path'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $path = '';
        }

        if ($path === '') {
            try {
                $configured = (string) (\WHMCS\Config\Setting::getValue('AttachmentsDir') ?? '');
            } catch (\Throwable $e) {
                $configured = '';
            }
            if ($configured !== '') {
                $path = $configured;
            } else {
                $path = ROOTDIR . DIRECTORY_SEPARATOR . 'attachments';
            }
        }

        if ($path !== '' && !preg_match('#^[/\\\\]#', $path)) {
            $path = ROOTDIR . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
        }

        self::$resolvedPath = is_dir($path) ? rtrim($path, '/\\') : '';

        return self::$resolvedPath;
    }

    /**
     * WHMCS legacy attachment token: "{byteSize}_{displayName}".
     */
    public static function storedName(string $displayName, string $content): string
    {
        return strlen($content) . '_' . $displayName;
    }

    public static function fileExists(string $storedName): bool
    {
        $storedName = trim($storedName);
        if ($storedName === '') {
            return false;
        }
        $dir = self::path();
        if ($dir === '') {
            return false;
        }

        return is_file($dir . DIRECTORY_SEPARATOR . $storedName);
    }

    /**
     * @return list<string> stored names (e.g. 12345_file.txt)
     */
    public static function parseAttachmentField(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\|+/', $value))));
    }

    /**
     * Extract run UUID from WHMCS attachment metadata / display name.
     */
    public static function runIdFromStoredName(string $storedName): string
    {
        if (preg_match('/run-([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.txt/i', $storedName, $m)) {
            return strtolower($m[1]);
        }

        return '';
    }

    /**
     * Write opening-message attachment; returns stored token or empty string on failure.
     */
    public static function writeOpeningAttachment(int $ticketId, string $displayName, string $content): string
    {
        $dir = self::path();
        if ($dir === '' || $ticketId <= 0 || $content === '') {
            return '';
        }

        $storedName = self::storedName($displayName, $content);
        $fullPath = $dir . DIRECTORY_SEPARATOR . $storedName;
        if (file_put_contents($fullPath, $content) === false) {
            return '';
        }

        if (!Capsule::schema()->hasColumn('tbltickets', 'attachment')) {
            return '';
        }

        Capsule::table('tbltickets')->where('id', $ticketId)->update([
            'attachment' => $storedName,
        ]);

        return $storedName;
    }

    /**
     * Drop opening-message attachment tokens whose files are missing.
     */
    public static function pruneBrokenOpeningAttachments(int $ticketId): void
    {
        if ($ticketId <= 0 || !Capsule::schema()->hasColumn('tbltickets', 'attachment')) {
            return;
        }

        $current = (string) (Capsule::table('tbltickets')->where('id', $ticketId)->value('attachment') ?? '');
        if ($current === '') {
            return;
        }

        $kept = [];
        foreach (self::parseAttachmentField($current) as $part) {
            if (self::fileExists($part)) {
                $kept[] = $part;
            }
        }

        $newVal = implode('|', $kept);
        if ($newVal !== $current) {
            Capsule::table('tbltickets')->where('id', $ticketId)->update(['attachment' => $newVal]);
        }
    }

}
