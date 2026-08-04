<?php

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

use WHMCS\Database\Capsule;

/**
 * Admin Client Services: Object Storage billing exemption panel.
 * Shown only when the selected service is the Cloud Storage product (pid_cloud_storage).
 */
add_hook('AdminAreaHeadOutput', 112235, function ($vars) {
    if (($vars['filename'] ?? '') !== 'clientsservices') {
        return '';
    }

    global $whmcs;
    $userid = $whmcs->get_req_var('userid');
    if (empty($userid)) {
        return '';
    }

    $service_id = null;
    if (!empty($whmcs->get_req_var('id'))) {
        $service_id = (int) $whmcs->get_req_var('id');
    } elseif (!empty($whmcs->get_req_var('productselect'))) {
        $service_id = (int) $whmcs->get_req_var('productselect');
    }
    if (empty($service_id)) {
        $svc = Capsule::table('tblhosting')->select('id')->where('userid', $userid)->first();
        $service_id = $svc ? (int) $svc->id : null;
    }
    if (empty($service_id)) {
        return '';
    }

    $service = Capsule::table('tblhosting')->find($service_id);
    if (!$service) {
        return '';
    }

    $pidCloudStorage = 48;
    try {
        $configured = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'pid_cloud_storage')
            ->value('value');
        if ((int) $configured > 0) {
            $pidCloudStorage = (int) $configured;
        }
    } catch (\Throwable $e) {
        // keep legacy fallback
    }

    if ((int) ($service->packageid ?? 0) !== $pidCloudStorage) {
        return '';
    }

    $billing_exempt = 0;
    $notes = '';
    if (Capsule::schema()->hasTable('s3_billing_flags')) {
        $row = Capsule::table('s3_billing_flags')->where('service_id', $service_id)->first();
        if ($row) {
            $billing_exempt = (int) ($row->billing_exempt ?? 0);
            $notes = (string) ($row->notes ?? '');
        }
    }

    $exemptChecked = $billing_exempt ? ' checked="checked"' : '';
    $notesEsc = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');
    $ajaxPath = '/includes/hooks/s3_billing_flags_ajax.php';

    $panel = '<div class="panel panel-default" id="s3-billing-flags-panel" style="margin-top:12px;">'
        . '<div class="panel-heading"><strong>Object Storage Billing</strong></div>'
        . '<div class="panel-body">'
        . '<p class="text-muted">Mark this Cloud Storage service as complimentary. While exempt, the billing cron keeps the recurring amount at $0.00 and skips usage-based MAX billing.</p>'
        . '<div class="form-group">'
        . '<label><input type="checkbox" name="s3_billing_exempt" id="s3_billing_exempt" value="1"' . $exemptChecked . '> Object storage billing exempt</label>'
        . '</div>'
        . '<div class="form-group">'
        . '<label for="s3_billing_notes">Notes</label>'
        . '<input type="text" class="form-control" name="s3_billing_notes" id="s3_billing_notes" value="' . $notesEsc . '" placeholder="e.g. Partner complimentary account" style="max-width:400px;">'
        . '</div>'
        . '<button type="button" class="btn btn-primary" id="s3-save-billing-flags">Save Billing Flag</button>'
        . '</div></div>';

    $script = '<script type="text/javascript">'
        . 'jQuery(document).ready(function(){'
        . 'var inject = function(){'
        . 'if (document.getElementById("s3-billing-flags-panel")) return;'
        . 'var content = ' . json_encode($panel) . ';'
        . 'var $summary = jQuery("#eb-e3-storage-summary");'
        . 'if ($summary.length) { $summary.after(content); return; }'
        . 'var $host = jQuery("#profileContent");'
        . 'if ($host.length) { $host.prepend(content); return; }'
        . 'var $c = jQuery("#servicecontent");'
        . 'if ($c.length) { $c.prepend(content); }'
        . '};'
        . 'inject();'
        . 'setTimeout(inject, 500);'
        . 'jQuery(document).on("click", "#s3-save-billing-flags", function(){'
        . 'var $btn = jQuery(this);'
        . 'if ($btn.prop("disabled")) return;'
        . 'var token = jQuery("input[name=\'token\']").first().val() || (typeof csrfToken !== "undefined" ? csrfToken : "") || "";'
        . 'var billing_exempt = jQuery("#s3_billing_exempt").prop("checked") ? 1 : 0;'
        . 'var notes = (jQuery("#s3_billing_notes").val() || "").trim();'
        . '$btn.prop("disabled", true).text("Saving…");'
        . 'jQuery.post("' . $ajaxPath . '", {'
        . 'ajax_action: "save_billing_flags",'
        . 'token: token,'
        . 'service_id: ' . (int) $service_id . ','
        . 'billing_exempt: billing_exempt,'
        . 'notes: notes'
        . '}).done(function(r){'
        . 'try { if (typeof r === "string") r = JSON.parse(r); } catch(e) { r = { status: false }; }'
        . 'if (r && r.status) { $btn.text("Saved").css("color","green"); setTimeout(function(){ location.reload(); }, 600); }'
        . 'else { alert(r && r.message ? r.message : "Save failed"); $btn.prop("disabled", false).text("Save Billing Flag"); }'
        . '}).fail(function(){ alert("Request failed"); $btn.prop("disabled", false).text("Save Billing Flag"); });'
        . '});'
        . '});'
        . '</script>';

    return $script;
});
