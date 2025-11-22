<?php
// die('hh');  
ob_start();
use Illuminate\Database\Capsule\Manager as Capsule;
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

include 'countrieslist.php';
#get settings data
$getsettings = $accountsummary->accountStatement_config();
$logoCalign = $getsettings['logocenteralign'];
$logoLalign = $getsettings['logoleftalign'];
$logoRalign = $getsettings['logorightalign'];
$enableCustomColor = $getsettings['enableCustomColor'];

#Default color
$headerBackgroundColor = "#0895d7";
$subHeaderBackgroundColor = "#edf9fd";
$borderColor = "#b5cbd2";

#get custom color scheme
if ($enableCustomColor == "on") {
    $customThemColor = $accountsummary->getThemeColor("v203");
    if (count((array) $customThemColor) != 0) {
        $getTemplateCustom = json_decode($customThemColor->settings);

        $headerBackgroundColor = $getTemplateCustom->color_head_bg_temp2;
        $subHeaderBackgroundColor = $getTemplateCustom->color_subhead_bg_temp2;
        $borderColor = $getTemplateCustom->color_border_temp2;
    }
}

//pdf v2.0.3

# tcpdf Font

$pdffont = $accountsummary->pdfFont();

$getPdfFont = $accountsummary->getPdfFont();

# tcpdf Page Size

$pdfpagesize = $accountsummary->pdfPaperSize();

$gatewayData = Capsule::table("tblpaymentgateways")->where("gateway", 'mailin')->where('setting', 'instructions')->first();

if (count((array) $gatewayData) > 0) {
    $gatewayParams = getGatewayVariables("mailin");
}

if (count((array) $gatewayData) == 0) {
    $gatewayData = Capsule::table("tblpaymentgateways")->where("gateway", 'banktransfer')->where('setting', 'instructions')->first();
    if (count((array) $gatewayData) > 0) {
        $gatewayParams = getGatewayVariables("banktransfer");
    }
}

$bankData2 = $gatewayParams['instructions'];
$bankData3 = explode("\n", $bankData2);
$bankData = implode("<br>", $bankData3);
//$bankData = $CONFIG['InvoicePayTo'];
$footerEmailquery = Capsule::table("mod_account_summary_configuration")->where('setting', 'footerEmail')->select('value')->get();
$footerEmail = $footerEmailquery[0]->value;

$bankDetailSetting = Capsule::table("mod_account_summary_configuration")->where('setting', 'displayBankDetails')->value('value');
$logUrlPath = Capsule::table("mod_account_summary_configuration")->where('setting', 'logoUrl')->value('value');


//footer
global $pagetext;

global $pageof;

$pagetext = $LANG['pdfpagetext'];
$pageof = $LANG['pdfpageof'];

