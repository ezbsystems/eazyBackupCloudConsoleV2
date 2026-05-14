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
            $payMethod = null;

            $invoiceDeatils = WHMCS\Billing\Invoice::find($invoice['id']);
            if (!$invoiceDeatils) {
                continue;
            }

            $invoiceDesc = (string) Capsule::table('tblinvoiceitems')->where('invoiceid',$invoice['id'])->value('description');
            $userName = wgs_getUserName($invoice['id']);

            if($invoice['rawstatus'] == 'paid')
            {
                $payMethodId = $invoiceDeatils->paymethodid;

                if ($payMethodId)
                {
                    $payMethod = WHMCS\Payment\PayMethod\Model::find($payMethodId);
                }

                if ($payMethod)
                {
                    $gatewayName = isset($gatewaysarray[$invoiceDeatils->paymentmethod]) ? $gatewaysarray[$invoiceDeatils->paymentmethod] : $invoiceDeatils->paymentmethod;
                    $desc = $gatewayName.' ('.$payMethod->payment->getDisplayName().')';
                }
                else
                {
                    $desc = isset($gatewaysarray[$invoiceDeatils->paymentmethod]) ? $gatewaysarray[$invoiceDeatils->paymentmethod] : $invoiceDeatils->paymentmethod;
                }
                $desc .= '<p style="display:none;">Invoice No :- '.$invoice['id'].'</p>';
                $desc .= '<p style="display:none;">'.$invoiceDesc.'</p>';

                if(!empty($userName))
                {
                    $desc .= '<span style="display:none;">'.$userName.'</span>';

                }

                $vars['invoices'][$key]['custDescpaid'] = $desc;
                $displayPaidDate = wgs_formatClientInvoiceDate($invoiceDeatils->datepaid, $invoiceDeatils->date);
                if ($displayPaidDate !== '') {
                    $vars['invoices'][$key]['datepaid'] = $displayPaidDate;
                }
            }
            // else
            // {
                if(!empty($invoiceDesc))
                {
                    $result = wgs_buildInvoicePeriodLabel($invoiceDesc, $invoiceDeatils->date);
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

function wgs_buildInvoicePeriodLabel($invoiceDesc, $fallbackDate)
{
    $period = wgs_extractInvoicePeriodLabel($invoiceDesc);
    if ($period !== '') {
        return $period;
    }

    if (wgs_isUsableMySQLDate($fallbackDate)) {
        return date('F jS\, Y', strtotime($fallbackDate));
    }

    return '';
}

function wgs_extractInvoicePeriodLabel($invoiceDesc)
{
    if (!preg_match_all('#\(([^()]*)\)#', (string) $invoiceDesc, $matches)) {
        return '';
    }

    foreach ($matches[1] as $candidate) {
        if (!preg_match('/(\d{1,4}[\/.\-]\d{1,2}[\/.\-]\d{1,4})\s*(?:-|\x{2013}|\x{2014}|to)\s*(\d{1,4}[\/.\-]\d{1,2}[\/.\-]\d{1,4})/iu', $candidate, $dateMatch)) {
            continue;
        }

        $startTs = wgs_parseInvoicePeriodDate($dateMatch[1]);
        $endTs = wgs_parseInvoicePeriodDate($dateMatch[2]);

        if ($startTs === false) {
            continue;
        }

        $startLabel = date('F Y', $startTs);
        if ($endTs !== false) {
            $endLabel = date('F Y', $endTs);
            if ($endLabel !== $startLabel) {
                return $startLabel.' - '.$endLabel;
            }
        }

        return $startLabel;
    }

    return '';
}

function wgs_parseInvoicePeriodDate($date)
{
    $date = trim((string) $date);
    if ($date === '') {
        return false;
    }

    if (function_exists('toMySQLDate')) {
        $mysqlDate = toMySQLDate($date);
        if (wgs_isUsableMySQLDate($mysqlDate)) {
            return strtotime($mysqlDate);
        }
    }

    foreach (array('Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y') as $format) {
        $dt = DateTime::createFromFormat('!'.$format, $date);
        $errors = DateTime::getLastErrors();
        if ($dt instanceof DateTime && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))) {
            return $dt->getTimestamp();
        }
    }

    $ts = strtotime($date);
    return $ts !== false && $ts > 0 ? $ts : false;
}

function wgs_formatClientInvoiceDate($date, $fallbackDate)
{
    $sourceDate = wgs_isUsableMySQLDate($date) ? $date : $fallbackDate;
    if (!wgs_isUsableMySQLDate($sourceDate)) {
        return '';
    }

    return fromMySQLDate($sourceDate, 0, 1);
}

function wgs_isUsableMySQLDate($date)
{
    $date = trim((string) $date);
    if ($date === '' || strpos($date, '0000-00-00') === 0) {
        return false;
    }

    $ts = strtotime($date);
    return $ts !== false && $ts > 0;
}

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