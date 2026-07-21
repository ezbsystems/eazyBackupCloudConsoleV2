<?php
declare(strict_types=1);

require_once dirname(__DIR__, 5) . '/init.php';
require_once dirname(__DIR__, 5) . '/modules/servers/comet/functions.php';

$server = comet_Server(['pid' => 54]);

if (!$server instanceof \Comet\Server) {
    fwrite(STDERR, "FAIL: PID-only server resolution did not return a Comet server\n");
    exit(1);
}

fwrite(STDOUT, "comet-server-pid-resolution-ok\n");
