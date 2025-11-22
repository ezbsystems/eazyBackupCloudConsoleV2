{* {include file="$template/includes/tablelist.tpl" tableName="ServicesList" filterColumn="4" noSortColumns="0"} *}
<script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>
<script type="text/javascript">
    jQuery(document).ready( function ()
    {
        $('.myloader').css("display", "none");
        //toggle particular service data using ajax request
        $(".service_list").click(function(e){
            e.preventDefault();


            if ($(this).find("i.fa.fa-caret-right").hasClass("active")) {
                $(".service_list i.fa.fa-caret-right").removeClass("active");
                $("#service-data").slideUp('slow');
                return false;
            }
            $('.myloader').css("display", "block");
            $(".table-container").addClass("loading");



            $("#service-data").slideUp('slow');
            $(".service_list i.fa.fa-caret-right").removeClass("active");
            $(this).find("i.fa.fa-caret-right").addClass("active");

            var id = $(this).closest('tr').attr('data-serviceid');


            $.ajax({
                method:'POST',
                data:{
                        'id': id
                    },
                url:'modules/servers/comet/ajax/ajax.php',
                success:function(data) {
                    $("#service-data").html(parsedData.html);
                    $(".table-container").removeClass("loading");
                    $('.myloader').css("display", "none");
                    $("#service-data").slideDown('slow');
                    $('.emailbackup-ajax').bootstrapSwitch('state');
                    $('#serviceid-' + id + ' .devicecounting').text(parsedData.devicecounting || 'No device');

                },
                error: function(data){
                   // alert(data);
                    $("#service-data").html(data);
                }
            });

        });

        // Passed value in rename device form
        $(document).on("click", ".rename_device", function(e) {
            e.preventDefault();
            var deviceId = $(this).data("deviceid");
            var serviceid = $(this).data("serviceid");
            var devicename = $(this).data("devicename");
            $('#devicename').val(devicename);
            $('#deviceId').val(deviceId);
            $('#serviceId').val(serviceid);
        });

        //Ajax request on rename device form
        $(document).on("click", "#devicerenamesubmit", function(e) {

            e.preventDefault();
            var serviceId =  $('#serviceId').val();
            var deviceId =  $('#deviceId').val();
            var devicename =  $('#devicename').val();
            $('.myloader').css("display", "block");
            $(".table-container").addClass("loading");

           if(devicename)
           {
               $.ajax
               ({
                    method:'POST',
                    data:{
                            'serviceId': serviceId ,
                            'deviceId': deviceId ,
                            'devicename': devicename ,
                        },
                    url:'modules/servers/comet/ajax/device_rename.php',
                    success:function(data) {
                        //console.log(data);
                        console.log(serviceId);
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");

                        $("#rename_device .close").trigger('click'); //closed popup modal here
                        //clicked two time bcz of toggle
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');

                         var successMessage = `<div class="successMessage alert alert-success text-center" role="alert">
                                                You changes have been saved successfully
                                                </div>`;
                        $("#successMessage").append(successMessage);
                        setTimeout(function() {
                            $('.successMessage').fadeOut('fast');
                        }, 3000);
                    },
                    error: function(data){
                    // alert(data);
                        $("#service-data").html(data);
                    }
                });
            }

        });

        // Passed value in Update emailform
        $(document).on("click", ".update-email", function(e) {
            e.preventDefault();
            var emailIdtoUpdate = $(this).data("emailid");
            var emailValuetoUpdate = $(this).data("email");
            $('#update-email-id').val(emailIdtoUpdate);
            $('#upudate-email-address').val(emailValuetoUpdate);
        });

        // Passed value after save button in updtae email ID
        $(document).on("click", "#updateemaildata", function(e) {
            e.preventDefault();
            $('#invalid_email_update').hide();
            var newEmail = $("#email-address").val();


            var emailkey = $('#update-email-id').val();
            var emailValue = $('#upudate-email-address').val();

            if(IsEmail(emailValue)==false){

                $('#invalid_email_update').show();
                return false;
            }

            $('#email-'+ emailkey).val(emailValue);
            $('.email-'+ emailkey).text(emailValue);
            $("#update-email .close").click();

            return false;

        });



        //Remove email listing for selected email
        $(document).on("click", ".remove-email", function(e) {
            var delete_confirmation =  confirm('are you sure you want to delete this?');
            if(delete_confirmation == true)
            {
                $(this).closest(".email-list").remove();
            } else {

                return false;
            }

        });

        //Add email data with popup
        $(document).on("click", "#addemaildata", function(e) {
            $('#invalid_email').hide();
            var newEmail = $("#email-address").val();

            if(IsEmail(newEmail)==false){
                $('#invalid_email').show();
                return false;
            }

            $(".totalemails").append(`<div class="row dataservice email-list">
                                    <div class="col-md-6">
                                        <input type="hidden" class="form-control form-control-sm" name="email[]" value="`+ newEmail +`"> `+ newEmail +`
                                    </div>
                                    <div class="col-md-6">
                                        <button class="btn btn-danger remove-email" title="Remove"><i class="fa fa-minus"></i></button>
                                        <button class="btn btn-primary" title="Edit"><i class="fa fa-edit"></i></button>

                                    </div>
                                </div>`);
                                $("#add-email .close").click();
        });



        //Ajax request for save profile
        $(document).on("click", "#saveprofile", function(e) {
            var serviceId = $("input[name='serviceId']").val();
            var accountName = $("input[name='accountname']").val();
            var MaximumDevices = $("input[name='MaximumDevices']").val();
            var emailreporting = $("input[name='emailreporting']").val();
            var emails = [];
            $("input[name='email[]']").each(function(index , value) {
                emails.push($(this).val());
            });
            console.log(serviceId);
            console.log(accountName);
            console.log(MaximumDevices);
            console.log(emailreporting);
            console.log(emails);
           // return false;
            $('.myloader').css("display", "block");
            $(".table-container").addClass("loading");


            $.ajax({
                url:"modules/servers/comet/ajax/saveprofile.php",
                method:'POST',
               // data:$('#saveprofileform').serialize(),
                data:{
                        'serviceId': serviceId ,
                        'accountname': accountName ,
                        'MaximumDevices': MaximumDevices ,
                        'email': emails ,
                        'emailreporting' : emailreporting

                    },
                success:function(data) {
                    var jsondata = JSON.parse(data);

                    console.log(jsondata['Status']);
                    console.log(jsondata);

                    if(jsondata['Status'] == 200 && jsondata['Message'] == 'OK')
                    {
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
                        //clicked two time bcz of toggle
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        //location.reload();
                        var successMessage = `<div class="successMessage alert alert-success text-center" role="alert">
                                                You changes have been saved successfully
                                                </div>`;
                        $("#successMessage").append(successMessage);
                        setTimeout(function() {
                          $('.successMessage').fadeOut('fast');
                       }, 3000);

                    }

                },
                error: function(data){
                   alert(data);

                }
            });
        });

        $(document).on("click", ".revoke_device", function() {
            var revoke_deviceId = $(this).attr("data-revokedeviceId");
            $(this).siblings('form').submit();
        });

        $(document).on("click", "#unlimited_device", function() {

           var unlimited_device =  $('#unlimited_device').is(':checked');
          // alert(unlimited_device);
           if(unlimited_device == true){
                $("#MaximumDevices").val('')
                 $("#MaximumDevices").prop('disabled', true);
           }
           if(unlimited_device == false){

                $("#MaximumDevices").removeAttr('disabled');

           }

        });

       // $('.comet-on-off').on("click", function(event) { onSwitchChange(); } );

        var table = jQuery('#tableServicesList').removeClass('hidden').DataTable({
                    "bInfo": false, //Dont display info e.g. "Showing 1 to 4 of 4 entries"
                    "paging": false,//Dont want paging
                    "bPaginate": false,//Dont want paging
                    "ordering": false,
                    });

        table.draw();
        jQuery('#tableLoading').addClass('hidden');

    });

