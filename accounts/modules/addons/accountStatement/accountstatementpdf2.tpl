<?php

ob_start();
use Illuminate\Database\Capsule\Manager as Capsule;

#get settings data
$getsettings = $accountsummary->accountStatement_config();
$logoCalign = $getsettings['logocenteralign'];
$logoLalign = $getsettings['logoleftalign'];
$logoRalign = $getsettings['logorightalign'];
$enableCustomColor = $getsettings['enableCustomColor'];

#Default color
$boxBackgroundColor = "#f2f6fa";
$boxTextColor = "#7dbb77";
$colorHrLine = "#7dbb77";
$tableHeadColor = "#7dbb77";
$tableRowEvenBackground = "#e8edf4";
$tableRowOddBackground = "#e3fbe3";
$tableTextColor = "#3a526a";

#get custom color scheme
if ($enableCustomColor == "on") {
    $customThemColor = $accountsummary->getThemeColor("v109");

    if (count((array) $customThemColor) != 0) {
        $getTemplate1Custom = json_decode($customThemColor->settings);

        $boxBackgroundColor = $getTemplate1Custom->color_box_bg_temp1;
        $boxTextColor = $getTemplate1Custom->color_box_txt_temp1;
        $colorHrLine = $getTemplate1Custom->color_fistline_temp1;
        $tableHeadColor = $getTemplate1Custom->color_tbl_head_temp1;
        $tableRowEvenBackground = $getTemplate1Custom->color_tbl_even_temp1;
        $tableRowOddBackground = $getTemplate1Custom->color_tbl_odd_temp1;
        $tableTextColor = $getTemplate1Custom->color_tbl_txt_temp1;
    }
}
//pdf v1.0.9

# tcpdf Font
$pdffont = $accountsummary->pdfFont();
$getPdfFont = $accountsummary->getPdfFont();

# tcpdf Page Size
$pdfpagesize = $accountsummary->pdfPaperSize();

//fotter
global $pagetext;
global $pageof;
$pagetext = $LANG['pdfpagetext'];
$pageof = $LANG['pdfpageof'];

class PDF extends TCPDF
{
    //Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        global $pagetext;
        global $pageof;

        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, $pagetext . $this->getAliasNumPage() . $pageof . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// create new PDF document
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

if ($getPdfFont == 'custom' && !empty($pdffont)) {
    $pdffont = TCPDF_FONTS::addTTFfont(__DIR__ . '/font/' . $pdffont, 'TrueTypeUnicode', '', 32);
}

$pdf->SetPrintHeader(false);

if ($pdfpagesize == '' || $pdfpagesize == 'A4') {
    $pdfpagesize = 'A4';
}


$pdf->AddPage("P", strtoupper($pdfpagesize));
$logUrlPath = Capsule::table("mod_account_summary_configuration")->where('setting', 'logoUrl')->value('value');
# Logo display
if ($logUrlPath != '') {
    if (!empty($logoCalign) && !empty($logoLalign) && !empty($logoRalign)) {
        $pdf->Image($logUrlPath, $logoLalign, $logoCalign, $logoRalign);
    } else {
        $pdf->Image($logUrlPath, 12, 2, 35, 27);
    }
} else {
    $logoFilename = 'placeholder.png';

    if (file_exists(ROOTDIR . '/assets/img/logo.png')) {
        $logoFilename = 'logo.png';
    } elseif (file_exists(ROOTDIR . '/assets/img/logo.jpg')) {
        $logoFilename = 'logo.jpg';
    }

    if (!empty($logoCalign) && !empty($logoLalign) && !empty($logoRalign)) {
        $pdf->Image(ROOTDIR . '/assets/img/' . $logoFilename, $logoLalign, $logoCalign, $logoRalign);
    } else {
        $pdf->Image(ROOTDIR . '/assets/img/' . $logoFilename, 12, 2, 35, 27);
    }
}

$clientsdetails = $accountsummary->get_clientdetails($userid);
$comapnyAddress = $accountsummary->get_companyAddress();
$comapnyAddres = '';
$accountsummary = new AccountSummary();

$currencyData = getCurrency($allStatements['userid']);

$logoEndposition = $pdf->GetY();

$pdf->SetY($logoEndposition + 10);

foreach ($comapnyAddress as $address) {
    $comapnyAddres .= trim($address) . '<br>';
}

$tblhtml = '
<table cellpadding="0" cellspacing="0">
<tr>
<td><br> <br> <br> <span style="color:#5c5c5c;">' . $comapnyAddres . '</span></td>
<td style="text-align:right; font-size:11px; color:#5c5c5c;margin=">                
<span style="font-size:20px; color:#333333;"><img src="' . ROOTDIR . '/modules/addons/accountStatement/img/phone-icon.png" width="15"> ' . $LANG['contactemp1'] . '</span><br>
<span style="font-size:13px; color:#2a2a2a;">' . html_entity_decode($clientsdetails["firstname"]) . ' ' . html_entity_decode($clientsdetails["lastname"]) . '</span><br>

' . html_entity_decode($clientsdetails["phonenumber"]) . '<br>

' . html_entity_decode($clientsdetails["email"]) . '<br><br>

<span style="font-size:13px; color:#2a2a2a;">' . $LANG['accountstatementpdftemp1'] . ' :</span> <br>

' . fromMySQLDate($startdate) . ' to ' . fromMySQLDate($enddate) . '<br><br>

</td>
</tr>
</table> 
';

$currencyData = getCurrency($allStatements['userid']);

$tblhtml .= '
<table cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td style="border:1px solid #e6e7ee; background-color:' . $boxBackgroundColor . '; width:22.5%;">
        <span><br></span>
            <span style="white-space: nowrap; font-size: 14px; color: ' . $boxTextColor . '; line-height:25px;">&nbsp; ' . formatCurrency($allStatements['totalpaid'], $currencyData['id']) . '</span><br><br>
            <span style="white-space: nowrap;font-size: 10px; color: #3a526a; line-height: 18px;"> &nbsp;' . $LANG['amountpaidpdf'] . ' </span>
        </td>    
        <td style="width:3%;">&nbsp;</td>
        <td style="border:1px solid #e6e7ee; background-color:' . $boxBackgroundColor . ';width:23%;">
        <span><br></span>
            <span style="white-space: nowrap; font-size: 14px; color: ' . $boxTextColor . '; line-height: 25px">&nbsp; ' .  formatCurrency($allStatements['totalCredit'], $currencyData['id']) . '</span><br><br>
            <span style="white-space: nowrap;font-size: 10px; color: #3a526a; line-height: 18px;"> &nbsp;' . $LANG['creditbalancepdf'] . '</span>
        </td>    
        <td style="width:3%;">&nbsp;</td>
        <td style="border:1px solid #e6e7ee; background-color:' . $boxBackgroundColor . ';width:23%;">
        <span><br></span>
            <span style="white-space: nowrap; font-size: 14px; color: ' . $boxTextColor . '; line-height: 25px">&nbsp; ' . formatCurrency($allStatements['totalOpeningBalance'], $currencyData['id']) . '</span><br>
            <span style="white-space: nowrap;font-size: 10px; color: #3a526a; line-height: 18px;"> &nbsp;' . $LANG['totalOpeningBalance'] . '</span><br>
            <span style="white-space: nowrap;font-size: 10px; color: #3a526a; line-height: 18px;"> &nbsp;' . $LANG['closingbalanceasontemp1'] . ' ' . fromMySQLDate($startdate) . '</span>
            <br>
        </td>

