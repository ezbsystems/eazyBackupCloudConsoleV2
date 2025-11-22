<?php
// die('ok ok');
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
$logUrlPath = $getsettings['logoUrl'];
$enableCustomColor = $getsettings['enableCustomColor'];

global $headerBgRgb;
#Default color
$headerBgRgb = array(225, 246, 255);
$headerTxtColor = "#0000";
$boxBgColor = "#00bfff";
$boxTxtColor = "#fff";
$hrfirst = "rgb(51,153,255)";
$hrsecond = "rgb(0,0,102)";
$odd_row_Color = "#fff";
$even_row_Color = "#fff";
$tbl_txt_color = "#0000";

#get custom color scheme
if ($enableCustomColor == "on") {
    $customThemColor = $accountsummary->getThemeColor("v204");
    if (!empty($customThemColor)) {
        $getTemplate3Custom = json_decode($customThemColor->settings);

        $headerBg = $getTemplate3Custom->color_hr_bg;
        $headerBgRgb = hex2rgb($headerBg);
        $headerTxtColor = $getTemplate3Custom->color_hr_txt;
        $boxBgColor = $getTemplate3Custom->color_box_bg;
        $boxTxtColor = $getTemplate3Custom->color_box_txt;
        $hrfirst = $getTemplate3Custom->color_fistline;
        $hrsecond = $getTemplate3Custom->color_secondline;
        $odd_row_Color = $getTemplate3Custom->color_tbl_odd;
        $even_row_Color = $getTemplate3Custom->color_tbl_evn;
        $tbl_txt_color = $getTemplate3Custom->color_tbl_txt;
    }
}

#get client details
$clientsdetails = $accountsummary->get_clientdetails($userid);

$comapnyAddress = $accountsummary->get_companyAddress();
$comapnyAddres = '';
$accountsummary = new AccountSummary();

global $countAddessSize;
$countAddessSize = count($comapnyAddress);
foreach ($comapnyAddress as $address) {
    $comapnyAddres .= trim($address) . '<br>';
}

//pdf v2.0.4
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
    public $comapnyAddress;
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
    public function Header()
    {
        if ($this->page == 1) {
            global $countAddessSize;
            global $headerBgRgb;
            #Header Background color  
            $defaultLineHeight = 39;
            if ($countAddessSize < 3) {
                $defaultLineHeight = 39;
            } else {
                $alreadySize = $countAddessSize - 3;
                $defaultLineHeight = 40 + $alreadySize * 5;
            }

            $this->Rect(0, 0, 350, 80, 'F', '', $fill_color = $headerBgRgb);
        }
    }
}

function hex2rgb($hex)
{
    $hex = str_replace("#", "", $hex);

    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    $rgb = array($r, $g, $b);
    return $rgb; // returns an array with the rgb values
}

// create new PDF document
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

if ($getPdfFont == 'custom' && !empty($pdffont)) {
    $pdffont = TCPDF_FONTS::addTTFfont(__DIR__ . '/font/' . $pdffont, 'TrueTypeUnicode', '', 32);
}

if ($pdfpagesize == '' || $pdfpagesize == 'A4') {
    $pdfpagesize = 'A4';
}

#width height of pdf
$pageLayout = array('249', '266');

$pdf->AddPage("P", $pdfpagesize);

# Logo display
if ($logUrlPath != '') {
    if (!empty($logoCalign) && !empty($logoLalign) && !empty($logoRalign)) {
        $pdf->Image($logUrlPath, $logoLalign, $logoCalign, $logoRalign);
    } else {
        $pdf->Image($logUrlPath, 16, 5, 35, 30);
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
        $pdf->Image(ROOTDIR . '/assets/img/' . $logoFilename, 16, 5, 35, 30);
    }
}



$logoEndposition = $pdf->GetY();

$pdf->SetY($logoEndposition);

$pdf->SetLeftMargin(15);
$pdf->SetRightMargin(17);

$pdf->SetXY(17, 7);
$tblhtml = ' 
<table cellpadding="0" cellspacing="0" width="100%" style="color:' . $headerTxtColor . '">
<tr>
    <td></td>
    <td style="text-align:right;"><span style="font-size:13px;width: 150px;"> ' . $clientsdetails['companyname'] . ' <br>
    ' . $clientsdetails['firstname'] . ' ' . $clientsdetails['lastname'] . ' <br>
    ' . $clientsdetails['address1'] . ' <br>
    ' . $clientCity . '
    ' . $jsonArraycountries[$clientsdetails['country']]['name'] . ' <br></span></td>
