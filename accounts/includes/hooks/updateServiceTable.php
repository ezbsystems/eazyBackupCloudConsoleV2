<?php
add_hook('AdminAreaClientSummaryPage', 1, function($vars) {
    $script = <<<EOD
<script>
jQuery(document).ready(function() {
    function updateServiceRows() {
        jQuery('#summaryServices tbody tr').each(function() {
            var row = jQuery(this);
            // Get the service link from the second cell (index 1)
            var serviceLink = row.find('td').eq(1).find('a').attr('href');
            if (!serviceLink) {
                console.log("No service link found in row");
                return;
            }
            // Use a regex that specifically looks for '?id=' or '&id=' to extract the service id
            var serviceIdMatch = serviceLink.match(/[?&]id=(\\d+)/);
            if (serviceIdMatch && serviceIdMatch[1]) {
                var serviceId = serviceIdMatch[1];
                console.log("Fetching username for service id: " + serviceId);
                // Call our custom endpoint to get the username
                jQuery.ajax({
                    url: '/modules/addons/eazybackup/get_service_username.php', // root-relative URL
                    data: { serviceid: serviceId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.username) {
                            // Update the product/service cell (third column, index 2)
                            var productCell = row.find('td').eq(2);
                            // Assume current text is in format "ProductName - No Domain" or similar
                            var currentText = productCell.text();
                            var parts = currentText.split(" - ");
                            var productName = parts[0];
                            console.log("Updating service id " + serviceId + " with username: " + response.username);
                            productCell.html(productName + ' - <strong>' + response.username + '</strong>');
                        } else {
                            console.log("No username returned for service id " + serviceId);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log("AJAX error for service id " + serviceId + ": " + error);
                    }
                });
            } else {
                console.log("Could not extract service id from link: " + serviceLink);
            }
        });
    }
    
    // Bind our update function to the DataTable's draw event
    jQuery(document).on('draw.dt', '#summaryServices', function() {
        console.log("DataTable draw event triggered");
        updateServiceRows();
    });
    
    // Also call updateServiceRows immediately in case the table is already rendered
    updateServiceRows();
});
</script>
EOD;
    return $script;
});
