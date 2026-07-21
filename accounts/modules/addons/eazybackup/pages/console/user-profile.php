<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/../../../../../modules/servers/comet/functions.php';  // Comet Server core functions
require_once __DIR__ . '/../../../../../modules/servers/comet/summary_functions.php';


/**
 * Fetches and processes user profile data.
 *
 * @param array $vars Additional module variables.
 * @return array An associative array of user data or an 'error' key if something goes wrong.
 */
// At the top, ensure summary_functions.php is loaded
function eazybackup_user_profile(array $vars = []) {
    // Prefer values passed from the router; fall back to GET for compatibility
    $username  = $vars['username']  ?? ($_GET['username']  ?? null);
    $serviceid = (int) ($vars['serviceid'] ?? ($_GET['serviceid'] ?? 0));
    if (!$serviceid) {
        return ["error" => "Service ID is required"];
    }

    // Fetch the package ID for the given service id to avoid username ambiguity
    $service = Capsule::table('tblhosting')
        ->where('id', $serviceid)
        ->first(['packageid', 'server']);

    if (!$service || !(int) $service->packageid) {
        return ['error' => 'Invalid username or package ID not found'];
    }

    $packageid = (int) $service->packageid;
    $serverid = (int) ($service->server ?? 0);

    // #region agent log
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-e7e55b.log', json_encode([
        'sessionId' => 'e7e55b',
        'runId' => 'pre-fix',
        'hypothesisId' => 'H3',
        'location' => 'pages/console/user-profile.php:34',
        'message' => 'Resolved profile route identifiers',
        'data' => [
            'serviceId' => $serviceid,
            'packageId' => (int) $packageid,
            'usernamePresent' => is_string($username) && trim($username) !== '',
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    // #endregion

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

    try {
        $params = comet_ProductParams($packageid, $serverid);
        $params['username'] = $username;

        // #region agent log
    try {
        $debugGroupId = (int) (Capsule::table('tblproducts')->where('id', $packageid)->value('servergroup') ?? 0);
        $debugGroupName = $debugGroupId > 0
            ? (string) (Capsule::table('tblservergroups')->where('id', $debugGroupId)->value('name') ?? '')
            : '';
        $debugLegacyServer = $debugGroupName !== ''
            ? Capsule::table('tblservers')->where('name', $debugGroupName)->first(['id', 'hostname'])
            : null;
        $debugRelatedServerIds = $debugGroupId > 0
            ? Capsule::table('tblservergroupsrel')->where('groupid', $debugGroupId)->pluck('serverid')->map(static function ($id) {
                return (int) $id;
            })->values()->all()
            : [];
        $debugRelatedServers = empty($debugRelatedServerIds)
            ? []
            : Capsule::table('tblservers')->whereIn('id', $debugRelatedServerIds)->get(['id', 'hostname'])->map(static function ($server) {
                return [
                    'id' => (int) $server->id,
                    'hostnamePresent' => trim((string) ($server->hostname ?? '')) !== '',
                ];
            })->values()->all();
        @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-e7e55b.log', json_encode([
            'sessionId' => 'e7e55b',
            'runId' => 'pre-fix',
            'hypothesisId' => 'H1,H2',
            'location' => 'pages/console/user-profile.php:51',
            'message' => 'Compared legacy and relational server resolution',
            'data' => [
                'serverGroupId' => $debugGroupId,
                'legacyNameMatch' => $debugLegacyServer !== null,
                'legacyHostnamePresent' => $debugLegacyServer !== null
                    && trim((string) ($debugLegacyServer->hostname ?? '')) !== '',
                'relatedServers' => $debugRelatedServers,
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $debugException) {
        @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-e7e55b.log', json_encode([
            'sessionId' => 'e7e55b',
            'runId' => 'pre-fix',
            'hypothesisId' => 'H1,H2',
            'location' => 'pages/console/user-profile.php:51',
            'message' => 'Server resolution diagnostics failed',
            'data' => ['exceptionClass' => get_class($debugException)],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
        // #endregion

        $user = comet_User($params);

        // #region agent log
    $debugHostname = trim((string) ($params['serverhostname'] ?? ''));
    $debugUserError = is_string($user) ? $user : '';
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-e7e55b.log', json_encode([
        'sessionId' => 'e7e55b',
        'runId' => 'pre-fix',
        'hypothesisId' => 'H2,H4',
        'location' => 'pages/console/user-profile.php:102',
        'message' => 'Profile API call outcome',
        'data' => [
            'scheme' => (string) ($params['serverhttpprefix'] ?? ''),
            'hostnamePresent' => $debugHostname !== '',
            'hostnameContainsScheme' => preg_match('~^https?://~i', $debugHostname) === 1,
            'hostnameContainsPath' => strpos(trim(preg_replace('~^https?://~i', '', $debugHostname), '/'), '/') !== false,
            'serverUsernamePresent' => trim((string) ($params['serverusername'] ?? '')) !== '',
            'resultIsError' => is_string($user),
            'uriParseError' => stripos($debugUserError, 'Unable to parse URI') !== false,
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        // #endregion
    } catch (\Throwable $exception) {
        return $profileFailure('configuration_or_runtime_failure');
    }

    if (is_string($user)) {
        return $profileFailure('profile_api_failure');
    }

    // Fetch vault data, preserving the GUID keys
    $vaults = [];
    if (isset($user->Destinations)) {
        foreach ($user->Destinations as $guid => $destination) {
            $vaults[$guid] = json_decode(json_encode($destination), true);
        }
    }
    
    // Convert CreateTime to a readable format, determine TOTP status, etc.
    $createdDate    = date('Y-m-d H:i:s', $user->CreateTime);
    $totpStatus     = (!empty($user->AllowPasswordAndTOTPLogin)) ? 'Active' : 'Disabled';
    $msAccountCount = MicrosoftAccountCount($user);

    // Compute Hyper-V and VMware VM usage counts from the profile (Sources tree)
    $hvGuestCount = 0;
    $vmwGuestCount = 0;
    try {
        if (isset($user->Sources) && is_array($user->Sources)) {
            foreach ($user->Sources as $srcId => $src) {
                if (!is_object($src)) { continue; }
                $engine = isset($src->Engine) ? strtolower((string)$src->Engine) : '';
                if ($engine === '' || (strpos($engine, 'hyperv') === false && strpos($engine, 'vmware') === false)) {
                    continue;
                }
                $totalVm = 0;
                $isSuccess = false;
                if (isset($src->Statistics) && is_object($src->Statistics)) {
                    // Prefer LastSuccessfulBackupJob if present
                    if (isset($src->Statistics->LastSuccessfulBackupJob) && is_object($src->Statistics->LastSuccessfulBackupJob)) {
                        $job = $src->Statistics->LastSuccessfulBackupJob;
                        if (isset($job->TotalVmCount)) { $totalVm = (int)$job->TotalVmCount; }
                        $isSuccess = true;
                    } elseif (isset($src->Statistics->LastBackupJob) && is_object($src->Statistics->LastBackupJob)) {
                        $job = $src->Statistics->LastBackupJob;
                        if (isset($job->TotalVmCount)) { $totalVm = (int)$job->TotalVmCount; }
                        if (isset($job->Status)) {
                            $status = is_numeric($job->Status) ? (int)$job->Status : strtoupper((string)$job->Status);
                            $isSuccess = ($status === 5000 || $status === 'SUCCESS');
                        }
                    }
                }
                if ($isSuccess && $totalVm > 0) {
                    if (strpos($engine, 'hyperv') !== false) { $hvGuestCount += $totalVm; }
                    if (strpos($engine, 'vmware') !== false) { $vmwGuestCount += $totalVm; }
                }
            }
        }
    } catch (\Throwable $e) { /* ignore */ }

    // Get optimized protected items for this account (from previous integration)
    $protectedItems = getUserProtectedItemsDetails($username, $serviceid);

    // **NEW: Retrieve Job Logs for this account**
    $jobLogs = getUserJobLogsDetails($username, $serviceid);

    // Resolve Lite (reduced storage) plan status for this service so the
    // template can lock down quota / vault controls accordingly.
    $isLitePlan = false;
    $liteCapGb  = 0;
    if (function_exists('comet_LiteCapForPid')) {
        $liteCapGb  = (int)comet_LiteCapForPid((int)$packageid);
        $isLitePlan = $liteCapGb > 0;
    }

    return [
        "modulelink"      => $vars['modulelink'],
        "serviceid"       => $serviceid,
        "username"        => $username,
        "packageid"       => $packageid,
        "user"            => $user,
        "vaults"          => $vaults,
        "createdDate"     => $createdDate,
        "totpStatus"      => $totpStatus,
        "msAccountCount"  => $msAccountCount,
        "hvGuestCount"    => $hvGuestCount,
        "vmwGuestCount"   => $vmwGuestCount,
        "protectedItems"  => $protectedItems,
        "devices"         => getUserDevicesDetails($username, $serviceid), // Already implemented earlier
        "jobLogs"         => $jobLogs, // New key for job log details
        "userProfileJson" => htmlspecialchars(json_encode($user, JSON_PRETTY_PRINT)),
        "isLitePlan"      => $isLitePlan,
        "liteCapGb"       => $liteCapGb,

    ];
}


