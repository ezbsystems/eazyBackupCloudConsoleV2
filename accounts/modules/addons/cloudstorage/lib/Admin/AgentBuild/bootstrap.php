<?php
// Lightweight loader for the AgentBuild namespace (no composer autoload in this addon).

$base = __DIR__;
$files = [
    $base . '/Settings.php',
    $base . '/JobStore.php',
    $base . '/ProcRunner.php',
    $base . '/WindowsRemote.php',
    $base . '/AzureSigner.php',
    $base . '/Steps/StepBase.php',
    $base . '/Steps/GitSync.php',
    $base . '/Steps/GoTest.php',
    $base . '/Steps/LinuxBuild.php',
    $base . '/Steps/WindowsBuild.php',
    $base . '/Steps/RecoveryBuild.php',
    $base . '/Steps/WindowsStage.php',
    $base . '/Steps/InnoCompile.php',
    $base . '/Steps/AzureSign.php',
    $base . '/Steps/WindowsFetch.php',
    $base . '/Steps/Verify.php',
    $base . '/Steps/Publish.php',
    $base . '/BuildRunner.php',
];
foreach ($files as $f) {
    require_once $f;
}
