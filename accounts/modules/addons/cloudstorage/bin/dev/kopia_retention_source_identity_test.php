<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/Client/KopiaRetentionSourceService.php';

use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionSourceService;

$a = KopiaRetentionSourceService::buildSourceFingerprint('hyperv', 'agent-1', 'vm-guid-1');
$b = KopiaRetentionSourceService::buildSourceFingerprint('hyperv', 'agent-1', 'vm-guid-1');
$c = KopiaRetentionSourceService::buildSourceFingerprint('hyperv', 'agent-1', 'vm-guid-2');

$ok = ($a === $b) && ($a !== $c);
echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