</tr>

<tr>
<td><span >' . $comapnyAddres . '</span></td>

<td style="text-align:right;margin-top:2px">                
 <span >

' . html_entity_decode($clientsdetails["phonenumber"]) . '<br>

' . html_entity_decode($clientsdetails["email"]) . '<br><br>
</span>
</td>
</tr>
</table>';

$currencyData = getCurrency($allStatements['userid']);

$tblhtml .= '
<table cellpadding="0" cellspacing="0" width="100%">
<br><br><br>

<tr>
<td width="30%" style="color:#9c999b;font-size:13px;">' . $LANG['temp3headtxt'] . '</td> 
<td width="54%"style="text-align:left;font-size:13px">' . fromMySQLDate($startdate) . ' to ' . fromMySQLDate($enddate) . '</td> 
<td width="11%"></td>
</tr>
<tr>
  <td height="28"></td>
</tr>

<tr>
    <td width="23%" style="border:2px solid #dcdcdc;text-align:left;">
       <table cellspacing="0" cellpadding="0"  width="100%">
            <tr>
              <td height="20"></td>
            </tr>
            <tr>
                <td><span style="white-space: nowrap; font-size: 18px;  line-height:normal">' . preg_replace("/([A-Z]+)/i", "", formatCurrency($allStatements['totalDebit'], $currencyData['id'])) . '</span>
                </td>
            </tr>
            <tr>
                <td style="line-height:7px;height:0px"></td>
            </tr>
             <tr>
                <td><span style="white-space: nowrap;font-size: 10px; line-height: normal;">' . $LANG['temp3amountpaid'] . '</span></td>
            </tr>
             <tr>
             <td height="0"></td>
            </tr>
            <tr>
                <td><p style="white-space: nowrap;font-size: 10px; color: #a8a8a8;">' . $LANG['temp3combinedbal'] . ' <br>' . $LANG['temp3balason'] . '<br>' . fromMySQLDate($enddate) . '</p>
                </td>
            </tr>
            <tr>
              <td height="0"></td>
            </tr>
        </table>
    </td>
<td width="2.5%">&nbsp;</td>
<td  width="50%">
    <table cellspacing="0"  width="100%">
        <tr>
           <td width="47%"  style="border:2px solid #dcdcdc;" ><br>
                <table cellpadding="0" cellspacing="1" width="100%">
                    <tr>
                      <td height="2"></td>
                    </tr>
                    <tr>
                        <td><span style="white-space: nowrap; font-size: 15px; line-height:normal">' . preg_replace("/([A-Z]+)/i", "", formatCurrency($allStatements['totalOpeningBalance'], $currencyData['id'])) . '</span>
                        </td>
                    </tr>
                     <tr><td style="height:0; line-height: 2px;"></td></tr>
                     <tr>
                        <td><span style="white-space: nowrap;font-size: 9px; color: #a1a1a1; line-height: normal;">' . $LANG['totalOpeningBalance'] . '</span>
                        </td>
                    </tr>
                    <tr>
                      <td height="0" style="line-height:7px;height:0px"></td>
                    </tr>
                </table>
            </td> 
            <td width="5%" ></td>
            <td width="47%" style="border:2px solid #dcdcdc;"><br>
                <table cellspacing="1" cellpadding="0"  width="100%">
                    <tr>
                      <td height="2"></td>
                    </tr>
                    <tr>
                        <td><span style="white-space: nowrap; font-size: 15px; color: #7dbb77; line-height:normal">' . preg_replace("/([A-Z]+)/i", "", formatCurrency($allStatements['availableCredits'], $currencyData['id'])) . '</span>
                        </td>
                    </tr>
                     <tr><td style="height:0; line-height: 2px;"></td></tr>
                     <tr>
                        <td><span style="white-space: nowrap;font-size: 9px; color: #a1a1a1; line-height: normal;">' . $LANG['temp3box2txt'] . '</span>
                        </td>
                    </tr>
                     <tr>
                      <td height="0" style="line-height:2px;height:0px"></td>
                    </tr>
                </table>
            </td>  
        </tr>
         <tr>
            <td width="25%" style="height: 15px;">&nbsp;</td>
        </tr>
         <tr>
           <td width="47%"  style="border:2px solid #dcdcdc;height:10px;padding:18px;" ><br>
                <table cellspacing="1" cellpadding="0"  width="100%">
                    <tr>
                      <td height="2"></td>
                    </tr>
                    <tr>
                        <td><span style="white-space: nowrap; font-size: 15px; line-height:normal">' . preg_replace("/([A-Z]+)/i", "", formatCurrency($allStatements['cancelledInvoicesprice'], $currencyData['id'])) . '</span>
                        </td>
                    </tr>
                     <tr><td style="height:0; line-height: 2px;"></td></tr>
                     <tr>
                        <td><span style="white-space: nowrap;font-size: 9px; color: #a1a1a1; line-height: normal;">' . $LANG['temp3box3txt'] . '(' . $allStatements['cancelledInvoices'] . ')</span>
                        </td>
                    </tr>
                    <tr>
                      <td height="0" style="line-height:2px;height:0px"></td>
                    </tr>
                </table>
            </td>
            <td width="5%"></td>
            <td width="47%" style="border:2px solid #dcdcdc;"><br>
                <table cellspacing="1" cellpadding="0"  width="100%">
                    <tr>
                      <td height="2"></td>
                    </tr>
                        <tr>
                            <td><span style="white-space: nowrap; font-size: 15px; color: #7dbb77; line-height:normal">' . preg_replace("/([A-Z]+)/i", "", formatCurrency($allStatements['totalrefunded'], $currencyData['id'])) . '</span>
                            </td>
                        </tr>
                        <tr><td style="height:0; line-height: 2px;"></td></tr>
                         <tr>
                            <td><span style="white-space: nowrap;font-size: 9px; color: #a1a1a1; line-height: normal;">' . $LANG['temp3box4txt'] . '</span>
                            </td>
                        </tr>
                         <tr>
                      <td height="0" style="line-height:2px;height:0px"></td>
                    </tr>
                </table>
            </td>  
        </tr>
    </table>
