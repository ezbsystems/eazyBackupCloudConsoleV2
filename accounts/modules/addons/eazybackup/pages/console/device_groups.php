<?php
use WHMCS\Database\Capsule;

/**
 * Client-area JSON endpoint for Phase 1 device grouping.
 * Route: ?m=eazybackup&a=device-groups
 *
 * Scope: WHMCS client_id (tblclients.id). One group per device. Ungrouped is implicit.
 */
function eb_device_groups(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    if ($clientId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        return;
    }

    // Ensure schema exists even on already-activated installs (dev/staging frequently skips activate()).
    try { ebdg_ensure_schema(); } catch (\Throwable $_) {}

    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '[]', true);
    if (!is_array($body)) $body = [];

    $action = isset($body['action']) ? (string)$body['action'] : (isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '');

    try {
        switch ($action) {
            case 'list':
                echo json_encode(ebdg_list($clientId));
                return;

            case 'createGroup':
                echo json_encode(ebdg_create_group($clientId, $body));
                return;

            case 'renameGroup':
                echo json_encode(ebdg_rename_group($clientId, $body));
                return;

            case 'deleteGroup':
                echo json_encode(ebdg_delete_group($clientId, $body));
                return;

            case 'reorderGroups':
                echo json_encode(ebdg_reorder_groups($clientId, $body));
                return;

            case 'assignDevice':
                echo json_encode(ebdg_assign_device($clientId, $body));
                return;

            case 'bulkAssign':
                echo json_encode(ebdg_bulk_assign($clientId, $body));
                return;

            default:
                echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
                return;
        }
    } catch (\Throwable $e) {
        // Log server-side for debugging
        try {
            if (function_exists('logModuleCall')) {
                logModuleCall('eazybackup', 'device-groups:error', ['action' => $action], $e->getMessage());
            }
        } catch (\Throwable $_) {}

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isDev = (stripos($host, 'dev.') !== false) || (stripos($host, 'localhost') !== false);
        $resp = ['status' => 'error', 'message' => 'Request failed'];
        if ($isDev) {
            $resp['detail'] = $e->getMessage();
        }
        echo json_encode($resp);
        return;
    }
}

function ebdg_ensure_schema(): void
{
    $schema = Capsule::schema();

    if (!$schema->hasTable('eb_device_groups')) {
        $schema->create('eb_device_groups', function ($table) {
            $table->bigIncrements('id');
            $table->integer('client_id')->index();
            $table->string('name', 191);
            $table->string('name_norm', 191);
            $table->string('color', 32)->nullable();
            $table->string('icon', 32)->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['client_id', 'name_norm'], 'uniq_client_group_name');
        });
    }

    if (!$schema->hasTable('eb_device_group_assignments')) {
        $schema->create('eb_device_group_assignments', function ($table) {
            $table->integer('client_id')->index();
            $table->string('device_id', 255);
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['client_id', 'device_id'], 'pk_client_device');
            $table->index(['client_id', 'group_id'], 'idx_client_group');
        });
    }
}

function ebdg_norm_name(string $name): array
{
    $name = trim($name);
    // collapse all whitespace sequences to a single space
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    $norm = mb_strtolower($name, 'UTF-8');
    return [$name, $norm];
}

function ebdg_group_belongs(int $clientId, int $groupId): bool
{
    if ($groupId <= 0) return false;
    $row = Capsule::table('eb_device_groups')->where('client_id', $clientId)->where('id', $groupId)->first();
    return !!$row;
}

function ebdg_devices_exist(int $clientId, array $deviceIds): array
{
    $deviceIds = array_values(array_filter(array_map('strval', $deviceIds), function ($v) { return $v !== ''; }));
    if (!$deviceIds) return [];
    return Capsule::table('comet_devices')
        ->where('client_id', $clientId)
        ->whereIn('id', $deviceIds)
        ->pluck('id')
        ->map(fn($x) => (string)$x)
        ->toArray();
}

