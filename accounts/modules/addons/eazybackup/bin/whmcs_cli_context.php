<?php
declare(strict_types=1);

/**
 * WHMCS expects web-server context values even when booted from CLI.
 * Long-running workers run under systemd with a clean environment, so seed
 * conservative HTTPS defaults before requiring WHMCS init.php.
 */
if (!function_exists('eb_cli_context_value')) {
    function eb_cli_context_value(string $key): string
    {
        $env = getenv($key);
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }
        if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
            return trim((string) $_SERVER[$key]);
        }
        if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
            return trim((string) $_ENV[$key]);
        }
        return '';
    }
}

if (!function_exists('eb_cli_context_host')) {
    function eb_cli_context_host(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '://')) {
            $parsed = parse_url($value, PHP_URL_HOST);
            if (is_string($parsed) && trim($parsed) !== '') {
                return trim($parsed);
            }
        }

        $value = preg_replace('#/.*$#', '', $value) ?? $value;
        return trim($value);
    }
}

if (!function_exists('eb_cli_context_seed')) {
    function eb_cli_context_seed(string $key, string $value): void
    {
        if ($value === '') {
            return;
        }

        if (!isset($_SERVER[$key]) || trim((string) $_SERVER[$key]) === '') {
            $_SERVER[$key] = $value;
        }
        if (!isset($_ENV[$key]) || trim((string) $_ENV[$key]) === '') {
            $_ENV[$key] = $value;
        }
        $env = getenv($key);
        if (!is_string($env) || trim($env) === '') {
            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('eb_apply_whmcs_cli_server_context')) {
    function eb_apply_whmcs_cli_server_context(string $defaultHost = 'accounts.eazybackup.ca'): void
    {
        $host = eb_cli_context_host(eb_cli_context_value('WHMCS_SERVER_NAME'));
        if ($host === '') {
            $host = eb_cli_context_host(eb_cli_context_value('SERVER_NAME'));
        }
        if ($host === '') {
            $host = eb_cli_context_host(eb_cli_context_value('HTTP_HOST'));
        }
        if ($host === '') {
            $host = $defaultHost;
        }

        $httpHost = eb_cli_context_host(eb_cli_context_value('HTTP_HOST'));
        if ($httpHost === '') {
            $httpHost = $host;
        }

        $https = eb_cli_context_value('HTTPS');
        if ($https === '') {
            $https = 'on';
        }

        $port = eb_cli_context_value('SERVER_PORT');
        if ($port === '') {
            $port = in_array(strtolower($https), ['off', '0', 'false', 'no'], true) ? '80' : '443';
        }

        eb_cli_context_seed('WHMCS_SERVER_NAME', $host);
        eb_cli_context_seed('SERVER_NAME', $host);
        eb_cli_context_seed('HTTP_HOST', $httpHost);
        eb_cli_context_seed('HTTPS', $https);
        eb_cli_context_seed('SERVER_PORT', $port);
    }
}

