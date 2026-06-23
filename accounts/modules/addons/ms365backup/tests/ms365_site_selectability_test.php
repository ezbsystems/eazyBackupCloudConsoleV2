<?php
declare(strict_types=1);

/**
 * Site selectability, classifier, and selection validation tests.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_site_selectability_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\AccessResult;
use Ms365Backup\BackupPlanner;
use Ms365Backup\CustomerSelectionCodec;
use Ms365Backup\GraphApiException;
use Ms365Backup\ResourceAccessClassifier;
use Ms365Backup\TenantResource;

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

function assert_throws(callable $fn, string $message): void
{
    try {
        $fn();
        assert_true(false, $message . ' (expected exception)');
    } catch (\Throwable $e) {
        assert_true(true, $message);
    }
}

$accessDenied = new GraphApiException(
    'sharepoint: graph 403 Forbidden: {"error":{"code":"accessDenied","message":"Access denied"}}',
    403,
    'accessDenied',
);
$classifierResult = ResourceAccessClassifier::classify($accessDenied);
assert_true(
    $classifierResult->status === AccessResult::STATUS_UNAVAILABLE,
    '403 accessDenied classifies as unavailable',
);
assert_true($classifierResult->skippable, '403 accessDenied is skippable');

$siteNotFound = new GraphApiException(
    'graph 404 Not Found: site not found',
    404,
    'itemNotFound',
);
$site404 = ResourceAccessClassifier::classify($siteNotFound);
assert_true(
    $site404->status === AccessResult::STATUS_UNAVAILABLE,
    '404 site path classifies as unavailable',
);

$accessibleSite = [
    'id' => 'site:abc',
    'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    'graph_id' => 'abc',
    'display_name' => 'Marketing',
    'access' => [
        'files' => AccessResult::STATUS_AVAILABLE,
        'lists' => AccessResult::STATUS_AVAILABLE,
    ],
];
$accessible = TenantResource::siteSelectability($accessibleSite);
assert_true($accessible['selectable'] === true, 'fully accessible site is selectable');
assert_true($accessible['capability_access']['files'] === true, 'files capability accessible');
assert_true($accessible['capability_access']['lists'] === true, 'lists capability accessible');

$deniedSite = [
    'id' => 'site:designer',
    'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    'graph_id' => 'designer',
    'display_name' => 'Designer',
    'access' => [
        'status' => AccessResult::STATUS_UNAVAILABLE,
        'files' => AccessResult::STATUS_UNAVAILABLE,
        'lists' => AccessResult::STATUS_UNAVAILABLE,
        'reason' => 'Access denied',
    ],
];
$denied = TenantResource::siteSelectability($deniedSite);
assert_true($denied['selectable'] === false, 'inaccessible site is not selectable');
assert_true($denied['disabled_reason'] !== '', 'inaccessible site has disabled_reason');

$partialSite = [
    'id' => 'site:partial',
    'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    'graph_id' => 'partial',
    'display_name' => 'Partial',
    'access' => [
        'files' => AccessResult::STATUS_AVAILABLE,
        'lists' => AccessResult::STATUS_UNAVAILABLE,
        'lists_reason' => 'Lists blocked',
    ],
];
$partial = TenantResource::siteSelectability($partialSite);
assert_true($partial['selectable'] === true, 'site with one accessible capability remains selectable');
assert_true($partial['capability_access']['files'] === true, 'partial site files accessible');
assert_true($partial['capability_access']['lists'] === false, 'partial site lists not accessible');

$unknownSite = [
    'id' => 'site:unknown',
    'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    'graph_id' => 'unknown',
    'display_name' => 'Unknown',
    'access' => [],
];
$unknown = TenantResource::siteSelectability($unknownSite);
assert_true($unknown['selectable'] === true, 'site without access probe remains selectable (backward compat)');

$inventory = [
    'resources' => [
        $accessibleSite,
        $deniedSite,
        $partialSite,
        [
            'id' => 'user:u1',
            'resource_type' => TenantResource::TYPE_USER,
            'graph_id' => 'u1',
            'display_name' => 'User One',
        ],
    ],
];

assert_throws(
    static fn () => CustomerSelectionCodec::validate(
        ['site:designer'],
        ['site:designer' => ['files' => true, 'lists' => true]],
        $inventory,
    ),
    'CustomerSelectionCodec rejects inaccessible site selection',
);

assert_throws(
    static fn () => CustomerSelectionCodec::validate(
        ['site:partial'],
        ['site:partial' => ['files' => true, 'lists' => true]],
        $inventory,
    ),
    'CustomerSelectionCodec rejects inaccessible lists capability',
);

CustomerSelectionCodec::validate(
    ['site:partial'],
    ['site:partial' => ['files' => true, 'lists' => false]],
    $inventory,
);
assert_true(true, 'CustomerSelectionCodec allows accessible files-only selection');

$planner = new BackupPlanner();
$plan = $planner->plan(
    ['site:designer'],
    $inventory,
    ['site:designer' => ['files' => true, 'lists' => true]],
);
$warnings = is_array($plan['warnings'] ?? null) ? $plan['warnings'] : [];
assert_true(
    count(array_filter($warnings, static fn ($w) => str_contains((string) $w, 'Designer'))) > 0,
    'BackupPlanner emits warning for selected inaccessible site',
);

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