</td>
<td width="3%">&nbsp;</td>
<td width="23%" style="border:2px solid #dcdcdc;text-align:left;background-color:' . $boxBgColor . ';color: ' . $boxTxtColor . ';">
        <table cellspacing="0" cellpadding="0"  width="100%">
            <tr>
              <td height="20"></td>
            </tr>
            <tr>
                <td><span style="white-space: nowrap; font-size: 18px;  line-height:normal">' . preg_replace("/([A-Z]+)/i", "", formatCurrency($allStatements['totalClosingBalancehead'], $currencyData['id'])) . ' </span>
                </td>
            </tr>
            <tr>
                <td style="line-height:7px;height:0px"></td>
            </tr>
             <tr>
                <td><span style="white-space: nowrap;font-size: 10px; line-height: normal;">' . $LANG['temp3closeingbal'] . '</span></td>
                </tr>
            <tr>
             <td height="0"></td>
            </tr>
            <tr>
                <td><p style="white-space: nowrap;font-size: 10px;">' . $LANG['temp3combinedbal'] . ' <br>' . $LANG['temp3balason'] . ' <br>' . fromMySQLDate($enddate) . '</p>
                </td>
            </tr>
            <tr>
             <td height="0"></td>
            </tr>
        </table>
    </td>
</tr>
<tr>
<td colspan="5"  ></td>
</tr>
<tr>
<td colspan="5"  ></td>
</tr>
<tr>
<td colspan="5" style="background-color:' . $hrfirst . ';height: 5px; overflow:hidden;line-height: 5px"></td>
</tr>
<tr>
<td colspan="5" style="background-color:' . $hrsecond . ';height: 5px; overflow:hidden;line-height: 5px"></td>
</tr>
</table>';

$tblhtml .= '<table cellspacing="0" cellpadding="0" width="100%" style="color:' . $tbl_txt_color . ';border-collapse: collapse;">
 <br><br><br> 
<tr>
    <td width="14%" style="color: #999999; font-size: 10px;">' . $LANG['temp3col1'] . '</td>
    <td width="43%" style="color: #999999; font-size: 10px;">' . $LANG['temp3col2'] . '</td>
    <td width="18%" style="color: #999999; font-size: 10px;">' . $LANG['temp3col3'] . '</td>
    <td width="15%" style="color: #999999; font-size: 10px;">' . $LANG['temp3col4'] . '</td>
    <td width="15%" style="color: #999999; font-size: 10px;">' . $LANG['temp3col5'] . '</td>
 </tr>
 <hr style="height: 1px">
';

$count = 0;

