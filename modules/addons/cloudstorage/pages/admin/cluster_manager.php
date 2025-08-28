<?php

require_once __DIR__ . '/../../lib/Admin/ClusterManager.php';

use WHMCS\Module\Addon\CloudStorage\Admin\ClusterManager;

/**
 * Admin Cluster Manager page – list/add/edit/delete Ceph clusters.
 */
function cloudstorage_admin_cluster_manager($vars)
{
    $clusters = ClusterManager::getAllClusters();

    // Use heredoc so PHP string quotes don't break JS/HTML
    $html = <<<HTML
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="m-0">Ceph Cluster Manager</h2>
            <button class="btn btn-primary" id="btnAdd">Add Cluster</button>
        </div>
        <table class="table table-bordered table-striped align-middle" id="clusterTable">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Name</th><th>Alias</th><th>Endpoint</th><th>Default</th><th style="width:120px">Actions</th>
                </tr>
            </thead>
            <tbody>
    HTML;

    if ($clusters->isEmpty()) {
        $html .= '<tr><td colspan="6" class="text-center">No clusters configured.</td></tr>';
    } else {
        foreach ($clusters as $cluster) {
            $rowJson = htmlspecialchars(json_encode($cluster), ENT_QUOTES);
            $html .= '<tr data-id="' . (int)$cluster->id . '" data-json="' . $rowJson . '">'
                . '<td>' . (int)$cluster->id . '</td>'
                . '<td>' . htmlspecialchars($cluster->cluster_name) . '</td>'
                . '<td>' . htmlspecialchars($cluster->cluster_alias) . '</td>'
                . '<td>' . htmlspecialchars($cluster->s3_endpoint) . '</td>'
                . '<td>' . ($cluster->is_default ? '<span class="badge bg-success">Yes</span>' : 'No') . '</td>'
                . '<td>'
                    . '<button class="btn btn-sm btn-secondary me-1 btn-edit">Edit</button>'
                    . '<button class="btn btn-sm btn-info me-1 btn-test">Test</button>'
                    . '<button class="btn btn-sm btn-danger btn-delete">Delete</button>'
                . '</td>'
                . '</tr>';
        }
    }

    $html .= "</tbody></table>";

    // Modal
    $html .= <<<MODAL
    <div class="modal fade" id="clusterModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalTitle">Add Cluster</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body">
            <form id="clusterForm">
              <input type="hidden" name="cluster_id" id="cluster_id" />
              <div class="form-group"><label class="form-label">Name</label><input type="text" name="cluster_name" id="cluster_name" class="form-control" required></div>
              <div class="form-group"><label class="form-label">Alias (unique)</label><input type="text" name="cluster_alias" id="cluster_alias" class="form-control" required></div>
              <div class="form-group"><label class="form-label">S3 Endpoint</label><input type="text" name="s3_endpoint" id="s3_endpoint" class="form-control" required></div>
              <div class="form-group"><label class="form-label">Admin Access Key</label><input type="text" name="admin_access_key" id="admin_access_key" class="form-control" required></div>
              <div class="form-group"><label class="form-label">Admin Secret Key</label><input type="text" name="admin_secret_key" id="admin_secret_key" class="form-control" required></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="is_default" name="is_default"><label class="form-check-label" for="is_default"> Default Cluster</label></div>
            </form>
            <div id="modalAlert" class="alert alert-danger hide"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="saveBtn">Save</button>
          </div>
        </div>
      </div>
    </div>
MODAL;

    // JS & dependencies (Bootstrap + jQuery are already loaded by WHMCS admin template, so just JS)
    $html .= <<<'JS'
<script>
(function waitForJq(){
    if(typeof window.jQuery==='undefined' || typeof jQuery.fn.modal==='undefined') { return setTimeout(waitForJq,100);} 
    var $ = jQuery;

    var $modal = $('#clusterModal');
    var alertBox = $('#modalAlert');

    function openModal(mode, data){
        alertBox.addClass('hide');
        if(mode==='add'){
            $('#modalTitle').text('Add Cluster');
            $('#clusterForm')[0].reset();
            $('#cluster_id').val('');
        } else {
            $('#modalTitle').text('Edit Cluster');
            $('#cluster_id').val(data.id);
            $('#cluster_name').val(data.cluster_name);
            $('#cluster_alias').val(data.cluster_alias);
            $('#s3_endpoint').val(data.s3_endpoint);
            $('#admin_access_key').val(data.admin_access_key);
            $('#admin_secret_key').val(data.admin_secret_key);
            $('#is_default').prop('checked', data.is_default==1);
        }
        $modal.modal('show');
    }

    var ajaxUrl = 'addonmodules.php?module=cloudstorage&action=ajax';

    $('#btnAdd').on('click', function(){ openModal('add'); });
    $(document).on('click','.btn-edit',function(){ openModal('edit', JSON.parse($(this).closest('tr').attr('data-json'))); });

    $(document).on('click','.btn-test',function(){
        var id = $(this).closest('tr').data('id');
        var row = JSON.parse($(this).closest('tr').attr('data-json'));
        var $btn = $(this);
        var original = $btn.text();
        $btn.prop('disabled', true).text('Testing...');
        $.post(ajaxUrl,{ajax_action:'test_cluster', cluster_alias: row.cluster_alias}, function(resp){
            if(resp.status==='success'){
                alert('Success: ' + (resp.message||'Connection OK'));
            } else {
                alert('Failed: ' + (resp.message||'Unable to connect'));
            }
        },'json').always(function(){ $btn.prop('disabled', false).text(original); });
    });

    $(document).on('click','.btn-delete',function(){
        if(!confirm('Delete this cluster?')) return;
        var id = $(this).closest('tr').data('id');
        $.post(ajaxUrl,{ajax_action:'delete_cluster',cluster_id:id},function(resp){
            if(resp.status==='success') location.reload(); else alert(resp.message||'Error');
        },'json');
    });

    $('#saveBtn').on('click',function(){
        var payload = $('#clusterForm').serializeArray();
        payload.push({name:'ajax_action',value: $('#cluster_id').val()? 'edit_cluster':'add_cluster'});
        $.post(ajaxUrl, payload, function(resp){
            if(resp.status==='success') location.reload(); else alertBox.text(resp.message||'Error').removeClass('hide');
        },'json');
    });
})();
</script>
JS;

    return $html . '</div>';
}
?>