function ebdg_list(int $clientId): array
{
    $groups = Capsule::table('eb_device_groups')
        ->where('client_id', $clientId)
        ->orderBy('sort_order', 'asc')
        ->orderBy('name', 'asc')
        ->get(['id', 'name', 'color', 'icon', 'sort_order', 'updated_at']);

    $assignRows = Capsule::table('eb_device_group_assignments')
        ->where('client_id', $clientId)
        ->get(['device_id', 'group_id']);

    $assignments = [];
    foreach ($assignRows as $r) {
        $did = (string)($r->device_id ?? '');
        if ($did === '') continue;
        $gid = isset($r->group_id) ? (int)$r->group_id : 0;
        $assignments[$did] = $gid > 0 ? $gid : null;
    }

    // counts per group (only assignments, not including Ungrouped)
    $counts = Capsule::table('eb_device_group_assignments')
        ->select('group_id', Capsule::raw('COUNT(*) AS c'))
        ->where('client_id', $clientId)
        ->whereNotNull('group_id')
        ->groupBy('group_id')
        ->get();

    $countMap = [];
    foreach ($counts as $c) {
        $gid = isset($c->group_id) ? (int)$c->group_id : 0;
        if ($gid <= 0) continue;
        $countMap[$gid] = (int)($c->c ?? 0);
    }

    $out = [];
    foreach ($groups as $g) {
        $gid = (int)$g->id;
        $out[] = [
            'id' => $gid,
            'name' => (string)($g->name ?? ''),
            'color' => $g->color !== null ? (string)$g->color : null,
            'icon' => $g->icon !== null ? (string)$g->icon : null,
            'sort_order' => (int)($g->sort_order ?? 0),
            'count' => $countMap[$gid] ?? 0,
        ];
    }

    return ['status' => 'success', 'groups' => $out, 'assignments' => $assignments];
}

