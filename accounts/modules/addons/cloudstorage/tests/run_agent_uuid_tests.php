<?php
require_once __DIR__ . '/agent_uuid_identity_test.php';

// Pseudo-contract assertions for request key constants
$src = file_get_contents(__DIR__ . '/../api/agent_next_run.php');
if (strpos($src, 'HTTP_X_AGENT_UUID') === false) {
    throw new RuntimeException('expected HTTP_X_AGENT_UUID contract in agent_next_run.php');
}
if (strpos($src, 'HTTP_X_AGENT_ID') !== false) {
    throw new RuntimeException('legacy HTTP_X_AGENT_ID contract still present in agent_next_run.php');
}