</script>

{literal}
<script type="text/javascript">

 function IsEmail(email) {
        var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        if(!regex.test(email)) {
            return false;
        }else{
            return true;
        }
    }

</script>
{/literal}

<style type="text/css">
    #tableServicesList_filter {
        float: left !important;
        width: 100%;
    }
    label{
        width: 100%;
    }
    .dataTables_wrapper .dataTables_filter label .form-control{
        width: 40%;
    }
    #tableServiceDetailList_info {
        display: none;
    }
   .borderless table {
    border-top-style: none;
    border-left-style: none;
    border-right-style: none;
    border-bottom-style: none;
    }
    .dataservice{
        padding : 10px 0px 10px 0px;
    }

    /* Rounded sliders */
    .slider.round {
    border-radius: 34px;
    }

    .slider.round:before {
    border-radius: 50%;
    }
    .icon_size {
    font-size: 12px;
    padding-top: 3px;
    padding-right: 5px;
    }
    .save_changes_btn {
    background-color: green;
    color: white;
    }

    /* 7-Dec-2021 */
    .table.borderless>thead>tr>th, .table.borderless>tbody>tr>td {
        border: 0 none !important;
        background: transparent !important;
    }
    .input-group-btns {
        display: inline-block;
        background-color: #e2e2e3;
        border-radius: 4px;
        overflow: hidden;
        border: 1px solid #bcbcbc;
        margin-top: 10px;
    }
    .checkbox-btn {
        float: left;
        border-right: 1px solid #bcbcbc;
    }
    .checkbox-btn label {
        padding: 8px 10px;
        margin: 0;
    }
    .checkbox-btn input[type=checkbox] {
        display: none;
    }
    .checkbox-btn input[type=checkbox] + label:after {
        content: "\2714";
        border: 0.1em solid #767676;
        border-radius: 0.2em;
        display: inline-block;
        width: 18px;
        height: 18px;
        vertical-align: middle;
        color: transparent;
        transition: .2s;
        font-size: 12px;
        text-align: center;
        float: right;
        margin-left: 8px;
        background-color: #fff;
    }
    .checkbox-btn input[type=checkbox]:checked + label:after {
        background-color: MediumSeaGreen;
        border-color: MediumSeaGreen;
        color: #fff;
    }
    .input-group-btns .select-box {
        background-color: transparent;
        padding: 8px 11px;
        float: left;
        border: 0 none;
        max-width: 80px;
        outline: none;
    }
    .input-group-btns .input-group-text {
        float: left;
        text-align: center;
        border-left: 1px solid #bcbcbc;
    }
    .input-group-btns .custom-control-input {
        background-color: transparent !important;
        border: 0 none;
        padding: 8px 11px;
    }
    i.fa.fa-caret-right.active{
        transform: rotate(90deg);
    }
    .loading .myloader {
    position: absolute;
    display: block;
    padding: 20px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    }
    .loading #tableServicesList_wrapper{
        opacity: 0.50;
    }

    .alert.alert-success.text-center {
    background-color: #155724 !important;
}
</style>
{* <pre>
{$services|@print_r}
</pre> *}