class MYPDF extends TCPDF
{
    // Page footer
    public function Footer()
    {
        global $pagetext;
        global $pageof;

        // Position at 15 mm from bottom
        $this->SetY(-15);
        $this->SetX(10);
        // Set font
        $this->SetFont('helvetica', 'I', 8);

        // Page number

        $this->Cell(0, 10, $pagetext . $this->getAliasNumPage() . $pageof . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// create new PDF document

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

if ($getPdfFont == 'custom' && !empty($pdffont)) {
    $pdffont = TCPDF_FONTS::addTTFfont(__DIR__ . '/font/' . $pdffont, 'TrueTypeUnicode', '', 32);
}

$pdf->SetMargins(0, 10, 0, true);
$pdf->SetPrintHeader(false);

if ($pdfpagesize == '') {
    $pdfpagesize = 'A3';
}

if ($pdfpagesize == 'A3') {
    $fontSizeHd = '20px';
    $boxWidth = '148';
    $trskBoxWidth = '421';
    $fontSize = '10px';
} elseif ($pdfpagesize == 'Letter') {
    $fontSizeHd = '15px';
    $boxWidth = '100';
    $trskBoxWidth = '283';
    $fontSize = '8px';
} else {
    $fontSizeHd = '15px';
    $boxWidth = '100';
    $trskBoxWidth = '273';
    $fontSize = '8px';
}

$pdf->AddPage("P", strtoupper($pdfpagesize));


# Logo display
if ($logUrlPath != '') {
    if (!empty($logoCalign) && !empty($logoLalign) && !empty($logoRalign)) {
        $pdf->Image($logUrlPath, $logoLalign, $logoCalign, $logoRalign);
    } else {
        $pdf->Image($logUrlPath, 16, 5, 30, 25);
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
        $pdf->Image(ROOTDIR . '/assets/img/' . $logoFilename, 16, 5, 30, 29);
    }
}

$clientsdetails = $accountsummary->get_clientdetails($userid);

if ($jsonArraycountries[$clientsdetails["country"]]["name"] == "Norway") {
    $clientCity = $clientsdetails["postcode"] . ' ' . $clientsdetails["city"];
} else {
    $clientCity = $clientsdetails["city"] . ' ' . $clientsdetails["postcode"];
}

$comapnyAddress = $accountsummary->get_companyAddress();

$comapnyAddres = '';

$accountsummary = new AccountSummary();

foreach ($comapnyAddress as $address) {
    $comapnyAddres .= trim($address) . '<br>';
}

$currencyData = getCurrency($allStatements['userid']);

$tblhtml = '    
    <table cellpadding="0" cellspacing="0">

        <tr>
            <td colspan="3" style="height:38px;">
            </td>
        </tr>
        <tr>
            <td style="width:50px;">&nbsp;</td>
            <td style="width:222px; font-size:10px;"><br><br><br><br><span style="color:#5c5c5c; ">' . $comapnyAddres . '</span></td>
            <td style="width:317px;" valign="top" > 
                <table cellpadding="0" cellspacing="0" >
                    <tr >
                        <td style="width:317px;color:#ffffff;background-color:' . $headerBackgroundColor . ';line-height:50px;text-align:left;font-size:22px;">
                            &nbsp; ' . $LANG['accountstatementpdf'] . '
                        </td>
                    </tr>
                    <tr>
                        <td style="width:317px;color:#000;background-color:' . $subHeaderBackgroundColor . ';font-size:11px; ">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td colspan="4" style="height:15px;">
                                        &nbsp;
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:22px; text-align:right;">
                                         &nbsp;<img src="' . ROOTDIR . '/modules/addons/accountStatement/img/calendar.png" style="width:16px;">
                                    </td>

                                   <td style="width:110px;">                                     
                                        ' . $LANG['accountstatementpdfdate'] . '                                        
                                    </td>

                                    <td style="width:22px; text-align:right;">
                                        <img src="' . ROOTDIR . '/modules/addons/accountStatement/img/calendar.png" style="width:16px;">
                                    </td>
                                    <td style="width:166px;">                                       
                                       ' . $LANG['accountstatementpdfperiod'] . '                                  
                                    </td>

                                </tr>
                                <tr>
                                    <td style="width:22px;">
                                        &nbsp;
                                    </td>
                                    <td style="width:110px;color:#7f858b;font-size:10px; line-height:25px;">                                                                       
                                        ' . fromMySQLDate(date('d-m-Y')) . '
                                    </td>

                                    <td style="width:16px;">
                                        &nbsp;
                                    </td>
                                    <td style="width:196px;color:#7f858b;font-size:10px; line-height:25px;">

                                        ' . fromMySQLDate($startdate) . ' - ' . fromMySQLDate($enddate) . '
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4" style="height:15px;">
                                        &nbsp;
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="3" style="height:20px;"></td>
        </tr>
        <tr>
            <td style="width:45px;">&nbsp;</td>
            <td style="width:220px;"><span style="color:#000; font-size:10px;">
                <b>' . $LANG['customeridpdf'] . ': #' . $userid . '</b> <br>
                ' . $clientsdetails['companyname'] . ' <br>
                ' . $clientsdetails['firstname'] . ' ' . $clientsdetails['lastname'] . ' <br>
                ' . $clientsdetails['address1'] . ' <br>
                ' . $clientsdetails['address2'] . ' <br>
                ' . $clientCity . ' <br>
                ' . $jsonArraycountries[$clientsdetails['country']]['name'] . ' <br>
            </span></td>
            <td style="width:100%"> 
                <br><br>
                <table cellpadding="12" cellspacing="0">                   
                    <tr>
                        <td style="width:10px;">
                        </td>
                        <td style="border:1px solid ' . $borderColor . ';  font-size:11px; line-height:17px; text-align:left;">   
                           ' . $LANG['amountpaidpdf'] . ':&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($allStatements['totalDebit'], $currencyData['id'])) . '</b> <br>
                            ' . $LANG['closingbalancetemp1'] . ':&nbsp;&nbsp;&nbsp;&nbsp;' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($allStatements['totalClosingBalanceHead'], $currencyData['id'])) . '
                            <br>
                            ' . $LANG['totalOpeningBalance'] . ':&nbsp;&nbsp;' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($allStatements['totalOpeningBalance'], $currencyData['id'])) . '
                            <br>
                            ' . $LANG['creditbalancepdf'] . ': &nbsp;&nbsp;&nbsp;&nbsp; ' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($allStatements['creditbalance'], $currencyData['id'])) . '
                        </td>                        
                    </tr>
                </table>
            </td>
        </tr>        
    </table> 
    <table cellpadding="0" cellspacing="0">
        <tr>
            <td colspan="3" style="height:30px;">&nbsp;</td>
        </tr>    
        <tr>
            <td style="width:45px;">
                &nbsp;
            </td>
            <td style="width:92%;">
                <table cellpadding="5" width="97%" cellspacing="0" style="font-size:11px;" >
                <tr  >
                    <th style="border-bottom:1px solid ' . $borderColor . ';" width="13%"><b>' . $LANG['columndatepdf'] . '</b></th>
                    <th style="border-bottom:1px solid ' . $borderColor . ';" width="25%"><b>' . $LANG['columnitemdescriptionpdf'] . '</b></th>
                    <th style="border-bottom:1px solid ' . $borderColor . ';" width="16%"><b>' . $LANG['columndebitpdf'] . '</b></th>
                    <th style="border-bottom:1px solid ' . $borderColor . ';" width="17%"><b>' . $LANG['columncreditpdf'] . '</b></th>
                    <th style="border-bottom:1px solid ' . $borderColor . ';" width="13%"><b>' . $LANG['columnstatuspdf'] . '</b></th>
                    <th style="border-bottom:1px solid ' . $borderColor . ';" width="17%"><b>' . $LANG['columnbalancepdf'] . '</b></th>
                </tr>';
$sd = 1;

$totalOpeningBalance = $allStatements["totalOpeningBalance"];




// $totalOpeningBalance = 0;
foreach ($allStatements['data'] as $dateTime => $statements) {
    foreach ($statements as $statement) {


        // echo "<pre>";
        // print_r($statement);
        // die;

        // if (strtotime($statement["datepaid"]) > strtotime($allStatements["enddate"]) && !isset($statement["invoicepaidcreated"])) {
        //     continue;
        // } 


        if ($statement['tDetail'] == 'invoicecreated') {
            if (!empty($statement['invoicenum'])) {
                $tDetail = $LANG['descriptioninvoicecreatednopdf'] . ' #' . $statement['invoicenum'];
            } else {
                $tDetail = $LANG['descriptioninvoicecreatednopdf'] . ' #' . $statement['id'];
            }
        } elseif ($statement['tDetail'] == 'refunded') {
            $tDetail = $LANG['columnitemstatusrefund'] . $statement['description'];
        } elseif ($statement['tDetail'] == 'invoicepaid') {

            if (isset($statement["invoicepaidcreated"]) && $statement["invoicepaidcreated"] == "invoicepaidcreated") {
                if (!empty($statement['invoicenum'])) {
                    $tDetail = $LANG['descriptioninvoicecreatednopdf'] . ' #' . $statement['invoicenum'];
                } else {
                    $tDetail = $LANG['descriptioninvoicecreatednopdf'] . ' #' . $statement['id'];
                }
            } else {
                if ($statement["subtotal"] == "0.00") {
                    $tDetail = $LANG['descriptioninvoicepaidnopdf'] . ' #' . $statement['id'] . ' ' . $LANG['transactionidtemp2'] . ' #' . $statement['transid'];
                } else {
                    $tDetail = $LANG['descriptioninvoicepaidnopdf'] . ' #' . $statement['invoiceid'] . ' ' . $LANG['transactionidtemp2'] . ' #' . $statement['transid'];
                }
            }
        } elseif ($statement['tDetail'] == 'creditapplied') {
            $tDetail = $LANG['creditappliedinvoicetemp2'] . ' #' . substr($statement['description'], strpos($statement['description'], "#") + 1);
        } elseif ($statement['tDetail'] == 'creditadded' && $statement['tAction'] == 'creditadded') {
            $tDetail = $LANG['columnitemstatuscreditadd'] . ' ' . $statement['description'];

        } elseif ($statement['tDetail'] == 'creditRemoved') {
            if (empty($statement['description'])) {
                $tDetail = $LANG['statuscreditremoved'];
            } else {
                $tDetail = $statement['description'];
            }
        } elseif ($statement["tDetail"] == "Opening Balance") {
            $tDetail = "Opening Balance as of $dateTime";
        }
        $status = $LANG['cr'];

        if ($statement['tAction'] == 'Refunded') {
            $status = $LANG['cr'] . '/' . $LANG['statusrefund'];
        } elseif ($statement['tAction'] == 'invoiceAdded' || $statement['tAction'] == 'creditRemoved') {
            $status = $LANG['dr'];
        }

        $Overpayment ="";

        if (isset($statement['status']) && !empty($statement['status'])) {
            if ($statement['status'] == 'Unpaid') {

              

                $balanceAmt = $statement['total'] + $totalOpeningBalance;


                $totalOpeningBalance = $statement['dAmount'] + $totalOpeningBalance;


                $invoicestatus = $LANG['statusunpaid'];
                
            } elseif ($statement['status'] == 'Paid' && !isset($statement["invoicepaidcreated"])) {

                $balanceAmt = abs($statement['amountin'] - $totalOpeningBalance);

                if ($statement['amountin'] > $statement['total']) {
                    $balanceAmt = abs($statement['total'] - $totalOpeningBalance);

                    $Overpayment = $statement['amountin'] - $statement['total'];
                }

                $totalOpeningBalance = abs($statement['total'] - $totalOpeningBalance);
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




        if (isset($statement["invoicepaidcreated"]) && $statement["invoicepaidcreated"] == "invoicepaidcreated") {
            $balanceAmt = abs($statement['total'] + $totalOpeningBalance);


            $totalOpeningBalance = abs($statement['total'] + $totalOpeningBalance);
            $tblhtml .= '<tr>
                <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;white-space: nowrap;">' . fromMySQLDate(date('d-m-Y', strtotime($statement["date"]))) . '</td>
                <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">' . $tDetail . '</td>
                <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($statement['total'], $currencyData['id'])) . '</td>
                <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($statement['dAmount'], $currencyData['id'])) . '</td>
                <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">DR/Unpaid</td>
                <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($balanceAmt, $currencyData['id'])) . '</td>
            </tr>';
            $sd++;
        } 
        else {
            $tblhtml .= '<tr>
                        <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;white-space: nowrap;">' . fromMySQLDate(date('d-m-Y', strtotime($dateTime))) . '</td>
                        <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">' . $tDetail . '</td>
                        <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($statement['dAmount'], $currencyData['id'])) . '</td>
                        <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">' . preg_replace("/([a-z]+)([0-9]+)/i","\\1 \\2",!empty($Overpayment) ? formatCurrency($statement['total'], $currencyData['id']) . "<br>Overpayment (" . formatCurrency($Overpayment, $currencyData['id']) . ")" : formatCurrency(!empty($amount) ? $amount : $statement['cAmount'], $currencyData['id'])) . '</td>
                        <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">' . $status . '</td>
                        <td style="border-bottom:1px solid ' . $borderColor . ';font-size:10px;">' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($balanceAmt, $currencyData['id'])) . '</td>
                    </tr>';

            $sd++;
        }

    }
}


