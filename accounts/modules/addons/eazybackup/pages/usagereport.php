<?php

use Smarty;

// 1) Load autoloader, Comet functions, and any other required files
require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../modules/servers/comet/functions.php';

// 2) Initialize or retrieve your $params. 
//    For demonstration, let's do a minimal example:
// $params = [];
// $params['serverhttpprefix'] = 'https'; 
// $params['serverhostname']   = 'csw.eazybackup.ca';
// $params['serverusername']   = 'api';
// $params['serverpassword']   = 'JzZjd9DYnGqDD8pmTEchZoEbPGvBEmAJsLJziFiuXPGhhKCo9swSK2LZQV4BmNbiF3BgQpqvXDqzbxMNXjZbQoBuaj3mrsGWUTSXvV4yN4dtD77JiqvW2va4RWT52n6n';

// // 3) Hard-coded organization ID (for testing)
// $organizationId = '02a66aa9-30c3-4b78-8574-ed41c192b792';

// 4) Use the function from functions.php to get the raw data
$combinedSourceInfo = comet_getAllUserEngineCounts($params, $organizationId);

// 5) Transform $combinedSourceInfo into $userData and $totals

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