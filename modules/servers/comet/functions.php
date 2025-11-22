<?php

/**
 * Comet Provisioning Module Functions
 *
 * @copyright (c) 2019 eazyBackup Systems Ltd.
 */

use Comet\BackupJobDetail;
use Comet\Server;
use Comet\UserProfileConfig;
use Comet\AdminGetJobsForUserRequest;
use Comet\JobStatus;
use Comet\JobType;
use Comet\CometItem;
use Comet\CometDevice;
use Comet\CometUser;
use WHMCS\Database\Capsule;

// Require any libraries needed for the module to function FIRST.
require_once __DIR__ . "/vendor/autoload.php";

// Include necessary classes
require_once __DIR__ . "/JobType.php";
require_once __DIR__ . "/JobStatus.php";
require_once __DIR__ . "/CometItem.php";
require_once __DIR__ . "/CometDevice.php";
require_once __DIR__ . "/CometUser.php";


if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Also, perform any initialization required by the service's library.

/**
 * @param $sid
 * @return mixed
 */
function comet_ServiceParams($sid)
{
    $server = new WHMCS\Module\Server();
    $server->loadByServiceID($sid);

    return $server->buildParams();
}

/**
 * @param  int  $pid
 * @return array
 * @throws Exception
 */
function comet_ProductParams($pid)
{
    $product = Capsule::table("tblproducts")->find($pid);
    if (!$product) {
        // Some hooks call this for non Comet products; return a null-param
        // skeleton so callers can safely bail out.
        return [
            "serverhttpprefix" => null,
            "serverhostname"   => null,
            "serverport"       => null,
            "serverusername"   => null,
            "serverpassword"   => null,
        ];
    }

    $groupid = (int)($product->servergroup ?? 0);
    if ($groupid <= 0) {
        return [
            "serverhttpprefix" => null,
            "serverhostname"   => null,
            "serverport"       => null,
            "serverusername"   => null,
            "serverpassword"   => null,
        ];
    }

    $servergroup = Capsule::table("tblservergroups")->find($groupid);
    if (!$servergroup) {
        return [
            "serverhttpprefix" => null,
            "serverhostname"   => null,
            "serverport"       => null,
            "serverusername"   => null,
            "serverpassword"   => null,
        ];
    }

    // For historic reasons, older setups matched server name == group name.
    // Prefer an explicit group to server mapping when present; fall back to name match.
    $server = Capsule::table("tblservergroupsrel")
        ->join("tblservers", "tblservers.id", "=", "tblservergroupsrel.serverid")
        ->where("tblservergroupsrel.groupid", $groupid)
        ->orderBy("tblservers.id", "asc")
        ->first();

    if (!$server) {
        $server = Capsule::table("tblservers")->where(["name" => $servergroup->name])->first();
    }

    if (!$server) {
        return [
            "serverhttpprefix" => null,
            "serverhostname"   => null,
            "serverport"       => null,
            "serverusername"   => null,
            "serverpassword"   => null,
        ];
    }

    return [
        "serverhttpprefix" => !empty($server->secure) ? "https" : "http",
        "serverhostname"   => (string)$server->hostname,
        "serverport"       => empty($server->port) ? "" : ":" . $server->port,
        "serverusername"   => (string)$server->username,
        "serverpassword"   => (string)localAPI("DecryptPassword", ['password2' => $server->password])["password"],
    ];
}

/**
 * @param  array  $params
 * @return Server
 * @throws Exception
 */
function comet_Server(array $params)
{
    try {
        // If server hostname is missing, resolve from service or product context.
        if (!isset($params["serverhostname"]) || $params["serverhostname"] === "") {
            if (isset($params["serviceid"])) {
                $params = comet_ServiceParams((int)$params["serviceid"]);
            } elseif (isset($params["pid"])) {
                $params = comet_ProductParams((int)$params["pid"]);
            } else {
                throw new \RuntimeException("comet_Server: missing serverhostname and no serviceid/pid provided");
            }
        }

        $hostname = preg_replace(["^http://^i", "^https://^i", "^/^"], "", (string)$params["serverhostname"]);
        if ($hostname === "") {
            throw new \RuntimeException("comet_Server: empty serverhostname after normalization");
        }

        $scheme = isset($params["serverhttpprefix"]) && $params["serverhttpprefix"] !== ""
            ? (string)$params["serverhttpprefix"]
            : "https";

        $url = $scheme . "://" . $hostname . "/";

        return new \Comet\Server($url, (string)$params["serverusername"], (string)$params["serverpassword"]);
    } catch (\Exception $e) {
        // Log a safe subset of params (never passwords) then rethrow so callers can handle.
        $safe = $params;
        unset($safe["password"], $safe["serverpassword"]);
        if (function_exists('logModuleCall')) {
            try {
                logModuleCall(
                    "comet",
                    "ServerCreate",
                    $safe,
                    null,
                    $e->getMessage(),
                    [$e->getTraceAsString()]
                );
            } catch (\Throwable $__) {
                // ignore logging failures
            }
        }
        throw $e;
    }
}