        <td style="width:3%;">&nbsp;</td>
        <td style="border:1px solid #e6e7ee; background-color:' . $boxBackgroundColor . ';width:23%;">
        <span><br></span>
            <span style="white-space: nowrap; font-size: 14px; color:' . $boxTextColor . '; line-height: 25px">&nbsp; ' . formatCurrency($allStatements['outstanding'], $currencyData['id']) . '</span><br>
            <span style="white-space: nowrap;font-size: 10px; color: #3a526a; line-height: 18px;">&nbsp;'. $LANG['outstandingunpaidinvoicetemp1'].'</span><br>
            <span style="white-space: nowrap;font-size: 10px; color: #3a526a; line-height: 18px;">&nbsp;' . $LANG['closingbalanceasontemp1'] . ' ' . fromMySQLDate($enddate) . '</span>
            <br>
        </td>
    </tr>

</table><br><br>

<div style="width:100%; height:10px; line-height:0; border-bottom:5px solid ' . $colorHrLine . ';"></div><br>
<table cellpadding="0" width="100%" cellspacing="0" style="font-size:10px;color:' . $tableTextColor . ';">
    <thead>
        <tr style="color:' . $tableHeadColor . '">
            <th width="20%" style="line-height:10px; font-size:10px;">' . $LANG['datetemp1'] . '<br></th>
            <th width="5%" style="line-height:10px; font-size:10px;"> <br></th>
            <th width="45%" style="line-height:10px; font-size:10px;">' . $LANG['transactiondetailtemp1'] . '<br></th>
            <th width="15%" style="line-height:10px; font-size:10px; ">' . $LANG['statustemp1'] . '<br></th>
            <th width="15%" style="line-height:10px; font-size:10px;">' . $LANG['amounttemp1'] . '<br></th>
        </tr>
    </thead>
<tbody>';

$sd = 1;

$fontClr = '';


// echo "<pre>";
// print_r($allStatements['enddate']);

// echo "stop";
// echo ($allStatements["enddate"]);
// die;

foreach ($allStatements['data'] as $dateTime => $statements) {
    foreach ($statements as $statement) {
        // $datepaid = $statement["datepaid"];
        // $date = date("Y-m-d", strtotime($datepaid));

        // if (strtotime($date) != strtotime($allStatements["enddate"]) && !isset($statement["invoicepaidcreated"])) {
        //     continue;
        // }
        if ($sd % 2 == '0') {
            $rowBg = "background-color:" . $tableRowEvenBackground . ";";
        } else {
            $rowBg = "background-color:" . $tableRowOddBackground . ";";
        }

        $img = 'paid.png';
        $status = $LANG['cr'];

        if ($statement['tAction'] == 'Refunded') {
            $img = 'refund.png';
            $status = $LANG['cr'] . '/' . $LANG['statusrefund'];
        } elseif ($statement['tAction'] == 'invoiceAdded' || $statement['tAction'] == 'creditRemoved') {
            $img = 'unpaid.png';
            $status = $LANG['dr'];
        }

        $plusSymble = '';
        if ($statement['tAction'] == 'creditAdded') {

            $plusSymble = "+ ";
        } elseif ($statement['tAction'] == 'creditRemoved') {

            $plusSymble = "- ";
        } else {
            $fontClr = "";
        }

        if (isset($statement['status']) && !empty($statement['status'])) {

            if ($statement['status'] == 'Unpaid') {
                $invoicestatus = $LANG['statusunpaid'];

            } elseif ($statement['status'] == 'Paid') {
                $invoicestatus = $LANG['statuspaid'];

            } elseif ($statement['status'] == 'Cancelled') {
                $invoicestatus = $LANG['statuscancelled'];

            } elseif ($statement['status'] == 'Refunded') {
                $invoicestatus = $LANG['statusrefund'];
            } elseif ($statement['status'] == 'Draft') {
                $invoicestatus = $LANG['statusdraft'];
            } elseif ($statement['status'] == 'Collections') {
                $invoicestatus = $LANG['statuscollections'];
            } elseif ($statement['status'] == 'Payment Pending') {
                $invoicestatus = $LANG['statuspaymentpending'];
            }
            $status = $status . '/' . $invoicestatus;

        }

        $tblhtml .= '<tr  nobr="true"><td style="' . $rowBg . ';" colspan="5"></td></tr>    
        <tr style="color:' . $tableTextColor . '"  nobr="true">';
        if (isset($statement["invoicepaidcreated"]) && $statement["invoicepaidcreated"] == "invoicepaidcreated") {
            $tblhtml .= '
            <td width="20%" style=" ' . $rowBg . ' ">  ' . fromMySQLDate($statement["date"]) . ' </td>
            <td width="5%" style="' . $rowBg . '"><img width="20px" src="' . ROOTDIR . '/modules/addons/accountStatement/img/unpaid.png"></td>
            <td width="45%" style="' . $rowBg . '">' . $statement['tDetail'] . '</td>
            <td width="15%" style="' . $rowBg . '">DR/Unpaid</td>
            <td width="15%" style=" ' . $rowBg . ' ; color:' . $fontClr . ';">' . $plusSymble . formatCurrency($statement['total'], $currencyData['id']) . '</td>';

        } else {
            $tblhtml .= '
                <td width="20%" style=" ' . $rowBg . ' ">  ' . fromMySQLDate($dateTime) . ' </td>
                <td width="5%" style="' . $rowBg . '"><img width="20px" src="' . ROOTDIR . '/modules/addons/accountStatement/img/' . $img . '"></td>
                <td width="45%" style="' . $rowBg . '">' . $statement['tDetail'] . '</td>
                <td width="15%" style="' . $rowBg . '"> ' . $status . '</td>
                <td width="15%" style=" ' . $rowBg . ';color:' . $fontClr . ';">' . $plusSymble . formatCurrency(!empty($statement['total']) ? $statement['total'] : $statement['tAmount'], $currencyData['id'])  . '</td>';
        }

        $tblhtml .= '</tr>
        <tr  nobr="true"><td style="' . $rowBg . ';" colspan="5"> </td></tr>';
        $sd++;

    }
}
// die('okok'); 

$tblhtml .= '</tbody></table>';
$pdf->SetFont('DejaVu Sans', '', 11, '', true);
$pdf->writeHTML($tblhtml, true, false, false, false, '');

$accountsummary->remove_attachments($userid);

$log_filename = 'Account_Statement_' . $userid . '_' . date('Y-m-d_h-i-s') . '.pdf';

global $attachments_dir;

$attachmentPath = [];

if (Capsule::Schema()->hasTable('tblfileassetsettings')) {
    $configtableAttachmentPath = Capsule::table("tblfileassetsettings")->join('tblstorageconfigurations', 'tblfileassetsettings.storageconfiguration_id', '=', 'tblstorageconfigurations.id')->select('tblstorageconfigurations.settings')->where('tblfileassetsettings.asset_type', 'email_attachments')->first();
    $attachmentPath = json_decode($configtableAttachmentPath->settings);
}

if (!empty($attachmentPath->local_path)) {
    $path_log = $attachmentPath->local_path . '/' . $log_filename;
} else {
    if (isset($attachments_dir)) {
        $attachments_dir = $attachments_dir . '/';
    } else {
        $basepath = realpath(__DIR__ . '/../../..');
        $attachments_dir = $basepath . "/attachments/";
    }
    $path_log = $attachments_dir . $log_filename;
}
$lnk = fopen($path_log, "w");

$aa = $pdf->Output($path_log, 'F');

if (!$aa) {
    $accountsummary->add_attachments($userid, $log_filename);
    $temp = true;
} else {
    $temp = false;
}

fwrite($lnk, ob_get_clean());
fclose($lnk);