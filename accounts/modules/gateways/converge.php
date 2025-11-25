<?php

/* * ******************* Converge WHMCS Gateway Module ********************
 * 
 *  Date: 20 May 2016
 *  Update: 11 Nov, 2016
 *  Version: v1.0.4
 *  @By: WHMCS GLOBAL SERVICES
 *  @Author: WHMCS GLOBAL SERVICES
 *  Web site: https://whmcsglobalservices.com/
 * 
 *  ***********************************************************************
 */

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined("WHMCS"))
    die("This file cannot be accessed directly");

#Gateway configurations options

function converge_config() {
    $configarray = array(
        "version" => array("FriendlyName" => "Version", "Description" => "<span style='font-weight: 600;'>1.0.4</span>",),
        "FriendlyName" => array("Type" => "System", "Value" => "Virtual Merchant"),
        "merchant_id" => array("FriendlyName" => "Merchant Id", "Type" => "text", "Size" => "30"),
        "user_id" => array("FriendlyName" => "User Id", "Type" => "text", "Size" => "20"),
        "pin" => array("FriendlyName" => "Pin", "Type" => "text", "Size" => "35"),
        "multicurrency" => array("FriendlyName" => "Enable Multi Currency", "Type" => "yesno"),
        "dba" => array("FriendlyName" => "DBA", "Type" => "text", "Size" => "30"),
        "ssl_show_form" => array("FriendlyName" => "Enable SSL Show Form", "Type" => "yesno"),
        "testmode" => array("FriendlyName" => "Test Mode", "Type" => "yesno"),
        "testurlmode" => array("FriendlyName" => "Test Url", "Type" => "yesno", "Description" => "Enable to test with test account (only for test purpose)."),
        "license" => array("FriendlyName" => "License", "Type" => "text", "Size" => "35",),
        "licenseregto" => array("FriendlyName" => "License Registered To", "Description" => "Not Available",),
        "licenseregmail" => array("FriendlyName" => "License Registered Email", "Description" => "Not Available",),
        "licenseduedate" => array("FriendlyName" => "License Due Date", "Description" => "Not Available",),
        "licensestatus" => array("FriendlyName" => "License Status", "Description" => "Not Available",),
        "update" => array("FriendlyName" => "Last Update", "Description" => "11 Nov, 2016",),
    );

    $licenseinfo = converge_doCheckLicense();

    if ($licenseinfo['status'] != 'licensekeynotfound') {
        if ($licenseinfo['registeredname']) {
            $configarray['licenseregto']['Description'] = $licenseinfo['registeredname'];
        }
        if ($licenseinfo['email']) {
            $configarray['licenseregmail']['Description'] = $licenseinfo['email'];
        }
        if ($licenseinfo['nextduedate']) {
            $configarray['licenseduedate']['Description'] = $licenseinfo['nextduedate'];
        }
        if ($licenseinfo['status'] == 'Active')
            $color = '#17ac17';
        else
            $color = '#bf3c28';

        $configarray['licensestatus']['Description'] = '<b style="letter-spacing: 0.05em;color:' . $color . '">' . $licenseinfo['status'] . '</b>';
        $configarray['license']['Value'] = $licenseinfo['licensekey'];
    }

    return $configarray;
}

#capture function (recurring)

