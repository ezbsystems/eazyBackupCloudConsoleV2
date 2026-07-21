# Server Resolution and Client Error Sanitization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve each backup profile through its assigned WHMCS server and prevent provider or URI details from reaching customer-visible errors.

**Architecture:** Extend the shared product-parameter helper to accept an optional assigned server ID validated through `tblservergroupsrel`, with relational and legacy fallbacks for package-only callers. Make the user-profile route service-aware and collapse all helper/API failures into a generic customer response with sanitized internal classification.

**Tech Stack:** PHP 8.2, WHMCS Capsule, WHMCS server/product tables, Comet PHP SDK, standalone PHP regression scripts.

## Global Constraints

- Keep the active debug instrumentation unchanged through post-fix runtime verification.
- Do not log usernames, hostnames, credentials, URIs, or raw exception messages.
- Do not change the production server during development implementation.
- Preserve compatibility for existing one-argument `comet_ProductParams($pid)` callers.
- Do not require a database schema change.

---

### Task 1: Assigned server resolution

**Files:**
- Create: `accounts/modules/addons/eazybackup/bin/dev/comet_product_params_server_resolution_test.php`
- Modify: `accounts/modules/servers/comet/functions.php:49-69`

**Interfaces:**
- Consumes: WHMCS product ID and optional assigned server ID.
- Produces: `comet_ProductParams($pid, $assignedServerId = null): array`.

- [ ] **Step 1: Write the failing integration test**

Create a focused script that loads WHMCS, finds a Comet product whose server-group name differs from a related server name, calls `comet_ProductParams($pid, $serverId)`, and asserts that the returned hostname and username match the related server. Never print either value.

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__, 5) . '/init.php';
require_once dirname(__DIR__, 5) . '/modules/servers/comet/functions.php';

use WHMCS\Database\Capsule;

$fixture = Capsule::table('tblproducts as p')
    ->join('tblservergroups as sg', 'sg.id', '=', 'p.servergroup')
    ->join('tblservergroupsrel as sgr', 'sgr.groupid', '=', 'sg.id')
    ->join('tblservers as s', 's.id', '=', 'sgr.serverid')
    ->where('p.servertype', 'comet')
    ->whereColumn('sg.name', '<>', 's.name')
    ->where('s.hostname', '<>', '')
    ->select(['p.id as product_id', 's.id as server_id', 's.hostname', 's.username'])
    ->first();

if (!$fixture) {
    fwrite(STDERR, "FAIL: no relational server-resolution fixture found\n");
    exit(1);
}

$params = comet_ProductParams((int) $fixture->product_id, (int) $fixture->server_id);
$passed = hash_equals((string) $fixture->hostname, (string) ($params['serverhostname'] ?? ''))
    && hash_equals((string) $fixture->username, (string) ($params['serverusername'] ?? ''));

if (!$passed) {
    fwrite(STDERR, "FAIL: assigned related server was not resolved\n");
    exit(1);
}

