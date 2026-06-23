<?php
/**
 * TEMPORARY DIAGNOSTIC — capture backtraces for "Array to string conversion"
 * warnings (Laravel data_get with an array key) so the offending caller can be
 * identified and fixed.
 *
 * Active ONLY while the sentinel file exists:
 *     accounts/.dataget_capture_on
 * Disable instantly by deleting that file. Remove this hook once the source is
 * found. Writes de-duplicated backtraces to accounts/.dataget_capture.log.
 *
 * It chains to WHMCS's existing error handler so normal error logging is
 * unaffected.
 */

if (!defined('WHMCS')) {
    return;
}

$ms365CaptureSentinel = __DIR__ . '/../../.dataget_capture_on';
if (!@file_exists($ms365CaptureSentinel)) {
    return;
}

$ms365CaptureLog = __DIR__ . '/../../.dataget_capture.log';

$previousHandler = set_error_handler(
    function ($errno, $errstr, $errfile, $errline) use (&$previousHandler, $ms365CaptureLog) {
        if (stripos((string) $errstr, 'Array to string conversion') !== false) {
            // Cap total log size to avoid runaway growth on a hot path.
            if ((int) (@filesize($ms365CaptureLog) ?: 0) < 3_000_000) {
                $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60);
                $frames = [];
                foreach ($bt as $f) {
                    $frames[] = ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?')
                        . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
                }
                $context = $_SERVER['REQUEST_URI'] ?? ($GLOBALS['argv'][0] ?? 'cli');
                $entry = '=== ' . date('c') . ' | ' . $errstr . ' @ ' . $errfile . ':' . $errline
                    . ' | ctx=' . $context . " ===\n" . implode("\n", $frames) . "\n\n";
                @file_put_contents($ms365CaptureLog, $entry, FILE_APPEND | LOCK_EX);
            }

            // Mark handled so this specific warning is NOT re-logged to
            // tblerrorlog while armed (prevents re-bloat during the hunt).
            return true;
        }

        // All other errors: preserve normal WHMCS handling.
        if (is_callable($previousHandler)) {
            return ($previousHandler)($errno, $errstr, $errfile, $errline);
        }

        return false;
    }
);