function converge_capture($params) {

    $Converge = new Converge($params);  # object

    $value['ssl_transaction_type'] = 'CCSALE';   # transaction type 
    $value['ssl_amount'] = $params['amount'];
    $value['ssl_salestax'] = '0.00';
    $value['ssl_description'] = $params['description'];
    $value['ssl_invoice_number'] = $params['invoiceid'];
    $value['ssl_customer_code'] = $params['clientdetails']['userid'];
    $value['ssl_first_name'] = $params['clientdetails']['firstname'];
    $value['ssl_last_name'] = $params['clientdetails']['lastname'];
    $value['ssl_company'] = $params['clientdetails']['companyname'];
    $value['ssl_avs_address'] = $params['clientdetails']['address1'];
    $value['ssl_address2'] = $params['clientdetails']['address2'];
    $value['ssl_city'] = $params['clientdetails']['city'];
    $value['ssl_state'] = $params['clientdetails']['state'];
    $value['ssl_avs_zip'] = $params['clientdetails']['postcode'];
    $value['ssl_country'] = $params['clientdetails']['country'];
    $value['ssl_phone'] = $params['clientdetails']['phonenumber'];
    $value['ssl_email'] = $params['clientdetails']['email'];
    if ($params['multicurrency'] == 'on') {
        $value['ssl_transaction_currency'] = $params['currency'];
    }
    if (isset($params['dba']) && !empty($params['dba'])) {
        $value['ssl_dynamic_dba'] = $params['dba'];
    }

    if (isset($params['gatewayid']) && !empty($params['gatewayid'])) {
        $value['ssl_token'] = $params['gatewayid'];
        $value['ssl_cvv2cvc2_indicator'] = 0;
    } else {
        $value['ssl_card_number'] = $params['cardnum'];
        $value['ssl_exp_date'] = $params['cardexp'];
        $value['ssl_get_token'] = 'Y';
        $value['ssl_add_token'] = 'Y';
        $value['ssl_cvv2cvc2_indicator'] = 1;
        $value['ssl_cvv2cvc2'] = $params['cccvv'];
    }

    $value['ssl_show_form'] = 'false';
    $value['ssl_result_format'] = 'ascii';

    $sendData = $Converge->DoCurlRequest('ccsale', 'POST', $value); #Send request data

    if ($sendData['response']['ssl_result'] == '0' && !isset($sendData['response']['errorCode'])) {
        return array("status" => "success", "transid" => $sendData['response']['ssl_txn_id'], "rawdata" => $sendData['response']);
    } else {
        return array("status" => "error", "rawdata" => $sendData['response']);
    }
}

$ssl_show_form = Capsule::table('tblpaymentgateways')->where('gateway', 'converge')->where('setting', 'ssl_show_form')->get();
$ssl_show_form = (array) $ssl_show_form[0];

