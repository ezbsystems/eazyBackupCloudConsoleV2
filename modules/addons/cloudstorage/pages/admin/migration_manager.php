<?php
require_once __DIR__ . '/../../lib/MigrationController.php';
require_once __DIR__ . '/../../lib/Admin/ProductConfig.php';
require_once __DIR__ . '/../../lib/Admin/ClusterManager.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\MigrationController;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Admin\ClusterManager;

function cloudstorage_admin_migration_manager($vars)
{
    // primary customers only
    // Avoid cross-table string joins (collation mismatches). Build in steps.
    $hosting = Capsule::table('tblhosting')
        ->where('packageid', ProductConfig::$E3_PRODUCT_ID)
        ->select('userid','username')
        ->get();

    $userIdToUsername = [];
    $allHostUsernames = [];
    foreach ($hosting as $h) {
        $userIdToUsername[(int)$h->userid] = $h->username;
        if ($h->username !== null && $h->username !== '') {
            $allHostUsernames[] = $h->username;
        }
    }
    $allHostUsernames = array_values(array_unique($allHostUsernames));

    $primaryUsernames = [];
    if (!empty($allHostUsernames)) {
        $primaryUsernames = Capsule::table('s3_users')
            ->whereIn('username', $allHostUsernames)
            ->where(function ($q) { $q->whereNull('parent_id')->orWhere('parent_id', 0); })
            ->pluck('username')
            ->toArray();
    }

    $eligibleClientIds = [];
    if (!empty($primaryUsernames)) {
        $primarySet = array_flip($primaryUsernames);
        foreach ($userIdToUsername as $cid => $uname) {
            if ($uname !== null && isset($primarySet[$uname])) {
                $eligibleClientIds[] = $cid;
            }
        }
        $eligibleClientIds = array_values(array_unique($eligibleClientIds));
    }

    $clients = [];
    if (!empty($eligibleClientIds)) {
        $clients = Capsule::table('tblclients')
            ->whereIn('id', $eligibleClientIds)
            ->select('id','firstname','lastname','companyname')
            ->get();
    }

    $rows = [];
    $clusters = ClusterManager::getAllClusters();
    $clusterAliases = $clusters->pluck('cluster_alias')->toArray();
    foreach ($clients as $cl) {
        $rows[] = [
            'id'     => $cl->id,
            'name'   => trim($cl->firstname . ' ' . $cl->lastname . ' ' . $cl->companyname),
            'status' => MigrationController::getBackendForClient($cl->id),
        ];
    }

    $html = '<div class="p-4">';
    $html .= '<h2>Cloud Storage Migration Manager</h2>';
    $html .= '<table class="table table-bordered table-striped align-middle"><thead class="table-dark"><tr>';
    $html .= '<th>ID</th><th>Name</th><th>Status</th><th style="width:240px">Action</th></tr></thead><tbody>';

    if (empty($rows)) {
        $html .= '<tr><td colspan="4" class="text-center">No clients found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $friendly = htmlspecialchars($row['status']);
            $options = '';
            foreach ($clusterAliases as $alias) {
                $selected = $alias === $row['status'] ? ' selected' : '';
                $options .= '<option value="'.htmlspecialchars($alias).'"'.$selected.'>'.htmlspecialchars($alias).'</option>';
            }
            // Status chip derived from access key state and flip timestamp
            $statusAlias = $row['status'];
            $keys = ClusterManager::getAccessKeysByClient((int)$row['id']);
            $isFrozen = false; $latestFlip = null;
            foreach ($keys as $k) {
                if (($k['state'] ?? '') === 'frozen') { $isFrozen = true; }
                if (!empty($k['flipped_at'])) {
                    if ($latestFlip === null || $k['flipped_at'] > $latestFlip) { $latestFlip = $k['flipped_at']; }
                }
            }
            if ($isFrozen) {
                $statusChip = '<span class="badge bg-warning text-dark">Frozen</span>';
            } elseif ($latestFlip) {
                $statusChip = '<span class="badge bg-info">Flipped @ '.htmlspecialchars($latestFlip).'</span>';
            } else {
                $statusChip = '<span class="badge '.($statusAlias===($clusterAliases[0]??'old_ceph_cluster')?'bg-secondary':'bg-success').'">Active @ '.htmlspecialchars($statusAlias).'</span>';
            }

            $btn = ''
                . '<div class="d-flex align-items-center gap-2">'
                .   $statusChip
                .   '<select class="form-select form-select-sm cluster-select" data-id="'.$row['id'].'" style="max-width:220px">'.$options.'</select>'
                .   '<button class="btn btn-sm btn-primary btn-apply-cluster" data-id="'.$row['id'].'">Flip</button>'
                .   '<button class="btn btn-sm btn-outline-warning btn-freeze" data-id="'.$row['id'].'">Freeze</button>'
                .   '<button class="btn btn-sm btn-outline-success btn-unfreeze" data-id="'.$row['id'].'">Unfreeze</button>'
                .   '<button class="btn btn-sm btn-outline-dark btn-reset-backend" data-id="'.$row['id'].'">Reset</button>'
                . '</div>';
            $html .= '<tr>';
            $html .= '<td>'.$row['id'].'</td>';
            $html .= '<td>'.htmlspecialchars($row['name']).'</td>';
            $html .= '<td>'.$friendly.'</td>';
            $html .= '<td>'.$btn.'</td>';
            $html .= '</tr>';
        }
    }

    $html .= '</tbody></table></div>';

    $token = function_exists('generate_token') ? generate_token('link') : '';

    $html .= <<<'JS'
