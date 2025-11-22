<?php

/**
 * Comet Provisioning Module
 *
 * @copyright (c) 2019 eazyBackup Systems Ltd.
 */

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Require any libraries needed for the module to function.
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/hooks.php";


// Also, perform any initialization required by the service's library.

/**
 * Define module related meta data.
 *
 * @return array
 */
function comet_MetaData()
{
    return [
        "DisplayName" => "Comet Backup",
        "APIVersion" => "1.1",
        "RequiresServer" => true,
        "ServiceSingleSignOnLabel" => "Login to Comet Server as User",
        "AdminSingleSignOnLabel" => "Login to Comet Server as Admin",
    ];
}

/**
 * Define product configuration options.
 *
 * @return array
 */
function comet_ConfigOptions()
{
    return [
        "PolicyGroupGUID" => [
            "FriendlyName" => "Policy Group",
            "Type" => "dropdown",
            "Description" => "<br>Select a policy to apply to users of this product",
            "Loader" => "comet_ConfigOptionsPoliciesLoader",
            "SimpleMode" => true
        ],
        "StorageProviderID" => [
            "FriendlyName" => "Storage Vault",
            "Type" => "dropdown",
            "Description" => "<br>Select the initial storage vault for new users",
            "Loader" => "comet_ConfigOptionsStorageProvidersLoader",
            "SimpleMode" => true
        ],
    ];
}

/**
 * Get a list of policies from the Comet Server.
 *
 * @param array $params
 *
 * @return array
 * @throws Exception
 */
function comet_ConfigOptionsPoliciesLoader(array $params)
{
    return ["" => "None"] + comet_Server($params)->AdminPoliciesList();
}

/**
 * Get a list of storage providers from the Comet Server.
 *
 * @param array $params
 *
 * @return array
 * @throws Exception
 */
function comet_ConfigOptionsStorageProvidersLoader(array $params)
{
    return ["" => "None"] + comet_Server($params)->AdminRequestStorageVaultProviders();
}

/**
 * Provision a new instance of a product/service.
 *
 * Provision a new instance of a product/service. This is called any
 * time provisioning is requested inside of WHMCS. Depending upon the
 * configuration, this happens:
 *
 *      - When a new order is placed
 *      - When an invoice for a new order is paid
 *      - Upon manual request by an admin user
 *
 * @param array $params
 *
 * @return string "success" or an error message
 * @throws Exception
 */