#nolocalcc(if we add both function capture and link)
if ($ssl_show_form['value'] == 'on' || $ssl_show_form['value'] == 1) {

    function converge_nolocalcc() {
        
    }

}
#Link function
if ($ssl_show_form['value'] == 'on' || $ssl_show_form['value'] == 1) {

    function converge_link($params) {
        $licenseinfo = converge_doCheckLicense();
        if ($licenseinfo['status'] != 'Active') {
            return "<b style='color:red'> Gateway didn't configure Properly. Please contact to Administrator</b>";
        }

        $htmlInput = '';
        if ($params['multicurrency'] == 'on') {
            $htmlInput .= '<input type = "hidden" name = "ssl_transaction_currency" value = "' . $params['currency'] . '">';
        }
        if (isset($params['dba']) && !empty($params['dba'])) {
            $htmlInput .= '<input type = "hidden" name = "ssl_dynamic_dba" value = "' . $params['dba'] . '">';
        }

        if ($params['testmode'] == 1 || $params['testmode'] == 'on') {
            $htmlInput .= '<input type = "hidden" name = "ssl_test_mode" value = "true">';
        } else {
            $htmlInput .= '<input type="hidden" name="ssl_test_mode" value="false">';
        }

        if ($params['testurlmode'] == 1 || $params['testurlmode'] == 'on') {
            $payment_url = 'https://api.demo.convergepay.com/VirtualMerchantDemo/process.do';
        } else {
            $payment_url = 'https://api.convergepay.com/VirtualMerchant/process.do';
        }

        $params['clientdetails']['country'] = converge_country_get_iso3_mapping($params['clientdetails']['country']);

        $url = $payment_url;
        $code = '<form action="' . $url . '" method="POST">
                    <input type="hidden" name="converge_gateway" value="true">
                    <input type="hidden" name="ssl_merchant_id" value="' . $params['merchant_id'] . '">
                    <input type="hidden" name="ssl_user_id" value="' . $params['user_id'] . '">
                    <input type="hidden" name="ssl_pin" value="' . $params['pin'] . '">
                    <input type="hidden" name="ssl_transaction_type" value="CCSALE">
                    <input type="hidden" name="ssl_amount" value="' . $params['amount'] . '">
                    <input type="hidden" name="ssl_salestax" value="0.00">
                    <input type="hidden" name="ssl_description" value="' . $params['description'] . '">
                    <input type="hidden" name="ssl_invoice_number" value="' . $params['invoiceid'] . '">
                    <input type="hidden" name="ssl_customer_code" value="' . $params['clientdetails']['userid'] . '">
                    <input type="hidden" name="ssl_first_name" value="' . $params['clientdetails']['firstname'] . '">
                    <input type="hidden" name="ssl_last_name" value="' . $params['clientdetails']['lastname'] . '">
                    <input type="hidden" name="ssl_company" value="' . $params['clientdetails']['companyname'] . '">
                    <input type="hidden" name="ssl_email" value="' . $params['clientdetails']['email'] . '">
                    <input type="hidden" name="ssl_avs_address" value="' . $params['clientdetails']['address1'] . '">
                    <input type="hidden" name="ssl_address2" value="' . $params['clientdetails']['address2'] . '">
                    <input type="hidden" name="ssl_city" value="' . $params['clientdetails']['city'] . '">
                    <input type="hidden" name="ssl_state" value="' . $params['clientdetails']['state'] . '">
                    <input type="hidden" name="ssl_avs_zip" value="' . $params['clientdetails']['postcode'] . '">
                    <input type="hidden" name="ssl_country" value="' . $params['clientdetails']['country'] . '">
                    <input type="hidden" name="ssl_phone" value="' . $params['clientdetails']['phonenumber'] . '">
                    ' . $htmlInput . '
                    <input type="hidden" name="ssl_verify" value="Y">
                    <input type="hidden" name="ssl_get_token" value="Y">
                    <input type="hidden" name="ssl_add_token" value="Y">
                    <input type="hidden" name="ssl_cvv2cvc2_indicator" value="1">
                    <input type="hidden" name="ssl_show_form" value="true">
                    <input type="hidden" name="ssl_result_format" value="HTML">
                    <input type="hidden" name="ssl_receipt_decl_method" value="GET">
                    <input type="hidden" name="ssl_test_mode" value="true">
                    <input type="hidden" name="ssl_receipt_link_method" value="REDG">
                    <input type="hidden" name="ssl_receipt_apprvl_method=" value="GET">
                    <input type="hidden" name="ssl_receipt_decl_method=" value="GET">
                    <input type="hidden" name="ssl_receipt_decl_get_url" value="' . $params['systemurl'] . '/modules/gateways/callback/converge.php">
                    <input type="hidden" name="ssl_receipt_apprvl_get_url" value="' . $params['systemurl'] . '/modules/gateways/callback/converge.php">
                    <input type="hidden" name="ssl_receipt_link_url" value="' . $params['systemurl'] . '/modules/gateways/callback/converge.php">
                    <input type="submit" name="submitted" value="Pay Now">
                </form>';
        return $code;
    }

}

# Store Remote

