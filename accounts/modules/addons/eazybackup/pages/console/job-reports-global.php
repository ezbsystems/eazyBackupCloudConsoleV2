<?php

use WHMCS\Database\Capsule;
use WHMCS\Session;

require_once __DIR__ . '/../../../../../modules/servers/comet/functions.php';

/** Max usernames processed in fan-out; excess is truncated with meta.partial + warning */
const GLOBAL_JOB_REPORTS_MAX_USERNAMES = 50;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

header('Content-Type: application/json');

try {
    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
        exit;
    }

    $clientId = 0;
    try { $clientId = (int) (Session::get('uid') ?: 0); } catch (\Throwable $e) { $clientId = 0; }
    if ($clientId <= 0) { $clientId = (int) ($_SESSION['uid'] ?? 0); }
    if ($clientId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        exit;
    }

    // Parse request filters with defaults
    $rangeHours = max(1, min(720, (int)($post['rangeHours'] ?? 72)));
    $username = trim((string)($post['username'] ?? ''));
    $statuses = [];
    if (!empty($post['statuses']) && is_array($post['statuses'])) {
        foreach ($post['statuses'] as $s) {
            $statuses[] = trim((string)$s);
        }
    }
    $q = trim((string)($post['q'] ?? ''));
    $page = max(1, (int)($post['page'] ?? 1));
    $pageSize = min(200, max(10, (int)($post['pageSize'] ?? 25)));
    $sortByRaw = (string)($post['sortBy'] ?? 'Started');
    $sortByWhitelist = ['Username', 'JobID', 'Device', 'ProtectedItem', 'StorageVault', 'Version', 'Type', 'Status', 'Directories', 'Files', 'Size', 'VaultSize', 'Uploaded', 'Downloaded', 'Started', 'Ended', 'Duration', 'Classification', 'StatusCode', 'ServiceID'];
    $sortBy = in_array($sortByRaw, $sortByWhitelist, true) ? $sortByRaw : 'Started';
    $sortDir = strtolower((string)($post['sortDir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

    $filters = [
        'rangeHours' => $rangeHours,
        'username'   => $username,
        'statuses'   => $statuses,
        'q'          => $q,
        'page'       => $page,
        'pageSize'   => $pageSize,
        'sortBy'     => $sortBy,
        'sortDir'    => $sortDir,
    ];

    // Resolve active services/usernames from tblhosting scoped to client and Active domainstatus
    $services = Capsule::table('tblhosting')
        ->select('username', 'id')
        ->where('domainstatus', 'Active')
        ->where('userid', $clientId)
        ->get();

    $usernameToServiceId = [];
    $usernames = [];
    foreach ($services as $svc) {
        $un = (string)$svc->username;
        if ($un !== '') {
            $usernameToServiceId[$un] = (int)$svc->id;
            $usernames[] = $un;
        }
    }

    // If username filter provided, ensure it belongs to map
    if ($username !== '') {
        if (!isset($usernameToServiceId[$username])) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Username not found or access denied',
            ]);
            exit;
        }
    }

    // Task 3: Comet fan-out scope — all usernames or single filtered username
    $targetUsernames = ($username !== '') ? [$username] : $usernames;
    $metaWarnings = [];
    $metaPartial = false;

    if (count($targetUsernames) > GLOBAL_JOB_REPORTS_MAX_USERNAMES) {
        $targetUsernames = array_slice($targetUsernames, 0, GLOBAL_JOB_REPORTS_MAX_USERNAMES);
        $metaPartial = true;
        $metaWarnings[] = sprintf('Truncated to %d usernames for performance; results are partial.', GLOBAL_JOB_REPORTS_MAX_USERNAMES);
    }

    // Status mapping helpers (aligned with job-reports)
    $mapStatus = function (int $code): string {
        switch ($code) {
            case 5000: return 'SUCCESS';
            case 6000: return 'ACTIVE';
            case 6001: return 'ACTIVE';
            case 6002: return 'REVIVED';
            case 7000: return 'TIMEOUT';
            case 7001: return 'WARNING';
            case 7002: return 'ERROR';
            case 7003: return 'QUOTA_EXCEEDED';
            case 7004: return 'MISSED';
            case 7005: return 'CANCELLED';
            case 7006: return 'ALREADY_RUNNING';
            case 7007: return 'ABANDONED';
            default:   return 'UNKNOWN';
        }
    };
    $statusLabels = ['Success', 'Running', 'Timeout', 'Warning', 'Error', 'Missed', 'Skipped', 'Cancelled'];
    $emptyStatusCounts = array_fill_keys($statusLabels, 0);
    $normalizeStatus = function ($codeOrLabel) use ($mapStatus): string {
        if (is_numeric($codeOrLabel)) {
            $codeOrLabel = $mapStatus((int)$codeOrLabel);
        }
        $u = strtoupper(trim((string)$codeOrLabel));
        if ($u === 'SUCCESS') return 'Success';
        if ($u === 'RUNNING' || $u === 'ACTIVE' || $u === 'REVIVED' || $u === 'ALREADY_RUNNING') return 'Running';
        if ($u === 'TIMEOUT') return 'Timeout';
        if ($u === 'WARNING') return 'Warning';
        if ($u === 'ERROR' || $u === 'QUOTA_EXCEEDED' || $u === 'ABANDONED') return 'Error';
        if ($u === 'MISSED') return 'Missed';
        if ($u === 'SKIPPED') return 'Skipped';
        if ($u === 'CANCELLED' || $u === 'CANCELED') return 'Cancelled';
        return 'Unknown';
    };

    $statusFilters = [];
    foreach ($statuses as $candidate) {
        $label = $normalizeStatus($candidate);
        if (in_array($label, $statusLabels, true)) {
            $statusFilters[$label] = true;
        }
    }
    $statusFilters = array_keys($statusFilters);

    // Time window cutoff (a) filter order
    $cutoffTime = time() - ($rangeHours * 3600);

    $allRows = [];

    foreach ($targetUsernames as $un) {
        $serviceId = $usernameToServiceId[$un] ?? 0;
        if ($serviceId <= 0) {
            continue;
        }
        try {
            $params = comet_ServiceParams($serviceId);
            $params['username'] = $un;
            $server = comet_Server($params);

            $jobs = $server->AdminGetJobsForUser($un);
            if (!is_array($jobs)) {
                continue;
            }

            $sourceGUIDs = array_values(array_unique(array_map(function ($j) { return $j->SourceGUID ?? ''; }, $jobs)));
            $descriptions = \Comet\CometItem::getProtectedItemDescriptions($server, $un, $sourceGUIDs);

            $destMap = [];
            $deviceMap = [];
            $ph = $server->AdminGetUserProfileAndHash($un);
            if ($ph && $ph->Profile) {
                $profile = $ph->Profile;
                if (!empty($profile->Destinations)) {
                    foreach ($profile->Destinations as $guid => $dest) {
                        $destMap[(string)$guid] = (string)($dest->Description ?? '');
                    }
                }
                if (!empty($profile->Devices)) {
                    foreach ($profile->Devices as $devId => $dev) {
                        $deviceMap[(string)$devId] = (string)($dev->FriendlyName ?? '');
                    }
                }
            }

            foreach ($jobs as $j) {
                $start = (int)($j->StartTime ?? 0);
                $end   = (int)($j->EndTime ?? 0);
                $dur   = $end > 0 && $start > 0 ? max(0, $end - $start) : 0;
                $friendlyType = \Comet\JobType::toString($j->Classification ?? 0);
                $friendlyStatus = $normalizeStatus($mapStatus((int)($j->Status ?? 0)));
                $itemDesc = $descriptions[$j->SourceGUID ?? ''] ?? 'Unknown Item';
                $devId = (string)($j->DeviceID ?? $j->Device ?? '');
                $deviceFriendly = $deviceMap[$devId] ?? (string)($j->Device ?? $devId);
                $destGuid = (string)($j->Destination ?? $j->DestinationGUID ?? '');
                $vaultName = $destMap[$destGuid] ?? (string)($j->DestinationLocation ?? '');

                $allRows[] = [
                    'Username'        => (string)($j->Username ?? $un),
                    'JobID'           => (string)($j->GUID ?? ''),
                    'Device'          => (string)$deviceFriendly,
                    'ProtectedItem'   => (string)$itemDesc,
                    'StorageVault'    => (string)$vaultName,
                    'Version'         => (string)($j->ClientVersion ?? ''),
                    'Type'            => (string)$friendlyType,
                    'Status'          => (string)$friendlyStatus,
                    'Directories'     => (int)($j->TotalDirectories ?? 0),
                    'Files'           => (int)($j->TotalFiles ?? 0),
                    'Size'            => (int)($j->TotalSize ?? 0),
                    'VaultSize'       => 0, // Intentionally 0 in global list to avoid per-row detail calls; use per-service job report for vault sizes
                    'Uploaded'        => (int)($j->UploadSize ?? 0),
                    'Downloaded'      => (int)($j->DownloadSize ?? 0),
                    'Started'         => $start,
                    'Ended'           => $end,
                    'Duration'        => $dur,
                    'Classification'  => (int)($j->Classification ?? 0),
                    'StatusCode'      => (int)($j->Status ?? 0),
                    'SourceGUID'      => (string)($j->SourceGUID ?? ''),
                    'DestinationGUID' => (string)$destGuid,
                    'ServiceID'       => $serviceId,
                ];
            }
        } catch (\Throwable $e) {
            $metaPartial = true;
            $metaWarnings[] = sprintf('Username %s: %s', $un, $e->getMessage());
        }
    }

    // (a) time window filter
    $rows = array_values(array_filter($allRows, function ($r) use ($cutoffTime) {
        return ((int)($r['Started'] ?? 0)) >= $cutoffTime;
    }));

    // (b) search q filter
    if ($q !== '') {
        $qLower = mb_strtolower($q);
        $rows = array_values(array_filter($rows, function ($r) use ($qLower) {
            $blob = mb_strtolower(($r['Username'] ?? '') . ' ' . ($r['JobID'] ?? '') . ' ' . ($r['Device'] ?? '') . ' ' . ($r['ProtectedItem'] ?? '') . ' ' . ($r['StorageVault'] ?? '') . ' ' . ($r['Type'] ?? '') . ' ' . ($r['Status'] ?? ''));
            return strpos($blob, $qLower) !== false;
        }));
    }

    // Facets: statusCounts and usernameCounts (before status filter)
    $statusCounts = $emptyStatusCounts;
    $usernameCounts = [];
    foreach ($rows as $r) {
        $label = (string)($r['Status'] ?? '');
        if (isset($statusCounts[$label])) {
            $statusCounts[$label]++;
        }
        $u = (string)($r['Username'] ?? '');
        if ($u !== '') {
            $usernameCounts[$u] = ($usernameCounts[$u] ?? 0) + 1;
        }
    }

    // (c) status filter
    if (!empty($statusFilters)) {
        $activeSet = array_flip($statusFilters);
        $rows = array_values(array_filter($rows, function ($r) use ($activeSet) {
            $label = (string)($r['Status'] ?? '');
            return isset($activeSet[$label]);
        }));
    }

    // (d) sort
    usort($rows, function ($a, $b) use ($sortBy, $sortDir) {
        $av = $a[$sortBy] ?? null;
        $bv = $b[$sortBy] ?? null;
        if ($av == $bv) return 0;
        $cmp = ($av < $bv) ? -1 : 1;
        return $sortDir === 'asc' ? $cmp : -$cmp;
    });

    // (e) paginate
    $total = count($rows);
    $offset = ($page - 1) * $pageSize;
    $paged = array_slice($rows, $offset, $pageSize);

    $response = [
        'status'  => 'success',
        'total'   => $total,
        'rows'    => $paged,
        'facets'  => [
            'statusCounts'   => $statusCounts,
            'usernameCounts' => $usernameCounts,
        ],
        'usernames'           => $usernames,
        'usernameToServiceId' => $usernameToServiceId,
        'filters'             => $filters,
    ];

    if ($metaPartial || !empty($metaWarnings)) {
        $response['meta'] = [
            'partial' => $metaPartial,
            'warnings' => $metaWarnings,
        ];
    }

    echo json_encode($response);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit;
