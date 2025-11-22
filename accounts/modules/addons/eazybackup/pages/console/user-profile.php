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
    $packageid = Capsule::table('tblhosting')
        ->where('id', $serviceid)
        ->value('packageid');

    if (!$packageid) {
        return ["error" => "Invalid username or package ID not found"];
    }

    // Fetch the server parameters and user data using the Comet API
    $params = comet_ProductParams($packageid);
    $params['username'] = $username;
    $user = comet_User($params);
    if (is_string($user)) {
        return ["error" => "Error fetching user data: " . $user];
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

    ];
}


