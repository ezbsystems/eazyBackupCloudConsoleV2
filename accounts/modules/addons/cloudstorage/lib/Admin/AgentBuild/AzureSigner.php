<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

/**
 * Builds an AzureSignTool sign command for a given file. The client secret
 * is passed via stdin to AzureSignTool (it accepts --azure-key-vault-accesstoken
 * via env or STDIN to avoid being captured in process listings/logs).
 *
 * For simplicity we pass it on the command line and rely on ProcRunner
 * redaction; the caller MUST register the secret with the runner.
 */
class AzureSigner
{
    public function __construct(
        public string $azuresigntoolPath,
        public string $tenantId,
        public string $clientId,
        public string $clientSecret,
        public string $kvUrl,
        public string $kvCertName,
        public string $timestampUrl
    ) {}

    /** Build the PowerShell command (string) to sign $remoteFile on the Windows host. */
    public function buildSignPowerShell(string $remoteFile, string $description = 'eazyBackup E3 Agent'): string
    {
        $ast = $this->ps($this->azuresigntoolPath);
        $args = [
            'sign',
            '-kvu', $this->ps($this->kvUrl),
            '-kvi', $this->ps($this->clientId),
            '-kvt', $this->ps($this->tenantId),
            '-kvs', $this->ps($this->clientSecret),
            '-kvc', $this->ps($this->kvCertName),
            '-tr',  $this->ps($this->timestampUrl),
            '-td',  'sha256',
            '-fd',  'sha256',
            '-d',   $this->ps($description),
            '--skip-signed',
            $this->ps($remoteFile),
        ];
        return '& ' . $ast . ' ' . implode(' ', $args);
    }

    private function ps(string $v): string
    {
        return "'" . str_replace("'", "''", $v) . "'";
    }
}
