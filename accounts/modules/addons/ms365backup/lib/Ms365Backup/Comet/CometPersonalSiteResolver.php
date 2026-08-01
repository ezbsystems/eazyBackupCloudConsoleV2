<?php
declare(strict_types=1);

namespace Ms365Backup\Comet;

use Ms365Backup\GraphClient;
use Ms365Backup\TenantResource;

/**
 * Detects Comet personal OneDrive site keys and resolves owners to Azure AD user IDs.
 *
 * Primary path: GET /sites/{id}/drive → owner.user.id
 * Fallback when the site is blocked (Graph 423): sites/getAllSites metadata
 * (displayName /personal/… path) matched against e3 inventory users/mailboxes.
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
     * Encode a UPN/email the way SharePoint personal paths do:
     * jane.doe@contoso.com → jane_doe_contoso_com
     */
    public static function emailToPersonalPath(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || !str_contains($email, '@')) {
            return '';
        }

        return str_replace(['@', '.'], '_', $email);
    }

    /**
     * Extract personal path segment from a OneDrive webUrl.
     * https://contoso-my.sharepoint.com/personal/jane_doe_contoso_com → jane_doe_contoso_com
     */
    public static function personalPathFromWebUrl(string $webUrl): string
    {
        $webUrl = strtolower(trim($webUrl));
        if ($webUrl === '') {
            return '';
        }
        if (!preg_match('#/personal/([^/?]+)#', $webUrl, $m)) {
            return '';
        }

        return rawurldecode($m[1]);
    }

    /**
     * @param list<string> $siteKeys
     * @param list<array<string, mixed>> $inventoryResources
     * @return array{
     *   owners: array<string, string>,
     *   unresolved: list<string>,
     *   errors: array<string, string>
     * }
     */
    public static function resolveOwners(
        GraphClient $graph,
        array $siteKeys,
        array $inventoryResources = [],
    ): array {
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
                if ($ownerId !== '') {
                    $owners[$siteKey] = $ownerId;
                    $owners[strtolower($siteKey)] = $ownerId;
                    continue;
                }
                $unresolved[] = $siteKey;
            } catch (\Throwable $e) {
                $unresolved[] = $siteKey;
                $errors[$siteKey] = $e->getMessage();
            }
        }

        $unresolved = array_values(array_unique($unresolved));
        if ($unresolved === [] || $inventoryResources === []) {
            return [
                'owners' => $owners,
                'unresolved' => $unresolved,
                'errors' => $errors,
            ];
        }

        try {
            $directory = self::indexPersonalSitesViaGetAllSites($graph, $unresolved);
        } catch (\Throwable $e) {
            $errors['_getAllSites'] = $e->getMessage();

            return [
                'owners' => $owners,
                'unresolved' => $unresolved,
                'errors' => $errors,
            ];
        }

        $still = [];
        foreach ($unresolved as $siteKey) {
            $meta = $directory[strtolower($siteKey)] ?? null;
            if (!is_array($meta)) {
                $still[] = $siteKey;
                $errors[$siteKey] = ($errors[$siteKey] ?? '') !== ''
                    ? $errors[$siteKey]
                    : 'Personal site not found in getAllSites directory.';
                continue;
            }
            $ownerId = self::matchOwnerInInventory(
                $inventoryResources,
                (string) ($meta['displayName'] ?? ''),
                (string) ($meta['webUrl'] ?? ''),
            );
            if ($ownerId === '') {
                $still[] = $siteKey;
                $errors[$siteKey] = 'Personal site metadata found but no inventory user/mailbox matched'
                    . ' (displayName=' . ($meta['displayName'] ?? '') . ', path='
                    . self::personalPathFromWebUrl((string) ($meta['webUrl'] ?? '')) . ').';
                continue;
            }
            $owners[$siteKey] = $ownerId;
            $owners[strtolower($siteKey)] = $ownerId;
            unset($errors[$siteKey]);
        }

        return [
            'owners' => $owners,
            'unresolved' => array_values($still),
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

        try {
            $listed = $graph->get('sites/' . rawurlencode($siteKey) . '/drives', [
                '$select' => 'id,name,driveType,owner',
                '$top' => '10',
            ]);
        } catch (\Throwable $e) {
            throw $e;
        }

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

    /**
     * @param list<string> $wantedKeys
     * @return array<string, array{id: string, displayName: string, webUrl: string}>
     */
    public static function indexPersonalSitesViaGetAllSites(GraphClient $graph, array $wantedKeys): array
    {
        $wanted = [];
        foreach ($wantedKeys as $key) {
            $wanted[strtolower((string) $key)] = true;
        }
        if ($wanted === []) {
            return [];
        }

        $found = [];
        foreach ($graph->paginate('sites/getAllSites', [
            '$select' => 'id,displayName,webUrl,name',
            '$top' => '100',
        ]) as $site) {
            if (!is_array($site)) {
                continue;
            }
            $id = strtolower(trim((string) ($site['id'] ?? '')));
            if ($id === '' || !isset($wanted[$id])) {
                continue;
            }
            $found[$id] = [
                'id' => (string) ($site['id'] ?? ''),
                'displayName' => (string) ($site['displayName'] ?? $site['name'] ?? ''),
                'webUrl' => (string) ($site['webUrl'] ?? ''),
            ];
            if (count($found) >= count($wanted)) {
                break;
            }
        }

        return $found;
    }

    /**
     * @param list<array<string, mixed>> $inventoryResources
     */
    public static function matchOwnerInInventory(
        array $inventoryResources,
        string $displayName,
        string $webUrl,
    ): string {
        $path = self::personalPathFromWebUrl($webUrl);
        $displayName = strtolower(trim($displayName));

        $byPath = '';
        $byName = '';
        foreach ($inventoryResources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            if (!in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
                continue;
            }
            $graphId = trim((string) ($resource['graph_id'] ?? ''));
            if ($graphId === '') {
                continue;
            }

            if ($path !== '') {
                $emails = [];
                $email = trim((string) ($resource['email'] ?? ''));
                if ($email !== '') {
                    $emails[] = $email;
                }
                $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
                foreach (['mail', 'user_principal_name', 'upn'] as $metaKey) {
                    $v = trim((string) ($meta[$metaKey] ?? ''));
                    if ($v !== '') {
                        $emails[] = $v;
                    }
                }
                foreach ($emails as $candidate) {
                    if (self::emailToPersonalPath($candidate) === $path) {
                        $byPath = $graphId;
                        break 2;
                    }
                }
            }

            if ($displayName !== '' && $byName === '') {
                $name = strtolower(trim((string) ($resource['display_name'] ?? '')));
                if ($name !== '' && $name === $displayName) {
                    $byName = $graphId;
                }
            }
        }

        return $byPath !== '' ? $byPath : $byName;
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
