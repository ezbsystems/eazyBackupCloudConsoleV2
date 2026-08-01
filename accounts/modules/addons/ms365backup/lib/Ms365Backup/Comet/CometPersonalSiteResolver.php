<?php
declare(strict_types=1);

namespace Ms365Backup\Comet;

use Ms365Backup\GraphClient;

/**
 * Detects Comet personal OneDrive site keys and resolves Graph drive owners.
 */
final class CometPersonalSiteResolver
{
    /**
     * Comet/Graph personal site keys look like:
     *   contoso-my.sharepoint.com,{siteCollectionId},{webId}
     */
    public static function isPersonalSiteKey(string $siteKey): bool
    {
        $key = strtolower(trim($siteKey));
        if ($key === '' || !str_contains($key, ',')) {
            return false;
        }

        return str_contains($key, '-my.sharepoint.') || str_contains($key, '.my.sharepoint.');
    }

    /**
     * @param array<string, int> $backupOptions
     * @return list<string>
     */
    public static function personalSiteKeysFromBackupOptions(array $backupOptions): array
    {
        $out = [];
        foreach ($backupOptions as $key => $_) {
            $key = (string) $key;
            if (self::isPersonalSiteKey($key)) {
                $out[] = $key;
            }
        }

        return array_values($out);
    }

    /**
     * Resolve personal site keys to owner Azure AD user object IDs.
     *
     * @param list<string> $siteKeys
     * @return array{
     *   owners: array<string, string>,
     *   unresolved: list<string>,
     *   errors: array<string, string>
     * }
     */
    public static function resolveOwners(GraphClient $graph, array $siteKeys): array
    {
        $owners = [];
        $unresolved = [];
        $errors = [];

        foreach ($siteKeys as $siteKey) {
            $siteKey = (string) $siteKey;
            if ($siteKey === '') {
                continue;
            }
            try {
                $ownerId = self::resolveOwnerAzureId($graph, $siteKey);
                if ($ownerId === '') {
                    $unresolved[] = $siteKey;
                    continue;
                }
                $owners[$siteKey] = $ownerId;
                $owners[strtolower($siteKey)] = $ownerId;
            } catch (\Throwable $e) {
                $unresolved[] = $siteKey;
                $errors[$siteKey] = $e->getMessage();
            }
        }

        return [
            'owners' => $owners,
            'unresolved' => array_values(array_unique($unresolved)),
            'errors' => $errors,
        ];
    }

    public static function resolveOwnerAzureId(GraphClient $graph, string $siteKey): string
    {
        $path = 'sites/' . rawurlencode($siteKey) . '/drive';
        try {
            $drive = $graph->get($path, [
                '$select' => 'id,name,driveType,owner',
            ]);
            $ownerId = self::ownerIdFromDrive($drive);
            if ($ownerId !== '') {
                return $ownerId;
            }
        } catch (\Throwable $_) {
            // Fall through to /drives list.
        }

        $listed = $graph->get('sites/' . rawurlencode($siteKey) . '/drives', [
            '$select' => 'id,name,driveType,owner',
            '$top' => '10',
        ]);
        foreach ($listed['value'] ?? [] as $drive) {
            if (!is_array($drive)) {
                continue;
            }
            $ownerId = self::ownerIdFromDrive($drive);
            if ($ownerId !== '') {
                return $ownerId;
            }
        }

        return '';
    }

    /** @param array<string, mixed> $drive */
    private static function ownerIdFromDrive(array $drive): string
    {
        $owner = $drive['owner'] ?? null;
        if (!is_array($owner)) {
            return '';
        }
        $user = $owner['user'] ?? null;
        if (is_array($user)) {
            return trim((string) ($user['id'] ?? ''));
        }

        return '';
    }
}
