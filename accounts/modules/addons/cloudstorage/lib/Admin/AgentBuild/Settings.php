<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Read (and optionally decrypt) cloudstorage module settings used by
 * the agent build automation pipeline.
 */
class Settings
{
    /** @var array<string,string> */
    private static $cache = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (!array_key_exists($key, self::$cache)) {
            try {
                $val = Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudstorage')
                    ->where('setting', $key)
                    ->value('value');
                self::$cache[$key] = ($val !== null && $val !== '') ? (string) $val : '';
            } catch (\Throwable $e) {
                self::$cache[$key] = '';
            }
        }
        $v = self::$cache[$key];
        return ($v === '') ? $default : $v;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) return $default;
        return !in_array(strtolower($v), ['', '0', 'false', 'off', 'no'], true);
    }

    public static function decryptedSecret(string $key): ?string
    {
        $enc = self::get($key);
        if ($enc === null || $enc === '') return null;
        try {
            if (function_exists('localAPI')) {
                $r = localAPI('DecryptPassword', ['password2' => $enc]);
                if (is_array($r) && isset($r['password']) && $r['password'] !== '') {
                    return (string) $r['password'];
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return $enc;
    }

    public static function all(): array
    {
        return [
            'repo_path'         => self::get('agent_build_repo_path', '/var/www/eazybackup.ca/e3-backup-agent'),
            'default_git_ref'   => self::get('agent_build_default_git_ref', 'main'),
            'publish_dir'       => self::get('agent_build_publish_dir', '/var/www/eazybackup.ca/accounts/client_installer'),
            'win_host'          => self::get('agent_build_windows_host', '192.168.92.210'),
            'win_user'          => self::get('agent_build_windows_user', 'Administrator'),
            'win_ssh_key'       => self::get('agent_build_windows_ssh_key', '/root/.ssh/windows_server_ed25519'),
            'win_work_dir'      => self::get('agent_build_windows_work_dir', 'C:\\E3Build'),
            'iscc_path'         => self::get('agent_build_iscc_path', 'C:\\Program Files (x86)\\Inno Setup 6\\ISCC.exe'),
            'signing_enabled'   => self::getBool('agent_build_signing_enabled', false),
            'azure_tenant_id'   => self::get('agent_build_azure_tenant_id'),
            'azure_client_id'   => self::get('agent_build_azure_client_id'),
            'azure_kv_url'      => self::get('agent_build_azure_kv_url'),
            'azure_kv_cert'     => self::get('agent_build_azure_kv_cert_name'),
            'azure_ts_url'      => self::get('agent_build_signing_timestamp_url', 'http://timestamp.digicert.com'),
            'azuresigntool'     => self::get('agent_build_azuresigntool_path', 'C:\\Tools\\AzureSignTool\\AzureSignTool.exe'),
        ];
    }
}
