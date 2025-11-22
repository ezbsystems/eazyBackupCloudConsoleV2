<?php

use WHMCS\Database\Capsule;

function applyDiscount($service_id, $discount_per) {
    // Log the start of the process
    logActivity('Starting applyDiscount for service ID: ' . $service_id . ' with discount percentage: ' . $discount_per);

    // Find the service details in tblhosting
    $service = Capsule::table('tblhosting')->find($service_id);
    if (!$service) {
        logActivity('Invalid service ID provided: ' . $service_id);
        return ['status' => false, 'message' => 'Invalid service ID.'];
    }

    // Retrieve the current recurring amount from tblhosting
    $current_amount = $service->amount;

    // Log the current recurring amount
    logActivity('Current recurring amount for service ID ' . $service_id . ': ' . $current_amount);

    // Calculate the discount amount
    $discount_amount = ($current_amount * $discount_per) / 100;
    $new_price = $current_amount - $discount_amount;

    // Log discount calculation details
    logActivity('Discount details - Discount Percentage: ' . $discount_per . '%, Discount Amount: ' . $discount_amount . ', New Price: ' . $new_price);

    try {
        // Insert or update discount details in mod_rd_discountServices table
        Capsule::table('mod_rd_discountServices')->updateOrInsert(
            ['serviceid' => $service_id],
            [
                'beforeDisAmt' => $current_amount,
                'discount_per' => $discount_per,
                'nextduedate' => $service->nextduedate
            ]
        );
        logActivity('Discount details updated for service ID ' . $service_id);

        // Update the service amount in tblhosting
        Capsule::table('tblhosting')->where('id', $service_id)->update(['amount' => $new_price]);
        logActivity('Service amount updated for service ID ' . $service_id . ' to ' . $new_price);

        return ['status' => true];
    } catch (\Exception $e) {
        logActivity('Error applying discount for service ID ' . $service_id . ': ' . $e->getMessage());
        return ['status' => false, 'message' => 'Error applying discount.'];
    }
}




function removeDiscount($service_id) {
    $discount_data = Capsule::table('mod_rd_discountServices')->where('serviceid', $service_id)->first();
    if (!$discount_data) {
        return ['status' => false, 'message' => 'No discount found for the given service ID.'];
    }

    Capsule::table('tblhosting')->where('id', $service_id)->update(['amount' => $discount_data->beforeDisAmt]);
    Capsule::table('mod_rd_discountServices')->where('serviceid', $service_id)->delete();

    return ['status' => true];
}
