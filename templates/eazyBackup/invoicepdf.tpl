<?php
use WHMCS\Database\Capsule;
# Logo
$logoFilename = 'placeholder.png';
if (file_exists(ROOTDIR . '/assets/img/logo.png')) {
    $logoFilename = 'logo.png';
} elseif (file_exists(ROOTDIR . '/assets/img/logo.jpg')) {
    $logoFilename = 'logo.jpg';
}
$pdf->Image(ROOTDIR . '/assets/img/' . $logoFilename, 15, 25, 35);


# Company Details
$pdf->SetXY(15, 25);
$pdf->SetFont($pdfFont, '', 13);
foreach ($companyaddress as $addressLine) {
    $pdf->Cell(180, 4, trim($addressLine), 0, 1, 'R');
    $pdf->SetFont($pdfFont, '', 9);
}
if ($taxCode) {
    $pdf->Cell(180, 4, $taxIdLabel . ': ' . trim($taxCode), 0, 1, 'R');
}
$pdf->Ln(5);

# Header Bar

/**
 * Invoice header
 *
 * You can optionally define a header/footer in a way that is repeated across page breaks.
 * For more information, see http://docs.whmcs.com/PDF_Invoice#Header.2FFooter
 */

$pdf->SetFont($pdfFont, 'B', 15);
$pdf->SetFillColor(239);
$pdf->Cell(0, 8, $pagetitle, 0, 1, 'L', '1');
$pdf->SetFont($pdfFont, '', 10);
$pdf->Cell(0, 6, Lang::trans('invoicesdatecreated') . ': ' . $datecreated, 0, 1, 'L', '1');
$pdf->Cell(0, 6, Lang::trans('invoicesdatedue') . ': ' . $duedate, 0, 1, 'L', '1');
$pdf->Ln(10);

$startpage = $pdf->GetPage();

# Clients Details
$addressypos = $pdf->GetY();
$pdf->SetFont($pdfFont, 'B', 10);
$pdf->Cell(0, 4, Lang::trans('invoicesinvoicedto'), 0, 1);
$pdf->SetFont($pdfFont, '', 9);
if ($clientsdetails["companyname"]) {
    $pdf->Cell(0, 4, $clientsdetails["companyname"], 0, 1, 'L');
    $pdf->Cell(0, 4, Lang::trans('invoicesattn') . ': ' . $clientsdetails["firstname"] . ' ' . $clientsdetails["lastname"], 0, 1, 'L');
} else {
    $pdf->Cell(0, 4, $clientsdetails["firstname"] . " " . $clientsdetails["lastname"], 0, 1, 'L');
}
$pdf->Cell(0, 4, $clientsdetails["address1"], 0, 1, 'L');
if ($clientsdetails["address2"]) {
    $pdf->Cell(0, 4, $clientsdetails["address2"], 0, 1, 'L');
}
$pdf->Cell(0, 4, $clientsdetails["city"] . ", " . $clientsdetails["state"] . ", " . $clientsdetails["postcode"], 0, 1, 'L');
$pdf->Cell(0, 4, $clientsdetails["country"], 0, 1, 'L');
if (array_key_exists('tax_id', $clientsdetails) && $clientsdetails['tax_id']) {
    $pdf->Cell(0, 4, $taxIdLabel . ': ' . $clientsdetails['tax_id'], 0, 1, 'L');
}
if ($customfields) {
    $pdf->Ln();
    foreach ($customfields as $customfield) {
        $pdf->Cell(0, 4, $customfield['fieldname'] . ': ' . $customfield['value'], 0, 1, 'L');
    }
}
$pdf->Ln(10);

# Invoice Items
$tblhtml = '<table width="100%" bgcolor="#ccc" cellspacing="1" cellpadding="2" border="0">
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;text-align:center;">
        <td width="80%">' . Lang::trans('invoicesdescription') . '</td>
        <td width="20%">' . Lang::trans('quotelinetotal') . '</td>
    </tr>';