<script>
    var ajaxUrl = 'addonmodules.php?module=cloudstorage&action=ajax';
    function confirmByTyping(message, expected){
        var input = prompt(message + "\n\nType: " + expected + " to confirm");
        return input!==null && input.trim()===expected;
    }
    $(document).on('click','.btn-apply-cluster',function(){
        var id = $(this).data('id');
        var alias = $(this).closest('td').find('.cluster-select').val();
        var name = $(this).closest('tr').find('td:nth-child(2)').text().trim();
        if(!confirmByTyping('Flip client '+id+' to cluster '+alias+'?', name)) return;
        $.post(ajaxUrl,{ajax_action:'set_client_cluster',client_id:id,cluster_alias:alias, token: TOKEN_PLACEHOLDER },function(resp){
            if(resp.status==='success') location.reload(); else alert(resp.message||'Error');
        },'json');
    });
    $(document).on('click','.btn-freeze',function(){
        var id = $(this).data('id');
        var name = $(this).closest('tr').find('td:nth-child(2)').text().trim();
        if(!confirmByTyping('Freeze all access keys for client '+id+'?', name)) return;
        $.post(ajaxUrl,{ajax_action:'freeze_client',client_id:id, token: TOKEN_PLACEHOLDER },function(resp){
            if(resp.status==='success') location.reload(); else alert(resp.message||'Error');
        },'json');
    });
    $(document).on('click','.btn-unfreeze',function(){
        var id = $(this).data('id');
        var name = $(this).closest('tr').find('td:nth-child(2)').text().trim();
        if(!confirmByTyping('Unfreeze all access keys for client '+id+'?', name)) return;
        $.post(ajaxUrl,{ajax_action:'unfreeze_client',client_id:id, token: TOKEN_PLACEHOLDER },function(resp){
            if(resp.status==='success') location.reload(); else alert(resp.message||'Error');
        },'json');
    });
    $(document).on('click','.btn-reset-backend',function(){
        var id = $(this).data('id');
        var name = $(this).closest('tr').find('td:nth-child(2)').text().trim();
        if(!confirmByTyping('Reset client '+id+' cluster mapping to default?', name)) return;
        $.post(ajaxUrl,{ajax_action:'reset_client_cluster',client_id:id, token: TOKEN_PLACEHOLDER },function(resp){
            if(resp.status==='success') location.reload(); else alert(resp.message||'Error');
        },'json');
    });
</script>
JS;

    // Inject CSRF token into the JS we just appended
    $html = str_replace('TOKEN_PLACEHOLDER', "'" . addslashes($token) . "'", $html);

    return $html;
}
?>