/**
 * @param array $params
 * @throws Exception
 */
function comet_UpdateUser(array $params)
{
    if (!comet_ValidateBackupUsername($params["username"])) {
        throw new \Exception("Invalid backup username");
    }

    $policyId = $params["configoption1"];
    $deviceQuota = 0;
    $storageQuota = $deviceQuota + $params["configoptions"]["Additional Storage"];

    // Get the user profile and modify it
    $profile = comet_Server($params)->AdminGetUserProfile($params["username"]);

    // Set the user's policy
    $profile->PolicyID = $policyId;

    // Set the user's email address
    $profile->Emails = [$params["clientsdetails"]["email"]];
    $profile->SendEmailReports = true;

    // Set the user's device and storage quotas
    $profile->MaximumDevices = $deviceQuota;
    $profile->AllProtectedItemsQuotaBytes = 2 ** 40 * $storageQuota;
    $profile->AllProtectedItemsQuotaEnabled = false;


    // Set the user's RequirePasswordChange true
    //$profile->RequirePasswordChange = true;

    // Save the user profile to the Comet server
    comet_Server($params)->AdminSetUserProfile($params["username"], $profile);
    comet_ClearUserCache();
}

/**
 * @param  array  $params
 * @param  bool  $suspended
 * @return string
 */
