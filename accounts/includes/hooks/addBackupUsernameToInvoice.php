<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

add_hook('ClientAreaPage', 1, function ($vars) {
    // We only want to modify the invoice detail page (viewinvoice.php)
    if (($vars['filename'] ?? '') !== 'viewinvoice') {
        return;
    }
    
    // Get the invoice ID from the URL
    $invoiceId = $_GET['id'] ?? 0;
    if (!$invoiceId) {
        return;
    }
    
    // Fetch all invoice items for this invoice
    $invoiceItems = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->get();
    
    // Prepare an array mapping invoice item IDs to backup usernames
    $backupUsernames = [];
    
    foreach ($invoiceItems as $item) {
        // Typically, service-related items have a type like "Hosting" (or check your own invoice item types)
        // and the "relid" field stores the service ID.
        if ($item->type === 'Hosting' && $item->relid) {
            // Retrieve the service row – similar to what eazybackup_clientarea does
            $service = Capsule::table('tblhosting')
                ->where('id', $item->relid)
                ->where('userid', (int) (isset($_SESSION['uid']) ? $_SESSION['uid'] : 0))
                ->select('username')
                ->first();
            
            if ($service && !empty($service->username)) {
                // Map the invoice item id to the backup username
                $backupUsernames[$item->id] = $service->username;
            }
        }
    }
    
    // Return the variable so Smarty can access it in the template.
    // We’ll call this variable "backupUsernames"
    return [
        'backupUsernames' => $backupUsernames
    ];
});
