jQuery(document).ready(function() {
    // Use global loader helper functions
    // Use delegated event handling:
    jQuery('#tableServicesList tbody').on('click', 'tr[data-serviceid]', function(e) {
        e.preventDefault();
        // Remove any extra detail rows
        jQuery(".cometuserDetails").remove();

        // Grab the <i> caret within the row if it exists
        let $caret = jQuery(this).find("i.fa.fa-caret-right").first();

        // If caret is active, slide up and remove active state
        if ($caret.hasClass("active")) {
            jQuery("#service-data").slideUp('slow');
            jQuery("i.fa.fa-caret-right").removeClass("active");
            return false;
        }

        // Show modern global loader
        window.showGlobalLoader();
        jQuery(".table-container").addClass("loading");

        // Slide up any opened data
        jQuery("#service-data").slideUp('slow');
        jQuery("i.fa.fa-caret-right").removeClass("active");
        $caret.addClass("active");

        // Get the service ID
        let id = jQuery(this).data('serviceid');

        // Make the AJAX request
        jQuery.ajax({
            method: 'POST',
            url: 'modules/servers/comet/ajax/ajax.php',
            data: {
                'id': id
            },
            success: function(data) {
                // Add new row for the AJAX details
                let cometuserDetails = `
                    <tr class="cometuserDetails">
                      <td colspan="10">
                        <div id="service-data" style="display:none"></div>
                      </td>
                    </tr>`;
                jQuery(cometuserDetails).insertAfter("#serviceid-" + id);

                // Insert data, hide loader, and slide down
                jQuery("#service-data").html(data);
                jQuery(".table-container").removeClass("loading");
                window.hideGlobalLoader();
                jQuery("#service-data").slideDown('slow');

                // If you have any toggles or additional init steps:
                jQuery('.emailbackup-ajax').bootstrapSwitch('state');
            },
            error: function(err) {
                jQuery("#service-data").html("Error: " + err.responseText);
            }
        });
    });


    function parseStorageString(str) {
        // If the string is empty or null, return 0
        if (!str) {
            return 0;
        }
    
        // Match patterns like "100GB", "1 TB", "500 MB", "2.5GB", etc.
        // This regex looks for an optional decimal, then a unit
        var match = str.trim().match(/^([\d\.]+)\s*(B|KB|MB|GB|TB|PB|EB)?$/i);
        if (!match) {
            return 0;
        }
    
        var value = parseFloat(match[1]);
        var unit = (match[2] || 'B').toUpperCase();
    
        switch (unit) {
            case 'B':  return value;
            case 'KB': return value * 1024;
            case 'MB': return value * 1024 * 1024;
            case 'GB': return value * 1024 * 1024 * 1024;
            case 'TB': return value * 1024 * 1024 * 1024 * 1024;
            case 'PB': return value * 1024 * 1024 * 1024 * 1024 * 1024;
            case 'EB': return value * 1024 * 1024 * 1024 * 1024 * 1024 * 1024;
            default:   return 0;
        }
    }
    
    // Register a custom DataTables sort type for ascending and descending.
    jQuery.fn.dataTableExt.oSort['storage-size-asc'] = function(a, b) {
        var x = parseStorageString(a);
        var y = parseStorageString(b);
        return (x < y) ? -1 : ((x > y) ? 1 : 0);
    };
    
    jQuery.fn.dataTableExt.oSort['storage-size-desc'] = function(a, b) {
        var x = parseStorageString(a);
        var y = parseStorageString(b);
        return (x < y) ? 1 : ((x > y) ? -1 : 0);
    };



    $(document).ready(function() {

        // Function to display messages using Tailwind classes
        function showMessage(containerId, message, type = 'success') {
            const successClasses = "";
            const dangerClasses  = "";
        
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
                window.showGlobalLoader();
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
        
                        // In your AJAX success callback:
                        if (jsondata['result'] === "success") {
                            $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();
                            $('#reset-password-modal').addClass('hidden');

                            // Use the global function to display a success message
                            window.displayMessage("#successMessage", "Your password has been changed successfully.", "success");
                        } else {
                            // In error scenario, similarly:
                            $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();
                            $('#reset-password-modal').addClass('hidden');

                            window.displayMessage("#errorMessage", "Your password has not been changed. " + (jsondata['message'] || ''), "error");
                        }
                    },
                    error: function(xhr, status, error){
                        console.log('AJAX Error:', error);
                        // Hide loading indicators
                        $(".table-container").removeClass("loading");
                        window.hideGlobalLoader();
        
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

    let messageTimeout;
    window.displayMessage = function(selector, message, type) {
        // Clear any existing timeout to prevent multiple timers
        if (messageTimeout) {
            clearTimeout(messageTimeout);
        }

        // Hide both containers first
        $("#successMessage, #errorMessage").addClass("hidden");

        // Select the appropriate container
        var $container = $(selector);

        // Update the text inside the container
        $container.text(message);

        // Remove the "hidden" class so it becomes visible
        $container.removeClass("hidden");

        // Manage styling based on message type
        if (type === "success") {
            // Remove any error-related classes, add success-related classes
            $container.removeClass("alert-danger text-gray-100");
            $container.addClass("alert-success  text-gray-100");
        } else if (type === "error") {
            // Remove any success-related classes, add error-related classes
            $container.removeClass("alert-success text-gray-100");
            $container.addClass("alert-danger text-gray-100");
        }

        // Set a timeout to hide the message after 3 seconds (3000 milliseconds)
        messageTimeout = setTimeout(function() {
            $container.addClass("hidden");
        }, 4000);

        // **New Enhancements: Scroll and Focus**

        // Delay to ensure the message is rendered before scrolling
        setTimeout(function() {
            // Set focus to the message container
            $container.focus();

            // Scroll smoothly to the message container
            $('html, body').animate({
                scrollTop: $container.offset().top - 20 // Adjust offset as needed
            }, 500); // Duration in milliseconds
        }, 100); // Delay in milliseconds
    }



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
            window.showGlobalLoader();
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
                            window.hideGlobalLoader();
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
                            window.hideGlobalLoader();
    
                        // Refresh the service list (twice, as per your existing code)
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');
    
                        displayMessage("#successMessage", "Your changes have been saved successfully.", "success");
                    } else {
                        console.log('Error occurs here: ' + data);
                        $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();
                        displayMessage("#errorMessage", "Your changes have not been saved successfully.", "error");
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log('Something went wrong: ' + textStatus + ' - ' + errorThrown);
                    $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();
                    displayMessage("#errorMessage", "Something went wrong!!", "error");
                }
            });
        });
    
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
            window.showGlobalLoader();
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
                        // Clear fields, remove loaders, etc.
                        $('#update-email-address').val("");
                        $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();

                        // Dispatch the event to tell Alpine to close
                        document.getElementById('update-email-modal').dispatchEvent(
                        new CustomEvent('close-update-email-modal', { bubbles: true })
                        );

                        // Refresh the service list
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');

                        // Show success message                            
                        displayMessage("#successMessage", "Your changes have been saved successfully.", "success");
                    } else {
                        console.log('Error Occurs: ', data);
                        $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');
                        $("#serviceid-" + serviceId + " .service_list").trigger('click');

                        // Show error message
                        displayMessage("#errorMessage", "There was an error preventing your changes from being saved.", "error");
                    }
                },
                error: function(data) {
                    console.log('Something went wrong: ', data);
                    $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();
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

        window.showGlobalLoader();
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
                            window.hideGlobalLoader();
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
                            window.hideGlobalLoader();
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
                            window.hideGlobalLoader();
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
        e.preventDefault(); // Prevent default form submission

        // Gather form values
        var serviceId       = $("input[name='serviceId']").val();
        var accountName     = $("input[name='accountname']").val();
        var MaximumDevices  = $("input[name='MaximumDevices']").val();
        // ... other data collection ...

        // Handle the Unlimited Devices state
        if (MaximumDevices === '') {
            MaximumDevices = 0; // Treat empty as Unlimited
        } else {
            MaximumDevices = parseInt(MaximumDevices, 10);
            if (isNaN(MaximumDevices)) {
                MaximumDevices = 1; 
            } else if (MaximumDevices < 0) {
                MaximumDevices = 1; 
            } else if (MaximumDevices > 999) {
                MaximumDevices = 999; 
            }
        }

        // Validation for MaximumDevices
        if (MaximumDevices > 999){
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Instead of appending, consider using your global displayMessage function:
            displayMessage("#errorMessage", "Maximum device cannot be greater than 999", "error");
            return false;
        }

        var emailreportingStatus = $('#emailreportingStatus').is(':checked');
        var emailreporting = emailreportingStatus ? "on" : "";

        var emails = [];
        $("input[name='email[]']").each(function(index, value) {
            emails.push($(this).val());
        });

        // Show loader
        window.showGlobalLoader();
        $(".table-container").addClass("loading");

        // Clear any old messages (optional, if your displayMessage function doesn't already do so)
        $("#successMessage").html('').addClass('hidden');
        $("#errorMessage").html('').addClass('hidden');

        $.ajax({
            url: "modules/servers/comet/ajax/saveprofile.php",
            method: 'POST',
            data: {
                serviceId: serviceId,
                accountname: accountName,
                MaximumDevices: MaximumDevices,
                email: emails,
                emailreporting: emailreporting
            },
            success: function(data) {
                $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();

                var jsondata;
                try {
                    jsondata = JSON.parse(data);
                } catch (err) {
                    console.log("Invalid JSON response:", data);
                    displayMessage("#errorMessage", "Invalid server response.", "error");
                    return;
                }

                if (jsondata['Status'] == 200 && jsondata['Message'] == 'OK') {
                    // Refresh the services table so the user sees the updated info
                    // (Requires jQuery if that's how you trigger the refresh)
                    $('#serviceid-' + serviceId + ' .service_list').trigger('click');

                    // Show success message
                    displayMessage("#successMessage", "Your changes have been saved successfully.", "success");
                } else {
                    // Possibly still refresh the table, in case partial changes took effect
                    $('#serviceid-' + serviceId + ' .service_list').trigger('click');

                    // Show error message
                    displayMessage("#errorMessage", "Your changes have not been saved successfully.", "error");
                }
            },
            error: function(err) {
                console.log("Something went wrong:", err);
                $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();

                // Refresh table if desired
                $('#serviceid-' + serviceId + ' .service_list').trigger('click');

                // Show error message
                displayMessage("#errorMessage", "Something Went wrong!!", "error");
            }
        });
    });


    //Revoke device form submit
    $(document).on("click", ".revoke_device", function() {
        var revoke_deviceId = $(this).attr("data-revokedeviceId");
        $(this).siblings('form').submit();
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

        window.showGlobalLoader();
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
                            window.hideGlobalLoader();
                    var base_url = window.location.origin;
                    var upgradablepath = '/upgrade.php?type=package&id=' + encodeURIComponent(serviceId);
                    var redirectUrl = base_url + upgradablepath;
                    window.location.href = redirectUrl;
                }
                else{

                    $(".table-container").removeClass("loading");
                            window.hideGlobalLoader();

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
                            window.hideGlobalLoader();

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
