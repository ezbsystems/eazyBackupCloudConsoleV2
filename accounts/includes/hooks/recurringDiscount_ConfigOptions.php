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
                        <button type="button" class="button btn btn-sm btn-info apply-config-discount" data-configoptionid="' . $configOption->configid . '">Apply</button>
                        ' . $removeButton . '
                    </div>
                    ' . $discountBadge . '
                </div>
            `);';
    }
        $script .= '
            });

            // Apply discount
            jQuery(document).on("click", ".apply-config-discount", function () {
                var configoptionid = $(this).data("configoptionid");
                var discount_price = $("input[name=config_discount_" + configoptionid + "]").val();
                $(this).attr("disabled", true);
                $(this).html("Loading... <i class=\"fas fa-fw fa-sync fa-spin\"></i>");
                let search = new URLSearchParams(window.location.search);
                $.ajax({
                    type: "POST",
                    url: "/includes/hooks/configOptionsDiscount_ajax.php",
                    data: {"ajax_action": "apply_config_discount", "discount_price": discount_price, "configoptionid": configoptionid, "service_id": ' . $service_id . '},
                    success: function (result) {
                        let response = JSON.parse(result);
                        console.log(response);
                        if(response.status){
                            location.reload();
                        }else{
                            alert("Failed to apply discount: " + response.message);
                            $(".apply-config-discount").attr("disabled", false).html("Apply");
                        }
                    }
                });
            });

            // Remove discount
            jQuery(document).on("click", ".remove-config-discount", function () {
                var configoptionid = $(this).data("configoptionid");
                $(this).attr("disabled", true);
                $(this).html("Loading... <i class=\"fas fa-fw fa-sync fa-spin\"></i>");
                let search = new URLSearchParams(window.location.search);
                $.ajax({
                    type: "POST",
                    url: "/includes/hooks/configOptionsDiscount_ajax.php",
                    data: {"ajax_action": "remove_config_discount", "configoptionid": configoptionid, "service_id": ' . $service_id . '},
                    success: function (result) {
                        let response = JSON.parse(result);
                        console.log(response);
                        if(response.status){
                            location.reload();
                        }else{
                            alert("Failed to remove discount: " + response.message);
                            $(".remove-config-discount").attr("disabled", false).html("Remove");
                        }
                    }
                });
            });
        </script>';

        $return .= $script;

        return $return;
    }
});