<div class="table-container clearfix ">
<div id="successMessage" style="background-color: #155724; !important">

</div>

    <table id="tableServicesList" class="table table-list hidden">
        <thead>
            <tr>
                <th>Account Username</th>
                <th>Plan</th>
                <th>Devices</th>
                <th>Total Storage</th>
                <th>Microsoft 365 Users</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {foreach key=num item=service from=$services}
                {* <tr onclick="clickableSafeRedirect(event, 'clientarea.php?action=productdetails&amp;id={$service.id}', false)"> *}
                <tr class="test" id="serviceid-{$service.id}" data-serviceid="{$service.id}">
                    <td class="service_username {if $service.username }service_list{/if}  dropdown_icon serviceid-{$service.id}" data-id="{$service.id}"> <a href="javascript:void(0)" style="padding-left:20px;"><i class="fa fa-caret-right"></i> </a>{$service.username}</td>
                    <td class="text-center {if $service.username }service_list{/if}">{$service.product}</td>
                    <td class="text-center {if $service.username }service_list{/if}">{if $service.devicecounting }{$service.devicecounting}{else}No device{/if}</td>
                    <td class="text-center {if $service.username }service_list{/if}">{$service.TotalStorage}</td>
                    <td class="text-center {if $service.username }service_list{/if}">{$service.MicrosoftAccountCount}</td>
                    <td class="text-center {if $service.username }service_list{/if}"><span class="status-{$service.status|strtolower}">{$service.statustext}</span></td>
                    <td class="text-center {if $service.username }service_list{/if}" data-order="{$service.amountnum}">{$service.amount}<br />{$service.billingcycle}{$hasdevices}</td>
                    <td class="text-center">
                        {* <a href="clientarea.php?action=productdetails&amp;id={$service.id}" class="btn btn-block btn-info">
                            {$LANG.manageproduct}
                        </a> *}
                        <div class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></a>
                            <ul class="dropdown-menu">
                                <li><a href="" data-toggle="modal" data-target="#reset-password"><span class="icon_size"><i class="fas fa-sync"></i></span>Reset Password</a></li>
                                {if $service.IsSuspended }
                                    <li> <span class="icon_size"><i class="far fa-stop-circle"></i></span> <span class="label label-warning">Suspended</span></li>
                                {else}
                                    <li> <span class="icon_size"><i class="far fa-stop-circle"></i></span><span class="label label-success" style="background-color:#5cb85c ;">Active</span></li>
                                {/if}
                                {* <li><a href="/upgrade.php?type=package&id={$service.id}"> <span class="change_plan" style="padding-left:13px;">Change plan</span></a></li> *}
                                <li><a href="/upgrade.php?type=package&id={$service.id}"> <span class="icon_size"><i class="fa fa-exchange" aria-hidden="true"></i></span> Change Plan</a></li>
                                <li style="line-height:3;" ><a href="/clientarea.php?action=cancel&id={$service.id}"> <span class="icon_size"> <i class="fa fa-trash text-danger" aria-hidden="true"></i> </span><span class="text-danger">Delete Account </span></a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
    <div class="text-center" id="tableLoading">
        <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p>
    </div>
    <div class="extra_details">
        <div class="myloader" > <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p></div>
    </div>
    <div id="service-data" style="display:none">
    </div>
