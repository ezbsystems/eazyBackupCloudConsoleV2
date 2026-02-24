<?php
declare(strict_types=1);

/**
 * Unit test for KopiaRetentionOperationService enqueue dedupe by operation_token.
 * TDD: first enqueue => success, second same token => duplicate.
 */

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/Client/KopiaRetentionOperationService.php';

use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionOperationService;

$token = 'test-token-' . uniqid('', true);
$first = KopiaRetentionOperationService::enqueue(1001, 'retention_apply', ['repo_id' => 1001], $token);
$second = KopiaRetentionOperationService::enqueue(1001, 'retention_apply', ['repo_id' => 1001], $token);

$ok = ($first['status'] === 'success') && ($second['status'] === 'duplicate');
echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