function converge_storeremote($params) {

    $Converge = new Converge($params);  # object

    $value['ssl_transaction_type'] = 'ccgettoken';   # transaction type
    $value['ssl_customer_code'] = $params['clientdetails']['userid'];
    $value['ssl_first_name'] = $params['clientdetails']['firstname'];
    $value['ssl_last_name'] = $params['clientdetails']['lastname'];
    $value['ssl_company'] = $params['clientdetails']['companyname'];
    $value['ssl_avs_address'] = $params['clientdetails']['address1'];
    $value['ssl_address2'] = $params['clientdetails']['address2'];
    $value['ssl_city'] = $params['clientdetails']['city'];
    $value['ssl_state'] = $params['clientdetails']['state'];
    $value['ssl_avs_zip'] = $params['clientdetails']['postcode'];
    $value['ssl_country'] = $params['clientdetails']['country'];
    $value['ssl_phone'] = $params['clientdetails']['phonenumber'];
    $value['ssl_email'] = $params['clientdetails']['email'];
    $value['ssl_card_number'] = $params['cardnum'];
    $value['ssl_exp_date'] = $params['cardexp'];
    $value['ssl_verify'] = 'Y';
    $value['ssl_add_token'] = 'Y';
    $value['ssl_show_form'] = 'false';
    $value['ssl_result_format'] = 'ascii';
    if (!empty($params['cardcvv']))
        $value['ssl_cvv2cvc2'] = $params['cardcvv'];

    $sendData = $Converge->DoCurlRequest('ccgettoken', 'POST', $value); #Send request data

    if ($sendData['response']['ssl_result'] == '0' && !isset($sendData['response']['errorCode'])) {
        return array("status" => "success", "gatewayid" => $sendData['response']['ssl_token'], "rawdata" => $sendData['response']);
    } else {
        return array("status" => "failed", "rawdata" => $sendData['response']);
    }
}

# Refund

function converge_refund($params) {

    $licenseinfo = converge_doCheckLicense();

    if ($licenseinfo['status'] != 'Active') {
        return array("status" => "failed", "rawdata" => "Gateway didn't configure Properly, Your license is " . $licenseinfo['status']);
    }

    $Converge = new Converge($params);  # object

    $value['ssl_transaction_type'] = 'ccreturn';   # transaction type 
    $value['ssl_amount'] = $params['amount'];
    $value['ssl_show_form'] = 'false';
    $value['ssl_result_format'] = 'ascii';
    $value['ssl_txn_id'] = $params['transid'];  # transaction id

    $sendData = $Converge->DoCurlRequest('ccreturn', 'POST', $value); #Send request data

    if ($sendData['response']['ssl_result'] == '0' && !isset($sendData['response']['errorCode'])) {
        return array("status" => "success", "transid" => $sendData['response']['ssl_txn_id'], "rawdata" => $sendData['response']);
    } else {
        return array("status" => "failed", "rawdata" => $sendData['response']);
    }
}

# Class

class Converge {

    public $apiUrl;
    private $merchantId;
    private $userId;
    private $pin;
    private $testMode;
    private $testurlmode;
    public $SSLForm;
    var $ssl_test_mode;
    var $ssl_show_form;
    var $ssl_result_format;

    #Call constructer

    public function __construct($params) {
        $this->merchantId = $params['merchant_id'];
        $this->userId = $params['user_id'];
        $this->pin = $params['pin'];
        $this->testMode = $params['testmode'];
        $this->testurlmode = $params['testurlmode'];
        $this->SSLForm = $params['ssl_show_form'];

        if ($this->SSLForm == 'on' || $this->SSLForm == 1) {
            $this->ssl_show_form = 'true';
            $this->ssl_result_format = 'HTML';
        } else {
            $this->ssl_show_form = 'false';
            $this->ssl_result_format = 'ascii';
        }

        if ($this->testMode == 'on' || $this->testMode == 1) {
            $this->ssl_test_mode = 'true';
        } else {
            $this->ssl_test_mode = 'false';
        }
        if ($this->testurlmode == 'on' || $this->testurlmode == 1) {
            $this->apiUrl = 'https://api.demo.convergepay.com/VirtualMerchantDemo/process.do';
        } else {
            $this->apiUrl = 'https://api.convergepay.com/VirtualMerchant/process.do';
        }
    }

    # Send curl request

