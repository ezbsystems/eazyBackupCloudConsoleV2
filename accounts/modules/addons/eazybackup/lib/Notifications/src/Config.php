<?php
declare(strict_types=1);

namespace EazyBackup\Notifications;

use WHMCS\Database\Capsule;

final class Config
{
    public static function get(string $key, $default = null)
    {
        $val = Capsule::table('tbladdonmodules')
            ->where('module', 'eazybackup')
            ->where('setting', $key)
            ->value('value');
        if ($val === null) return $default;
        return $val;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = (string)self::get($key, $default ? 'on' : '');
        $v = strtolower(trim($v));
        return in_array($v, ['1','on','yes','true'], true);
    }

    /** Resolve a template setting to an email template name. Setting may store an ID or a name. */
    public static function templateName(string $settingKey): string
    {
        $raw = (string)self::get($settingKey, '');
        if ($raw === '') return '';
        // If numeric, look up by ID
        if (ctype_digit($raw)) {
            try {
                $name = \WHMCS\Database\Capsule::table('tblemailtemplates')->where('id', (int)$raw)->value('name');
                if (is_string($name) && $name !== '') return $name;
            } catch (\Throwable $e) { /* ignore */ }
        }
        return $raw;
    }
}


