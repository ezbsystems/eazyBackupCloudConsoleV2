<?php
declare(strict_types=1);

namespace Ms365Backup;

final class GraphSitePaths
{
    public static function encodeSiteId(string $siteId): string
    {
        return implode(',', array_map('rawurlencode', explode(',', $siteId)));
    }

    public static function sitePath(string $siteId, string $suffix = ''): string
    {
        $encoded = self::encodeSiteId($siteId);
        $suffix = ltrim($suffix, '/');

        return $suffix === '' ? 'sites/' . $encoded : 'sites/' . $encoded . '/' . $suffix;
    }
}
