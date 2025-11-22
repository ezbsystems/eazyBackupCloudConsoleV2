<?php
add_hook('ClientAreaHeadOutput', 1, function($vars) {
    if (isset($_GET['m']) && $_GET['m'] == 'eazybackup') {
    $script = <<<EOD
<script>
jQuery(document).ready(function() {
    function updateUsageData() {
        // Loop through each row in the services table
        jQuery('#tableServicesList tbody tr').each(function() {
            var row = jQuery(this);
            var serviceId = row.data('serviceid');
            if (!serviceId) {
                console.log("No service id found for row");
                return;
            }
            jQuery.ajax({
                url: '/modules/addons/eazybackup/client_usage.php',
                data: { serviceid: serviceId },
                dataType: 'json',
                success: function(response) {
                    if (response.totalStorage !== undefined &&
                        response.msAccountCount !== undefined &&
                        response.deviceCount !== undefined &&
                        response.totalStorageGB !== undefined) {

                        // Calculate how close the usage is to the next tier.
                        var usageGB = parseFloat(response.totalStorageGB);
                        var remainder = usageGB % 1000; // remainder in GB
                        var colorClass = ""; // Default: no alert
                        var tooltip = "";

                        // If usage is between 900GB and 950GB into the tier, alert with yellow.
                        if (remainder >= 900 && remainder < 950) {
                            colorClass = "text-yellow-600";
                            tooltip = "Approaching next billing tier";
                        }
                        // If usage is between 950GB and 1000GB into the tier, alert with red.
                        else if (remainder >= 950 && remainder < 1000) {
                            colorClass = "text-red-700";
                            tooltip = "Almost reached next billing tier";
                        }

                        // Update the table cells:
                        // Column mapping:
                        // - Devices: column index 2
                        // - Total Storage: column index 3
                        // - MS 365 Users: column index 4
                        row.find('td').eq(2).html(response.deviceCount);

                        // Update Total Storage cell with color and a tooltip if needed.
                        var storageHtml = '<span class="' + colorClass + '" title="' + tooltip + '">' + response.totalStorage + '</span>';
                        row.find('td').eq(3).html(storageHtml);

                        row.find('td').eq(4).html(response.msAccountCount);
                    } else {
                        console.log("Invalid response for service id " + serviceId, response);
                    }
                },
                error: function(xhr, status, error) {
                    console.log("AJAX error for service id " + serviceId + ": " + error);
                }
            });
        });
    }
    // Call our function once the page is ready
    updateUsageData();
});
</script>
EOD;
    return $script;
}
});
