<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

/**
 * Inject an "e3 Object Storage Summary" panel into the admin client service page
 * for the Cloud Storage product (PID=48).
 *
 * Location target (admin DOM): div#profileContent
 */
add_hook('AdminAreaHeadOutput', 112230, function ($vars) {
    if (($vars['filename'] ?? '') !== 'clientsservices') {
        return '';
    }

    try {
        global $whmcs;

        // Resolve service ID (matches patterns used by existing hooks)
        $serviceId = null;
        if (empty($whmcs->get_req_var('id'))) {
            $serviceId = $whmcs->get_req_var('productselect');
        } else {
            $serviceId = $whmcs->get_req_var('id');
        }

        $userId = $whmcs->get_req_var('userid');
        if (empty($whmcs->get_req_var('id')) && empty($whmcs->get_req_var('productselect')) && $userId) {
            $service = Capsule::table('tblhosting')->select('id')->where('userid', (int)$userId)->first();
            if ($service) { $serviceId = $service->id; }
        }

        $serviceId = (int)$serviceId;
        if ($serviceId <= 0) {
            return '';
        }

        $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$svc) {
            return '';
        }

        // Only for e3 Cloud Storage product (PID=48)
        $pid = (int)($svc->packageid ?? 0);
        if ($pid !== 48) {
            return '';
        }

        $primaryUsername = (string)($svc->username ?? '');
        if ($primaryUsername === '') {
            return '';
        }

        // Resolve primary s3_users (prefer parent_id NULL)
        $primaryUser = Capsule::table('s3_users')
            ->where('username', $primaryUsername)
            ->whereNull('parent_id')
            ->first();
        if (!$primaryUser) {
            // Fallback: if the stored username is actually a tenant, try to locate its parent
            $tenantRow = Capsule::table('s3_users')
                ->where('username', $primaryUsername)
                ->whereNotNull('parent_id')
                ->first();
            if ($tenantRow && isset($tenantRow->parent_id)) {
                $primaryUser = Capsule::table('s3_users')->where('id', (int)$tenantRow->parent_id)->first();
            }
        }

        if (!$primaryUser) {
            // Still no mapping - show a small note panel instead of failing silently
            $note = "No matching s3_users record found for service username '{$primaryUsername}'.";
            $noteJson = json_encode($note);
            return <<<HTML
<script type="text/javascript">
(function($){
  function inject(){
    if($("#eb-e3-storage-summary").length) return;
    var host = $("#profileContent");
    if(!host.length){ setTimeout(inject, 250); return; }
    var html = '<div class="panel panel-default" id="eb-e3-storage-summary">'
      + '<div class="panel-heading"><strong>e3 Object Storage Summary</strong></div>'
      + '<div class="panel-body"><span class="text-muted">' + {$noteJson} + '</span></div>'
      + '</div>';
    host.prepend(html);
  }
  $(function(){ inject(); });
})(jQuery);
</script>
HTML;
        }

        $primaryUserId = (int)$primaryUser->id;

        // Sub-tenants for the primary
        $tenantRows = Capsule::table('s3_users')
            ->where('parent_id', $primaryUserId)
            ->get(['id', 'username']);

        $userIds = [$primaryUserId];
        $usernames = [(string)$primaryUser->username];
        foreach ($tenantRows as $t) {
            $userIds[] = (int)$t->id;
            $usernames[] = (string)$t->username;
        }

        // Bucket count from registry (active only if the column exists)
        $hasBucketIsActive = false;
        try { $hasBucketIsActive = Capsule::schema()->hasColumn('s3_buckets', 'is_active'); } catch (\Throwable $e) {}

        $bucketQuery = Capsule::table('s3_buckets')->whereIn('user_id', $userIds);
        if ($hasBucketIsActive) {
            $bucketQuery->where('is_active', 1);
        }
        $bucketCount = (int)$bucketQuery->count();

        // Total storage bytes: sum latest history per bucket_name+bucket_owner for the buckets belonging to these users.
        // We join s3_buckets -> s3_users to get the expected bucket_owner (username).
        $latestSub = Capsule::raw('(
            SELECT bucket_name, bucket_owner, MAX(collected_at) AS max_collected_at
            FROM s3_bucket_sizes_history
            GROUP BY bucket_name, bucket_owner
        ) h2');

        $histQuery = Capsule::table('s3_buckets as b')
            ->join('s3_users as u', 'u.id', '=', 'b.user_id')
            ->leftJoin($latestSub, function ($join) {
                $join->on('h2.bucket_name', '=', 'b.name')
                    ->on('h2.bucket_owner', '=', 'u.username');
            })
            ->leftJoin('s3_bucket_sizes_history as h1', function ($join) {
                $join->on('h1.bucket_name', '=', 'b.name')
                    ->on('h1.bucket_owner', '=', 'u.username')
                    ->on('h1.collected_at', '=', 'h2.max_collected_at');
            })
            ->whereIn('b.user_id', $userIds);

        if ($hasBucketIsActive) {
            $histQuery->where('b.is_active', 1);
        }

        $totals = $histQuery->select([
            Capsule::raw('SUM(COALESCE(h1.bucket_size_bytes, 0)) AS total_bytes'),
            Capsule::raw('SUM(COALESCE(h1.bucket_object_count, 0)) AS total_objects')
        ])->first();

        $totalBytes = (int)($totals->total_bytes ?? 0);
        $totalObjects = (int)($totals->total_objects ?? 0);

        // Format bytes
        $formattedBytes = eb_cloudstorage_format_bytes($totalBytes);

        $billingExempt = false;
        $billingNotes = '';
        try {
            if (Capsule::schema()->hasTable('s3_billing_flags')) {
                $flag = Capsule::table('s3_billing_flags')->where('service_id', $serviceId)->first();
                $billingExempt = $flag !== null && (int) ($flag->billing_exempt ?? 0) === 1;
                $billingNotes = $flag ? (string) ($flag->notes ?? '') : '';
            }
        } catch (\Throwable $e) {
            $billingExempt = false;
            $billingNotes = '';
        }

        // JSON for JS injection
        $data = [
            'serviceId' => $serviceId,
            'primary' => (string)$primaryUser->username,
            'tenantCount' => count($userIds) - 1,
            'bucketCount' => $bucketCount,
            'totalBytes' => $totalBytes,
            'totalBytesFormatted' => $formattedBytes,
            'totalObjects' => $totalObjects,
            'activeBucketsOnly' => $hasBucketIsActive ? true : false,
            'billing_exempt' => $billingExempt,
            'billing_notes' => $billingNotes,
            'ajaxPath' => '/includes/hooks/s3_billing_flags_ajax.php',
        ];
        $json = json_encode($data);

        return <<<HTML
<script type="text/javascript">
(function($){
  var data = $json;
  function billingFormHtml(){
    var checked = data.billing_exempt ? ' checked="checked"' : '';
    var notes = (data.billing_notes || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
    return '<div id="s3-billing-flags-panel" style="margin-top:14px;padding-top:12px;border-top:1px solid #eee;">'
      + '<strong>Object Storage Billing</strong>'
      + '<p class="text-muted" style="margin:6px 0 8px;">Mark this Cloud Storage service as complimentary. While exempt, the billing cron keeps the recurring amount at \$0.00.</p>'
      + '<div class="form-group" style="margin-bottom:8px;">'
      +   '<label><input type="checkbox" id="s3_billing_exempt" value="1"' + checked + '> Object storage billing exempt</label>'
      + '</div>'
      + '<div class="form-group" style="margin-bottom:8px;">'
      +   '<label for="s3_billing_notes">Notes</label>'
      +   '<input type="text" class="form-control" id="s3_billing_notes" value="' + notes + '" placeholder="e.g. Partner complimentary account" style="max-width:400px;">'
      + '</div>'
      + '<button type="button" class="btn btn-primary btn-sm" id="s3-save-billing-flags">Save Billing Flag</button>'
      + '</div>';
  }
  function panelHtml(){
    var sub = (data.tenantCount > 0) ? ('<span class="label label-info" style="margin-left:8px;">' + data.tenantCount + ' tenant(s)</span>') : '';
    var exemptBadge = data.billing_exempt ? ('<span class="label label-success" style="margin-left:8px;">Billing exempt</span>') : '';
    var note = data.activeBucketsOnly ? '' : '<div class="text-muted" style="margin-top:6px;">Note: Bucket active flag not available; counts may include inactive buckets.</div>';
    return '<div class="panel panel-default" id="eb-e3-storage-summary">'
      + '<div class="panel-heading"><strong>e3 Object Storage Summary</strong>' + sub + exemptBadge + '</div>'
      + '<div class="panel-body">'
      +   '<div class="row">'
      +     '<div class="col-md-4"><strong>Total Buckets:</strong><br><span style="font-size:18px;">' + data.bucketCount + '</span></div>'
      +     '<div class="col-md-4"><strong>Total Storage:</strong><br><span style="font-size:18px;">' + data.totalBytesFormatted + '</span></div>'
      +     '<div class="col-md-4"><strong>Total Objects:</strong><br><span style="font-size:18px;">' + (data.totalObjects || 0).toLocaleString() + '</span></div>'
      +   '</div>'
      +   '<div class="text-muted" style="margin-top:8px;">Primary storage user: <span style="font-family:monospace;">' + data.primary + '</span></div>'
      +   note
      +   billingFormHtml()
      + '</div>'
      + '</div>';
  }
  function bindSave(){
    $(document).off('click.s3BillingFlags', '#s3-save-billing-flags').on('click.s3BillingFlags', '#s3-save-billing-flags', function(){
      var \$btn = $(this);
      if (\$btn.prop('disabled')) return;
      var token = $("input[name='token']").first().val() || (typeof csrfToken !== 'undefined' ? csrfToken : '') || '';
      var billing_exempt = $('#s3_billing_exempt').prop('checked') ? 1 : 0;
      var notes = ($('#s3_billing_notes').val() || '').trim();
      \$btn.prop('disabled', true).text('Saving…');
      $.post(data.ajaxPath, {
        ajax_action: 'save_billing_flags',
        token: token,
        service_id: data.serviceId,
        billing_exempt: billing_exempt,
        notes: notes
      }).done(function(r){
        try { if (typeof r === 'string') r = JSON.parse(r); } catch(e) { r = { status: false }; }
        if (r && r.status) { \$btn.text('Saved').css('color','green'); setTimeout(function(){ location.reload(); }, 600); }
        else { alert(r && r.message ? r.message : 'Save failed'); \$btn.prop('disabled', false).text('Save Billing Flag'); }
      }).fail(function(){ alert('Request failed'); \$btn.prop('disabled', false).text('Save Billing Flag'); });
    });
  }
  function inject(){
    if($("#eb-e3-storage-summary").length) return;
    var host = $("#profileContent");
    if(!host.length){ setTimeout(inject, 250); return; }
    host.prepend(panelHtml());
    bindSave();
  }
  $(function(){ inject(); bindSave(); });
})(jQuery);
</script>
HTML;
    } catch (\Throwable $e) {
        // Fail silently in admin UI, but keep a trace in module log
        try { logModuleCall('cloudstorage', 'admin_clientsservices_storage_summary_error', [], $e->getMessage()); } catch (\Throwable $_) {}
        return '';
    }
});

/**
 * Simple bytes formatter for admin display.
 */
function eb_cloudstorage_format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $value = (float)$bytes;
    $i = 0;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return round($value, $precision) . ' ' . $units[$i];
}