</div>



<!-- Modal Reset Password -->
<div id="reset-password" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content panel panel-primary">
        <div class="modal-header panel-heading">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Reset Password</h4>
        </div>
        <div class="modal-body panel-body">
            <div class="container">


                <form class="form-horizontal using-password-strength" method="post" action="{$smarty.server.PHP_SELF}?action=productdetails#tabChangepw" role="form">
                    {* <input type="hidden" name="id" value="{$id}" /> *}
                    <input type="hidden" name="id" value="1" />
                    <input type="hidden" name="AuthType" value="Password" />
                    <input type="hidden" name="modulechangepassword" value="true" />

                    <div id="newPassword1" class="form-group has-feedback">
                        <label for="inputNewPassword1" class="col-sm-4 control-label">{$LANG.newpassword}</label>
                        <div class="col-sm-5">
                            <input type="password" class="form-control" id="inputNewPassword1" name="newpw" autocomplete="off" />
                            <span class="form-control-feedback glyphicon"></span>
                            {include file="$template/includes/pwstrength.tpl"}
                        </div>
                        <div class="col-sm-3">
                            <button type="button" class="btn btn-default generate-password" data-targetfields="inputNewPassword1,inputNewPassword2">
                                {$LANG.generatePassword.btnLabel}
                            </button>
                        </div>
                    </div>
                    <div id="newPassword2" class="form-group has-feedback">
                        <label for="inputNewPassword2" class="col-sm-4 control-label">{$LANG.confirmnewpassword}</label>
                        <div class="col-sm-5">
                            <input type="password" class="form-control" id="inputNewPassword2" name="confirmpw" autocomplete="off" />
                            <span class="form-control-feedback glyphicon"></span>
                            <div id="inputNewPassword2Msg">
                            </div>
                        </div>
                    </div>
                    {* <div id="oldPassword" class="form-group has-feedback">
                        <label for="inputoldPassword" class="col-sm-4 control-label">Old Password</label>
                        <div class="col-sm-5">
                            <input type="password" class="form-control" id="inputoldPassword" name="oldPassword" autocomplete="off" />
                            <span class="form-control-feedback glyphicon"></span>
                            <div id="inputoldPasswordMsg">
                            </div>
                        </div>
                    </div> *}
                    <div class="form-group">
                        <div class="col-sm-offset-6 col-sm-6">
                            <input class="btn btn-primary" type="submit" value="{$LANG.clientareasavechanges}" />
                            <input class="btn" type="reset" value="{$LANG.cancel}" />
                        </div>
                    </div>

                </form>
            </div>
        </div>
        <div class="modal-footer panel-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>
    </div>
  </div>