function ebdg_create_group(int $clientId, array $body): array
{
    $name = isset($body['name']) ? (string)$body['name'] : '';
    [$nameClean, $nameNorm] = ebdg_norm_name($name);
    if ($nameClean === '') return ['status' => 'error', 'message' => 'Group name is required'];

    $exists = Capsule::table('eb_device_groups')
        ->where('client_id', $clientId)
        ->where('name_norm', $nameNorm)
        ->exists();
    if ($exists) return ['status' => 'error', 'message' => 'Group name must be unique'];

    $color = isset($body['color']) ? (string)$body['color'] : null;
    $icon = isset($body['icon']) ? (string)$body['icon'] : null;
    if ($color === '') $color = null;
    if ($icon === '') $icon = null;

    $max = Capsule::table('eb_device_groups')->where('client_id', $clientId)->max('sort_order');
    $sort = is_numeric($max) ? ((int)$max + 10) : 10;

    $id = Capsule::table('eb_device_groups')->insertGetId([
        'client_id' => $clientId,
        'name' => $nameClean,
        'name_norm' => $nameNorm,
        'color' => $color,
        'icon' => $icon,
        'sort_order' => $sort,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return ['status' => 'success', 'group' => ['id' => (int)$id, 'name' => $nameClean, 'color' => $color, 'icon' => $icon, 'sort_order' => $sort, 'count' => 0]];
}

function ebdg_rename_group(int $clientId, array $body): array
{
    $groupId = isset($body['group_id']) ? (int)$body['group_id'] : 0;
    if ($groupId <= 0) return ['status' => 'error', 'message' => 'Missing group'];
    if (!ebdg_group_belongs($clientId, $groupId)) return ['status' => 'error', 'message' => 'Group not found'];

    $name = isset($body['name']) ? (string)$body['name'] : '';
    [$nameClean, $nameNorm] = ebdg_norm_name($name);
    if ($nameClean === '') return ['status' => 'error', 'message' => 'Group name is required'];

    $exists = Capsule::table('eb_device_groups')
        ->where('client_id', $clientId)
        ->where('name_norm', $nameNorm)
        ->where('id', '!=', $groupId)
        ->exists();
    if ($exists) return ['status' => 'error', 'message' => 'Group name must be unique'];

    Capsule::table('eb_device_groups')
        ->where('client_id', $clientId)
        ->where('id', $groupId)
        ->update([
            'name' => $nameClean,
            'name_norm' => $nameNorm,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    return ['status' => 'success', 'group' => ['id' => $groupId, 'name' => $nameClean]];
}

function ebdg_delete_group(int $clientId, array $body): array
{
    $groupId = isset($body['group_id']) ? (int)$body['group_id'] : 0;
    if ($groupId <= 0) return ['status' => 'error', 'message' => 'Missing group'];
    if (!ebdg_group_belongs($clientId, $groupId)) return ['status' => 'error', 'message' => 'Group not found'];

    $moved = Capsule::table('eb_device_group_assignments')
        ->where('client_id', $clientId)
        ->where('group_id', $groupId)
        ->count();

    Capsule::table('eb_device_group_assignments')
        ->where('client_id', $clientId)
        ->where('group_id', $groupId)
        ->delete();

    Capsule::table('eb_device_groups')
        ->where('client_id', $clientId)
        ->where('id', $groupId)
        ->delete();

    return ['status' => 'success', 'moved' => (int)$moved];
}

function ebdg_reorder_groups(int $clientId, array $body): array
{
    $ids = $body['ordered_ids'] ?? $body['orderedIds'] ?? [];
    if (!is_array($ids)) $ids = [];
    $ordered = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
    if (!$ordered) return ['status' => 'error', 'message' => 'Missing order'];

    // Validate ownership
    $owned = Capsule::table('eb_device_groups')
        ->where('client_id', $clientId)
        ->whereIn('id', $ordered)
        ->pluck('id')
        ->map(fn($x) => (int)$x)
        ->toArray();
    sort($owned);
    $tmp = $ordered; sort($tmp);
    if ($owned !== $tmp) return ['status' => 'error', 'message' => 'Invalid group order'];

    $order = 10;
    foreach ($ordered as $gid) {
        Capsule::table('eb_device_groups')
            ->where('client_id', $clientId)
            ->where('id', $gid)
            ->update(['sort_order' => $order, 'updated_at' => date('Y-m-d H:i:s')]);
        $order += 10;
    }
    return ['status' => 'success'];
}

function ebdg_assign_device(int $clientId, array $body): array
{
    $deviceId = isset($body['device_id']) ? (string)$body['device_id'] : (isset($body['deviceId']) ? (string)$body['deviceId'] : '');
    $deviceId = trim($deviceId);
    if ($deviceId === '') return ['status' => 'error', 'message' => 'Missing device'];

    $exists = Capsule::table('comet_devices')
        ->where('client_id', $clientId)
        ->where('id', $deviceId)
        ->exists();
    if (!$exists) return ['status' => 'error', 'message' => 'Device not found'];

    $groupId = null;
    if (array_key_exists('group_id', $body)) $groupId = $body['group_id'];
    else if (array_key_exists('groupId', $body)) $groupId = $body['groupId'];

    $gid = ($groupId === null || $groupId === '' ? 0 : (int)$groupId);
    if ($gid > 0 && !ebdg_group_belongs($clientId, $gid)) return ['status' => 'error', 'message' => 'Group not found'];

    if ($gid <= 0) {
        Capsule::table('eb_device_group_assignments')
            ->where('client_id', $clientId)
            ->where('device_id', $deviceId)
            ->delete();
        return ['status' => 'success', 'device_id' => $deviceId, 'group_id' => null];
    }

    Capsule::table('eb_device_group_assignments')->updateOrInsert(
        ['client_id' => $clientId, 'device_id' => $deviceId],
        ['group_id' => $gid, 'updated_at' => date('Y-m-d H:i:s')]
    );

    return ['status' => 'success', 'device_id' => $deviceId, 'group_id' => $gid];
}

function ebdg_bulk_assign(int $clientId, array $body): array
{
    $deviceIds = $body['device_ids'] ?? $body['deviceIds'] ?? [];
    if (!is_array($deviceIds)) $deviceIds = [];
    $deviceIds = array_values(array_unique(array_filter(array_map('strval', $deviceIds), fn($v) => trim($v) !== '')));
    if (!$deviceIds) return ['status' => 'error', 'message' => 'No devices selected'];

    $groupId = null;
    if (array_key_exists('group_id', $body)) $groupId = $body['group_id'];
    else if (array_key_exists('groupId', $body)) $groupId = $body['groupId'];
    $gid = ($groupId === null || $groupId === '' ? 0 : (int)$groupId);
    if ($gid > 0 && !ebdg_group_belongs($clientId, $gid)) return ['status' => 'error', 'message' => 'Group not found'];

    $found = ebdg_devices_exist($clientId, $deviceIds);
    sort($found);
    $tmp = $deviceIds; sort($tmp);
    if ($found !== $tmp) return ['status' => 'error', 'message' => 'One or more devices were not found'];

    if ($gid <= 0) {
        Capsule::table('eb_device_group_assignments')
            ->where('client_id', $clientId)
            ->whereIn('device_id', $deviceIds)
            ->delete();
        return ['status' => 'success', 'updated' => count($deviceIds), 'group_id' => null];
    }

    foreach ($deviceIds as $did) {
        Capsule::table('eb_device_group_assignments')->updateOrInsert(
            ['client_id' => $clientId, 'device_id' => $did],
            ['group_id' => $gid, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }

    return ['status' => 'success', 'updated' => count($deviceIds), 'group_id' => $gid];
}