$bkHt = '65';

$tblhtml .= '</table>
            </td>
            <td>
                &nbsp;
            </td>
        </tr>
        <tr>
            <td colspan="3" style="height:55px;">&nbsp;</td>
        </tr>
        <tr>
            <td style="width:48px;">
                &nbsp;
            </td>
            <td style="border:1px solid ' . $borderColor . '; font-size:11px; line-height:17px; width:500px;">
                <table cellpadding="8" cellspacing="0">
                    <tr>
                        <th style="border-right:1px solid #b5cbd2;">' . $LANG['dayscolumnfirst'] . ' <br><b>' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($allStatements['due120day'], $currencyData['id'])) . '</b></th>
                        <th style="border-right:1px solid #b5cbd2;">' . $LANG['dayscolumnsecond'] . ' <br><b>' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($allStatements['due90day'], $currencyData['id'])) . '</b></th>
                        <th style="border-right:1px solid #b5cbd2;">' . $LANG['dayscolumnthird'] . '<br><b>' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($allStatements['due60day'], $currencyData['id'])) . '</b></th>
                        <th style="border-right:1px solid #b5cbd2;">' . $LANG['dayscolumnfourth'] . '<br><b>' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($allStatements['due30day'], $currencyData['id'])) . '</b></th>
                        <th style="border-right:1px solid #b5cbd2;border-top:1px solid #b5cbd2;border-bottom:1px solid #b5cbd2;background-color:' . $subHeaderBackgroundColor . ';">' . $LANG['currentduepdf'] . ' <br><b>' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($allStatements['totalClosingBalance'], $currencyData['id'])) . '</b></th>
                    </tr>                                     
                </table>
            </td>
            <td>
                &nbsp;
            </td>
        </tr>
        <tr>
            <td colspan="3" style="height:' . $bkHt . 'px;">&nbsp;</td>
        </tr>
        <tr>
            <td style="width:45px;">
                &nbsp;
            </td>
            <td style="width:500px; font-size:11px;">
                <table cellpadding="0" cellspacing="0">
                </table></td>
            <td>
                &nbsp;
            </td>
        </tr>
    </table>';

