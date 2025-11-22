jQuery(document).ready( function ()
{
        /*
        //toggle particular service data using ajax request
        */
        $(".service_list").click(function(e){
            e.preventDefault();
            $( ".cometuserDetails" ).remove();

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
                    var cometuserDetails = '<tr class="cometuserDetails"><td colspan="10"><div id="service-data" style="display:none"></div></td></tr>';
                    $(cometuserDetails).insertAfter("#serviceid-"+id);
                    $("#service-data").html(data);
                    $(".table-container").removeClass("loading");
                    $('.myloader').css("display", "none");
                    $("#service-data").slideDown('slow');
                    $('.emailbackup-ajax').bootstrapSwitch('state');

                },
                error: function(data){
                   // alert(data);
                    $("#service-data").html(data);
                }
            });
        });


        $(document).ready(function() {

            // Function to display messages using Tailwind classes
            function showMessage(containerId, message, type = 'success') {
                const successClasses = "text-center block mb-4 p-4 border border-green-300 bg-green-50 text-green-700 rounded";
                const dangerClasses  = "text-center block mb-4 p-4 border border-red-300 bg-red-50 text-red-700 rounded";
            
                const uniqueClass = `alert-${Date.now()}`;
                const alertClasses = (type === 'success') ? successClasses : dangerClasses;
            
                // Build the HTML for the alert itself
                const messageHtml = `
                    <div class="${uniqueClass} ${alertClasses}" role="alert">
                        ${message}
                    </div>
                `;
            
                // Remove .hidden so #successMessage is no longer display:none,
                // then append the new alert DIV inside of it
                $(containerId)
                    .removeClass('hidden')    // <--- Ensures the parent is now visible
                    .append(messageHtml);
            
                // Optional fade-out
                setTimeout(function() {
                    $(`.${uniqueClass}`).fadeOut('fast', function() {
                        $(this).remove();
                    });
                }, 5000);
            }


            $(document).ready(function() {
                // Function to validate password strength
                function isValidPassword(password) {
                    // Example: At least 8 characters. Modify as needed.
                    return password.length >= 8;
                }
            
                // Handle click on Reset Password buttons
                $(document).on("click", ".resetservicepassword", function(e) {
                    e.preventDefault();
                    var resetpasswordserviceId = $(this).data('serviceid');
                    console.log("Reset Password Service ID:", resetpasswordserviceId); // Debugging
            
                    // Populate the hidden service ID field
                    $('#resetpasswordserviceId').val(resetpasswordserviceId);
                    // Clear password fields
                    $("#inputNewPassword1").val('');
                    $("#inputNewPassword2").val('');
                    // Clear previous error messages
                    $('#passworderrorMessage').html('').hide();
            
                    // Show the modal
                    $('#reset-password-modal').removeClass('hidden');
                });
            
                // Handle closing the Reset Password modal
                $(document).on("click", "#close-reset-modal", function(e) {
                    e.preventDefault();
                    $('#reset-password-modal').addClass('hidden');
                });
            
                // Handle form submission for resetting password
                $(document).on("submit", "#reset-password-form", function(e) {
                    e.preventDefault();
                    $('#passworderrorMessage').html('').hide();
            
                    var serviceId = $('#resetpasswordserviceId').val();
                    var newPassword = $('#inputNewPassword1').val();
                    var confirmNewPassword = $('#inputNewPassword2').val();
            
                    console.log("Submitting Service ID:", serviceId); // Debugging
                    console.log("New Password:", newPassword); // Debugging
                    console.log("Confirm New Password:", confirmNewPassword); // Debugging
            
                    // Validate password length
                    if (!isValidPassword(newPassword)) {
                        var passwordError = 'Backup password must be at least 8 characters long.';
                        $('#passworderrorMessage').html(passwordError).show();
                        return false;
                    }
            
                    // Validate password confirmation
                    if (newPassword !== confirmNewPassword) {
                        var confirmError = 'Passwords do not match.';
                        $('#passworderrorMessage').html(confirmError).show();
                        return false;
                    }
            
                    // Optional: Add more password strength validations here
            
                    // Show loading indicators
                    $('.myloader').css("display", "block");
                    $(".table-container").addClass("loading");
            
                    // Send AJAX request to change password
                    $.ajax({
                        method: 'POST',
                        url: 'modules/servers/comet/ajax/changepassword.php',
                        data: {
                            'serviceId': serviceId,
                            'newpassword': newPassword,
                        },
                        success: function(data) {
                            var jsondata;
                            try {
                                jsondata = JSON.parse(data);
                            } catch (e) {
                                console.log("Invalid JSON Response:", data);
                                jsondata = { 'result': 'error', 'message': 'Invalid server response.' };
                            }
            
                            console.log("AJAX Response:", jsondata); // Debugging
            
                            if (jsondata['result'] === "success") {
                                // Hide loading indicators
                                $(".table-container").removeClass("loading");
                                $('.myloader').css("display", "none");
            
                                // Close the modal
                                $('#reset-password-modal').addClass('hidden');
            
                                // Show success message
                                var successMessage = `<div class="successMessage alert alert-success text-center" role="alert">
                                                        Your password has been changed successfully.
                                                    </div>`;
                                $("#successMessage").html(successMessage).removeClass('hidden');
                                setTimeout(function() {
                                    $('.successMessage').fadeOut('fast', function() {
                                        $(this).addClass('hidden').html('');
                                    });
                                }, 5000);
                            } else {
                                // Hide loading indicators
                                $(".table-container").removeClass("loading");
                                $('.myloader').css("display", "none");
            
                                // Close the modal
                                $('#reset-password-modal').addClass('hidden');
            
                                // Show error message
                                var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                        Your password has not been changed. ${jsondata['message'] || ''}
                                                    </div>`;
                                $("#errorMessage").html(errorMessage).removeClass('hidden');
                                setTimeout(function() {
                                    $('.errorMessage').fadeOut('fast', function() {
                                        $(this).addClass('hidden').html('');
                                    });
                                }, 5000);
                            }
                        },
                        error: function(xhr, status, error){
                            console.log('AJAX Error:', error);
                            // Hide loading indicators
                            $(".table-container").removeClass("loading");
                            $('.myloader').css("display", "none");
            
                            // Close the modal
                            $('#reset-password-modal').addClass('hidden');
            
                            // Show error message
                            var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                    Something went wrong!
                                                </div>`;
                            $("#errorMessage").html(errorMessage).removeClass('hidden');
                            setTimeout(function() {
                                $('.errorMessage').fadeOut('fast', function() {
                                    $(this).addClass('hidden').html('');
                                });
                            }, 3000);
                        }
                    });
            
                    return false;
                });
            });
    });           




        // =========================================================
        // MANAGE VAULT MODAL
        // =========================================================

    jQuery(document).ready(function($) {

        // =========================================================
        // 1. OPEN MANAGE VAULT MODAL & POPULATE FIELDS
        // =========================================================
        $(document).on("click", "#storage-vault-manage", function(e) {
            e.preventDefault();
            
            
            // Show the modal
            $('#manage-vault-modal').fadeIn(300);
    
            // Capture data attributes from the clicked element
            var vaultstorageid       = $(this).data("vaultstorageid");
            var storagename          = $(this).data("storagename");
            var StorageLimitEnabled  = $(this).data("storagelimitenabled");
            var StorageLimitBytes    = $(this).data("storagelimitbytes");
    
            // Parse out numeric and unit parts
            var storagesize      = parseInt(StorageLimitBytes.replace(/[^0-9.]/g, "")) || "";
            var storageStandard  = StorageLimitBytes.replace(/[0-9]/g, '');
    
            // Populate hidden and text fields
            $('#vault_storageID').val(vaultstorageid);
            $('#storagename').val(storagename);
            $("#storageSize").val(storagesize);
            $('#standardSize').val(storageStandard);
    
            // Check if unlimited is enabled
            if (!StorageLimitEnabled) 
                {
                // If no limit is set
                $("#storageUnlimited").prop('checked', true);
                
                // Disable numeric fields
                $("#storageSize").prop('disabled', true).addClass("not-allowed").removeClass("storagesizeenable");
                $("#standardSize").prop('disabled', true).addClass("not-allowed").removeClass("standardsizeenable");
    
            } else {
                // If there's a set limit
                $("#storageUnlimited").prop('checked', false);
    
                // Enable numeric fields
                $("#storageSize").prop('disabled', false).removeClass("not-allowed").addClass("storagesizeenable");
                $("#standardSize").prop('disabled', false).removeClass("not-allowed").addClass("standardsizeenable");                    
            }
        });
    
        // =========================================================
        // 2. CLOSE MANAGE VAULT MODAL
        //    (Applies to both "Close" button & the "X" icon)
        // =========================================================
        $(document).on("click", "#manage-vault-modal .close", function(e) {
            e.preventDefault();
    
            $('#manage-vault-modal').fadeOut(300, function() {
                // Reset the form once hidden
                $('#manage-vault-modal form')[0].reset();
    
                // Re-enable fields in case they were disabled
                $('#manage-vault-modal form input, #manage-vault-modal form select').prop('disabled', false);
                $("#storageSize").removeClass("not-allowed storagesizeenable");
                $("#standardSize").removeClass("not-allowed standardsizeenable");
            });
        });
    
        // =========================================================
        // 3. TOGGLE "UNLIMITED" STORAGE OPTION
        // =========================================================
        $(document).on("click", "#storageUnlimited", function() {

            var storageUnlimited =  $('#storageUnlimited').is(':checked');
           // console.log(storageUnlimited)

            if(storageUnlimited == true)
            {

                $("#storageSize").attr( 'disabled', true );
                $("#storageSize").val('');
                $("#storageSize").addClass("not-allowed");
                $("#storageSize").addClass("bg-gray-200");
                $("#storageSize").removeClass("storagesizeenable");

                $("#standardSize").attr( 'disabled', true );
                $("#standardSize").val('');
                $("#standardSize").addClass("not-allowed");
                $("#standardSize").addClass("bg-gray-200");
                $("#standardSize").removeClass("standardsizeenable");
            } else {

                $("#storageSize").attr( 'disabled', false );
                $("#storageSize").removeClass("not-allowed");
                $("#storageSize").removeClass("bg-gray-200");
                $("#storageSize").addClass("storagesizeenable");

                $("#standardSize").attr( 'disabled', false );
                $("#standardSize").removeClass("not-allowed");
                $("#standardSize").removeClass("bg-gray-200");
                $("#standardSize").addClass("standardsizeenable");
            }

        });
    
        // =========================================================
        // 4. SUBMIT MANAGE VAULT FORM VIA AJAX
        // =========================================================
        $("#manageVaultrequest").on("click", function(e) {
            e.preventDefault(); // Prevent default form submission
    
            // Gather form data
            var serviceId         = $("input[name='serviceId']").val(); // Ensure this input exists
            var vault_storageID   = $("#vault_storageID").val();
            var storagename       = $("#storagename").val().trim();
            var storageSize       = $("#storageSize").val();
            var standardSize      = $("#standardSize").val();
            var storageUnlimited  = $("#storageUnlimited").is(':checked') ? 1 : 0;
    
            // Basic validation
            if (storagename === '') {
                displayMessage("#errorMessage", "Storage Vault Name cannot be empty.", "error");
                return;
            }
    
            if (!storageUnlimited && (storageSize === '' || storageSize <= 0)) {
                displayMessage("#errorMessage", "Please enter a valid storage size.", "error");
                return;
            }
    
            // Show loader
            $('.myloader').css("display", "block");
            $(".table-container").addClass("loading");
    
            // Prepare data for AJAX
            var postData = {
                'serviceId': serviceId,
                'storageVaultId': vault_storageID,
                'storageVaultSize': storageSize,
                'storageVaultName': storagename,
                'storageVaultstandardSize': standardSize,
                'storageUnlimited': storageUnlimited
            };
    
            // Perform AJAX request
            $.ajax({
                url: "modules/servers/comet/ajax/managevault.php",
                method: 'POST',
                data: postData,
                success: function(data) {
                    var jsondata;
                    try {
                        jsondata = JSON.parse(data);
                    } catch (e) {
                        console.log('Invalid JSON response');
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
                        displayMessage("#errorMessage", "Invalid server response.", "error");
                        return;
                    }
    
                    if (jsondata['Status'] == 200 && jsondata['Message'] == 'OK') {
                        // Close modal
                        $('#manage-vault-modal').fadeOut(300, function() {
                            $('#manage-vault-modal form')[0].reset();
                            $('#manage-vault-modal form input, #manage-vault-modal form select')
                                .prop('disabled', false);
                        });
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
    
                        // Refresh the service list (twice, as per your existing code)
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');
    
                        displayMessage("#successMessage", "Your changes have been saved successfully.", "success");
                    } else {
                        console.log('Error occurs here: ' + data);
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
                        displayMessage("#errorMessage", "Your changes have not been saved successfully.", "error");
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log('Something went wrong: ' + textStatus + ' - ' + errorThrown);
                    $(".table-container").removeClass("loading");
                    $('.myloader').css("display", "none");
                    displayMessage("#errorMessage", "Something went wrong!!", "error");
                }
            });
        });
    
    });
    

        /**
        // Passed value in rename device form
        */
        $(document).on("click", ".rename_device", function(e) {
            e.preventDefault();
            var deviceId = $(this).data("deviceid");
            var serviceid = $(this).data("serviceid");
            var devicename = $(this).data("devicename");
            $('#devicename').val(devicename);
            $('#deviceId').val(deviceId);
            $('#serviceId').val(serviceid);
        });

        // Ajax request on rename device form
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
                            'serviceId': serviceId,
                            'deviceId': deviceId,
                            'devicename': devicename,
                        },
                    url:'modules/servers/comet/ajax/device_rename.php',
                    success:function(data) {
                        //console.log(data);

                        var jsondata = JSON.parse(data);
                        if(jsondata['Status'] == 200 && jsondata['Message'] == 'OK')
                        {
                            $(".table-container").removeClass("loading");
                            $('.myloader').css("display", "none");

                            // Trigger the close button's click to close the modal
                            $("#rename_device .close").trigger('click');

                            // Trigger clicks to refresh the service list (ensure these selectors are correct)
                            $("#serviceid-"+serviceId+" .service_list").trigger('click');
                            $("#serviceid-"+serviceId+" .service_list").trigger('click');

                            var successMessage = `<div class="successMessage alert alert-success text-center" role="alert">
                                                    Your changes have been saved successfully
                                                </div>`;
                            $("#successMessage").append(successMessage);
                            setTimeout(function() {
                                $('.successMessage').fadeOut('fast');
                            }, 3000);
                        }
                        else{
                            $(".table-container").removeClass("loading");
                            $('.myloader').css("display", "none");

                            // Trigger the close button's click to close the modal
                            $("#rename_device .close").trigger('click');

                            // Trigger clicks to refresh the service list
                            $("#serviceid-"+serviceId+" .service_list").trigger('click');
                            $("#serviceid-"+serviceId+" .service_list").trigger('click');

                            var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                Your changes have not been saved successfully
                                            </div>`;
                            $("#errorMessage").append(errorMessage);
                            setTimeout(function() {
                                $('.errorMessage').fadeOut('fast');
                            }, 5000);
                        }
                    },
                    error: function(data){
                        // Handle AJAX errors
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");

                        // Trigger the close button's click to close the modal
                        $("#rename_device .close").trigger('click');

                        // Trigger clicks to refresh the service list
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');

                        var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                        Something went wrong!
                                        </div>`;
                        $("#errorMessage").append(errorMessage);
                        setTimeout(function() {
                            $('.errorMessage').fadeOut('fast');
                        }, 3000);
                    }
                });
            }

        });


        // Function to validate email format
        function IsEmail(email) {
            var regex = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
            return regex.test(email);
        }

        $(document).ready(function() {
            // Handle click on .update-email buttons
            $(document).on("click", ".update-email", function(e) {
                e.preventDefault();
                var emailIdtoUpdate = $(this).data("emailid");
                var emailValuetoUpdate = $(this).data("email");
                console.log("Update Email Clicked: ", { emailIdtoUpdate, emailValuetoUpdate });

                // Populate the modal fields
                $('#update-email-id').val(emailIdtoUpdate);
                $('#update-email-address').val(emailValuetoUpdate);
                $('#invalid_email_update').html('').hide();

                // Show the modal
                $('#update-email-modal').removeClass('hidden');
            });

            // Handle closing the modal
            $(document).on("click", "#close-modal", function(e) {
                e.preventDefault();
                $('#update-email-modal').addClass('hidden');
            });

            // Handle form submission for updating email
            $(document).on("submit", "#update-email-form", function(e) {
                e.preventDefault();
                $('#invalid_email_update').html('').hide();

                var emailkey = $('#update-email-id').val();
                var emailValue = $('#update-email-address').val();
                console.log("Save Updated Email: ", { emailkey, emailValue });

                if (IsEmail(emailValue) == false) {
                    var emailerrorMessage = '<p>Please enter a valid email address.</p>';
                    $('#invalid_email_update').html(emailerrorMessage).show();
                    return false;
                }

                // Update the email in the DOM
                $('#email-' + emailkey).val(emailValue);
                $('.email-' + emailkey).text(emailValue);

                var reportingEmails = [];
                $("input[name='email[]']").each(function(index, value) {
                    reportingEmails.push($(this).val());
                });
                console.log("Emails Before Update: ", reportingEmails);

                var serviceId = $("input[name='serviceId']").val();
                $('.myloader').css("display", "block");
                $(".table-container").addClass("loading");

                $.ajax({
                    url: "modules/servers/comet/ajax/email_actions.php",
                    method: 'POST',
                    data: {
                        'serviceId': serviceId,
                        'email': reportingEmails
                    },
                    success: function(data) {
                        var jsondata = JSON.parse(data);
                        console.log("Update Email Response: ", jsondata);

                        if (jsondata['Status'] == 200 && jsondata['Message'] == 'OK') {
                            // Close the modal
                            $('#update-email-modal').addClass('hidden');
                            $('#update-email-address').val("");
                            $(".table-container").removeClass("loading");
                            $('.myloader').css("display", "none");

                            // Refresh the service list
                            $("#serviceid-" + serviceId + " .service_list").trigger('click');
                            $("#serviceid-" + serviceId + " .service_list").trigger('click');

                            // Show success message
                            var successMessage = `<div class="successMessage alert alert-success text-center" role="alert">
                                                    Your changes have been saved successfully.
                                                </div>`;
                            $("#successMessage").append(successMessage);
                            setTimeout(function() {
                                $('.successMessage').fadeOut('fast');
                            }, 3000);
                        } else {
                            console.log('Error Occurs: ', data);
                            $(".table-container").removeClass("loading");
                            $('.myloader').css("display", "none");
                            $("#serviceid-" + serviceId + " .service_list").trigger('click');
                            $("#serviceid-" + serviceId + " .service_list").trigger('click');

                            // Show error message
                            var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                    Your changes have not been saved successfully.
                                                </div>`;
                            $("#errorMessage").append(errorMessage);
                            setTimeout(function() {
                                $('.errorMessage').fadeOut('fast');
                            }, 5000);
                        }
                    },
                    error: function(data) {
                        console.log('Something went wrong: ', data);
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');

                        // Show error message
                        var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                Something went wrong!
                                            </div>`;
                        $("#errorMessage").append(errorMessage);
                        setTimeout(function() {
                            $('.errorMessage').fadeOut('fast');
                        }, 3000);
                    }
                });

                return false;
            });
        });


        // Remove email listing for selected email
        $(document).on("click", ".remove-email", function(e) {
            e.preventDefault();

            var active_emails = [];
            $("input[name='email[]']").each(function(index, value) {
                active_emails.push($(this).val());
            });
            console.log("Active Emails Before Deletion: ", active_emails);

            var delete_confirmation = confirm('Are you sure you want to delete this?');
            if (delete_confirmation == true) {
                var serviceId = $("input[name='serviceId']").val();
                $(this).closest(".email-list").remove();

                var reportingEmails = [];
                $("input[name='email[]']").each(function(index, value) {
                    reportingEmails.push($(this).val());
                });
                console.log("Emails After Deletion: ", reportingEmails);

                // Ensure the email list is unique
                reportingEmails = [...new Set(reportingEmails)];
                console.log("Unique Emails After Deletion: ", reportingEmails);

                $('.myloader').css("display", "block");
                $(".table-container").addClass("loading");

                $.ajax({
                    url: "modules/servers/comet/ajax/email_actions.php",
                    method: 'POST',
                    data: {
                        'serviceId': serviceId,
                        'email': reportingEmails
                    },
                    success: function(data) {
                        var jsondata = JSON.parse(data);
                        console.log("Remove Email Response: ", jsondata);

                        if (jsondata['Status'] == 200 && jsondata['Message'] == 'OK') {
                            $(".table-container").removeClass("loading");
                            $('.myloader').css("display", "none");
                            $("#serviceid-" + serviceId + " .service_list").trigger('click');
                            $("#serviceid-" + serviceId + " .service_list").trigger('click');
                            var successMessage = `<div class="successMessage alert alert-success text-center" role="alert">
                                                    Your changes have been saved successfully
                                                </div>`;
                            $("#successMessage").append(successMessage);
                            setTimeout(function() {
                                $('.successMessage').fadeOut('fast');
                            }, 3000);
                        } else {
                            console.log('Error Occurs: ', data);
                            $(".table-container").removeClass("loading");
                            $('.myloader').css("display", "none");
                            $("#serviceid-" + serviceId + " .service_list").trigger('click');
                            $("#serviceid-" + serviceId + " .service_list").trigger('click');
                            var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                    Your changes have not been saved successfully
                                                </div>`;
                            $("#errorMessage").append(errorMessage);
                            setTimeout(function() {
                                $('.errorMessage').fadeOut('fast');
                            }, 5000);
                        }
                    },
                    error: function(data) {
                        console.log('Something went wrong: ', data);
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');
                        var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                Something Went wrong!!
                                            </div>`;
                        $("#errorMessage").append(errorMessage);
                        setTimeout(function() {
                            $('.errorMessage').fadeOut('fast');
                        }, 3000);
                    }
                });
            } else {
                return false;
            }
        });

        //Add email data with popup
        $(document).on("click", "#addemaildata", function(e) {
            $('#invalid_email').html('');
            $('#invalid_email').hide();
            var serviceId = $("input[name='serviceId']").val();

            var reportingEmails = [];
            $("input[name='email[]']").each(function(index , value) {
                reportingEmails.push($(this).val());
            });
            //console.log(reportingEmails);
            var newReportingEmail = $("#email-address").val();

            if(IsEmail(newReportingEmail) == false){
                var emailerrorMesaage = '<p style="color:red ! important ;">Please enter correct email address</p>';

                $('#invalid_email').show();
                $('#invalid_email').append(emailerrorMesaage);
                setTimeout(function() {
                        $('#invalid_email').fadeOut('fast');
                    }, 1000);
                return false;
            }
            reportingEmails.push(newReportingEmail);
            //console.log(reportingEmails);

            $('.myloader').css("display", "block");
            $(".table-container").addClass("loading");
            $.ajax({
                url:"modules/servers/comet/ajax/email_actions.php",
                method:'POST',
                data:{
                        'serviceId': serviceId ,
                        'email': reportingEmails
                    },
                success:function(data) {
                    var jsondata = JSON.parse(data);

                    //console.log(jsondata['Status']);
                    //console.log(jsondata);

                    if(jsondata['Status'] == 200 && jsondata['Message'] == 'OK')
                    {
                        $("#add-email .close").click();
                        $('#email-address').val("");
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
                    else{
                        console.log('error occurs here' + data)
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
                        //clicked two time bcz of toggle
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        //location.reload();
                        var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                You changes have not saved successfully
                                                </div>`;
                        $("#errorMessage").append(errorMessage);
                        setTimeout(function() {
                            $('.errorMessage').fadeOut('fast');
                        }, 5000);
                    }

                },
                error: function(data){
                   //alert(data);
                   console.log('something went wrong' + data)
                   $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
                        //clicked two time bcz of toggle
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        //location.reload();
                        var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                               Something Went wrong!!
                                                </div>`;
                        $("#errorMessage").append(errorMessage);
                        setTimeout(function() {
                            $('.errorMessage').fadeOut('fast');
                        }, 3000);

                }
            });
        });

        //Ajax request for save profile
        $(document).on("click", "#saveprofile", function(e) {
            var serviceId = $("input[name='serviceId']").val();
            var accountName = $("input[name='accountname']").val();
            var MaximumDevices = $("input[name='MaximumDevices']").val();
            //console.log(MaximumDevices)

            if(MaximumDevices > 999){
                //console.log('value is greater than 999')
                window.scrollTo({ top: 0, behavior: 'smooth' });

                 var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                Maximum device cannot be greater than 999
                                                </div>`;
                        $("#errorMessage").append(errorMessage);
                        setTimeout(function() {
                            $('.errorMessage').fadeOut('fast');
                        }, 5000);
                        $('#errorMessage').focus();
                          return false;
            }

            var emailreportingStatus =  $('#emailreportingStatus').is(':checked');
            //console.log(emailreportingStatus);
            if(emailreportingStatus == true){
                var emailreporting = "on";
            } else{
                var emailreporting = "";
            }
            // var emailreporting = $("input[name='emailreporting']").val();
            var emails = [];
            $("input[name='email[]']").each(function(index , value) {
                emails.push($(this).val());
            });

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
                    else{
                         console.log('error occurs here' + data)
                        $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
                        //clicked two time bcz of toggle
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        //location.reload();
                        var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                                You changes have not saved successfully
                                                </div>`;
                        $("#errorMessage").append(errorMessage);
                        setTimeout(function() {
                            $('.errorMessage').fadeOut('fast');
                        }, 5000);
                    }

                },
                error: function(data){
                   //alert(data);
                   console.log('something went wrong' + data)
                   $(".table-container").removeClass("loading");
                        $('.myloader').css("display", "none");
                        //clicked two time bcz of toggle
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        $("#serviceid-"+serviceId+" .service_list").trigger('click');
                        //location.reload();
                        var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                               Something Went wrong!!
                                                </div>`;
                        $("#errorMessage").append(errorMessage);
                        setTimeout(function() {
                            $('.errorMessage').fadeOut('fast');
                        }, 3000);

                }
            });
        });

        //Revoke device form submit
        $(document).on("click", ".revoke_device", function() {
            var revoke_deviceId = $(this).attr("data-revokedeviceId");
            $(this).siblings('form').submit();
        });


        /**
        * Disable/Enable for maximum devices
        */
        $(document).on("click", "#unlimited_device", function() {
            console.log('test here')

            var unlimited_device =  $('#unlimited_device').is(':checked');

            if(unlimited_device == true){

                $("#MaximumDevices").val('');
                $("#MaximumDevices").attr('disabled', true);
                $("#MaximumDevices").addClass("not-allowed");
                $("#MaximumDevices").removeClass("mdenable");

            }
            if(unlimited_device == false){

                //$("#MaximumDevices").removeAttr('disabled');
                $("#MaximumDevices").attr( 'disabled', false );
                $("#MaximumDevices").removeClass("not-allowed");
                $("#MaximumDevices").addClass("mdenable");
            }
        });


/*
** if upgradable available redirection on upgrade page
** if upgradable not available error message on same page
*/
$(document).on("click", "#upgrade", function(e) {

    e.preventDefault();
    var serviceId = $(this).data('serviceid');
    var userservice = $(this).data('userservice'); // Correctly retrieves data-userservice
    var type = "package";

    console.log("Upgrade Service ID:", serviceId); // Debugging
    console.log("User Service:", userservice); // Debugging

    if (!serviceId) {
        console.error("Service ID is missing.");
        // Optionally, display an error message to the user
        var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                Service ID is missing. Please contact support.
                            </div>`;
        $("#errorMessage").append(errorMessage);
        setTimeout(function() {
            $('.errorMessage').fadeOut('fast');
        }, 5000);
        return;
    }

    $('.myloader').css("display", "block");
    $(".table-container").addClass("loading");

    $.ajax ({
        method:'POST',
        data:{
                'serviceId': serviceId,
                'type': type
            },
        url:'modules/servers/comet/ajax/productupgrade.php',
        success:function(data) {

            var jsondata = JSON.parse(data);
            console.log("AJAX Response:", jsondata); // Debugging

            if(jsondata['status'] === "available" )
            {
                $(".table-container").removeClass("loading");
                $('.myloader').css("display", "none");
                var base_url = window.location.origin;
                var upgradablepath = '/upgrade.php?type=package&id=' + encodeURIComponent(serviceId);
                var redirectUrl = base_url + upgradablepath;
                window.location.href = redirectUrl;
            }
            else{

                $(".table-container").removeClass("loading");
                $('.myloader').css("display", "none");

                var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                    Your plan for <b>${userservice}</b> cannot be upgraded further. Please contact support if you would like to downgrade your current plan.
                                    </div>`;
                $("#errorMessage").append(errorMessage);
                setTimeout(function() {
                    $('.errorMessage').fadeOut('fast');
                }, 7000);
            }
        },
        error: function(data){
            $(".table-container").removeClass("loading");
            $('.myloader').css("display", "none");

            var errorMessage = `<div class="errorMessage alert alert-danger text-center" role="alert">
                                Something went wrong!
                            </div>`;
            $("#errorMessage").append(errorMessage);
            setTimeout(function() {
                $('.errorMessage').fadeOut('fast');
            }, 3000);
        }
    });
});

        
});



function IsEmail(email) {
    var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    if(!regex.test(email)) {
        return false;
    }else{
        return true;
    }
}
