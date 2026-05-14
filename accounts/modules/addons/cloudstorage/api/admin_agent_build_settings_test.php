<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\WindowsRemote;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['adminid'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required']))->send();
    exit;
}

$results = [];

// 1. Local repo present
$repo = (string) Settings::get('agent_build_repo_path');
$results['repo'] = [
    'name' => 'Local agent repo',
    'ok'   => is_dir($repo . '/.git'),
    'detail' => $repo . (is_dir($repo . '/.git') ? ' (git repo found)' : ' (not a git repo)'),
];

// 2. Local Go toolchain
[$rc, $out] = ProcRunner::capture(['go', 'version']);
$results['go'] = ['name' => 'Go toolchain', 'ok' => $rc === 0, 'detail' => $out ?: 'go not found'];

// 3. SSH probe (echo ok)
$remote = WindowsRemote::fromSettings();
[$rc, $out] = ProcRunner::capture($remote->powershell("Write-Output 'ok'"));
$results['ssh'] = [
    'name' => 'SSH to Windows host',
    'ok'   => $rc === 0 && stripos($out, 'ok') !== false,
    'detail' => trim((string) $out) ?: 'no output',
];

// 4. iscc.exe present
$iscc = (string) Settings::get('agent_build_iscc_path');
$cmd = "if (Test-Path -LiteralPath '" . str_replace("'", "''", $iscc) . "') { 'OK' } else { 'MISSING' }";
[$rc, $out] = ProcRunner::capture($remote->powershell($cmd));
$results['iscc'] = [
    'name' => 'Inno Setup Compiler',
    'ok'   => $rc === 0 && stripos($out, 'OK') !== false,
    'detail' => $iscc . ' -> ' . trim((string) $out),
];

// 5. AzureSignTool present
$ast = (string) Settings::get('agent_build_azuresigntool_path');
$cmd = "if (Test-Path -LiteralPath '" . str_replace("'", "''", $ast) . "') { 'OK' } else { 'MISSING' }";
[$rc, $out] = ProcRunner::capture($remote->powershell($cmd));
$results['ast'] = [
    'name' => 'AzureSignTool',
    'ok'   => $rc === 0 && stripos($out, 'OK') !== false,
    'detail' => $ast . ' -> ' . trim((string) $out),
];

// 6. Signing config completeness (no live KV call)
$s = Settings::all();
$missing = [];
foreach (['azure_tenant_id','azure_client_id','azure_kv_url','azure_kv_cert'] as $k) {
    if (empty($s[$k])) $missing[] = $k;
}
$secret = Settings::decryptedSecret('agent_build_azure_client_secret');
if (empty($secret)) $missing[] = 'azure_client_secret';
$results['azure_config'] = [
    'name' => 'Code-signing settings',
    'ok'   => empty($missing) && !empty($s['signing_enabled']),
    'detail' => empty($missing) ? 'all required signing fields configured' : 'missing: ' . implode(', ', $missing),
];

// 7. Publish dir writable
$pub = (string) Settings::get('agent_build_publish_dir');
$results['publish'] = [
    'name' => 'Publish directory',
    'ok'   => is_dir($pub) && is_writable($pub),
    'detail' => $pub . (is_writable($pub) ? ' (writable)' : ' (not writable)'),
];

(new JsonResponse(['status' => 'success', 'checks' => $results]))->send();
