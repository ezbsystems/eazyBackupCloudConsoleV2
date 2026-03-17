<?php

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

use WHMCS\Database\Capsule;

/**
 * Admin Client Services: Billing Overrides panel.
 * Injects a "Billing Overrides" section so admins can set per-service storage/device billing exemptions
 * (e.g. "Uses e3 Object Storage" — storage billing not required).
 */
add_hook('AdminAreaHeadOutput', 112234, function ($vars) {
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
        $service_id = (int)$whmcs->get_req_var('id');
    } elseif (!empty($whmcs->get_req_var('productselect'))) {
        $service_id = (int)$whmcs->get_req_var('productselect');
    }
    if (empty($service_id)) {
        $svc = Capsule::table('tblhosting')->select('id')->where('userid', $userid)->first();
        $service_id = $svc ? (int)$svc->id : null;
    }
    if (empty($service_id)) {
        return '';
    }

    $service = Capsule::table('tblhosting')->find($service_id);
    if (!$service) {
        return '';
    }

    $clientId = (int)($service->userid ?? 0);

    // Load current flags (table may not exist on very old installs)
    $storage_exempt = 0;
    $devices_exempt = 0;
    $notes = '';
    if (Capsule::schema()->hasTable('eb_billing_flags')) {
        $row = Capsule::table('eb_billing_flags')->where('service_id', $service_id)->first();
        if ($row) {
            $storage_exempt = (int)($row->storage_exempt ?? 0);
            $devices_exempt = (int)($row->devices_exempt ?? 0);
            $notes = (string)($row->notes ?? '');
        }
    }

    // Does this client have an active e3 Object Storage service (packageid=48)?
    $hasE3 = false;
    if ($clientId > 0) {
        $hasE3 = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('packageid', 48)
            ->where('domainstatus', 'Active')
            ->exists();
    }

    $e3Badge = '';
    if ($hasE3) {
        $e3Badge = '<span class="label label-info" style="margin-left:8px;">This client has an active e3 Object Storage service</span>';
    }

    $storageChecked = $storage_exempt ? ' checked="checked"' : '';
    $devicesChecked = $devices_exempt ? ' checked="checked"' : '';
    $notesEsc = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');
    $ajaxPath = '/includes/hooks/eb_billing_flags_ajax.php';
    $token = function_exists('generate_token') ? (string)generate_token('plain') : '';
    $tokenEsc = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

    $panel = '<tr id="eb-billing-flags-row"><td colspan="2" class="fieldarea">'
        . '<div class="panel panel-default">'
        . '<div class="panel-heading"><strong>Billing Overrides</strong></div>'
        . '<div class="panel-body">'
        . '<p class="text-muted">Per-service exemptions for billing (e.g. storage billed via e3 Object Storage).</p>'
        . '<div class="form-group">'
        . '<label><input type="checkbox" name="eb_storage_exempt" id="eb_storage_exempt" value="1"' . $storageChecked . '> Storage billing exempt</label>'
        . $e3Badge
        . '</div>'
        . '<div class="form-group">'
        . '<label><input type="checkbox" name="eb_devices_exempt" id="eb_devices_exempt" value="1"' . $devicesChecked . '> Device billing exempt</label>'
        . '</div>'
        . '<div class="form-group">'
        . '<label for="eb_billing_notes">Notes</label>'
        . '<input type="text" class="form-control" name="eb_billing_notes" id="eb_billing_notes" value="' . $notesEsc . '" placeholder="e.g. Customer uses e3 Object Storage for this service" style="max-width:400px;">'
        . '</div>'
        . '<button type="button" class="btn btn-primary" id="eb-save-billing-flags">Save Billing Flags</button>'
        . '</div></div></td></tr>';

    $script = '<script type="text/javascript">'
        . 'jQuery(document).ready(function(){'
        . 'var inject = function(){'
        . 'if (document.getElementById("eb-billing-flags-row")) return;'
        . 'var content = ' . json_encode($panel) . ';'
        . 'var $c = jQuery("#servicecontent");'
        . 'var $anchor = $c.find("table.form tr").filter(function(){ return jQuery(this).find("td:first").text().toLowerCase().indexOf("configurable") >= 0 || jQuery(this).find("td").text().toLowerCase().indexOf("config option") >= 0; }).last();'
        . 'if ($anchor.length) { $anchor.after(content); } else {'
        . 'var $tb = $c.find("table.form tbody").first();'
        . 'if ($tb.length) $tb.append(content); else $c.append(content);'
        . '}'
        . '};'
        . 'inject();'
        . 'setTimeout(inject, 500);'
        . 'jQuery(document).on("click", "#eb-save-billing-flags", function(){'
        . 'var $btn = jQuery(this);'
        . 'if ($btn.prop("disabled")) return;'
        . 'var token = jQuery("input[name=\'token\']").first().val() || (typeof csrfToken !== "undefined" ? csrfToken : "") || "";'
        . 'var storage_exempt = jQuery("#eb_storage_exempt").prop("checked") ? 1 : 0;'
        . 'var devices_exempt = jQuery("#eb_devices_exempt").prop("checked") ? 1 : 0;'
        . 'var notes = (jQuery("#eb_billing_notes").val() || "").trim();'
        . '$btn.prop("disabled", true).text("Saving…");'
        . 'jQuery.post("' . $ajaxPath . '", {'
        . 'ajax_action: "save_billing_flags",'
        . 'token: token,'
        . 'service_id: ' . (int)$service_id . ','
        . 'storage_exempt: storage_exempt,'
        . 'devices_exempt: devices_exempt,'
        . 'notes: notes'
        . '}).done(function(r){'
        . 'try { if (typeof r === "string") r = JSON.parse(r); } catch(e) { r = { status: false }; }'
        . 'if (r && r.status) { $btn.text("Saved").css("color","green"); setTimeout(function(){ location.reload(); }, 600); }'
        . 'else { alert(r && r.message ? r.message : "Save failed"); $btn.prop("disabled", false).text("Save Billing Flags"); }'
        . '}).fail(function(){ alert("Request failed"); $btn.prop("disabled", false).text("Save Billing Flags"); });'
        . '});'
        . '});'
        . '</script>';

    return $script;
});