$totalOpeningBalance = $allStatements["totalOpeningBalance"];
foreach ($allStatements['data'] as $dateTime => $statements) {
    foreach ($statements as $statement) {


        // if (strtotime($statement["datepaid"]) > strtotime($allStatements["enddate"]) && !isset($statement["invoicepaidcreated"])) {
        //     continue;
        // }
        $balanceAmt = '';
        $colorstyle = '';
        $Overpayment ="";
        if ($statement['activity'] == 'invoiceAdded') {
            if (!empty($statement['invoicenum'])) {
                $tDetail = $LANG['descriptioninvoicecreatednotemp3'] . ' #' . $statement['invoicenum'];
            } else {
                $tDetail = $LANG['descriptioninvoicecreatednotemp3'] . ' #' . $statement['id'];
            }
        } elseif ($statement['activity'] == 'Refunded') {
            $tDetail = $LANG['temp3refunded'] . ' (' . $LANG['refundinvoicelbltemp3'] . ' ' . $statement['invoiceid'] . ')';
        } elseif ($statement['activity'] == 'paymentAdded') {
            if (isset($statement["invoicepaidcreated"]) && $statement["invoicepaidcreated"] == "invoicepaidcreated") {
                if (!empty($statement['invoicenum'])) {
                    $tDetail = $LANG['descriptioninvoicecreatednopdf'] . ' #' . $statement['invoicenum'];
                } else {
                    $tDetail = $LANG['descriptioninvoicecreatednopdf'] . ' #' . $statement['id'];
                }
            } else {
                if ($statement["subtotal"] == "0.00") {
                    $tDetail = $LANG['descriptioninvoicepaidnotemp3'] . ' #' . $statement['id'] . '<br height="0"><span style="color:#808080">' . $LANG['transactionidtemp3'] . ' #' . $statement['transid'] . '</span>';
                } else {
                    $tDetail = $LANG['descriptioninvoicepaidnotemp3'] . ' #' . $statement['invoiceid'] . '<br height="0"><span style="color:#808080">' . $LANG['transactionidtemp3'] . ' #' . $statement['transid'] . '</span>';
                }
            }
            $statement['status'] = $LANG['statuspaid'];
        } elseif ($statement['activity'] == 'creditApplied') {
            $matches = (int) filter_var($statement['description'], FILTER_SANITIZE_NUMBER_INT);
            $tDetail = $LANG['creditapplytemp3'] . ' (' . $LANG['creditappliedlbltemp3'] . ' ' . $matches . ')';
            $statement['status'] = 'creditApplied';
        } elseif ($statement['activity'] == 'creditAdded') {
            if (empty($statement['description'])) {
                $tDetail = $LANG['creditaddedtemp3'];
            } else {
                $matches = (int) filter_var($statement['description'], FILTER_SANITIZE_NUMBER_INT);
                $tDetail = $LANG['creditaddedtemp3'] . ' (' . $LANG['creditaddedlbltemp3'] . ' ' . $matches . ')';
            }

            $statement['status'] = 'creditAdded';
        } elseif ($statement['activity'] == 'creditRemoved') {
            if (empty($statement['description'])) {
                $tDetail = $LANG['creditremovetemp3'];
            } else {
                $tDetail = $statement['description'];
            }
            $statement['status'] = 'creditRemoved';
        } elseif ($statement['activity'] == 'creditupgrade') {
            $statement['status'] = 'Upgrade/Downgrade';
            $colorstyle = '#808080';
        } elseif ($statement['activity'] == 'openingBalance') {
            $tDetail = "Opening Balance as of $dateTime";
        }
        if ($statement['status'] == 'Unpaid') {
            $balanceAmt = $statement['dAmount'] + $totalOpeningBalance;
            $totalOpeningBalance = $statement['dAmount'] + $totalOpeningBalance;

            $colorstyle = 'red';
            $statement['status'] = $LANG['statusunpaid'];
        } elseif ($statement['status'] == 'Draft') {
            $colorstyle = '#3366FF';
            $statement['status'] = $LANG['statusdraft'];
        } elseif ($statement['status'] == 'Paid' && !isset($statement["invoicepaidcreated"])) {
            $balanceAmt = abs($statement['amountin'] - $totalOpeningBalance);

            if ($statement['amountin'] > $statement['total']) {
                $balanceAmt = abs($statement['total'] - $totalOpeningBalance);

                $Overpayment = $statement['amountin'] - $statement['total'];
            }


            $totalOpeningBalance = abs($statement['total'] - $totalOpeningBalance);
            $statement['status'] = $LANG['statuspaid'];
            $colorstyle = '#006400';
        } elseif ($statement['status'] == 'Cancelled') {
            $invoicestatus = $LANG['statuscancelled'];
            $statement['status'] = $LANG['statuscancelled'];
            $colorstyle = '#808080';
        } elseif ($statement['status'] == 'Refunded' || $statement['activity'] == 'Refunded') {
            $invoicestatus = $LANG['statusrefund'];
            $statement['status'] = $LANG['statusrefund'];
            $colorstyle = 'red';
        } elseif ($statement['status'] == 'Collections') {
            $invoicestatus = $LANG['statuscollections'];
            $statement['status'] = $LANG['statuscollections'];
            $colorstyle = '#f1a635';
        } elseif ($statement['status'] == 'Payment Pending') {
            $invoicestatus = $LANG['statuspaymentpending'];
            $statement['status'] = $LANG['statuspaymentpending'];
            $colorstyle = '#3498DB';
        } elseif ($statement['status'] == 'Credit Removed' || $statement['status'] == 'creditRemoved') {
            $statement['status'] = $LANG['statuscreditremoved'];
            $colorstyle = 'red';
        } elseif ($statement['status'] == 'creditAdded') {
            $statement['status'] = $LANG['statuscreditadded'];
            $colorstyle = '#006400';
        } elseif ($statement['status'] == 'Credit Applied' || $statement['status'] == 'creditApplied') {
            $statement['status'] = $LANG['statuscreditapplied'];
            $colorstyle = '#07B7F9';
            if ($statement['relid'] == 0) {
                continue;
            }
        }
        if (isset($statement["invoicepaidcreated"]) && $statement["invoicepaidcreated"] == "invoicepaidcreated") {
            $balanceAmt = abs($statement['total'] + $totalOpeningBalance);
            $totalOpeningBalance = abs($statement['total'] + $totalOpeningBalance);
            $colorstyle = 'red';
            $statement['status'] = $LANG['statusunpaid'];
        }

        if (++$count % 2) {
            $rowcolor = $odd_row_Color;
        } else {
            $rowcolor = $even_row_Color;
        }

        $tblhtml .= '<tr style="background-color:' . $rowcolor . '" nobr="true">
                        <td height="5" colspan="5" > </td>
                    </tr>';
        if (isset($statement["invoicepaidcreated"]) && $statement["invoicepaidcreated"] == "invoicepaidcreated") {


            $tblhtml .= '<tr style="background-color:' . $rowcolor . '" nobr="true" > 
                                <td>' . fromMySQLDate(date('d-m-Y', strtotime($statement["date"]))) . '</td>
                                <td ><span>' . $tDetail . '</span></td>
                                <td >' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($statement['total'], $currencyData['id'])) . '</td>
                                <td  style="color:' . $colorstyle . '">' . $statement['status'] . '</td>
                                <td>' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency($balanceAmt, $currencyData['id'])) . '</td>
                            </tr>';
        } else {
            $tblhtml .= '<tr style="background-color:' . $rowcolor . '" nobr="true" > 
                                <td>' . fromMySQLDate(date('d-m-Y', strtotime($dateTime))) . '</td>
                                <td ><span>' . $tDetail . '</span></td>
                                <td >' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", !empty($Overpayment) ? formatCurrency($statement['total'], $currencyData['id']) . "<br>Overpayment (" . formatCurrency($Overpayment, $currencyData['id']) . ")" : formatCurrency(!empty($amount) ? $amount : $statement['tAmount'], $currencyData['id'])) . '</td>

                                <td  style="color:' . $colorstyle . '">' . $statement['status'] . '</td>
                                <td>' . preg_replace("/([a-z]+)([0-9]+)/i", "\\1 \\2", formatCurrency((float) $balanceAmt, $currencyData['id'])) . '</td>
                            </tr>';
        }


        $tblhtml .= ' <tr style="background-color:' . $rowcolor . '" nobr="true">
                        <td height="25" colspan="5" > </td>
                    </tr> 
                    <tr  nobr="true">
                        <td colspan="5" style="background-color:' . $rowcolor . ';border-bottom:1px solid #999999;">
                        </td> 
                    </tr>';
    }
}

$tblhtml .= '</table>';

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
$file_path = $pdf->Output($path_log, 'F');

if (!$file_path) {
    $accountsummary->add_attachments($userid, $log_filename);
    $temp = true;
} else {
    $temp = false;
}

fwrite($lnk, ob_get_clean());
fclose($lnk);

?>