foreach ($invoiceitems as $item) {
    if ($item['type'] === 'Upgrade') {
        if(!empty($item['relid'])) {
            $relation_id = $item['relid'];
            $relid = Capsule::table('tblupgrades')->where('id' , $relation_id )->where('type' , 'configoptions' )->value('relid');
            if(!empty($relid)){
                $username = Capsule::table('tblhosting')->where('id' , $relid)->value('username');
                if(!empty($username)){
                    $serviceuserName = $username;
                }
            }
        }
    }
        $text = preg_replace("/\:\s0\sx\s/","+",$item['description']);
        $text = preg_replace("/.*\+/","+",$text);
        $text = preg_replace("/\+.*(\n?)/","",$text);
        $item['description'] = $text;
    if ($item['type'] === 'Hosting') {
        $hostingItemUsername = null; 
        $serviceDetails = null; 
        
        $apiParams = ['serviceid' => $item['relid']];
        // Add clientid if available in $item, as GetClientsProducts can use it.
        // However, to minimize deviation from original, let's first ensure serviceid call is safe.
        // if (isset($item['userid']) && $item['userid']) {
        //    $apiParams['clientid'] = $item['userid'];
        // }
        $serviceData = localAPI('GetClientsProducts', $apiParams);

        if (is_array($serviceData) && 
            isset($serviceData['result']) && $serviceData['result'] === 'success' &&
            isset($serviceData['totalresults']) && $serviceData['totalresults'] > 0 &&
            isset($serviceData['products']['product'][0]) && is_array($serviceData['products']['product'][0])) {
            
            $serviceDetails = $serviceData['products']['product'][0];
        }
        // No specific error logging here as WHMCS generally logs API errors if they occur.

        if (!empty($serviceDetails)) { // Check if $serviceDetails was populated
            if (!empty($serviceDetails['username'])) {
                $hostingItemUsername = $serviceDetails['username'];
            } elseif (!empty($serviceDetails['domain'])) {
                $hostingItemUsername = $serviceDetails['domain']; // Fallback to domain
            }
        }

        if (!empty($hostingItemUsername)) {
            // Ensure $item['description'] is a string before appending
            if (!isset($item['description']) || !is_string($item['description'])) {
                 $item['description'] = strval(isset($item['description']) ? $item['description'] : ''); // Ensure it's a string
            }
            $item["description"] .= "\nUsername: " . $hostingItemUsername;
        }
    }
    $tblhtml .= '
    <tr bgcolor="#fff">
        <td align="left">' . nl2br($item['description']) . '<br /> '.(!empty($serviceuserName)? "Username: ".$serviceuserName : false).'</td>
        <td align="center">' . $item['amount'] . '</td>
    </tr>';
}
$tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . Lang::trans('invoicessubtotal') . '</td>
        <td align="center">' . $subtotal . '</td>
    </tr>';
if ($taxname) {
    $tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . $taxrate . '% ' . $taxname . '</td>
        <td align="center">' . $tax . '</td>
    </tr>';
}
if ($taxname2) {
    $tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . $taxrate2 . '% ' . $taxname2 . '</td>
        <td align="center">' . $tax2 . '</td>
    </tr>';
}
$tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . Lang::trans('invoicescredit') . '</td>
        <td align="center">' . $credit . '</td>
    </tr>
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . Lang::trans('invoicestotal') . '</td>
        <td align="center">' . $total . '</td>
    </tr>
</table>';

$pdf->writeHTML($tblhtml, true, false, false, false, '');

$pdf->Ln(5);

# Transactions
$pdf->SetFont($pdfFont, 'B', 12);
$pdf->Cell(0, 4, Lang::trans('invoicestransactions'), 0, 1);

$pdf->Ln(5);

$pdf->SetFont($pdfFont, '', 9);

$tblhtml = '<table width="100%" bgcolor="#ccc" cellspacing="1" cellpadding="2" border="0">
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;text-align:center;">
        <td width="25%">' . Lang::trans('invoicestransdate') . '</td>
        <td width="25%">' . Lang::trans('invoicestransgateway') . '</td>
        <td width="30%">' . Lang::trans('invoicestransid') . '</td>
        <td width="20%">' . Lang::trans('invoicestransamount') . '</td>
    </tr>';

if (!count($transactions)) {
    $tblhtml .= '
    <tr bgcolor="#fff">
        <td colspan="4" align="center">' . Lang::trans('invoicestransnonefound') . '</td>
    </tr>';
} else {
    foreach ($transactions AS $trans) {
        $tblhtml .= '
        <tr bgcolor="#fff">
            <td align="center">' . $trans['date'] . '</td>
            <td align="center">' . $trans['gateway'] . '</td>
            <td align="center">' . $trans['transid'] . '</td>
            <td align="center">' . $trans['amount'] . '</td>
        </tr>';
    }
}
$tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td colspan="3" align="right">' . Lang::trans('invoicesbalance') . '</td>
        <td align="center">' . $balance . '</td>
    </tr>
</table>';

$pdf->writeHTML($tblhtml, true, false, false, false, '');

# Custom text for notes
$customText = "For annual plans, if your usage exceeds the allocated limits at any point during the year, we will issue a prorated invoice to cover the additional usage. To avoid additional charges, you can set limits on Storage and Devices through your client area or by contacting us.";

# Notes
if ($notes) {
    $notes .= "\n" . $customText; // Append custom text to existing notes
    $pdf->Ln(5);
    $pdf->SetFont($pdfFont, '', 8);
    $pdf->MultiCell(170, 5, Lang::trans('invoicesnotes') . ': ' . $notes, 0, 'L');
} else {
    // If there are no existing notes, just print the custom text
    $pdf->Ln(5);
    $pdf->SetFont($pdfFont, '', 8);
    $pdf->MultiCell(170, 5, Lang::trans('invoicesnotes') . ': ' . $customText);
}
/**
 * Invoice footer
 */