</div>



<!-- Modal Rename Device-->
<div id="rename_device" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content panel panel-primary">
        <div class="modal-header panel-heading">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Rename Device</h4>
        </div>
        <div class="modal-body panel-body">
            <div class="container">


                <form class="" method="post" action="#">
                    <input type="hidden" id="serviceId" name="serviceId "  />
                    <input type="hidden" id="deviceId" name="deviceId "  />
                    <label for="inputPassword5" class="form-label">Enter a new name for the selected device:</label>
                    <input type="text" id="devicename" name="devicename" class="form-control" >
                    {* <div class="form-group text-center">
                        <button type="button" id="devicerenamesubmit" class="btn-primary save_changes_btn"> Save Changes</button>
                    </div> *}
                </form>
            </div>
        </div>
        <div class="modal-footer panel-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary modal-submit" id="devicerenamesubmit">Save</button>
        </div>
    </div>
  </div>
</div>


<!-- Modal Add Email-->
<div id="add-email" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content panel panel-primary">
        <div class="modal-header panel-heading">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Email Address</h4>
        </div>
        <div class="modal-body panel-body">
            <div class="container">
                <form class="" method="post" action="#">
                    <label for="email-address" class="form-label">Email Address:</label>
                    <input type="email" placeholder="Add Email Address..." id="email-address" name="email-address" class="form-control" >
                    <span id="invalid_email" style="display:none;"> <p style="color:red ! important ;">Please enter correct email address</p></span>
                </form>
            </div>
        </div>
        <div class="modal-footer panel-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary modal-submit" id="addemaildata">Add Email</button>
        </div>
    </div>
  </div>
</div>


<!-- Modal Update Email-->
<div id="update-email" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content panel panel-primary">
        <div class="modal-header panel-heading">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Update Email Address</h4>
        </div>
        <div class="modal-body panel-body">
            <div class="container">
                <form class="" method="post" action="#">
                    <input type="hidden"  id= "update-email-id" name ="email-id"  value="">
                    <label for="email-address" class="form-label">Email Address:</label>
                    <input type="email" placeholder="Add Email Address..." id="upudate-email-address" name="email-address" class="form-control" >
                     <span id="invalid_email_update" style="display:none;"> <p style="color:red ! important ;">Please enter correct email address</p></span>
                </form>
            </div>
        </div>
        <div class="modal-footer panel-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary modal-submit" id="updateemaildata">Update Email</button>
        </div>
    </div>
  </div>
</div>

