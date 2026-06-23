<?php
declare(strict_types=1);

require_once __DIR__ . '/../ms365backup_autoload.php';

use Ms365Backup\WorkerClaimService;

// effectiveReportedLoad is pure logic; queue load is read from DB when invoked in production.
$nodeId = 'test-node-ghost';

// When no queue claims exist, ghost worker load should read as idle.
// (runningClaimCountForNode returns 0 for unknown node with no rows.)
assert(WorkerClaimService::effectiveReportedLoad($nodeId, 4) === 0);
assert(WorkerClaimService::effectiveReportedLoad($nodeId, 0) === 0);

echo "ghost_load_test: ok\n";