function comet_SuspendUser(array $params, bool $suspended = true)
{
    try {
        $user = comet_Server($params)->AdminGetUserProfile($params["username"]);
        $user->IsSuspended = $suspended;
        comet_Server($params)->AdminSetUserProfile($params["username"], $user);
        comet_ClearUserCache();
    } catch (\Exception $e) {
        logModuleCall(
            "comet",
            __FUNCTION__,
            $params["username"],
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return "Error updating suspend status: " . $e->getMessage();
    }

    return "success";
}

/**
 * @param  array  $params
 * @param  string  $deviceId
 * @return string
 * @throws Exception
 */
function comet_RevokeDevice(array $params, string $deviceId)
{
    try {
        comet_Server($params)->AdminRevokeDevice($params["username"], $deviceId);
        comet_ClearUserCache();
    } catch (\Exception $e) {
        logModuleCall(
            "comet",
            __FUNCTION__,
            $params["username"],
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return "Error revoking device: " . $e->getMessage();
    }

    return "success";
}

/**
 * @param array $params
 * @param bool $retrieve
 * @return UserProfileConfig
 * @throws Exception
 */
function comet_User(array $params, $retrieve = false)
{
    try {
        if (!comet_CachedUser($params["username"]) || $retrieve) {
            // FIXME: Add params
            // FIXME: Invalidate cache if the user has changed...
            $_SESSION["cometusercache"] = comet_Server($params)->AdminGetUserProfile($params["username"]);
        }
        return comet_CachedUser($params["username"]);
    } catch (\Exception $e) {
        return $e->getMessage() . ' comet_User function error';
    }
}

/**
 * @param string $username
 * @return UserProfileConfig|false
 */
function comet_CachedUser($username)
{
    try {
        /** @var UserProfileConfig $c */
        $c = $_SESSION["cometusercache"];
        if ($c instanceof UserProfileConfig && $c->Username == $username) {
            return $c;
        }

        //return false;
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 * @return void
 */
function comet_ClearUserCache()
{
    unset($_SESSION["cometusercache"]);
}

/**
 * @param  array  $params
 * @return BackupJobDetail[]
 * @throws Exception
 */
function comet_RunningJobs(array $params)
{
    return comet_Server($params)->AdminGetJobsForDateRange(0, 0);
}

/**
 * @param  array  $params
 * @param  string  $jobid
 * @return BackupJobDetail
 * @throws Exception
 */
function comet_GetJobDetails(array $params, $jobid)
{
    return comet_Server($params)->AdminGetJobProperties($jobid);
}

/**
 * @param  array  $params
 * @param  string  $jobid
 * @return string
 * @throws Exception
 */
function comet_GetJobReport(array $params, $jobid)
{
    return comet_Server($params)->AdminGetJobLog($jobid);
}

/**
 * Fetches the job report from the Comet Server
 *
 * @param int $clientId
 * @param string $jobId
 * @return string|null
 */
function getJobReport($clientId, $jobId) {
    $products = Capsule::table('tblhosting')
        ->where('userid', $clientId)
        ->get(['username', 'packageid']); // Fetch the package ID

    foreach ($products as $product) {
        $params = comet_ProductParams($product->packageid); // Pass the package ID to the function
        $params['username'] = $product->username;

        if ($params['serverhostname'] === null || $params['serverusername'] === null) {
            // Skip if server details are not found
            continue;
        }

        try {
            $cometServer = comet_Server($params);
            $jobReport = comet_GetJobReport($params, $jobId);

            if ($jobReport) {
                return $jobReport;
            }
        } catch (Exception $e) {
            error_log("Error fetching job report for user {$params['username']} and job ID $jobId: " . $e->getMessage());
        }
    }

    return null;
}


/**
 * @param array $params
 * @param string $connectionGuid
 * @param string $protectedItemGuid
 * @param string $storageVaultGuid
 * @return \Comet\APIResponseMessage
 * @throws Exception
 */
function comet_RunBackupJob(array $params, $connectionGuid, $protectedItemGuid, $storageVaultGuid)
{
    return comet_Server($params)
        ->AdminDispatcherRunBackupCustom($connectionGuid, $protectedItemGuid, $storageVaultGuid);
}

/**
 * @param UserProfileConfig $user
 * @return int
 * @throws Exception
 */
function comet_StorageUsedBytes($user)
{
    $storageUsed = 0;
    foreach ($user->Sources as $source) {
        $storageUsed += $source->Statistics->LastBackupJob->TotalSize;
    }

    return $storageUsed;
}

/**
 * @param UserProfileConfig $user
 * @return string
 * @throws Exception
 */
function comet_StorageQuotaUsedPercent($user)
{
    $quota = $user->AllProtectedItemsQuotaBytes;

    if ($quota > 0) {
        return sprintf("%.0f", comet_StorageUsedBytes($user) / $quota * 100.0);
    } else {
        return "0";
    }
}

/**
 * @param UserProfileConfig $user
 * @param string $deviceId
 * @return string
 */
function comet_DeviceName($user, $deviceId)
{
    foreach ($user->Devices as $id => $device) {
        if ($id == $deviceId) {
            return $device->FriendlyName;
        }
    }
}

/**
 * @param UserProfileConfig $user
 * @return int
 * @throws Exception
 */
function comet_DeviceCount($user)
{
    return count($user->Devices);
}

/**
 * @param UserProfileConfig $user
 * @return float
 * @throws Exception
 */
function comet_DeviceCountPercent($user)
{
    // Check if MaximumDevices is set and greater than zero
    if (empty($user->MaximumDevices) || $user->MaximumDevices <= 0) {
        // Avoid division by zero, return 0% as default
        return 0.0;
    }

    return comet_DeviceCount($user) / $user->MaximumDevices * 100.0;
}


/**
 * @param $percent
 * @return string
 */
function comet_ProgressClass($percent)
{
    if ($percent > 80) {
        return "danger";
    } else if ($percent > 50) {
        return "warning";
    }

    return "success";
}

/**
 * @param array $params
 */
function comet_UpdateServiceCredentials($params)
{
    // Overwrite auto-generated username and password
    $dbQueryParams = [
        "username" => $params["username"],
        "password" => encrypt($params["password"]),       
    ];


    // Apply DB updates
    Capsule::table("tblhosting")->where("id", $params["serviceid"])->update($dbQueryParams);

    // Remove the unencrypted custom fields from the db

    Capsule::table("tblcustomfieldsvalues")->where("relid", $params["serviceid"])->update(["value" => ""]);
}

/**
 * @param string $str
 * @return bool
 */
function comet_ValidateBackupUsername($str)
{
    return preg_match("/^[a-zA-Z0-9_.-]{6,}$/", $str);
}

/**
 * @param string $str
 * @return bool
 */
function comet_ValidateBackupPassword($str)
{
    return preg_match("/^.{8,}$/", $str);
}

/**
 * @return array
 */
function comet_GetPids()
{
    $pids = [];
    $products = localAPI("GetProducts", ["module" => "comet"])["products"]["product"];
    foreach ($products as $product) {
        $pids[] = $product["pid"];
    }

    return $pids;
}

/**
 * @param $str
 * @return mixed
 */
function comet_GetPidForProduct($str)
{
    $products = localAPI("GetProducts", ["module" => "comet"])["products"]["product"];
    foreach ($products as $product) {
        if (strpos(strtolower($product["name"]), $str) !== false) {
            return $product["pid"];
        }
    }
}

/**
 * @param $pid int
 * @return array
 */
function comet_GetCustomFieldsForProduct($pid)
{
    return localAPI("GetProducts", ["pid" => $pid])["products"]["product"][0]["customfields"]["customfield"];
}

/**
 * @param $pid int
 * @param $str string
 * @return int|bool
 */
function comet_GetCustomFieldId($pid, $str)
{
    foreach (comet_GetCustomFieldsForProduct($pid) as $customfield) {
        if (strpos(strtolower($customfield["name"]), $str) !== false) {
            return $customfield["id"];
        }
    }

    return false;
}

/**
 * @param $params
 * @param $token
 * @return mixed
 */
function comet_GetCustomField($params, $str)
{
    foreach ($params["customfields"] as $key => $value) {
        if (strpos(strtolower($key), $str) !== false) {
            return $value;
        }
    }
}

function comet_GetClientGroup()
{
    global $clientsdetails;

    if ($clientsdetails["groupid"] == 0) {
        return "eazyBackup";
    }

    return Capsule::table("tblclientgroups")->find($clientsdetails["groupid"])->groupname;
}

/**
 * @param int $bytes
 * @param int $decimals
 * @return string
 */
function comet_HumanFileSize($bytes, $decimals = 0)
{
    $size = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = (int) floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

/**
 * @param \Comet\SourceConfig $source
 * @return string
 */
function comet_HumanProtectedItemType($source)
{
    switch ($source->Engine) {
        case \Comet\Def::ENGINE_BUILTIN_FILE:
            return "Files and Folders";
        case \Comet\Def::ENGINE_BUILTIN_EXCHANGEEDB:
            return "Microsoft Exchange Server";
        case \Comet\Def::ENGINE_BUILTIN_MSSQL:
            return "Microsoft SQL Server";
        case \Comet\Def::ENGINE_BUILTIN_MYSQL:
            return "MySQL";
        case "engine1/mongodb":
            return "MongoDB";
        case \Comet\Def::ENGINE_BUILTIN_STDOUT:
            return "Program Output";
        case \Comet\Def::ENGINE_BUILTIN_SYSTEMSTATE:
            return "Windows System State";
        case \Comet\Def::ENGINE_BUILTIN_WINDOWSSYSTEM:
            return "Windows System Backup";
        case \Comet\Def::ENGINE_BUILTIN_VSSWRITER:
            return "Application-Aware Writer";
        case \Comet\Def::ENGINE_BUILTIN_HYPERV:
            return "Microsoft Hyper-V";
    }
}

/**
 * @param int $status
 * @return string
 */
function comet_HumanJobStatus($status)
{
    switch ($status) {
        case 5000:
            return "Success";
        case 6000:
        case 6001:
            return "Running";
        case 7000:
            return "Timeout";
        case 7001:
            return "Warning";
        case 7002:
        case 7003:
            return "Error";
        case 7004:
        case 7006:
            return "Skipped";
        case 7005:
            return "Cancelled";
        default:
            return "Unknown";
    }
}

/**
 * @param int $status
 * @return string
 */
function comet_JobStatusIcon($status)
{
    switch ($status) {
        case 5000:
            return "fas fa-check text-success";
        case 6000:
        case 6001:
            return "fas fa-play text-success";
        case 7000:
            return "fas fa-clock text-warning";
        case 7001:
            return "fas fa-exclamation text-warning";
        case 7002:
        case 7003:
            return "fas fa-exclamation-triangle text-danger";
        case 7004:
        case 7006:
            return "fas fa-forward text-info";
        case 7005:
            return "fas fa-ban text-danger";
        default:
            return "fas fa-question text-info";
    }
}


/**
 * @param string $optionName
 * @param int $groupId
 * @return int
 */

function get_base_price($optionName, $groupId)
{

    $AdditionalDeviceID = Capsule::table('tblproductconfigoptions')->where([
        ["optionname", "=", $optionName],
        ["gid", "=", $groupId]
    ])->value('id');
    // return   $AdditionalDeviceID;

    if ($AdditionalDeviceID) {
        $sub_optionID = Capsule::table('tblproductconfigoptionssub')->where("configid", "=", $AdditionalDeviceID)->value('id');

        if ($sub_optionID) {
            $price = Capsule::table('tblpricing')->where("relid", "=", $sub_optionID)->value('monthly');
            return  $price;
        }
    }
}

function MicrosoftAccountCount($user)
{
    $microsoftUser = [];
    foreach ($user->Sources as $id => $microsoft365User) {
        $microsoftUser[] = $microsoft365User->Statistics->LastBackupJob->TotalAccountsCount;
    }
    return array_sum($microsoftUser);
}



/**
 * @param $params

 * @return
 */
// function comet_updatePasswordCustomfield(array $params)
// {
//     $passwordCustomfieldName = "Create account password";
//     $pid = $params["pid"];
//     $serviceid = $params["serviceid"];
//     $customfieldID = Capsule::table("tblcustomfields")->where(["fieldname" => $passwordCustomfieldName, "relid" => $pid])->value('id');

//     //Capsule::table("tblcustomfieldsvalues")->where(["relid" => $serviceid, "fieldid" => $customfieldID])->update(["value" => encrypt($params["password"])]);
//     Capsule::table("tblcustomfieldsvalues")->where(["relid" => $serviceid, "fieldid" => $customfieldID])->update(["value" => $params["password"]]);
// }


function getJobLogs($clientId) {
    $products = Capsule::table('tblhosting')
        ->where('userid', $clientId)
        ->get(['username', 'packageid']); // Fetch the package ID

    $jobLogs = [];

    foreach ($products as $product) {
        $params = comet_ProductParams($product->packageid); // Pass the package ID to the function
        $params['username'] = $product->username;

        if ($params['serverhostname'] === null || $params['serverusername'] === null) {
            // Skip if server details are not found
            continue;
        }

        try {
            $cometServer = comet_Server($params);
            $jobs = $cometServer->AdminGetJobsForUser($params['username']);

            if (is_array($jobs)) {
                $jobLogs = array_merge($jobLogs, $jobs);
            }
        } catch (Exception $e) {
            error_log("Error fetching job logs for user {$params['username']}: " . $e->getMessage());
        }
    }

    return $jobLogs;
}


/**
 * Get detailed job logs for a specific user
 *
 * @param int $clientId
 * @return array
 */

function getJobDetails($clientId, $itemId = null) {
    $products = Capsule::table('tblhosting')
        ->where('userid', $clientId)
        ->get(['username', 'packageid']); // Fetch the package ID

    $jobDetails = [];

    foreach ($products as $product) {
        $params = comet_ProductParams($product->packageid); // Pass the package ID to the function
        $params['username'] = $product->username;

        if ($params['serverhostname'] === null || $params['serverusername'] === null) {
            // Skip if server details are not found
            continue;
        }

        try {
            $cometServer = comet_Server($params);
            $jobs = $cometServer->AdminGetJobsForUser($params['username']);

            if (is_array($jobs)) {
                $sourceGUIDs = array_map(function($job) {
                    return $job->SourceGUID;
                }, $jobs);

                error_log("Fetching Protected Item descriptions for user {$params['username']} and GUIDs: " . implode(", ", array_unique($sourceGUIDs)));

                $descriptions = CometItem::getProtectedItemDescriptions($cometServer, $params['username'], array_unique($sourceGUIDs));
                
                foreach ($jobs as $job) {
                    // Add friendly job type and status names
                    $job->FriendlyJobType = JobType::toString($job->Classification);
                    $job->FriendlyStatus = JobStatus::toString($job->Status);

                    // Fetch the Protected Item description
                    $job->ProtectedItemDescription = $descriptions[$job->SourceGUID] ?? 'Unknown Item';

                    // If itemId is provided, filter jobs by itemId
                    if ($itemId === null || $job->SourceGUID === $itemId) {
                        $jobDetails[] = $job;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching job logs for user {$params['username']}: " . $e->getMessage());
        }
    }

    return $jobDetails;
}



function getJobDetail($clientId, $jobId) {
    $products = Capsule::table('tblhosting')
        ->where('userid', $clientId)
        ->get(['username', 'packageid']); // Fetch the package ID

    foreach ($products as $product) {
        $params = comet_ProductParams($product->packageid); // Pass the package ID to the function
        $params['username'] = $product->username;

        if ($params['serverhostname'] === null || $params['serverusername'] === null) {
            // Skip if server details are not found
            continue;
        }

        try {
            $cometServer = comet_Server($params);
            $job = $cometServer->AdminGetJobProperties($jobId);

            if ($job) {
                // Add friendly job type and status names
                $job->FriendlyJobType = JobType::toString($job->Classification);
                $job->FriendlyStatus = JobStatus::toString($job->Status);
                return $job;
            }
        } catch (Exception $e) {
            error_log("Error fetching job detail for job ID $jobId: " . $e->getMessage());
        }
    }

    return null;
}

function getAccountJobDetails($username) {
    // Debugging: Log the start of the function
    error_log("getAccountJobDetails called with username: " . $username);

    $packageid = Capsule::table('tblhosting')
        ->where('username', $username)
        ->value('packageid');

    if (!$packageid) {
        throw new Exception('Invalid username or package ID not found.');
    }

    // Debugging: Log the package ID fetched
    error_log("Fetched package ID: " . $packageid . " for username: " . $username);

    $params = comet_ProductParams($packageid);
    $params['username'] = $username;

    if ($params['serverhostname'] === null || $params['serverusername'] === null) {
        throw new Exception('Server details not found.');
    }

    $jobDetails = [];

    try {
        $cometServer = comet_Server($params);
        $jobs = $cometServer->AdminGetJobsForUser($params['username']);

        // Debugging: Log the keys of the first job to inspect its properties
        if (!empty($jobs)) {
            error_log("Job object keys: " . implode(", ", array_keys(get_object_vars(reset($jobs)))));
        }

        if (is_array($jobs)) {
            $sourceGUIDs = array_map(function($job) {
                return $job->SourceGUID;
            }, $jobs);

            // Fetch the descriptions for the Protected Items
            error_log("Fetching Protected Item descriptions for user {$username} and GUIDs: " . implode(", ", array_unique($sourceGUIDs)));
            $descriptions = CometItem::getProtectedItemDescriptions($cometServer, $username, array_unique($sourceGUIDs));
            
            foreach ($jobs as $job) {
                // Add friendly job type and status names
                $job->FriendlyJobType = JobType::toString($job->Classification);
                $job->FriendlyStatus = JobStatus::toString($job->Status);

                // Fetch the Protected Item description, set to 'Unknown Item' if not found
                $job->ProtectedItemDescription = $descriptions[$job->SourceGUID] ?? 'Unknown Item';

                $jobDetails[] = $job;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching job logs for user {$params['username']}: " . $e->getMessage());
        throw new Exception('Error fetching job logs.');
    }

    return $jobDetails;
}



function comet_getAllUserEngineCounts(array $params, string $organizationId): array
{
    // 1) Initialize a single Comet server object from our module's function
    $server = comet_Server($params);
    if (!($server instanceof Server)) {
        // Something went wrong, $server might be an error string
        error_log("Failed to create Comet\Server object in comet_getAllUserEngineCounts: " . print_r($server, true));
        return [];
    }

    // 2) Prepare arrays to store our final data
    $combinedSourceInfo = [];
    $totalVmCountsByUser = []; // Track total VM counts per user
    $engineTypes = [
        "engine1/windisk",
        "engine1/file",
        "engine1/exchangeedb",
        "engine1/mssql",
        "engine1/mysql",
        "engine1/mongodb",
        "engine1/stdout",
        "engine1/systemstate",
        "engine1/vsswriter"
    ];
    $engineCountsByUser = [];

    // 3) Retrieve all usernames from this single Comet server
    try {
        $usernames = $server->AdminListUsers();
    } catch (\Exception $e) {
        error_log("Error: Unable to AdminListUsers from the Comet server: " . $e->getMessage());
        return [];
    }

    // 4) Iterate over each username, filtering by org ID and building our data arrays
    foreach ($usernames as $username) {
        try {
            $userProfile = $server->AdminGetUserProfile($username);
        } catch (\Exception $e) {
            error_log("Error fetching user profile for {$username}: " . $e->getMessage());
            continue;
        }

        // Filter by OrganizationID
        if (empty($userProfile->OrganizationID) || $userProfile->OrganizationID !== $organizationId) {
            // Skip this user if they do not match the desired Organization ID
            continue;
        }

        // Initialize counters for this user if not set
        if (!isset($totalVmCountsByUser[$username])) {
            $totalVmCountsByUser[$username] = 0;
        }
        if (!isset($engineCountsByUser[$username])) {
            $engineCountsByUser[$username] = [];
            foreach ($engineTypes as $engineName) {
                $engineCountsByUser[$username][$engineName] = 0;
            }
        }

        // We'll track whether we found any sources at all
        $foundAnySource = false;

        // Grab Devices and Sources from the user profile
        $devices = $userProfile->Devices ?? [];
        $sources = $userProfile->Sources ?? [];

        foreach ($sources as $sourceKey => $source) {
            $foundAnySource = true; // We found at least one source

            // Count VM totals
            $TotalVmCountLast = (int)($source->Statistics->LastBackupJob->TotalVmCount ?? 0);
            $TotalVmCountLastSuccessful = (int)($source->Statistics->LastSuccessfulBackupJob->TotalVmCount ?? 0);

            $totalVmCountsByUser[$username] += ($TotalVmCountLast + $TotalVmCountLastSuccessful);

            // Check engine usage
            if (!empty($source->Engine) && in_array($source->Engine, $engineTypes, true)) {
                $engineCountsByUser[$username][$source->Engine]++;
            }

            // Build the record for combinedSourceInfo
            $itemid = htmlspecialchars($sourceKey);
            $engineName = htmlspecialchars($source->Engine ?? '');
            $description = htmlspecialchars($source->Description ?? '');
            $ownerDevice = htmlspecialchars($source->OwnerDevice ?? '');

            // Device Friendly Name
            $deviceFriendlyName = 'N/A';
            if (isset($devices->$ownerDevice)) {
                $deviceObject = $devices->$ownerDevice;
                $deviceFriendlyName = htmlspecialchars($deviceObject->FriendlyName);
            }

            // Extract "INCLUDE" engine props
            $engineProps_INCLUDE = [];
            if (!empty($source->EngineProps) && is_object($source->EngineProps)) {
                foreach ($source->EngineProps as $propKey => $propValue) {
                    if (strpos($propKey, 'INCLUDE') === 0) {
                        $engineProps_INCLUDE[] = htmlspecialchars($propValue);
                    }
                }
            }
            $engineProps_INCLUDE_str = implode(', ', $engineProps_INCLUDE);

            // MS Office 365 Accounts count
            $totalAccountsCount = (int)($source->Statistics->LastSuccessfulBackupJob->TotalAccountsCount ?? 0);

            // Format timestamps and sizes
            $lastBackupJob        = comet_FormatTimestamp($source->Statistics->LastBackupJob->StartTime ?? 0);
            $lastJobTotalSize     = comet_FormatFileSize($source->Statistics->LastBackupJob->TotalSize ?? 0);
            $lastBackupUploadSize = comet_FormatFileSize($source->Statistics->LastBackupJob->UploadSize ?? 0);
            $lastJobDownloadSize  = comet_FormatFileSize($source->Statistics->LastBackupJob->DownloadSize ?? 0);

            $lastSuccessJob         = comet_FormatTimestamp($source->Statistics->LastSuccessfulBackupJob->StartTime ?? 0);
            $lastSuccessJobTotalSize= comet_FormatFileSize($source->Statistics->LastSuccessfulBackupJob->TotalSize ?? 0);
            $lastsuccessUploadSize  = comet_FormatFileSize($source->Statistics->LastSuccessfulBackupJob->UploadSize ?? 0);
            $lastsuccessDownloadSize= comet_FormatFileSize($source->Statistics->LastSuccessfulBackupJob->DownloadSize ?? 0);

            $sourceInfo = [
                'Username'                       => $username,
                'itemid'                         => $itemid,
                'engine'                         => $engineName,
                'description'                    => $description,
                'ownerDevice'                    => $ownerDevice,
                'deviceFriendlyName'             => $deviceFriendlyName,
                'engineProps_INCLUDE_str'        => $engineProps_INCLUDE_str,
                'totalAccountsCount'             => $totalAccountsCount,
                'TotalVmCountLastBackupJob'      => $TotalVmCountLast,
                'TotalVmCountLastSuccessfulBackupJob' => $TotalVmCountLastSuccessful,
                'TotalVmCountForUser'            => $totalVmCountsByUser[$username],
                'lastBackupJob'                  => $lastBackupJob,
                'lastJobTotalSize'               => $lastJobTotalSize,
                'lastBackupUploadSize'           => $lastBackupUploadSize,
                'lastJobDownloadSize'            => $lastJobDownloadSize,
                'lastSuccessJob'                 => $lastSuccessJob,
                'lastSuccessJobTotalSize'        => $lastSuccessJobTotalSize,
                'lastsuccessUploadSize'          => $lastsuccessUploadSize,
                'lastsuccessDownloadSize'        => $lastsuccessDownloadSize,
            ];

            $combinedSourceInfo[] = $sourceInfo;
        }

        // If user has no sources at all, add a single entry
        if (!$foundAnySource) {
            $combinedSourceInfo[] = [
                'Username'                       => $username,
                'itemid'                         => '',
                'engine'                         => '',
                'description'                    => '',
                'ownerDevice'                    => '',
                'deviceFriendlyName'             => '',
                'engineProps_INCLUDE_str'        => '',
                'totalAccountsCount'             => 0,
                'TotalVmCountLastBackupJob'      => 0,
                'TotalVmCountLastSuccessfulBackupJob' => 0,
                'TotalVmCountForUser'            => $totalVmCountsByUser[$username],
                'lastBackupJob'                  => 'N/A',
                'lastJobTotalSize'               => 'N/A',
                'lastBackupUploadSize'           => 'N/A',
                'lastJobDownloadSize'            => 'N/A',
                'lastSuccessJob'                 => 'N/A',
                'lastSuccessJobTotalSize'        => 'N/A',
                'lastsuccessUploadSize'          => 'N/A',
                'lastsuccessDownloadSize'        => 'N/A',
            ];
        }
    }

    return $combinedSourceInfo;
}

function myUsageReportLogic(array $params, string $organizationId): array
{
    // 1) Call the known function that fetches combined data
    $combinedSourceInfo = comet_getAllUserEngineCounts($params, $organizationId);

    // Prepare $userData
    $userData = [];
    foreach ($combinedSourceInfo as $info) {
        $username = $info['Username'];
        if (!isset($userData[$username])) {
            $userData[$username] = [
                'DeviceCount'                => 0,
                'VmCount'                    => 0,
                'DiskImageCount'             => 0,
                'FilesAndFolders'            => 0,
                'MicrosoftExchangeServer'    => 0,
                'MicrosoftSQLServer'         => 0,
                'MySQL'                      => 0,
                'MongoDB'                    => 0,
                'ProgramOutput'              => 0,
                'WindowsServerSystemState'   => 0,
                'ApplicationAwareWriter'     => 0,
                'MS365Accounts'              => 0
            ];
        }
        // Each source counted as 1 device
        $userData[$username]['DeviceCount']++;

        // VM counts
        $userData[$username]['VmCount'] += $info['TotalVmCountLastBackupJob'];
        $userData[$username]['VmCount'] += $info['TotalVmCountLastSuccessfulBackupJob'];

        // Engine usage
        $engine = $info['engine'] ?? '';
        switch ($engine) {
            case "engine1/windisk":
                $userData[$username]['DiskImageCount']++;
                break;
            case "engine1/file":
                $userData[$username]['FilesAndFolders']++;
                break;
            case "engine1/exchangeedb":
                $userData[$username]['MicrosoftExchangeServer']++;
                break;
            case "engine1/mssql":
                $userData[$username]['MicrosoftSQLServer']++;
                break;
            case "engine1/mysql":
                $userData[$username]['MySQL']++;
                break;
            case "engine1/mongodb":
                $userData[$username]['MongoDB']++;
                break;
            case "engine1/stdout":
                $userData[$username]['ProgramOutput']++;
                break;
            case "engine1/systemstate":
                $userData[$username]['WindowsServerSystemState']++;
                break;
            case "engine1/vsswriter":
                $userData[$username]['ApplicationAwareWriter']++;
                break;
            default:
                // no-op
                break;
        }

        // MS365 accounts
        $userData[$username]['MS365Accounts'] += ($info['totalAccountsCount'] ?? 0);
    }

    // Prepare $totals
    $totals = [
        'DeviceCount'                => 0,
        'VmCount'                    => 0,
        'DiskImageCount'             => 0,
        'FilesAndFolders'            => 0,
        'MicrosoftExchangeServer'    => 0,
        'MicrosoftSQLServer'         => 0,
        'MySQL'                      => 0,
        'MongoDB'                    => 0,
        'ProgramOutput'              => 0,
        'WindowsServerSystemState'   => 0,
        'ApplicationAwareWriter'     => 0,
        'MS365Accounts'              => 0
    ];

    foreach ($userData as $uname => $ud) {
        $totals['DeviceCount']              += $ud['DeviceCount'];
        $totals['VmCount']                  += $ud['VmCount'];
        $totals['DiskImageCount']           += $ud['DiskImageCount'];
        $totals['FilesAndFolders']          += $ud['FilesAndFolders'];
        $totals['MicrosoftExchangeServer']  += $ud['MicrosoftExchangeServer'];
        $totals['MicrosoftSQLServer']       += $ud['MicrosoftSQLServer'];
        $totals['MySQL']                    += $ud['MySQL'];
        $totals['MongoDB']                  += $ud['MongoDB'];
        $totals['ProgramOutput']            += $ud['ProgramOutput'];
        $totals['WindowsServerSystemState'] += $ud['WindowsServerSystemState'];
        $totals['ApplicationAwareWriter']   += $ud['ApplicationAwareWriter'];
        $totals['MS365Accounts']            += $ud['MS365Accounts'];
    }



    return [
        'userData' => $userData,
        'totals'   => $totals,
    ];
}


/**
 * Format a Unix timestamp to a human-readable date/time or return 'N/A'.
 */
function comet_FormatTimestamp(int $timestamp): string
{
    if ($timestamp < 1) {
        return 'N/A';
    }
    return htmlspecialchars(gmdate('Y-m-d H:i:s', $timestamp));
}

/**
 * Format a file size from bytes to a human-readable string or return 'N/A'.
 */
function comet_FormatFileSize(int $size): string
{
    if ($size < 1) {
        return 'N/A';
    }
    return comet_HumanFileSize($size);
}