fwrite(STDOUT, "comet-product-params-server-resolution-ok\n");
```

- [ ] **Step 2: Run the integration test and verify RED**

Run:

```bash
php accounts/modules/addons/eazybackup/bin/dev/comet_product_params_server_resolution_test.php
```

Expected: exit 1 with `FAIL: assigned related server was not resolved`.

- [ ] **Step 3: Implement relational server resolution**

Replace the name-only resolution in `comet_ProductParams` with:

```php
function comet_ProductParams($pid, $assignedServerId = null)
{
    $product = Capsule::table('tblproducts')->find((int) $pid);
    if (!$product) {
        throw new \RuntimeException('Backup service configuration is unavailable.');
    }

    $groupId = (int) ($product->servergroup ?? 0);
    $serverGroup = $groupId > 0
        ? Capsule::table('tblservergroups')->find($groupId)
        : null;
    if (!$serverGroup) {
        throw new \RuntimeException('Backup service configuration is unavailable.');
    }

    $server = null;
    $assignedServerId = (int) ($assignedServerId ?? 0);
    if ($assignedServerId > 0) {
        $isRelated = Capsule::table('tblservergroupsrel')
            ->where('groupid', $groupId)
            ->where('serverid', $assignedServerId)
            ->exists();
        if (!$isRelated) {
            throw new \RuntimeException('Backup service configuration is unavailable.');
        }
        $server = Capsule::table('tblservers')->find($assignedServerId);
    } else {
        $relatedServerId = (int) (Capsule::table('tblservergroupsrel')
            ->where('groupid', $groupId)
            ->orderBy('serverid')
            ->value('serverid') ?? 0);
        if ($relatedServerId > 0) {
            $server = Capsule::table('tblservers')->find($relatedServerId);
        }
        if (!$server) {
            $server = Capsule::table('tblservers')
                ->where('name', (string) $serverGroup->name)
                ->first();
        }
    }

    if (!$server || trim((string) ($server->hostname ?? '')) === '') {
        throw new \RuntimeException('Backup service configuration is unavailable.');
    }

    return [
        'serverhttpprefix' => $server->secure ? 'https' : 'http',
        'serverhostname' => $server->hostname,
        'serverport' => empty($server->port) ? '' : ':' . $server->port,
        'serverusername' => $server->username,
        'serverpassword' => localAPI('DecryptPassword', ['password2' => $server->password])['password'],
    ];
}
```

- [ ] **Step 4: Run the integration test and verify GREEN**

Run:

```bash
php accounts/modules/addons/eazybackup/bin/dev/comet_product_params_server_resolution_test.php
```

Expected: exit 0 with `comet-product-params-server-resolution-ok`.

- [ ] **Step 5: Commit assigned-server resolution**

```bash
git add accounts/modules/servers/comet/functions.php accounts/modules/addons/eazybackup/bin/dev/comet_product_params_server_resolution_test.php
git commit -m "fix: resolve assigned backup server"
```

### Task 2: Customer-safe profile errors

**Files:**
- Create: `accounts/modules/addons/eazybackup/bin/dev/user_profile_error_sanitization_contract_test.php`
- Modify: `accounts/modules/addons/eazybackup/pages/console/user-profile.php:24-130`

**Interfaces:**
- Consumes: authorized service ID and canonical username.
- Produces: profile data on success or `['error' => GENERIC_MESSAGE]` on configuration/API failure.

- [ ] **Step 1: Write the failing contract test**

```php
<?php
declare(strict_types=1);

$sourceFile = dirname(__DIR__, 2) . '/pages/console/user-profile.php';
$source = @file_get_contents($sourceFile);
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read user-profile controller\n");
    exit(1);
}

$required = [
    "first(['packageid', 'server'])",
    'comet_ProductParams($packageid, $serverid)',
    "We couldn't load this backup user right now.",
    "'classification' =>",
];
$forbidden = [
    '"Error fetching user data: " . $user',
    "'Error fetching user data: ' . \$user",
];

foreach ($required as $marker) {
    if (strpos($source, $marker) === false) {
        fwrite(STDERR, "FAIL: missing safe profile marker\n");
        exit(1);
    }
}
foreach ($forbidden as $marker) {
    if (strpos($source, $marker) !== false) {
        fwrite(STDERR, "FAIL: raw provider error remains customer-visible\n");
        exit(1);
    }
}

fwrite(STDOUT, "user-profile-error-sanitization-ok\n");
```

- [ ] **Step 2: Run the contract test and verify RED**

Run:

```bash
php accounts/modules/addons/eazybackup/bin/dev/user_profile_error_sanitization_contract_test.php
```

Expected: exit 1 with `FAIL: missing safe profile marker`.

- [ ] **Step 3: Make the profile route service-aware**

Replace the package-only lookup with:

```php
$service = Capsule::table('tblhosting')
    ->where('id', $serviceid)
    ->first(['packageid', 'server']);

