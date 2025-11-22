<?php

use Comet\Server;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Eazybackup\Comet;

$excludeProductgroupIds = [2, 11];
$products = Capsule::table('tblproducts')
    ->select('id', 'servergroup')
    ->whereNotIn('gid', $excludeProductgroupIds)
    ->get();

$productServerGroupMap = [];

foreach ($products as $product) {
    if ($product->servergroup > 0) {
        $productServerGroupMap[$product->id] = $product->servergroup;
    }
}

$productIds = array_keys($productServerGroupMap);
$groupIds = array_unique(array_values($productServerGroupMap));

$servergroups = Capsule::table("tblservergroups")
    ->select('id', 'name')
    ->whereIn('id', $groupIds)
    ->pluck('name', 'id')
    ->toArray();

$servers = Capsule::table("tblservers")
    ->select('name', 'hostname', 'secure', 'port', 'username', 'password')
    ->whereIn("name", array_values($servergroups))
    ->get();

$serverHosts = [];

foreach ($servers as $server) {
    $password = localAPI("DecryptPassword", ['password2' => $server->password]);
    $host = $server->secure ? 'https' : 'http';
    $port = empty($server->port) ? "" : ":" . $server->port;
    $serverUsername = $server->username;
    $serverPassword = $password['password'];

    $hostname = preg_replace(["^http://^i", "^https://^i", "^/^"], "", $server->hostname);
    $url = $host . "://" . $hostname . "/";
    if (!empty($port)) {
        $url = $host . "://" . $hostname . $port . "/";
    }
    $packageId = array_search($server->name, $servergroups);
    $serverHosts[$packageId] = [
        'url' => $url,
        'username' => $server->username,
        'password' => $password['password'],
    ];
}

$deviceStatuses = [];
$cometObject = new Comet();
// get the device statuses
// foreach ($serverHosts as $packageId => $serverHost) {
    // $server = new Server($serverHost['url'], $serverHost['username'], $serverHost['password']);

    // try {
    //     $endTimestamp = time();
    //     // $startTimestamp = strtotime('-42 days');
    //     $startTimestamp = strtotime('-5 minutes');
    //     $jobs = $server->AdminGetJobsForDateRange($startTimestamp, $endTimestamp);
    //     $cometObject->upsertJobs($jobs);
    // } catch (\Exception $e) {
    //     logModuleCall(
    //         "eazybackup",
    //         'cron AdminGetJobsForDateRange',
    //         $serverHost,
    //         $e->getMessage(),
    //         $e->getTraceAsString()
    //     );
    //     continue;
    // }
// }


$hostings = Capsule::table('tblhosting')
    ->select('id', 'userid', 'username', 'packageid')
    ->where('domainstatus', 'Active')
    ->whereIn('packageid', $productIds)
    ->orderBy('id', 'ASC')
    ->get();
$usersNotExist = [];
// echo "<pre>"; print_r($productServerGroupMap);
foreach ($hostings as $hosting) {
    $packageId = $hosting->packageid;
    // $userId = $hosting->userid;
    $gid = $productServerGroupMap[$packageId];

    echo "<br>username: " . $hosting->username;

    echo "<br>url: " . $serverHosts[$gid]['url'];
    try {
        $server = new Server($serverHosts[$gid]['url'], $serverHosts[$gid]['username'], $serverHosts[$gid]['password']);
        // echo "<pre>"; print_r($server);
        $userProfile = $server->AdminGetUserProfile($hosting['username']);
        // echo "<pre>"; print_r($userProfile);
        // upsert devices
        // $cometObject->upsertDevices($userProfile, $hosting, $deviceStatuses[$gid]);
        // upsert protected items
        // $cometObject->upsertItems($userProfile, $hosting);
        // // upsert valuts
        // $cometObject->upsertVaults($userProfile, $hosting);
    } catch (\Exception $e) {
        $usersNotExist[] = [
            'username' => $hosting->username,
            'packageid' => $hosting->packageid,
            'gid' => $gid
        ];
        continue;
    }
}

echo "<pre>"; print_r($usersNotExist);

die('done');

