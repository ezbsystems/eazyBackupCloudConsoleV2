<?php

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

use WHMCS\Database\Capsule;

/**
 * Admin Client Services: Object Storage billing exemption panel.
 *
 * Shown for the Cloud Storage product. PID gate matches
 * cloudstorageUsage_ClientServices.php (legacy PID 48) and also accepts
 * pid_cloud_storage from addon settings when that differs.
 *
 * Note: the Object Storage Summary panel also embeds these controls so the
 * checkbox appears in the same place admins already look. This hook is the
 * fallback when the summary panel cannot render (e.g. missing s3_users row).
 */
add_hook('AdminAreaHeadOutput', 112235, function ($vars) {
    if (($vars['filename'] ?? '') !== 'clientsservices') {
        return '';
    }

    try {
        global $whmcs;

        // Resolve service ID (same pattern as cloudstorageUsage_ClientServices)
        $serviceId = null;
        if (empty($whmcs->get_req_var('id'))) {
            $serviceId = $whmcs->get_req_var('productselect');
        } else {
            $serviceId = $whmcs->get_req_var('id');
        }

        $userId = $whmcs->get_req_var('userid');
        if (empty($whmcs->get_req_var('id')) && empty($whmcs->get_req_var('productselect')) && $userId) {
            $service = Capsule::table('tblhosting')->select('id')->where('userid', (int) $userId)->first();
            if ($service) {
                $serviceId = $service->id;
            }
        }

        $serviceId = (int) $serviceId;
        if ($serviceId <= 0) {
            return '';
        }

        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) {
            return '';
        }

        $pid = (int) ($service->packageid ?? 0);
        $configuredPid = 48;
        try {
            $configured = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'pid_cloud_storage')
                ->value('value');
            if ((int) $configured > 0) {
                $configuredPid = (int) $configured;
            }
        } catch (\Throwable $e) {
            $configuredPid = 48;
        }

        // Match summary hook (hardcoded 48) OR configured setting.
        if ($pid !== 48 && $pid !== $configuredPid) {
            return '';
        }

        $billingExempt = 0;
        $notes = '';
        if (Capsule::schema()->hasTable('s3_billing_flags')) {
            $row = Capsule::table('s3_billing_flags')->where('service_id', $serviceId)->first();
            if ($row) {
                $billingExempt = (int) ($row->billing_exempt ?? 0);
                $notes = (string) ($row->notes ?? '');
            }
        }

        $exemptChecked = $billingExempt ? ' checked="checked"' : '';
        $notesEsc = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');
        // Same absolute path pattern as eb_billing_flags.php (site root, not /admin).
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
            . 'var inject = function(attempt){'
            . 'attempt = attempt || 0;'
            . 'if (document.getElementById("s3-billing-flags-panel")) return;'
            // Prefer attaching under the summary panel when present; otherwise profileContent.
            . 'var content = ' . json_encode($panel) . ';'
            . 'var $summary = jQuery("#eb-e3-storage-summary");'
            . 'if ($summary.length) { $summary.after(content); return; }'
            . 'var $host = jQuery("#profileContent");'
            . 'if ($host.length) { $host.prepend(content); return; }'
            . 'var $c = jQuery("#servicecontent");'
            . 'if ($c.length) { $c.prepend(content); return; }'
            . 'if (attempt < 20) { setTimeout(function(){ inject(attempt + 1); }, 250); }'
            . '};'
            . 'inject(0);'
            . 'jQuery(document).on("click", "#s3-save-billing-flags", function(){'
            . 'var $btn = jQuery(this);'
            . 'if ($btn.prop("disabled")) return;'
            . 'var token = jQuery("input[name=\'token\']").first().val() || (typeof csrfToken !== "undefined" ? csrfToken : "") || "";'
            . 'var billing_exempt = jQuery("#s3_billing_exempt").prop("checked") ? 1 : 0;'
            . 'var notes = (jQuery("#s3_billing_notes").val() || "").trim();'
            . '$btn.prop("disabled", true).text("Saving…");'
            . 'jQuery.post(' . json_encode($ajaxPath) . ', {'
            . 'ajax_action: "save_billing_flags",'
            . 'token: token,'
            . 'service_id: ' . (int) $serviceId . ','
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
    } catch (\Throwable $e) {
        try {
            logModuleCall('cloudstorage', 'admin_clientsservices_s3_billing_flags_error', [], $e->getMessage());
        } catch (\Throwable $_) {
        }
        return '';
    }
});
