<?php



use WHMCS\Database\Capsule;

use WHMCS\Module\Addon\Eazybackup\LiveJobState;



if (!defined('WHMCS')) { require_once __DIR__ . '/../../../../../init.php'; }



/**

 * Helpers

 */

function eb_assert_client() {

    if (!isset($_SESSION['uid']) || !$_SESSION['uid']) {

        http_response_code(403);

        header('Content-Type: application/json');

        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);

        exit;

    }

    return (int)$_SESSION['uid'];

}



function eb_active_usernames_for_client(int $clientId): array {

    try {

        $productIds = eazybackup_comet_product_ids();

        return Capsule::table('tblhosting')

            ->select('username')

            ->where('domainstatus', 'Active')

            ->where('userid', $clientId)

            ->whereIn('packageid', $productIds)

            ->pluck('username')

            ->toArray();

    } catch (\Throwable $e) {

        return [];

    }

}



function eb_running_jobs_for_client(int $clientId): array {

    $usernames = eb_active_usernames_for_client($clientId);

    if (empty($usernames)) { return []; }



    $rows = Capsule::table('eb_jobs_live as j')

        ->leftJoin('comet_devices as d', function ($join) use ($clientId) {

            $join->where('d.client_id', '=', $clientId)

                 ->whereNull('d.revoked_at')

                 ->whereRaw('BINARY d.username = BINARY j.username')

                 ->where(function ($match) {

                     $match->whereRaw('BINARY d.hash = BINARY j.device')

                           ->orWhereRaw('BINARY d.id = BINARY j.device')

                           ->orWhereRaw('BINARY d.name = BINARY j.device');

                 });

        })

        ->whereExists(function ($q) use ($clientId, $usernames) {

            $q->select(Capsule::raw('1'))

              ->from('comet_devices as d_scoped')

              ->where('d_scoped.client_id', $clientId)

              ->whereNull('d_scoped.revoked_at')

              ->whereIn('d_scoped.username', $usernames)

              ->whereRaw('BINARY d_scoped.username = BINARY j.username')

              ->where(function ($match) {

                  $match->whereRaw('BINARY d_scoped.id = BINARY j.device')

                        ->orWhereRaw('BINARY d_scoped.hash = BINARY j.device')

                        ->orWhereRaw('BINARY d_scoped.name = BINARY j.device');

              });

        })

        ->select(

            'j.server_id',

            'j.job_id',

            'j.username',

            'j.device',

            'j.job_type',

            'j.started_at',

            'j.last_update',

            'd.is_active',

            'd.offline_since',

            Capsule::raw("COALESCE(NULLIF(d.name, ''), j.device) as device_name")

        )

        ->orderBy('j.started_at', 'desc')

        ->limit(1000)

        ->get();



    $now = time();

    $out = [];

    foreach ($rows as $r) {

        $serverId = (string)$r->server_id;

        $jobId = (string)$r->job_id;

        $deviceIsActive = ($r->is_active === null) ? null : ((int)$r->is_active === 1);

        $derived = LiveJobState::deriveStatus($deviceIsActive, $r->offline_since ?? null, $now);



        $out[] = [

            'id'            => $serverId . ':' . $jobId,

            'job_id'        => $jobId,

            'server_id'     => $serverId,

            'username'      => (string)$r->username,

            'device'        => (string)$r->device,

            'device_name'   => (string)($r->device_name ?? $r->device),

            'status'        => $derived['status'],

            'status_reason' => $derived['status_reason'],

            'offline_since' => $derived['offline_since'],

            'started_at'    => (int)$r->started_at,

            'ended_at'      => 0,

        ];

    }

    return $out;

}



/** JSON snapshot: authoritative running jobs for the logged-in client */

function eb_pulse_snapshot() {

    $clientId = eb_assert_client();

    header('Content-Type: application/json');

    $running = eb_running_jobs_for_client($clientId);

    echo json_encode([

        'status' => 'success',

        'jobsRunning' => $running,

        't' => time(),

    ]);

    exit;

}



