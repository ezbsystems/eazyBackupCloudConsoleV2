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
        "protectedItems"  => $protectedItems,
        "devices"         => getUserDevicesDetails($username, $serviceid), // Already implemented earlier
        "jobLogs"         => $jobLogs, // New key for job log details
        "userProfileJson" => htmlspecialchars(json_encode($user, JSON_PRETTY_PRINT)),

    ];
}


