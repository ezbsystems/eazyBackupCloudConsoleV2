<?php

use WHMCS\Database\Capsule;


add_hook('InvoiceCreated', 911, function($vars) {

    try
    { 
        $invoiceID = $vars['invoiceid'];
       
        
        $postData = array(
            'invoiceid' => $invoiceID,
        );
    

        $invoice_detail = localAPI('GetInvoice', $postData);
        $userid = $invoice_detail['userid'];
        $currency_id = Capsule::table('tblclients')->where('id' , $userid)->value('currency');

       
        $invoiceDescriptions = [];
        foreach ($invoice_detail['items']['item'] as  $key => $invoiceitems) {
            $descriptions = "";
            if ($invoiceitems['type'] == 'Hosting') {
                $relid = $invoiceitems['relid'];
                $hidden_config = Capsule::table('tblhostingconfigoptions')
                    ->join('tblproductconfigoptions', 'tblproductconfigoptions.id', '=', 'tblhostingconfigoptions.configid')
                    ->join('tblproductconfigoptionssub', 'tblproductconfigoptionssub.configid', '=', 'tblproductconfigoptions.id')
                    ->where('tblproductconfigoptions.hidden', 1)
                    ->where('tblhostingconfigoptions.relid', $invoiceitems['relid'])
                    ->select('tblproductconfigoptionssub.optionname as suboption', 'tblproductconfigoptions.*', 'tblhostingconfigoptions.*')
                    ->get();
                foreach ($hidden_config as $key => $config) {
                    $billing_cycle = Capsule::table('tblhosting')->where('id' , $config->relid)->value('billingcycle');
                    
                    $config_option_price = Capsule::table('tblpricing')
                                           ->where('type' , 'configoptions')
                                           ->where('currency' , $currency_id)
                                           ->where('relid' , $config->configid)
                                           ->value(strtolower($billing_cycle));
                    $price_config = formatCurrency($config_option_price, $currency_id);

                    $descriptions .= "\n" . $config->optionname . ": " . $config->qty . " x 1 " . $config->suboption ." ". $price_config->__toString() ;

                }
                
               /* Apply DB updates */
                Capsule::table("tblinvoiceitems")->where(["relid"=> $invoiceitems['relid'], 'invoiceid' =>$invoiceID])->update(['description'=> $invoiceitems['description'].$descriptions]);

            }

        }
    } catch (Exception $e) {
        logActivity('Error at the time of invoice update'.$e->getMessage());
       
    }

});

