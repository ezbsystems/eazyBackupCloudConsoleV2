jQuery(document).ready(function ($) {

    // =========================================================
    // 1. OPEN MANAGE VAULT MODAL & POPULATE FIELDS
    // =========================================================
    $(document).on("click", "#storage-vault-manage", function (e) {
        e.preventDefault();

        // Show the modal
        $('#manage-vault-modal').fadeIn(300);

        // Capture data attributes from the clicked element
        var vaultstorageid = $(this).data("vaultstorageid");
        var storagename = $(this).data("storagename");
        var StorageLimitEnabled = $(this).data("storagelimitenabled");
        var StorageLimitBytes = $(this).data("storagelimitbytes");

        // Parse out numeric and unit parts
        var storagesize = parseInt(StorageLimitBytes.replace(/[^0-9.]/g, "")) || "";
        var storageStandard = StorageLimitBytes.replace(/[0-9]/g, '');

        // Populate hidden and text fields
        $('#vault_storageID').val(vaultstorageid);
        $('#storagename').val(storagename);
        $("#storageSize").val(storagesize);
        $('#standardSize').val(storageStandard);

        // Function to toggle storage fields
        function toggleStorageFields() {
            if ($("#storageUnlimited").is(":checked")) {
                // If unlimited is enabled
                $("#storageSize").prop('disabled', true).addClass("not-allowed bg-gray-200").removeClass("storagesizeenable");
                $("#standardSize").prop('disabled', true).addClass("not-allowed bg-gray-200").removeClass("standardsizeenable");
            } else {
                // If unlimited is disabled
                $("#storageSize").prop('disabled', false).removeClass("not-allowed bg-gray-200").addClass("storagesizeenable");
                $("#standardSize").prop('disabled', false).removeClass("not-allowed bg-gray-200").addClass("standardsizeenable");
            }
        }

        // Initial state based on data attributes
        if (!StorageLimitEnabled) {
            $("#storageUnlimited").prop('checked', true);
        } else {
            $("#storageUnlimited").prop('checked', false);
        }
        toggleStorageFields(); // Apply initial state

        // Event listener for changes to the Unlimited checkbox
        $("#storageUnlimited").off("change").on("change", function () {
            toggleStorageFields();
        });
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
$('#storageUnlimited').on('change', function() {
    if ($(this).is(':checked')) {
        $('#storageSize').val('').prop('disabled', true);
        $('#standardSize').val('GB').prop('disabled', true);
    } else {
        $('#storageSize').prop('disabled', false);
        $('#standardSize').prop('disabled', false);
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