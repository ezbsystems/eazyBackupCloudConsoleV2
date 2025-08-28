<?php

use WHMCS\Database\Capsule;

add_hook('ClientAreaPage', 1, function($vars)
{
    
    if($vars['templatefile'] == 'clientareainvoices')
    {
        App::load_function('gateway');

        $gatewaysarray = getGatewaysArray();

        foreach ($vars['invoices'] as $key => $invoice)
        {
            $desc = '';

            $invoiceDeatils = WHMCS\Billing\Invoice::find($invoice['id']);

            if($invoice['rawstatus'] == 'paid')
            {
                $payMethodId = $invoiceDeatils->paymethodid;

                if ($payMethodId)
                {
                    $payMethod = WHMCS\Payment\PayMethod\Model::find($payMethodId);
                }

                if ($payMethod)
                {
                    $desc = $gatewaysarray[$invoiceDeatils->paymentmethod].' ('.$payMethod->payment->getDisplayName().')';
                }
                else
                {
                    $desc = $gatewaysarray[$invoiceDeatils->paymentmethod];
                }
                $invoiceDesc = Capsule::table('tblinvoiceitems')->where('invoiceid',$invoice['id'])->value('description');
                $userName = wgs_getUserName($invoice['id']);
                $desc .= '<p style="display:none;">Invoice No :- '.$invoice['id'].'</p>';
                $desc .= '<p style="display:none;">'.$invoiceDesc.'</p>';

                if(!empty($userName))
                {
                    $desc .= '<span style="display:none;">'.$userName.'</span>';

                }

                $vars['invoices'][$key]['custDescpaid'] = $desc;
                $vars['invoices'][$key]['datepaid'] = fromMySQLDate($invoiceDeatils->datepaid, 0,1);
            }
            // else
            // {
                $invoiceDesc = Capsule::table('tblinvoiceitems')->where('invoiceid',$invoice['id'])->value('description');
                
                if(!empty($invoiceDesc))
                {
                    preg_match('#\((.*?)\)#', $invoiceDesc, $match);
                    
                    $result = '';
                    
                    if(!empty($match[1]))
                    {
                        $data = $match[1];
                        $exdata = explode(" ",$data);
                   
                        if(!empty($exdata[0]))
                        {
                            $result .= date('F Y',strtotime(toMySQLDate($exdata[0])));
                        }
                    
                        if(!empty($exdata[2]))
                        {
                            $result .= ' - '.date('F Y',strtotime(toMySQLDate($exdata[2])));
                        }
                    }
                    else
                    {
                        $result .= date('F jS\, Y',strtotime($invoiceDeatils->date));
                    }
                    
                    
                   
                    $userName = wgs_getUserName($invoice['id']);
                    $result .= '<p style="display:none;">Invoice No :- '.$invoice['id'].'</p>';
                    $result .= '<p style="display:none;">'.$invoiceDesc.'</p>';

                    if(!empty($userName))
                    {
                        $result .= '<span style="display:none;">'.$userName.'</span>';

                    }

                    $vars['invoices'][$key]['custDesc'] = 'Invoice for '.$result;
                }
            //}

        }
        return $vars;

    }
});

function wgs_getUserName($invoice_id)
{
    $username = '';

    if(!empty($invoice_id))
    {
        $itemDetails = Capsule::table('tblinvoiceitems')->where('invoiceid',$invoice_id )->get();

        foreach($itemDetails as $itemDetail)
        {
            if($itemDetail->type == 'Hosting')
            {
                $serviceId = $itemDetail->relid;
                break;
            }
            else if($itemDetail->type == 'Upgrade')
            {
                $serviceId = Capsule::table('tblupgrades')->where('id',$itemDetail->relid )->where('type','configoptions' )->value('relid');
                break;
            }

        }

        if(!empty($serviceId))
        {
            $username = Capsule::table('tblhosting')->where('id',$serviceId)->value('username');
        }
    }


    return $username;
}