if (!$service || !(int) $service->packageid) {
    return ['error' => 'Invalid username or package ID not found'];
}

$packageid = (int) $service->packageid;
$serverid = (int) ($service->server ?? 0);
```

Pass `$serverid` into the shared helper:

```php
$params = comet_ProductParams($packageid, $serverid);
```

- [ ] **Step 4: Add sanitized failure handling**

Add a local failure responder before the API call:

```php
$profileFailure = static function (string $classification) use ($serviceid, $packageid, $serverid): array {
    try {
        logModuleCall('eazybackup', 'user_profile_fetch_failed', [
            'serviceId' => $serviceid,
            'packageId' => $packageid,
            'serverId' => $serverid,
        ], [
            'status' => 'error',
            'classification' => $classification,
        ]);
    } catch (\Throwable $ignored) {
    }

    return ['error' => "We couldn't load this backup user right now. Please try again later or contact support."];
};
```

Wrap parameter resolution and the profile call in `try/catch`, retaining all existing debug regions unchanged:

```php
try {
    $params = comet_ProductParams($packageid, $serverid);
    $params['username'] = $username;
    // Existing debug regions remain unchanged here.
    $user = comet_User($params);
    // Existing outcome debug region remains unchanged here.
} catch (\Throwable $exception) {
    return $profileFailure('configuration_or_runtime_failure');
}

if (is_string($user)) {
    return $profileFailure('profile_api_failure');
}
```

- [ ] **Step 5: Run focused checks**

Run:

```bash
php accounts/modules/addons/eazybackup/bin/dev/user_profile_error_sanitization_contract_test.php
php -l accounts/modules/addons/eazybackup/pages/console/user-profile.php
php -l accounts/modules/servers/comet/functions.php
```

Expected: contract output `user-profile-error-sanitization-ok`; both syntax checks report no errors.

- [ ] **Step 6: Commit customer-safe profile errors**

```bash
git add accounts/modules/addons/eazybackup/pages/console/user-profile.php accounts/modules/addons/eazybackup/bin/dev/user_profile_error_sanitization_contract_test.php
git commit -m "fix: sanitize backup profile errors"
```

### Task 3: Runtime verification

**Files:**
- Retain: `accounts/modules/addons/eazybackup/pages/console/user-profile.php` debug regions.
- Read: `/var/www/eazybackup.ca/.cursor/debug-e7e55b.log`

**Interfaces:**
- Consumes: a development request for a service using a differently named server group and server.
- Produces: successful profile rendering plus post-fix NDJSON evidence.

- [ ] **Step 1: Clear this session's debug log**

Use the dedicated file deletion tool on:

```text
/var/www/eazybackup.ca/.cursor/debug-e7e55b.log
```

- [ ] **Step 2: Reproduce on development**

Open Dashboard → Users and open the affected profile through a service whose assigned server belongs to a differently named group.

- [ ] **Step 3: Analyze post-fix evidence**

Confirm the debug entries show:

- route service/package resolution;
- `legacyNameMatch: false`;
- a related server with `hostnamePresent: true`;
- API outcome with `hostnamePresent: true`, `resultIsError: false`, and `uriParseError: false`.

- [ ] **Step 4: Remove instrumentation only after confirmation**

Delete the three `// #region agent log` blocks only after the post-fix evidence succeeds and the user confirms the profile loads.

- [ ] **Step 5: Run final checks**

```bash
php accounts/modules/addons/eazybackup/bin/dev/comet_product_params_server_resolution_test.php
php accounts/modules/addons/eazybackup/bin/dev/user_profile_error_sanitization_contract_test.php
php -l accounts/modules/addons/eazybackup/pages/console/user-profile.php
php -l accounts/modules/servers/comet/functions.php
```

Expected: both regression scripts exit 0 and both syntax checks report no errors.