    public function DoCurlRequest($apiCommand, $httpMethod, $request) {
        $request['ssl_merchant_id'] = $this->merchantId;
        $request['ssl_user_id'] = $this->userId;
        $request['ssl_pin'] = $this->pin;
        $request['ssl_test_mode'] = $this->ssl_test_mode;

        $request_url = $this->apiUrl;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        if (!empty($request)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
        }
        $curl_response = curl_exec($ch);
        $curlInfo = curl_getinfo($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        $response = $this->convergePparseAsciiResponse($curl_response);

        return array('url' => $request_url, 'request' => $request, 'response' => $response, 'info' => $curlInfo, 'curl_error' => $curlError);
    }

    private function convergePparseAsciiResponse($ascii_string) {
        $data = array();
        $lines = explode("\n", $ascii_string);
        if (count($lines)) {
            foreach ($lines as $line) {
                $kvp = explode('=', $line);
                $data[$kvp[0]] = $kvp[1];
            }
        }
        return $data;
    }

}

function converge_country_get_iso3_mapping($code) {
    $array = array(
        'AND' => 'AD',
        'ARE' => 'AE',
        'AFG' => 'AF',
        'ATG' => 'AG',
        'AIA' => 'AI',
        'ALB' => 'AL',
        'ARM' => 'AM',
        'AGO' => 'AO',
        'ATA' => 'AQ',
        'ARG' => 'AR',
        'ASM' => 'AS',
        'AUT' => 'AT',
        'AUS' => 'AU',
        'ABW' => 'AW',
        'ALA' => 'AX',
        'AZE' => 'AZ',
        'BIH' => 'BA',
        'BRB' => 'BB',
        'BGD' => 'BD',
        'BEL' => 'BE',
        'BFA' => 'BF',
        'BGR' => 'BG',
        'BHR' => 'BH',
        'BDI' => 'BI',
        'BEN' => 'BJ',
        'BLM' => 'BL',
        'BMU' => 'BM',
        'BRN' => 'BN',
        'BOL' => 'BO',
        'BES' => 'BQ',
        'BRA' => 'BR',
        'BHS' => 'BS',
        'BTN' => 'BT',
        'BVT' => 'BV',
        'BWA' => 'BW',
        'BLR' => 'BY',
        'BLZ' => 'BZ',
        'CAN' => 'CA',
        'CCK' => 'CC',
        'COD' => 'CD',
        'CAF' => 'CF',
        'COG' => 'CG',
        'CHE' => 'CH',
        'CIV' => 'CI',
        'COK' => 'CK',
        'CHL' => 'CL',
        'CMR' => 'CM',
        'CHN' => 'CN',
        'COL' => 'CO',
        'CRI' => 'CR',
        'CUB' => 'CU',
        'CPV' => 'CV',
        'CUW' => 'CW',
        'CXR' => 'CX',
        'CYP' => 'CY',
        'CZE' => 'CZ',
        'DEU' => 'DE',
        'DJI' => 'DJ',
        'DNK' => 'DK',
        'DMA' => 'DM',
        'DOM' => 'DO',
        'DZA' => 'DZ',
        'ECU' => 'EC',
        'EST' => 'EE',
        'EGY' => 'EG',
        'ESH' => 'EH',
        'ERI' => 'ER',
        'ESP' => 'ES',
        'ETH' => 'ET',
        'FIN' => 'FI',
        'FJI' => 'FJ',
        'FLK' => 'FK',
        'FSM' => 'FM',
        'FRO' => 'FO',
        'FRA' => 'FR',
        'GAB' => 'GA',
        'GBR' => 'GB',
        'GRD' => 'GD',
        'GEO' => 'GE',
        'GUF' => 'GF',
        'GGY' => 'GG',
        'GHA' => 'GH',
        'GIB' => 'GI',
        'GRL' => 'GL',
        'GMB' => 'GM',
        'GIN' => 'GN',
        'GLP' => 'GP',
        'GNQ' => 'GQ',
        'GRC' => 'GR',
        'SGS' => 'GS',
        'GTM' => 'GT',
        'GUM' => 'GU',
        'GNB' => 'GW',
        'GUY' => 'GY',
        'HKG' => 'HK',
        'HMD' => 'HM',
        'HND' => 'HN',
        'HRV' => 'HR',
        'HTI' => 'HT',
        'HUN' => 'HU',
        'IDN' => 'ID',
        'IRL' => 'IE',
        'ISR' => 'IL',
        'IMN' => 'IM',
        'IND' => 'IN',
        'IOT' => 'IO',
        'IRQ' => 'IQ',
        'IRN' => 'IR',
        'ISL' => 'IS',
        'ITA' => 'IT',
        'JEY' => 'JE',
        'JAM' => 'JM',
        'JOR' => 'JO',
        'JPN' => 'JP',
        'KEN' => 'KE',
        'KGZ' => 'KG',
        'KHM' => 'KH',
        'KIR' => 'KI',
        'COM' => 'KM',
        'KNA' => 'KN',
        'PRK' => 'KP',
        'KOR' => 'KR',
        'XKX' => 'XK',
        'KWT' => 'KW',
        'CYM' => 'KY',
        'KAZ' => 'KZ',
        'LAO' => 'LA',
        'LBN' => 'LB',
        'LCA' => 'LC',
        'LIE' => 'LI',
        'LKA' => 'LK',
        'LBR' => 'LR',
        'LSO' => 'LS',
        'LTU' => 'LT',
        'LUX' => 'LU',
        'LVA' => 'LV',
        'LBY' => 'LY',
        'MAR' => 'MA',
        'MCO' => 'MC',
        'MDA' => 'MD',
        'MNE' => 'ME',
        'MAF' => 'MF',
        'MDG' => 'MG',
        'MHL' => 'MH',
        'MKD' => 'MK',
        'MLI' => 'ML',
        'MMR' => 'MM',
        'MNG' => 'MN',
        'MAC' => 'MO',
        'MNP' => 'MP',
        'MTQ' => 'MQ',
        'MRT' => 'MR',
        'MSR' => 'MS',
        'MLT' => 'MT',
        'MUS' => 'MU',
        'MDV' => 'MV',
        'MWI' => 'MW',
        'MEX' => 'MX',
        'MYS' => 'MY',
        'MOZ' => 'MZ',
        'NAM' => 'NA',
        'NCL' => 'NC',
        'NER' => 'NE',
        'NFK' => 'NF',
        'NGA' => 'NG',
        'NIC' => 'NI',
        'NLD' => 'NL',
        'NOR' => 'NO',
        'NPL' => 'NP',
        'NRU' => 'NR',
        'NIU' => 'NU',
        'NZL' => 'NZ',
        'OMN' => 'OM',
        'PAN' => 'PA',
        'PER' => 'PE',
        'PYF' => 'PF',
        'PNG' => 'PG',
        'PHL' => 'PH',
        'PAK' => 'PK',
        'POL' => 'PL',
        'SPM' => 'PM',
        'PCN' => 'PN',
        'PRI' => 'PR',
        'PSE' => 'PS',
        'PRT' => 'PT',
        'PLW' => 'PW',
        'PRY' => 'PY',
        'QAT' => 'QA',
        'REU' => 'RE',
        'ROU' => 'RO',
        'SRB' => 'RS',
        'RUS' => 'RU',
        'RWA' => 'RW',
        'SAU' => 'SA',
        'SLB' => 'SB',
        'SYC' => 'SC',
        'SDN' => 'SD',
        'SSD' => 'SS',
        'SWE' => 'SE',
        'SGP' => 'SG',
        'SHN' => 'SH',
        'SVN' => 'SI',
        'SJM' => 'SJ',
        'SVK' => 'SK',
        'SLE' => 'SL',
        'SMR' => 'SM',
        'SEN' => 'SN',
        'SOM' => 'SO',
        'SUR' => 'SR',
        'STP' => 'ST',
        'SLV' => 'SV',
        'SXM' => 'SX',
        'SYR' => 'SY',
        'SWZ' => 'SZ',
        'TCA' => 'TC',
        'TCD' => 'TD',
        'ATF' => 'TF',
        'TGO' => 'TG',
        'THA' => 'TH',
        'TJK' => 'TJ',
        'TKL' => 'TK',
        'TLS' => 'TL',
        'TKM' => 'TM',
        'TUN' => 'TN',
        'TON' => 'TO',
        'TUR' => 'TR',
        'TTO' => 'TT',
        'TUV' => 'TV',
        'TWN' => 'TW',
        'TZA' => 'TZ',
        'UKR' => 'UA',
        'UGA' => 'UG',
        'UMI' => 'UM',
        'USA' => 'US',
        'URY' => 'UY',
        'UZB' => 'UZ',
        'VAT' => 'VA',
        'VCT' => 'VC',
        'VEN' => 'VE',
        'VGB' => 'VG',
        'VIR' => 'VI',
        'VNM' => 'VN',
        'VUT' => 'VU',
        'WLF' => 'WF',
        'WSM' => 'WS',
        'YEM' => 'YE',
        'MYT' => 'YT',
        'ZAF' => 'ZA',
        'ZMB' => 'ZM',
        'ZWE' => 'ZW',
        'SCG' => 'CS',
        'ANT' => 'AN',
    );
    foreach ($array as $key => $value) {
        if ($value == $code)
            return $key;
    }
}

#License

function converge_checkLicense($licensekey, $localkey = "") {
    $whmcsurl = "http://whmcsglobalservices.com/members/"; #enter your own whmcs url here
    $licensing_secret_key = "VMConverge@2016"; #you can enter your own secret key here
    $check_token = time() . md5(mt_rand(1000000000, 1e+010) . $licensekey);
    $checkdate = date("Ymd");
    $usersip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : $_SERVER['LOCAL_ADDR'];
    $localkeydays = 5;
    $allowcheckfaildays = 3;
    $localkeyvalid = false;
    $lkey = Capsule::table('tblconfiguration')->where('setting', 'converge_localkey')->get(); //add for local key
    if ($lkey) {
        $localkey = $lkey[0]->value;
    }
    if ($localkey) {
        $localkey = str_replace("\n", "", $localkey);
        $localdata = substr($localkey, 0, strlen($localkey) - 32);
        $md5hash = substr($localkey, strlen($localkey) - 32);
        if ($md5hash == md5($localdata . $licensing_secret_key)) {
            $localdata = strrev($localdata);
            $md5hash = substr($localdata, 0, 32);
            $localdata = substr($localdata, 32);
            $localdata = base64_decode($localdata);
            $localkeyresults = unserialize($localdata);
            $originalcheckdate = $localkeyresults['checkdate'];
            if ($md5hash == md5($originalcheckdate . $licensing_secret_key)) {
                $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - $localkeydays, date("Y")));
                if ($localexpiry < $originalcheckdate) {
                    $localkeyvalid = true;
                    $results = $localkeyresults;
                    $validdomains = explode(",", $results['validdomain']);
                    if (!in_array($_SERVER['SERVER_NAME'], $validdomains)) {
                        $localkeyvalid = false;
                        $localkeyresults['status'] = "Invalid";
                        $results = array();
                    }
                    $validips = explode(",", $results['validip']);
                    if (!in_array($usersip, $validips)) {
                        $localkeyvalid = false;
                        $localkeyresults['status'] = "Invalid";
                        $results = array();
                    }
                    if ($results['validdirectory'] != dirname(__FILE__)) {
                        $localkeyvalid = false;
                        $localkeyresults['status'] = "Invalid";
                        $results = array();
                    }
                }
            }
        }
    }
    if (!$localkeyvalid) {
        $postfields['licensekey'] = $licensekey;
        $postfields['domain'] = $_SERVER['SERVER_NAME'];
        $postfields['ip'] = $usersip;
        $postfields['dir'] = dirname(__FILE__);
        if ($check_token) {
            $postfields['check_token'] = $check_token;
        }
        if (function_exists("curl_exec")) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $whmcsurl . "modules/servers/licensing/verify.php");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec($ch);
            curl_close($ch);
        } else {
            $fp = fsockopen($whmcsurl, 80, $errno, $errstr, 5);
            if ($fp) {
                $querystring = "";
                foreach ($postfields as $k => $v) {
                    $querystring .= "{$k}=" . urlencode($v) . "&";
                }
                $header = "POST " . $whmcsurl . "modules/servers/licensing/verify.php HTTP/1.0\r\n";
                $header .= "Host: " . $whmcsurl . "\r\n";
                $header .= "Content-type: application/x-www-form-urlencoded\r\n";
                $header .= "Content-length: " . @strlen(@$querystring) . "\r\n";
                $header .= "Connection: close\r\n\r\n";
                $header .= $querystring;
                $data = "";
                @stream_set_timeout(@$fp, 20);
                @fputs(@$fp, @$header);
                $status = @socket_get_status(@$fp);
                while (!feof(@$fp) && $status) {
                    $data .= @fgets(@$fp, 1024);
                    $status = @socket_get_status(@$fp);
                }
                @fclose(@$fp);
            }
        }
        if (!$data) {
            $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - ( $localkeydays + $allowcheckfaildays ), date("Y")));
            if ($localexpiry < $originalcheckdate) {
                $results = $localkeyresults;
            } else {
                $results['status'] = "Invalid";
                $results['description'] = "Remote Check Failed";
                return $results;
            }
        }
        preg_match_all("/<(.*?)>([^<]+)<\\/\\1>/i", $data, $matches);
        $results = array();
        foreach ($matches[1] as $k => $v) {
            $results[$v] = $matches[2][$k];
        }
        if ($results['md5hash'] && $results['md5hash'] != md5($licensing_secret_key . $check_token)) {
            $results['status'] = "Invalid";
            $results['description'] = "MD5 Checksum Verification Failed";
            return $results;
        }
        if ($results['status'] == "Active") {
            $results['checkdate'] = $checkdate;
            $data_encoded = serialize($results);
            $data_encoded = base64_encode($data_encoded);
            $data_encoded = md5($checkdate . $licensing_secret_key) . $data_encoded;
            $data_encoded = strrev($data_encoded);
            $data_encoded = $data_encoded . md5($data_encoded . $licensing_secret_key);
            $data_encoded = wordwrap($data_encoded, 80, "\n", true);
            $results['localkey'] = $data_encoded;
            if (!Capsule::table('tblconfiguration')->where('setting', 'converge_localkey')->get()) {
                Capsule::table('tblconfiguration')->insert(
                        [
                            'setting' => 'converge_localkey',
                            'value' => $results['localkey']
                        ]
                );
            } else {
                Capsule::table('tblconfiguration')
                        ->where('setting', 'converge_localkey')
                        ->update(
                                [
                                    'value' => $results['localkey']
                                ]
                );
            }
        }
        $results['remotecheck'] = true;
    }

    unset($postfields);
    unset($data);
    unset($matches);
    unset($whmcsurl);
    unset($licensing_secret_key);
    unset($checkdate);
    unset($usersip);
    unset($localkeydays);
    unset($allowcheckfaildays);
    unset($md5hash);
    return $results;
}

function converge_doCheckLicense() {
    $result_query = mysql_query("SELECT setting,value FROM tblpaymentgateways WHERE gateway='converge'") or die(mysql_error());
    $setting = array();
    while ($row = mysql_fetch_assoc($result_query)) {
        $setting[$row['setting']] = $row['value'];
    }
    if ($setting['license']) {
        $localkey = '';
        $result = converge_checkLicense($setting['license'], $localkey);

        $result['licensekey'] = $setting['license'];
    } else {
        $result['status'] = 'licensekeynotfound';
    }

    return $result;
}
