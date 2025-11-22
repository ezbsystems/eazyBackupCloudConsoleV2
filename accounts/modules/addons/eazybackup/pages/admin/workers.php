<?php

declare(strict_types=1);

// pages/admin/workers.php

require_once __DIR__ . '/../../lib/Admin/Workers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Admin session guard
    if (!isset($_SESSION['adminid']) || (int)$_SESSION['adminid'] <= 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized']);
        exit;
    }

    $op    = $_GET['op']    ?? 'list';
    $debug = $_GET['debug'] ?? '0';

    // Quick router self-test: ?op=ping
    if ($op === 'ping') {
        echo json_encode(['ok' => true, 'route' => 'admin-workers', 'ts' => gmdate('c')]);
        exit;
    }

    // Ensure class is loaded and has method
    if (!class_exists('\\Eazybackup\\Admin\\Workers') && !class_exists('\\EazyBackup\\Admin\\Workers')) {
        throw new \RuntimeException('Workers class not found. Check namespace/case and include path.');
    }
    $klass = class_exists('\\Eazybackup\\Admin\\Workers')
        ? '\\Eazybackup\\Admin\\Workers'
        : '\\EazyBackup\\Admin\\Workers';
    if (!method_exists($klass, 'list')) {
        throw new \RuntimeException($klass . '::list() not found');
    }

    if ($op !== 'list') {
        echo json_encode(['error' => 'Unknown op']);
        exit;
    }

    // Call library
    $data = $klass::list();
    if ($debug === '1') {
        $data['debug'] = eb_collect_debug_info();
    }

    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (\Throwable $e) {
    $out = ['error' => 'Exception: ' . $e->getMessage()];
    if (($_GET['debug'] ?? '0') === '1') {
        $out['trace'] = $e->getTraceAsString();
        $out['debug'] = eb_collect_debug_info();
    }
    echo json_encode($out, JSON_INVALID_UTF8_SUBSTITUTE);
}

function eb_collect_debug_info(): array
{
    $disabled = array_map('trim', explode(',', (string)(ini_get('disable_functions') ?: '')));
    $whichSys = @shell_exec('command -v systemctl 2>/dev/null') ?: '';
    $sysVer   = @shell_exec('/bin/systemctl --version 2>&1') ?: @shell_exec('/usr/bin/systemctl --version 2>&1') ?: '';
    $openbd   = (string)(ini_get('open_basedir') ?: '');
    return [
        'php_sapi'           => php_sapi_name(),
        'user_id'            => function_exists('posix_geteuid') ? posix_geteuid() : getmyuid(),
        'path'               => (string) getenv('PATH'),
        'open_basedir'       => $openbd,
        'disabled_functions' => $disabled,
        'which_systemctl'    => trim($whichSys),
        'systemctl_version'  => trim($sysVer),
    ];
}


