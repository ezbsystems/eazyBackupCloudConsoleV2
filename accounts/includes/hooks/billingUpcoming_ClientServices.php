<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

function eb_render_upcoming_charges_panel($vars) {
    if ($vars['filename'] !== 'clientsservices') { return ''; }

    try {
        global $whmcs;
        $return = '';
        $userid = $whmcs->get_req_var('userid');
        $serviceId = null;

        if (empty($whmcs->get_req_var('id'))) {
            $serviceId = $whmcs->get_req_var('productselect');
        } else {
            $serviceId = $whmcs->get_req_var('id');
        }

        if (empty($whmcs->get_req_var('id')) && empty($whmcs->get_req_var('productselect'))) {
            $service = Capsule::table('tblhosting')->select('id')->where('userid', $userid)->first();
            if ($service) { $serviceId = $service->id; }
        }

        $serviceId = (int)$serviceId;
        if ($serviceId <= 0) return '';
        $svc = Capsule::table('tblhosting')->select('userid','username')->where('id',$serviceId)->first();
        if (!$svc || !$svc->username) return '';

        // Upcoming charges: join recent notifications and grace for this service/username
        $recentCutoff = date('Y-m-d H:i:s', strtotime('-5 days'));
        $rows = Capsule::table('eb_notifications_sent as n')
            ->leftJoin('eb_billing_grace as g', function($j){ $j->on('g.username','=','n.username'); })
            ->where('n.service_id',$serviceId)
            ->whereNull('n.acknowledged_at')
            ->where('n.created_at','>=',$recentCutoff)
            ->orderBy('n.created_at','desc')
            ->limit(12)
            ->get(['n.created_at','n.category','n.subject','g.first_seen_at as grace_first_seen_at','g.grace_expires_at as grace_expires_at','g.grace_days as grace_days']);

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'category' => (string)$r->category,
                'subject' => (string)$r->subject,
                'created_at' => (string)$r->created_at,
                'first_seen' => (string)($r->grace_first_seen_at ?? ''),
                'expires' => (string)($r->grace_expires_at ?? ''),
                'days' => (int)($r->grace_days ?? 0),
            ];
        }

        $json = json_encode($items);
        $cooldownBase = 'addonmodules.php?module=eazybackup&action=billing-cooldown&serviceid=' . $serviceId;

        $html = <<<HTML
<script type="text/javascript">
(function($){
  var data = $json;
  function buildPanelHtml(){
    var tbl = '<table class="table table-striped table-condensed"><thead><tr><th>Category</th><th>Subject</th><th>First Seen</th><th>Grace Expires</th><th>Days</th></tr></thead><tbody>';
    if(!data || data.length===0){ tbl += '<tr><td colspan="5" class="text-muted">No upcoming charges</td></tr>'; }
    else { for(var i=0;i<data.length;i++){ var r=data[i]; tbl += '<tr><td>' + r.category + '</td><td>' + r.subject + '</td><td>' + (r.first_seen||'-') + '</td><td>' + (r.expires||'-') + '</td><td>' + (r.days||0) + '</td></tr>'; } }
    tbl += '</tbody></table>';
    return '<div class="panel panel-default" id="eb-upcoming-charges"><div class="panel-heading"><strong>Upcoming Charges</strong> <button id="eb-cooldown" class="btn btn-xs btn-default pull-right">Cooldown (+3 days)</button></div><div class="panel-body">' + tbl + '</div></div>';
  }
  function inject(){
    if($("#eb-upcoming-charges").length) return;
    var svc = $("#servicecontent");
    if(!svc.length){ setTimeout(inject, 250); return; }
    var anchor = svc.find(".context-btn-container");
    var html = buildPanelHtml();
    if(anchor.length){ anchor.after(html); } else { svc.prepend(html); }
  }
  $(function(){ inject(); });
  $(document).on("click", "#eb-cooldown", function(ev){
    ev.preventDefault();
    var tok = ($("input[name=token]").val()||"");
    var url = "$cooldownBase" + (tok? ("&token="+encodeURIComponent(tok)) : "");
    $.post(url, {}, function(resp){
      try{ var r=JSON.parse(resp); alert(r.message||"Cooldown applied"); }
      catch(_){ alert("Cooldown applied"); }
      location.reload();
    });
  });
})(jQuery);
</script>
HTML;

        return $html;
    } catch (\Throwable $e) { return ''; }
}

add_hook('AdminAreaHeadOutput', 112223, function ($vars) {
    return eb_render_upcoming_charges_panel($vars);
});

add_hook('AdminAreaFooterOutput', 112223, function ($vars) {
    return eb_render_upcoming_charges_panel($vars);
});


