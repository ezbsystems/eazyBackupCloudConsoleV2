<?php
declare(strict_types=1);

/**
 * Unit tests for BrowseBinaryInstaller filesystem behavior.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_browse_binary_installer_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Fleet\BrowseBinaryInstaller;

$failures = 0;

function assert_true(bool $cond, string $message): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$tmpdir = sys_get_temp_dir() . '/ms365-browse-installer-test-' . getmypid();
@mkdir($tmpdir, 0755, true);
$source = $tmpdir . '/source-worker';
$dest = $tmpdir . '/dest-worker';
file_put_contents($source, "#!/bin/sh\necho worker\n");
@chmod($source, 0755);

assert_true(
    copy($source, $dest) && chmod($dest, 0755) && is_executable($dest),
    'fixture copy creates executable destination'
);
assert_true(
    BrowseBinaryInstaller::installedMatchesArtifact($source, $dest),
    'installedMatchesArtifact true for identical files'
);

$mtimeBefore = filemtime($dest) ?: 0;
sleep(1);
touch($source, $mtimeBefore + 5);
assert_true(
    BrowseBinaryInstaller::installedMatchesArtifact($source, $dest),
    'installedMatchesArtifact true when content hash matches despite mtime drift'
);

file_put_contents($dest, "#!/bin/sh\necho other\n");
assert_true(
    !BrowseBinaryInstaller::installedMatchesArtifact($source, $dest),
    'installedMatchesArtifact false when destination content differs'
);

@unlink($dest);
assert_true(
    !BrowseBinaryInstaller::installedMatchesArtifact($source, $dest),
    'installedMatchesArtifact false when destination missing'
);

$missingRelease = BrowseBinaryInstaller::syncFromRelease(999999999);
assert_true(
    $missingRelease['ok'] === false && $missingRelease['error'] !== '',
    'syncFromRelease returns error for unknown release id'
);

$status = BrowseBinaryInstaller::status();
assert_true(
    in_array($status['status'], ['synced', 'out_of_date', 'missing'], true),
    'status returns known status value'
);
assert_true(
    isset($status['dest']) && $status['dest'] !== '',
    'status includes destination path'
);

@unlink($source);
@unlink($dest);
@rmdir($tmpdir);

exit($failures > 0 ? 1 : 0);
