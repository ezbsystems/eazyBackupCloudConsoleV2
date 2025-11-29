<?php

use WHMCS\Database\Capsule;

function createConfigOptionsDiscountTable()
{
    // Create table for storing recurring discounts on configurable options
    if (!Capsule::Schema()->hasTable('mod_rd_discountConfigOptions')) {
        Capsule::schema()->create('mod_rd_discountConfigOptions', function ($table) {
            $table->increments('id');
            $table->integer('serviceid');
            $table->integer('configoptionid');
            $table->double('discount_price', 8, 2); // Changed from discount_per to discount_price
            $table->double('price', 8, 2);
            $table->timestamp('created_at')->default(Capsule::raw("CURRENT_TIMESTAMP"));
            $table->timestamp('updated_at')->default(Capsule::raw("CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"));
        });
    } else {
        // If table already exists, modify the column
        Capsule::schema()->table('mod_rd_discountConfigOptions', function ($table) {
            if (Capsule::Schema()->hasColumn('mod_rd_discountConfigOptions', 'discount_per')) {
                $table->renameColumn('discount_per', 'discount_price');
                $table->double('discount_price', 8, 2)->change();
            }
        });
    }
}

add_hook('AdminAreaHeadOutput', 112223, function ($vars) {
    if ($vars["filename"] == "clientsservices") {
        global $whmcs;
        createConfigOptionsDiscountTable(); // Ensure the table exists and is updated
        $return = "";
        $userid = $whmcs->get_req_var('userid');
        $service_id = null;

        if (empty($whmcs->get_req_var('id'))) {
            $service_id = $whmcs->get_req_var('productselect');
        } else {
            $service_id = $whmcs->get_req_var('id');
        }

        if (empty($whmcs->get_req_var('id')) && empty($whmcs->get_req_var('productselect'))) {
            $service = Capsule::table('tblhosting')->select('id')->where('userid', $userid)->first();
            $service_id = $service->id;
        }

        $service = Capsule::table('tblhosting')->find($service_id);

        if (!$service) {
            return $return;
        }

        // Fetch the configurable options for the product
        $configOptions = Capsule::table('tblhostingconfigoptions')
            ->join('tblproductconfigoptions', 'tblhostingconfigoptions.configid', '=', 'tblproductconfigoptions.id')
            ->where('tblhostingconfigoptions.relid', $service_id)
            ->select('tblhostingconfigoptions.id as optionid', 'tblhostingconfigoptions.qty', 'tblproductconfigoptions.optionname as configname', 'tblproductconfigoptions.id as configid')
            ->get();

        // Prepare jQuery script to add recurring discount fields
        $script = '<script type="text/javascript">
            jQuery(document).ready(function() {';

        foreach ($configOptions as $configOption) {
            $configName = addslashes($configOption->configname);
            $oldConfigDiscount = Capsule::table('mod_rd_discountConfigOptions')->where('serviceid', $service_id)->where('configoptionid', $configOption->configid)->first();
            $oldConfigDiscountValue = $oldConfigDiscount ? $oldConfigDiscount->discount_price : '';
            $discountBadge = $oldConfigDiscount ? '<label class="label label-success" style="margin: 2px 0 0 0;"><span>Discount Price: $' . $oldConfigDiscount->discount_price . ' each</label>' : '';
            $removeButton = $oldConfigDiscount ? '<button type="button" class="button btn btn-sm btn-danger remove-config-discount" data-configoptionid="' . $configOption->configid . '" style="margin-left: 5px;">Remove</button>' : '';

            $script .= '
            $("td.fieldarea:has(input[name=\'configoption[' . $configOption->configid . ']\'])").append(`
                <div style="margin-top: 5px;">
                    <p style="margin: 10px 0 5px 0">Discount Price</p>
                    <div style="display: flex; align-items: center; position: relative;">
                        <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">$</span>
                        <input 
                            type="text" 
                            name="config_discount_' . $configOption->configid . '" 
                            size="20" 
                            class="form-control input-80" 
                            value="' . $oldConfigDiscountValue . '" 
                            style="padding-left: 20px; margin-right: 10px;"
                        >
                        ' . $removeButton . '
                    </div>
                    ' . $discountBadge . '
                </div>
            `);';
    }
        $script .= '
            });

            // Utility: collect current config option selections from the form
            function ebCollectOptions() {
                var options = [];
                var selectedCfg = {};
                // Selects
                $(\'select[name^="configoption["]\').each(function(){
                    var name = this.name || "";
                    var m = name.match(/^configoption\[(\d+)\]$/);
                    if (!m) return;
                    var cfg = parseInt(m[1], 10);
                    var val = $(this).val();
                    var subId = parseInt(val, 10);
                    if (!isNaN(subId)) { options.push({ configid: cfg, subId: subId }); selectedCfg[cfg] = true; }
                });
                // Radios (checked)
                $(\'input[type="radio"][name^="configoption["]:checked\').each(function(){
                    var name = this.name || "";
                    var m = name.match(/^configoption\[(\d+)\]$/);
                    if (!m) return;
                    var cfg = parseInt(m[1], 10);
                    var val = $(this).val();
                    var subId = parseInt(val, 10);
                    if (!isNaN(subId)) { options.push({ configid: cfg, subId: subId }); selectedCfg[cfg] = true; }
                });
                // Numeric qty (quantity options)
                $(\'input[type="number"][name^="configoption["], input[type="text"][name^="configoption["]\').each(function(){
                    var name = this.name || "";
                    var m = name.match(/^configoption\[(\d+)\]$/);
                    if (!m) return;
                    var cfg = parseInt(m[1], 10);
                    if (selectedCfg[cfg]) return;
                    var v = ($(this).val() || "").trim();
                    if (v === "") return;
                    var qty = parseInt(v, 10);
                    if (!isNaN(qty)) { options.push({ configid: cfg, qty: qty }); }
                });
                return options;
            }

            // Utility: collect all discount inputs from the page
            function ebCollectDiscounts() {
                var discounts = [];
                $(\'input[name^="config_discount_"]\').each(function () {
                    var m = this.name.match(/^config_discount_(\d+)$/);
                    if (!m) return;
                    var configId = parseInt(m[1], 10);
                    var val = ($(this).val() || "").trim();
                    if (val !== "" && !isNaN(val) && Number(val) >= 0) {
                        discounts.push({ configoptionid: configId, discount_price: val });
                    }
                });
                return discounts;
            }

            // Remove discount
            jQuery(document).on("click", ".remove-config-discount", function () {
                var configoptionid = $(this).data("configoptionid");
                $(this).attr("disabled", true);
                $(this).html(\'Loading... <i class="fas fa-fw fa-sync fa-spin"></i>\');
                let search = new URLSearchParams(window.location.search);
                $.ajax({
                    type: "POST",
                    url: "/includes/hooks/configOptionsDiscount_ajax.php",
                    dataType: "json",
                    data: {"ajax_action": "remove_config_discount", "configoptionid": configoptionid, "service_id": ' . $service_id . '},
                    success: function (response) {
                        try { if (typeof response === "string") { response = JSON.parse(response); } } catch (e) { response = { status:false, message: "Invalid JSON" }; }
                        if (response && response.status){
                            location.reload();
                        }else{
                            alert("Failed to remove discount: " + (response && response.message ? response.message : "Unknown error"));
                            $(".remove-config-discount").attr("disabled", false).html("Remove");
                        }
                    },
                    error: function () {
                        alert("Network error");
                        $(".remove-config-discount").attr("disabled", false).html("Remove");
                    }
                });
            });

            // Inject our actions row adjacent to the Recurring Amount row (robust selector)
            (function injectActionsRow() {
                if (document.getElementById("eb-discount-actions-row")) return;
                var $anchor = $(\'#inputAmount\').closest(\'tr\');
                if (!$anchor.length) { setTimeout(injectActionsRow, 250); return; }
                var rowHtml = \'<tr id="eb-discount-actions-row" style="display: table-row;"><td class="fieldlabel" width="20%"></td><td class="fieldarea" width="30%"></td><td class="fieldlabel" width="20%">Config Options Discounts</td><td class="fieldarea" width="30%"><div class="service-field-inline"><button type="button" class="button btn btn-sm" id="eb-recalc-now">Recalculate (discounts)</button> <button type="button" class="button btn btn-sm btn-primary" id="eb-save-with-discounts">Save with discounts</button></div></td></tr>\';
                $anchor.after(rowHtml);
            })();

            // Intercept Save when "Recalculate on Save" is checked to apply our discount-aware total
            jQuery(document).on("submit", "#frm1", function (e) {
                var $form = $(this);
                // If we already recalculated once for this submit, do not intercept again
                if ($form.data(\'ebRecalcDone\') === 1) {
                    return;
                }
                // Robustly detect any autorecalc controls (name or id match, including bootstrap switch backing input), and only enabled elements
                var $autoCandidates = $form.find("input,select,textarea").filter(function(){
                    var n = (this.name || "") + " " + (this.id || "");
                    return /autorecalc/i.test(n) && this.disabled !== true;
                });
                var needRecalc = false;
                $autoCandidates.each(function(){
                    var $el = $(this);
                    if (($el.prop("type") === "checkbox" && $el.prop("checked")) || ($el.val() === "on" || $el.val() === "1")) {
                        needRecalc = true;
                    }
                });
                if (needRecalc) {
                    e.preventDefault();
                    $.ajax({
                        type: "POST",
                        url: "/includes/hooks/configOptionsDiscount_ajax.php",
                        dataType: "json",
                        data: { ajax_action: "recalc_service_amount", service_id: ' . $service_id . ' },
                        success: function (res) {
                            var response;
                            try { response = (typeof res === \'string\') ? JSON.parse(res) : res; } catch (e) { response = { status:false, message: \'Invalid JSON\' }; }
                            if (response.status && response.data && response.data.amount) {
                                $form.find(\'input[name="amount"]\').val(response.data.amount);
                                // Prevent WHMCS from doing its own recalc (we already set amount):
                                // uncheck/disable/rename all autorecalc-like inputs
                                $autoCandidates.each(function(){
                                    var $el = $(this);
                                    if ($el.prop("type") === "checkbox") { $el.prop("checked", false); }
                                    $el.prop("disabled", true);
                                    var oldName = $el.attr("name");
                                    if (oldName) { $el.attr("name", oldName + "_disabled"); }
                                });
                                // ensure a false value is submitted to the server
                                if (!$form.find(\'input[name="autorecalc"]\').length) {
                                    $form.append(\'<input type="hidden" name="autorecalc" value="0">\');
                                } else {
                                    $form.find(\'input[name="autorecalc"]\').val("0");
                                }
                                // Mark this submit as finalized to avoid re-interception loop
                                $form.data(\'ebRecalcDone\', 1);
                                $form.trigger("submit");
                            } else {
                                alert(response.message || "Recalculation failed");
                            }
                        },
                        error: function () {
                            alert("Network error during recalculation");
                        }
                    });
                }
            });

            // Intercept "Auto Recalculate" button clicks (non-submit recalcs)
            jQuery(document).on("click", "#eb-recalc-now", function (e) {
                e.preventDefault();
                var svcId = ' . $service_id . ';
                var discounts = ebCollectDiscounts();

                // 1) Save all discounts first
                $.ajax({
                    type: "POST",
                    url: "/includes/hooks/configOptionsDiscount_ajax.php",
                    dataType: "json",
                    data: {
                        ajax_action: "save_discounts",
                        service_id: svcId,
                        discounts: discounts
                    },
                    success: function (saveRes) {
                        try { if (typeof saveRes === "string") saveRes = JSON.parse(saveRes); } catch (e) { saveRes = { status:false, message:"Invalid JSON" }; }
                        if (!saveRes || !saveRes.status) {
                            alert((saveRes && saveRes.message) ? saveRes.message : "Failed to save discounts");
                            return;
                        }

                        // 2) Recalculate using live selections / qtys
                        $.ajax({
                            type: "POST",
                            url: "/includes/hooks/configOptionsDiscount_ajax.php",
                            dataType: "json",
                            data: {
                                ajax_action: "calculate_amount",
                                service_id: svcId,
                                options: JSON.stringify(ebCollectOptions())
                            },
                            success: function (response) {
                                try { if (typeof response === "string") { response = JSON.parse(response); } } catch(e) { response = { status:false, message: "Invalid JSON" }; }
                                if (response && response.status && response.data && response.data.amount) {
                                    $(\'#servicecontent input[name="amount"]\').val(response.data.amount);
                                    alert("Amount recalculated: " + response.data.amount);
                                } else {
                                    alert((response && response.message) ? response.message : "Recalculation failed");
                                }
                            },
                            error: function () {
                                alert("Network error during recalculation");
                            }
                        });
                    },
                    error: function () {
                        alert("Network error while saving discounts");
                    }
                });
            });

            // Save with discounts: save discounts, calculate using live options, then commit
            jQuery(document).on("click", "#eb-save-with-discounts", function (e) {
                e.preventDefault();
                var svcId = ' . $service_id . ';
                var discounts = ebCollectDiscounts();

                // 1) Save all discounts in one go
                $.ajax({
                    type: "POST",
                    url: "/includes/hooks/configOptionsDiscount_ajax.php",
                    dataType: "json",
                    data: {
                        ajax_action: "save_discounts",
                        service_id: svcId,
                        discounts: discounts
                    },
                    success: function (saveRes) {
                        try { if (typeof saveRes === "string") saveRes = JSON.parse(saveRes); } catch (e) { saveRes = { status:false, message:"Invalid JSON" }; }
                        if (!saveRes || !saveRes.status) {
                            alert((saveRes && saveRes.message) ? saveRes.message : "Failed to save discounts");
                            return;
                        }

                        // 2) Recalculate using live form selections/qtys (no reliance on DB snapshot)
                        $.ajax({
                            type: "POST",
                            url: "/includes/hooks/configOptionsDiscount_ajax.php",
                            dataType: "json",
                            data: {
                                ajax_action: "calculate_amount",
                                service_id: svcId,
                                options: JSON.stringify(ebCollectOptions())
                            },
                            success: function (calc) {
                                try { if (typeof calc === "string") calc = JSON.parse(calc); } catch(e){ calc = { status:false, message:"Invalid JSON" }; }
                                if (!(calc && calc.status && calc.data && calc.data.amount)) {
                                    alert((calc && calc.message) ? calc.message : "Recalculation failed");
                                    return;
                                }
                                var amt = calc.data.amount;
                                $(\'#inputAmount, input[name="amount"]\').val(amt);

                                // 3) Commit the new amount
                                $.ajax({
                                    type: "POST",
                                    url: "/includes/hooks/configOptionsDiscount_ajax.php",
                                    dataType: "json",
                                    data: { ajax_action: "commit_amount", service_id: svcId, amount: amt },
                                    success: function (res) {
                                        try { if (typeof res === "string") res = JSON.parse(res); } catch(e){ res = { status:false, message:"Invalid JSON" }; }
                                        if (res && res.status) {
                                            var base = window.location.pathname + window.location.search.replace(/&?success=[^&]*/,"").replace(/&$/,"");
                                            window.location = base + (base.indexOf("?")>-1 ? "&" : "?") + "success=true";
                                        } else {
                                            alert((res && res.message) ? res.message : "Commit failed");
                                        }
                                    },
                                    error: function () { alert("Network error during commit"); }
                                });
                            },
                            error: function(){ alert("Network error during calculation"); }
                        });
                    },
                    error: function () {
                        alert("Network error while saving discounts");
                    }
                });
            });
        </script>';

        $return .= $script;

        return $return;
    }
});

