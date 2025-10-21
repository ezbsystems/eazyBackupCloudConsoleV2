<?php

namespace EazyBackup\Whitelabel;

class HostOps
{
    private array $cfg;
    public function __construct(array $vars) { $this->cfg = $vars; }

    public function writeHttpStub(string $fqdn): bool
    {
        return $this->invokeArgs('write_http_stub', [$fqdn]);
    }

    public function issueCert(string $fqdn): bool
    {
        $email = (string)($this->cfg['certbot_email'] ?? '');
        $selfIp = (string)($this->cfg['acme_selftest_ip'] ?? '');
        $env = [];
        if ($selfIp !== '') { $env['ACME_SELFTEST_IP'] = $selfIp; }
        return $this->invokeArgs('issue_cert', [$fqdn, $email], $env);
    }

    public function writeHttps(string $fqdn): bool
    {
        $upstream = (string)($this->cfg['nginx_upstream'] ?? 'http://obc_servers');
        return $this->invokeArgs('write_https', [$fqdn, $upstream]);
    }

    public function disableHost(string $fqdn): bool
    {
        return $this->invokeArgs('disable', [$fqdn]);
    }

    public function deleteHost(string $fqdn): bool
    {
        return $this->invokeArgs('delete', [$fqdn]);
    }

    /** Optional: dig helper for robust DNS checks via specific resolver */
    public function dig(string $host, string $resolver = '1.1.1.1', string $type = 'CNAME'): array
    {
        $host = trim($host);
        if ($host === '') { return ['ok'=>false,'answer'=>null,'raw'=>'']; }
        $type = preg_replace('/[^A-Z0-9]/','', strtoupper($type));
        $resolver = preg_replace('/[^0-9.]/','', $resolver);
        $cmd = 'dig +short ' . escapeshellarg($host) . ' ' . escapeshellarg($type) . ' @' . escapeshellarg($resolver);
        $out = [];
        try { $rc = 0; @exec($cmd . ' 2>&1', $out, $rc); } catch (\Throwable $e) { $this->log('dig error: '.$e->getMessage()); return ['ok'=>false,'answer'=>null,'raw'=>'']; }
        $raw = implode("\n", $out);
        $ans = '';
        foreach ($out as $line) { $line = trim($line); if ($line !== '') { $ans = $line; break; } }
        return ['ok'=>($ans !== ''), 'answer'=>$ans !== '' ? $ans : null, 'raw'=>$raw];
    }

    private function invokeArgs(string $action, array $args, array $env = []): bool
    {
        $mode = (string)($this->cfg['ops_mode'] ?? 'ssh');
        $host = (string)($this->cfg['ops_ssh_host'] ?? '');
        $user = (string)($this->cfg['ops_ssh_user'] ?? '');
        $key  = (string)($this->cfg['ops_ssh_key_path'] ?? '');
        $sudo = (string)($this->cfg['ops_sudo_script'] ?? '/usr/local/bin/tenant_provision');
        $cmd  = '';
        $envStr = '';
        if (!empty($env)) {
            $pairs = [];
            foreach ($env as $k=>$v) { $pairs[] = escapeshellarg($k) . '=' . escapeshellarg((string)$v); }
            // Note: for remote ssh, we prefix the command with KEY=VAL tokens (no export needed for a single command)
            $envStr = implode(' ', array_map(function($kv){ return str_replace("'=\'", "='", $kv); }, $pairs));
        }
        if ($mode === 'ssh' && $host !== '' && $user !== '') {
            $escapedAct  = escapeshellarg($action);
            $escapedArgs = implode(' ', array_map('escapeshellarg', $args));
            $ssh = 'ssh -o StrictHostKeyChecking=no -i ' . escapeshellarg($key) . ' ' . escapeshellarg($user . '@' . $host);
            $remote = ($envStr !== '' ? $envStr . ' ' : '') . escapeshellarg($sudo) . ' ' . $escapedAct . ' ' . $escapedArgs;
            $cmd = $ssh . ' ' . $remote;
        } else if ($mode === 'sudo') {
            $escapedArgs = implode(' ', array_map('escapeshellarg', $args));
            $prefix = ($envStr !== '' ? $envStr . ' ' : '');
            $cmd = $prefix . escapeshellcmd($sudo) . ' ' . escapeshellarg($action) . ' ' . $escapedArgs;
        }
        if ($cmd === '') { $this->log('hostops: missing config'); return false; }
        $this->log('hostops exec: ' . $cmd);
        // execute in background to avoid blocking if needed; here we run synchronously and capture RC only
        try { $rc = 0; @exec($cmd . ' 2>&1', $out, $rc); $this->log('hostops rc=' . $rc . ' out=' . substr(implode("\n", $out ?? []),0,400)); return $rc === 0; } catch (\Throwable $e) { $this->log('hostops err: ' . $e->getMessage()); return false; }
    }

    private function log(string $msg): void
    {
        try { logModuleCall('eazybackup','whitelabel_hostops',[], $msg); } catch (\Throwable $_) {}
    }
}


