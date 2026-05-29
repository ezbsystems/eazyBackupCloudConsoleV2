<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

class WindowsRemote
{
    public string $host;
    public string $user;
    public string $sshKey;

    public function __construct(string $host, string $user, string $sshKey)
    {
        $this->host = $host;
        $this->user = $user;
        $this->sshKey = $sshKey;
    }

    public static function fromSettings(): self
    {
        $s = Settings::all();
        return new self((string) $s['win_host'], (string) $s['win_user'], (string) $s['win_ssh_key']);
    }

    /** Common ssh argv used for both interactive and PowerShell commands. */
    private function sshBaseArgs(): array
    {
        return [
            'ssh',
            '-i', $this->sshKey,
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ServerAliveInterval=30',
            $this->user . '@' . $this->host,
        ];
    }

    /** Build argv for a remote PowerShell command (single -Command string). */
    public function powershell(string $command): array
    {
        // Encode as UTF16-LE base64 to avoid quoting issues
        $utf16le = mb_convert_encoding($command, 'UTF-16LE', 'UTF-8');
        $encoded = base64_encode($utf16le);
        return array_merge(
            $this->sshBaseArgs(),
            ['powershell', '-NoProfile', '-NonInteractive', '-EncodedCommand', $encoded]
        );
    }

    /** Build argv for a raw remote shell command (e.g. cmd.exe wrapper). */
    public function exec(string $remoteCommand): array
    {
        return array_merge($this->sshBaseArgs(), [$remoteCommand]);
    }

    /** Common scp argv.
     *  - `-O` forces the legacy SCP/RCP protocol; without it, OpenSSH 8.8+
     *    uses SFTP which fails against Windows OpenSSH paths containing
     *    backslashes.
     *  - `-T` disables the strict filename check the modern scp client
     *    performs on returned filenames. Windows OpenSSH normalises paths
     *    server-side, so the returned filename won't byte-match the request
     *    and the transfer aborts with "protocol error: filename does not
     *    match request" unless this check is disabled. */
    private function scpBaseArgs(): array
    {
        return ['scp', '-O', '-T', '-i', $this->sshKey, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new'];
    }

    /** scp argv: local -> remote. */
    public function scpUp(string $localPath, string $remotePath, bool $recursive = false): array
    {
        $args = $this->scpBaseArgs();
        if ($recursive) $args[] = '-r';
        $args[] = $localPath;
        $args[] = $this->user . '@' . $this->host . ':' . $remotePath;
        return $args;
    }

    /** scp argv: remote -> local. */
    public function scpDown(string $remotePath, string $localPath, bool $recursive = false): array
    {
        $args = $this->scpBaseArgs();
        if ($recursive) $args[] = '-r';
        $args[] = $this->user . '@' . $this->host . ':' . $remotePath;
        $args[] = $localPath;
        return $args;
    }
}
