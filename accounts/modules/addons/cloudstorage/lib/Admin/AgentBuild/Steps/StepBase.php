<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;

abstract class StepBase
{
    public string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    abstract public function execute(array $job, ProcRunner $runner, string $logPath): int;

    protected function flag(array $job, string $name, bool $default = false): bool
    {
        $f = json_decode((string) ($job['flags_json'] ?? '{}'), true) ?: [];
        return (bool) ($f[$name] ?? $default);
    }

    protected function appendLog(string $logPath, string $line): void
    {
        @file_put_contents($logPath, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
    }
}
