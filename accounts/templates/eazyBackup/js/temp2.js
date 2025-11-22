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

                // Update hidden input for form submission
                $("#hiddenStorageUnlimited").val(storageUnlimited ? 1 : 0);
    
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
                var storageUnlimited = $("#hiddenStorageUnlimited").val();
        
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