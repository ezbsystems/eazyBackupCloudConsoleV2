<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\UnitTestCase;

/**
 * Smoke test for the existing static contract gate.
 *
 * Source: bin/dev/msp_billing_release_gate.php
 *
 * Why this lives in the PHPUnit suite:
 *   1. Guards the ~30 contract markers from rotting silently.
 *   2. Confirms the gate script is still invocable from the same PHP we use for tests
 *      (catches accidental syntax errors in any of the contract-test scripts the gate
 *      delegates to).
 *
 * To avoid recursion the gate must NOT also re-invoke PHPUnit when it is already
 * running inside PHPUnit; the gate respects EB_GATE_SKIP_PHPUNIT=1 (set below).
 */
final class MspBillingReleaseGateRunsCleanTest extends UnitTestCase
{
    public function test_release_gate_passes(): void
    {
        $addonRoot = dirname(__DIR__, 2);
        $gateScript = $addonRoot . '/bin/dev/msp_billing_release_gate.php';
        self::assertFileExists($gateScript, 'msp_billing_release_gate.php must exist for this smoke test.');

        $phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $cmd = sprintf(
            '%s %s 2>&1',
            escapeshellarg($phpBinary),
            escapeshellarg($gateScript)
        );

        $env = $_ENV + ['EB_GATE_SKIP_PHPUNIT' => '1'];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, $addonRoot, $env);
        self::assertIsResource($proc, 'proc_open of release gate failed.');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        $output = trim($stdout . "\n" . $stderr);
        self::assertSame(0, $exitCode, "Release gate exited non-zero. Output:\n{$output}");
        self::assertStringContainsString(
            'MSP_BILLING_RELEASE_GATE_PASS',
            $output,
            "Release gate did not print success marker. Output:\n{$output}"
        );
    }
}