use Comet\RetentionPolicy;
use Comet\RetentionRange;
use Comet\GetProfileAndHashResponseMessage;
use Comet\UserProfileConfig;
use Comet\DestinationConfig;
function comet_CreateAccount(array $params)
{
    // --- Ensure Comet PHP SDK classes are available ---
    if (!class_exists('\Comet\RetentionPolicy')) {
        $localAutoload = __DIR__ . '/vendor/autoload.php';
        if (is_readable($localAutoload)) {
            require_once $localAutoload;
        }
    }

    // Support WHMCS custom field overrides
    if (!empty(comet_GetCustomField($params, "username")) && !empty(comet_GetCustomField($params, "password"))) {
        $username = comet_GetCustomField($params, "username");
        $password = comet_GetCustomField($params, "password");
        $params["username"] = $username;
        $params["password"] = $password;
    } else {
        $username = $params["username"];
        $password = $params["password"];
    }

    $storageProvider = $params["configoption2"]; // your selected Destination template / provider

    if (!comet_ValidateBackupUsername($username)) {
        return "Backup username must be at least 6 characters and may contain only a-z, A-Z, 0-9, _, ., -";
    }
    if (!comet_ValidateBackupPassword($password)) {
        return "Backup password must be at least 8 characters long.";
    }

    comet_UpdateServiceCredentials($params);

    try {
        // 1) Create user
        comet_Server($params)->AdminAddUser($username, $password, 1);
    } catch (\Exception $e) {
        // Log full context but avoid raw passwords
        $safe = $params;
        unset($safe['password'], $safe['serverpassword']);
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'comet',
                'CreateAccount.AddUser',
                ['username' => $username, 'params' => $safe],
                null,
                $e->getMessage(),
                [$e->getTraceAsString()]
            );
        }
        return "The username $username is already taken" . $e->getMessage();
    }

    try {
        // 2) APPLY USER POLICY *BEFORE* creating a Vault
        //    Your comet_UpdateUser($params) should assign the "eazybackup" policy to this user.
        //    If you want policy retention to win across *all* vaults, make sure the policy has
        //    “Enforce this retention policy for all Storage Vaults” checked in Comet.
        try {
            comet_UpdateUser($params);
        } catch (\Exception $e) {
            $safe = $params; unset($safe['password'], $safe['serverpassword']);
            if (function_exists('logModuleCall')) {
                logModuleCall(
                    'comet',
                    'CreateAccount.UpdateUser',
                    ['username' => $username, 'params' => $safe],
                    null,
                    $e->getMessage(),
                    [$e->getTraceAsString()]
                );
            }
            throw $e;
        }

        // 3) Create the Storage Vault
        try {
            comet_Server($params)->AdminRequestStorageVault($username, $storageProvider);
        } catch (\Exception $e) {
            $safe = $params; unset($safe['password'], $safe['serverpassword']);
            if (function_exists('logModuleCall')) {
                logModuleCall(
                    'comet',
                    'CreateAccount.RequestVault',
                    ['username' => $username, 'storageProvider' => $storageProvider, 'params' => $safe],
                    null,
                    $e->getMessage(),
                    [$e->getTraceAsString()]
                );
            }
            throw $e;
        }

        // 4) Force a 30-day default retention on any Vault that’s still at KEEP_EVERYTHING (801)
        //    Constants from Comet API: 801=KEEP_EVERYTHING, 802=DELETE_EXCEPT; range 902=JOBS_SINCE. :contentReference[oaicite:3]{index=3}
        $keep30 = new \Comet\RetentionPolicy();
        $keep30->Mode = 802; // DELETE_EXCEPT
        $r = new \Comet\RetentionRange();
        $r->Type   = 902; // JOBS_SINCE = relative window
        $r->Days   = 30;
        $r->Weeks  = 0;
        $r->Months = 0;
        $r->Years  = 0;
        $keep30->Ranges = [$r];

        /** @var \Comet\GetProfileAndHashResponseMessage $resp */
        $resp     = comet_Server($params)->AdminGetUserProfileAndHash($username);
        /** @var \Comet\UserProfileConfig $profile */
        $profile  = $resp->Profile;
        $prevHash = $resp->ProfileHash;

        if (!empty($profile->Destinations)) {
            foreach ($profile->Destinations as $guid => $destCfg) {
                // If the vault is using the default “keep forever” (801), override to our 30-day policy
                if (isset($destCfg->DefaultRetention) && is_object($destCfg->DefaultRetention)) {
                    $mode = (int)($destCfg->DefaultRetention->Mode ?? 0);
                } else {
                    $mode = 0;
                }

                if ($mode === 801 /* KEEP_EVERYTHING */ || $mode === 0 /* unset/blank */) {
                    $destCfg->DefaultRetention = $keep30;
                    $profile->Destinations[$guid] = $destCfg;
                }
            }
        }

        // Atomic update using profile hash
        comet_Server($params)->AdminSetUserProfileHash($username, $profile, $prevHash);

        // 5) Optional: tell any live client to refetch profile so the Vault policy is visible immediately
        try { comet_Server($params)->AdminDispatcherRefetchProfile($username, ""); } catch (\Exception $ignore) {}

    } catch (\Exception $e) {
        // Clean up if anything past user creation fails
        try {
            comet_Server($params)->AdminDeleteUser($username);
        } catch (\Exception $_ignore) {
        }

        // Log full context but avoid raw passwords
        $safe = $params;
        unset($safe['password'], $safe['serverpassword']);
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'comet',
                'CreateAccount.Provision',
                ['username' => $username, 'params' => $safe],
                null,
                $e->getMessage(),
                [$e->getTraceAsString()]
            );
        }
        return "Error creating account: " . $e->getMessage();
    }

    return "success";
}


/**
 * Suspend an instance of a product/service. This is invoked
 * automatically by WHMCS when a product becomes overdue on payment or
 * can be called manually by an admin user.
 *
 * @param array $params common module parameters
 *
 * @return string "success" or an error message
 */
