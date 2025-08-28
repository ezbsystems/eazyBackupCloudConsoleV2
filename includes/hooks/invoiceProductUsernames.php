<?php
use WHMCS\Database\Capsule;

add_hook('EmailPreSend', 1, function($vars) {
    // Check if the email being sent is either "Invoice Created" or "Credit Card Payment Confirmation"
    if (in_array($vars['messagename'], ['Invoice Created', 'Credit Card Payment Confirmation'])) {
        $invoiceId = (int) $vars['relid'];
        $usernames = [];

        // Retrieve all invoice items for the invoice
        $invoiceItems = Capsule::table('tblinvoiceitems')
            ->where('invoiceid', $invoiceId)
            ->get();

        // Loop through each invoice item (assuming product items have type "Hosting")
        foreach ($invoiceItems as $item) {
            if ($item->type === 'Hosting' && $item->relid) {
                // Fetch the hosting record for this service
                $service = Capsule::table('tblhosting')
                    ->where('id', $item->relid)
                    ->first();
                if ($service && $service->username) {
                    $usernames[] = $service->username;
                }
            }
        }
        
        // Remove duplicates and create a comma-separated list
        $usernames = array_unique($usernames);
        $usernamesString = implode(', ', $usernames);

        // Return the merge field, which can be referenced as {$product_usernames} in the email template.
        return [
            'product_usernames' => $usernamesString,
        ];
    }
});