$pdf->SetFont('DejaVu Sans', '', 11, '', true);
$pdf->writeHTML($tblhtml, true, false, false, false, '');

$tableY = $pdf->GetY();

$tblhtm2 = '<table cellpadding="0" cellspacing="0">
        <tr><td style="width:45px;">&nbsp;</td>
            <td style="width:20px;"><img src="' . ROOTDIR . '/modules/addons/accountStatement/img/card.png" width="18"></td>
            <td><b> &nbsp;' . $LANG['bankdetailtitle'] . '</b></td>
            <td></td>
        </tr>
        <tr>
            <td style="height:20px;">&nbsp;</td>
        </tr>
        <tr>
        <td style="width:45px;">&nbsp;</td>
            <td style="color:#595959;width:240px;" >' . $bankData . '</td>
        </tr>
    </table>';

$tblhtm3 = '<table cellpadding="0" cellspacing="0">
        <tr>
            <td style="height:20px;">&nbsp;</td>
        </tr>
        <tr>
        <td style="width:45px;">&nbsp;</td>
            <td style="color:#797979;width:240px;">' . $footerEmail . '</td>
        </tr>
    </table>';

if ($bankData && $bankDetailSetting == "on") {
    if ($tableY > 235) {
        $pdf->AddPage();
        $pdf->SetTopMargin(30);
        $pdf->SetY(10);
        $pdf->writeHTML($tblhtm2, true, false, false, false, '');
    } else {
        $pdf->writeHTML($tblhtm2, true, false, false, false, '');
    }
}

if ($footerEmail) {
    if ($tableY > 235) {
        $pdf->AddPage();
        $pdf->SetTopMargin(30);
        $pdf->SetY(10);
        $pdf->writeHTML($tblhtm3, true, false, false, false, '');
    } else {
        $pdf->writeHTML($tblhtm3, true, false, false, false, '');
    }
}

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