function comet_SuspendAccount(array $params)
{
    return comet_SuspendUser($params);
}

/**
 * Un-suspend instance of a product/service. This is invoked
 * automatically upon payment of an overdue invoice for a product, or
 * can be called manually by an admin user.
 *
 * @param array $params
 *
 * @return string "success" or an error message
 */
function comet_UnsuspendAccount(array $params)
{
    return comet_SuspendUser($params, false);
}

/**
 * Terminate instance of a product/service. This can be invoked automatically
 * for overdue products if enabled, or requested manually by an admin user.
 *
 * @param array $params
 *
 * @return string "success" or an error message
 */
function comet_TerminateAccount(array $params)
{
    try {
        comet_Server($params)->AdminDeleteUser($params["username"]);
        comet_ClearUserCache();
    } catch (\Exception $e) {
        logModuleCall(
            "comet",
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return "Error terminating account: " . $e->getMessage();
    }

    return "success";
}

/**
 * Called to apply any change in plan or quotas. It is called
 * to provision upgrade or downgrade orders, as well as being
 * able to be invoked manually by an admin user.
 *
 * This same function is called for upgrades and downgrades of both
 * products and configurable options.
 *
 * Note that when upgrading the product, any configurable options
 * on the product will be removed.
 *
 * @param array $params
 *
 * @return string "success" or an error message
 */
function comet_ChangePackage(array $params)
{
    try {
        comet_UpdateUser($params);
    } catch (\Exception $e) {
        logModuleCall(
            "comet",
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return "Error updating account: " . $e->getMessage();
    }

    return "success";
}

/**
 * Change the password for a Comet Server user. This can occur either due to a
 * client requesting it via the client area or an admin requesting it from the
 * admin side.
 *
 * This option is only available to client end users when the product is in an
 * active status.
 *
 * @param array $params
 *
 * @return string "success" or an error message
 */
function comet_ChangePassword(array $params)
{
    $params["AuthType"] = "Password";

    if (!comet_ValidateBackupPassword($params["password"])) {
        return "Backup password must be at least 8 characters long.";
    }

    try {
        $cometServer = comet_Server($params);
        $response = $cometServer->AdminResetUserPassword($params["username"], $params["AuthType"], $params["password"]);

        // Assuming the response is processed correctly; adjust if response handling is different.
        if ($response->Status !== 200) {
            throw new Exception('Error changing password: ' . $response->Message);
        }

        comet_ClearUserCache();
    } catch (\Exception $e) {
        logModuleCall(
            "comet",
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return "Error changing password: " . $e->getMessage();
    }

    comet_UpdateServiceCredentials($params);

    return "success";
}

/**
 * Test the Comet Server connection.
 *
 * @param array $params
 *
 * @return array
 * @throws Exception
 */
function comet_TestConnection(array $params)
{
    try {
        comet_Server($params)->AdminMetaVersion();
        $success = true;
        $errorMsg = "";
    } catch (\Exception $e) {
        logModuleCall(
            "comet",
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        $success = false;
        $errorMsg = $e->getMessage();
    }

    return [
        "success" => $success,
        "error" => $errorMsg,
    ];
}

/**
 * Client area output logic handling.
 *
 * This function is used to define module specific client area output. It should
 * return an array consisting of a template file and optional additional
 * template variables to make available to that template.
 *
 * The template file you return can be one of two types:
 *
 * * tabOverviewModuleOutputTemplate - The output of the template provided here
 *   will be displayed as part of the default product/service client area
 *   product overview page.
 *
 * * tabOverviewReplacementTemplate - Alternatively using this option allows you
 *   to entirely take control of the product/service overview page within the
 *   client area.
 *
 * Whichever option you choose, extra template variables are defined in the same
 * way. This demonstrates the use of the full replacement.
 *
 * Please Note: Using tabOverviewReplacementTemplate means you should display
 * the standard information such as pricing and billing details in your custom
 * template or they will not be visible to the end user.
 *
 * @param array $params
 *
 * @return array
 * @throws Exception
 */
function comet_ClientArea(array $params)
{
    // Determine the requested action and set service call parameters based on
    // the action.
    $action = $_REQUEST["a"] ?? "";

    if ($action == "revoke") {
        $response = comet_ClientAreaRevoke($params);
    } else if ($action == "jobreport") {
        $response = comet_ClientAreaJobReport($params);
    } else if ($action == "run") {
        comet_ClientAreaRun($params);
        sleep(1);
        header("Location: clientarea.php?action=productdetails&id=" . $params["serviceid"] . "&msg=ok");
        exit();
    } else {
        $response = comet_ClientAreaManage($params);
    }

    return $response;
}

/**
 * Renders output for the client area "manage" tab.
 *
 * @param array $params
 * @return array
 * @throws Exception
 */
function comet_ClientAreaManage(array $params)
{
    try {
        $user = comet_User($params);
    } catch (\Exception $e) {
        // No Comet user found
        return $e->getMessage();
    }

    $activeDevices = [];
    foreach (comet_Server($params)->AdminDispatcherListActive() as $id => $connection) {
        $activeDevices[$connection->DeviceID] = $id;
    }

    $deviceSources = [];
    foreach ($user->Sources as $source) {
        if (isset($deviceSources[$source->OwnerDevice])) {
            $deviceSources[$source->OwnerDevice]++;
        } else {
            $deviceSources[$source->OwnerDevice] = 1;
        }
    }

    $devices = [];
    foreach ($user->Devices as $id => $device) {
        $devices[$id] = [
            "id" => $id,
            "name" => $device->FriendlyName,
            "protecteditems" => $deviceSources[$id],
            "activity" => in_array($id, array_keys($activeDevices)) ? "Online" : "Offline",
        ];
    }

    $destinations = [];
    foreach ($user->Destinations as $id => $destination) {
        $destinations[$id] = [
            "description" => $destination->Description,
            "size" => comet_HumanFileSize($destination->Statistics->ClientProvidedSize->Size),
        ];
    }

    $sources = [];
    foreach ($user->Sources as $id => $source) {
        $sources[$id] = [
            "id" => $id,
            "jobid" => $source->Statistics->LastBackupJob->GUID,
            "laststatus" => comet_HumanJobStatus($source->Statistics->LastBackupJob->Status),
            "laststatusicon" => comet_JobStatusIcon($source->Statistics->LastBackupJob->Status),
            "description" => $source->Description,
            "type" => comet_HumanProtectedItemType($source),
            "lastbackupjob" => date("Y-m-d H:i:s", $source->ModifyTime),
            "size" => comet_HumanFileSize($source->Statistics->LastBackupJob->TotalSize),
            "device" => comet_DeviceName($user, $source->OwnerDevice),
            "deviceid" => $activeDevices[$source->Statistics->LastBackupJob->DeviceID],
            "deviceguid" => $source->Statistics->LastBackupJob->DeviceID,
            "destinationid" => $source->Statistics->LastBackupJob->DestinationGUID,
        ];
    }

    foreach (comet_RunningJobs($params) as $job) {
        foreach ($sources as $id => $source) {
            if ($job->StartTime > $source->ModifyTime && strtolower($id) == strtolower($job->SourceGUID)) {
                $sources[$id]["jobid"] = $job->GUID;
                $sources[$id]["laststatus"] = comet_HumanJobStatus($job->Status);
                $sources[$id]["laststatusicon"] = comet_JobStatusIcon($job->Status);
                $sources[$id]["lastbackupjob"] = date("Y-m-d H:i:s", $job->StartTime);
            }
        }
    }

    return [
        "templatefile" => "templates/clientarea",
        "vars" => [
            "user" => $user,
            "devices" => $devices,
            "hasdevices" => $user->MaximumDevices - count($devices),
            "devicestring" => $user->MaximumDevices > 1 ? "devices" : "device",
            "deviceusedpercent" => comet_DeviceCountPercent(comet_User($params)),
            "deviceprogress" => comet_ProgressClass(comet_DeviceCountPercent(comet_User($params))),
            "sources" => $sources,
            "quota" => comet_HumanFileSize(comet_User($params)->AllProtectedItemsQuotaBytes),
            "quotaused" => comet_HumanFileSize(comet_StorageUsedBytes(comet_User($params))),
            "quotausedpercent" => comet_StorageQuotaUsedPercent(comet_User($params)),
            "quotaprogress" => comet_ProgressClass(comet_StorageQuotaUsedPercent(comet_User($params))),
        ],
    ];
}

/**
 * Handles the client area "revoke" action.
 *
 * @param array $params
 * @return array
 * @throws Exception
 */
function comet_ClientAreaRevoke(array $params)
{

    $user = comet_User($params);

    $vars = [
        "id" => $_REQUEST["id"],
    ];

    if (empty($_REQUEST["deviceid"])) {
        $vars["invalid"] = true;
    } else {
        $vars["deviceid"] = $_REQUEST["deviceid"];
        $vars["devicename"] = $user->Devices[$_REQUEST["deviceid"]]->FriendlyName;


        if ($_REQUEST["confirm"] == "1") {
            try {
                comet_RevokeDevice($params, $_REQUEST["deviceid"]);
            } catch (\Exception $e) {
                $vars["error"] = true;
            }

            $vars["success"] = true;
        } else {
            $deviceSources = [];
            $stored = 0;
            foreach ($user->Sources as $source) {
                if (isset($deviceSources[$source->OwnerDevice])) {
                    $deviceSources[$source->OwnerDevice]++;
                } else {
                    $deviceSources[$source->OwnerDevice] = 1;
                }

                if ($source->OwnerDevice == $_REQUEST["deviceid"]) {
                    $stored += $source->Statistics->LastBackupJob->TotalSize;
                }
            }

            $vars["deviceprotecteditems"] = $deviceSources[$_REQUEST["deviceid"]] ?? 0;
            $vars["devicestored"] = comet_HumanFileSize($stored);
        }
    }

    return [
        "tabOverviewReplacementTemplate" => "templates/clientarearevoke",
        "templateVariables" => $vars,
    ];
}

/**
 * Handles the client area "job report" action.
 *
 * @param array $params
 * @return array
 * @throws Exception
 */
function comet_ClientAreaJobReport(array $params)
{
    $job = comet_GetJobDetails($params, $_REQUEST["jobid"]);
    $report = comet_GetJobReport($params, $_REQUEST["jobid"]);

    $rows = [];
    foreach (explode("\n", $report) as $line) {
        $line = explode("|", $line);
        $date = date("Y-m-d H:i:s", $line[0]);

        if ($line[1] == "E") {
            $status = "<span class=\"text-danger\">Error</span>";
        } else if ($line[1] == "W") {
            $status = "<span class=\"text-warning\">Warning</span>";
        } else {
            $status = "<span>Info</span>";
        }

        $rows[] = [$date, $status, $line[2]];
    }

    $vars = [
        "rows" => $rows,
        "username" => $job->Username,
        "device" => comet_User($params)->Devices[$job->DeviceID]->FriendlyName,
        "protecteditem" => comet_User($params)->Sources[$job->SourceGUID]->Description,
        "storagevault" => comet_User($params)->Destinations[$job->DestinationGUID]->Description,
        "type" => comet_HumanProtectedItemType(comet_User($params)->Sources[$job->SourceGUID]),
        "status" => comet_HumanJobStatus($job->Status),
        "started" => date("Y-m-d H:i:s", $job->StartTime),
        "stopped" => $job->EndTime > 0 ? date("Y-m-d H:i:s", $job->EndTime) : "Not Stopped",
        "totalsize" => comet_HumanFileSize($job->TotalSize),
        "stats" => $job->TotalFiles . " files, " . $job->TotalDirectories . " folders",
        "uploaded" => comet_HumanFileSize($job->UploadSize),
        "downloaded" => comet_HumanFileSize($job->DownloadSize),
    ];

    return [
        "tabOverviewReplacementTemplate" => "templates/jobreport",
        "templateVariables" => $vars,
    ];
}

/**
 * Handles the client area "run backup" action.
 *
 * @param array $params
 * @return array
 * @throws Exception
 */
function comet_ClientAreaRun(array $params)
{
    comet_RunBackupJob($params, $_POST["deviceid"], $_POST["sourceid"], $_POST["destinationid"]);
}
