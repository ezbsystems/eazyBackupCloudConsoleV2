<?php

use WHMCS\Database\Capsule;

add_hook('AdminAreaHeadOutput', 112222, function ($vars) {

    if ($vars["filename"] == "clientsservices") {
        global $whmcs;
        rd_createTab(); #create_table
        $return = "";
        $old_dis = '';


        #get_service_id
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

        if ($service->billingcycle == 'Free Account' || $service->billingcycle == 'One Time') {
            return $return;
        }

        #get_old_discount_per
        $whereClause = [
            ['serviceid', '=', $service_id],
            ['nextduedate', '=', $service->nextduedate]
        ];

        if (Capsule::Schema()->hasTable('mod_rd_discountServices')) {
            $oldData = Capsule::table('mod_rd_discountServices')->where($whereClause)->first();
            if (!empty($oldData->discount_per)) {
                $old_dis = $oldData->discount_per;
                $str = "$old_dis % discount applied";
            }
        }


        if (!empty($old_dis)) {
            $return = '<script type="text/javascript">
                jQuery(document).ready(function() {
                    $("#servicecontent table.form tr").find("input[name=amount]").parents(".service-field-inline").parents("div").first().after("<div class=\"service-field-inline\"><label class=\"label label-success\"><span>' . $str . '</span></label></div>");
                });
                </script>';

            $return .= '<script type="text/javascript">
                jQuery(document).ready(function() {
                    $("#servicecontent table.form tr").filter(":nth-child(4)").after("<tr><td class=\"fieldlabel\" width=\"20%\"></td><td class=\"fieldarea\" width=\"30%\"></td><td class=\"fieldlabel\" width=\"20%\">Recurring Discount (%)</td><td class=\"fieldarea\" width=\"30%\"><div style=\"width: 100%\"><div class=\"service-field-inline\"><input type=\"number\" name=\"discount_per\" size=\"20\" class=\"form-control input-100\" id=\"discount_per\" value=\"' . $old_dis . '\"></div><div class=\"service-field-inline\"><button type=\"button\" class=\"button btn btn-sm btn-info\" id=\"apply_discount_btn\">Update</button></div><div class=\"service-field-inline\"><button type=\"button\" class=\"button btn btn-sm btn-danger\" id=\"remove_discount_btn\">Remove</button></div></div></td></tr>");
                });
            </script>';
        } else {
            $return .= '<script type="text/javascript">
            jQuery(document).ready(function() {
                $("#servicecontent table.form tr").filter(":nth-child(4)").after("<tr><td class=\"fieldlabel\" width=\"20%\"></td><td class=\"fieldarea\" width=\"30%\"></td><td class=\"fieldlabel\" width=\"20%\">Recurring Discount (%)</td><td class=\"fieldarea\" width=\"30%\"><div style=\"width: 100%\"><div class=\"service-field-inline\"><input type=\"number\" name=\"discount_per\" size=\"20\" class=\"form-control input-100\" id=\"discount_per\" value=\"' . $old_dis . '\"></div><div class=\"service-field-inline\"><button type=\"button\" class=\"button btn btn-sm btn-info\" id=\"apply_discount_btn\">Apply</button></div></div></td></tr>");
            });
        </script>';
        }
        $return .= '<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <script type="text/javascript">
        jQuery(document).on("click", "#apply_discount_btn", function () {
            $("#apply_discount_btn").attr("disabled", true);
            $("#apply_discount_btn").html("Loading... <i class=\"fas fa-fw fa-sync fa-spin\"></i>");
            var discount_per = $("#discount_per").val();
            let search = new URLSearchParams(window.location.search);
            $.ajax({
                type: "POST",
                url: "/includes/hooks/ajax.php",
                data: {"ajax_action": "apply_discount","discount_per": discount_per,"service_id":' . $service_id . '},
                success: function (result) {
                    let response = JSON.parse(result);
                    console.log(response);
                    if(response.status){
                        if(search.has("success")){
                            window.location = window.location.href;
                        }else{
                            window.location = window.location.href+"&success=true";
                        }
                    }else{
                        $("#apply_discount_btn").attr("disabled", false);
                        $("#apply_discount_btn").html("Apply");
                        $("#frm1").before(`<div class=\"infobox custom_error_div\"><strong><span class=\"title\">Changes Fail</span></strong><br>`+response.message+`</div>`);
                        setTimeout(function() {
                            $(".custom_error_div").hide();
                        }, 3500);
                    }
                }
            });
        });
        jQuery(document).on("click", "#remove_discount_btn", function () {
            swal({
                title: "Are you sure?",
                text: "To remove recurring discount for this service",
                icon: "warning",
                buttons: true,
                dangerMode: true,
            })
            .then((willDelete) => {
                if (willDelete) {
                    $("#remove_discount_btn").attr("disabled", true);
                    $("#remove_discount_btn").html("Loading... <i class=\"fas fa-fw fa-sync fa-spin\"></i>");
                    var nextduedate = $("#inputNextduedate").val();
                    let search = new URLSearchParams(window.location.search);
                    $.ajax({
                        type: "POST",
                        url: "/includes/hooks/ajax.php",
                        data: { "ajax_action": "remove_discount", "nextduedate": nextduedate, "service_id": ' . $service_id . ' },
                        success: function (result) {
                            let response = JSON.parse(result);
                            console.log(response);
                            if (response.status) {
                                if (search.has("success")) {
                                    window.location = window.location.href;
                                } else {
                                    window.location = window.location.href + "&success=true";
                                }
                            } else {
                                $("#remove_discount_btn").attr("disabled", false);
                                $("#remove_discount_btn").html("Remove");
                                $("#frm1").before(`<div class=\"infobox custom_error_div\"><strong><span class=\"title\">Changes Fail</span></strong><br>` + response.message + `</div>`);
                                setTimeout(function() {
                                    $(".custom_error_div").hide();
                                }, 3500);
                            }
                        }
                    });
                }
            });
        });
        </script>';


        return $return;
    }
});




function rd_createTab()
{
    /*
        RemoveThisCodeForFreshInstall
        delete_old_table
    */
    Capsule::schema()->dropIfExists('mod_rd_discountService');

    if (!Capsule::Schema()->hasTable('mod_rd_discountServices')) {
        Capsule::schema()->create('mod_rd_discountServices', function ($table) {
            $table->increments('id');
            $table->integer('userid');
            $table->integer('serviceid');
            $table->float('beforeDisAmt');
            $table->float('discount_per');
            $table->date('nextduedate');
            $table->timestamp('created_at')->default(Capsule::raw("CURRENT_TIMESTAMP"));
            $table->timestamp('updated_at')->default(Capsule::raw("CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"));
        });
    }
    return true;
}
