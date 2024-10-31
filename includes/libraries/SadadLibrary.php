<?php

/**
 * SadadLibrary is responsible for handling Sadad API endpoints.
 * Created by SadadPay https://sadadpay.net/
 * Developed By plugins@sadadkw.com
 * Date: 05/05/2023
 *
 * API Documentation on https://sadadpay.readme.io
 */
class SadadLibrary {

    /**
     * The configuration used to connect to Sadad sandbox/live API server
     *
     * @var array
     */
    protected $config = array();

    /**
     * The refreshToken used to connect to authenticate Sadad sandbox/live API endpoints
     *
     * @var string
     */
    public $refreshToken;

    /**
     * Constructor that initiates a Sadad API process
     *
     * @param array $config It has the required (clientId, clientSecret, and testMode)
     *                      to process SadadPay API requests.
     */
    public function __construct($config) {
        $this->config = $config;
        $this->setIsTest($this->config);
        $this->setGatewayAPIUrl();
        $this->setGatewayPayUrl();
        if (empty($this->config['refreshToken']) || !is_string($this->config['refreshToken'])) {
            $this->setClientId($this->config);
            $this->setClientSecret($this->config);
            $this->generateRefreshToken();
        }else{
            $this->refreshToken = $this->config['refreshToken'];
        }

    }

    /**
     * Set the test mode. Set it to false for live mode
     *
     * @return void
     *
     * @throws Exception
     */
    protected function setIsTest($config) {
        if (!isset($config['isTest'])) {
            throw new Exception('Ops, you have to provide the "isTest" mode.');
        }
        if (!is_bool($config['isTest'])) {
            throw new Exception('Ops, The "isTest" mode must be boolean.');
        }
        $this->config['isTest'] = $config['isTest'];
        $this->config['sandbox'] = $this->config['isTest'] ? 'sandbox.' : '';
        $this->config['api'] = $this->config['isTest'] ? 'apisandbox.' : 'api.';
    }

    /**
     * Set the Log file path
     *
     * @return void
     *
     * @throws Exception
     */
    protected function setLogPath($config) {
        $this->config['log'] = empty($config['log']) ? null : $config['log'];
    }

    /**
     * Set the ClientId
     *
     * @return void
     *
     * @throws Exception
     */
    protected function setClientId($config) {
        if (empty($config['clientId'])) {
            throw new Exception('Ops, you have to provide the Sadad "clientId".');
        }

        $config['clientId'] = trim($config['clientId']);
        if (empty($config['clientId']) || !is_string($config['clientId'])) {
            throw new Exception('Ops, you have to provide the Sadad "clientId" and it should be a string.');
        }

        $this->config['clientId'] = $config['clientId'];
    }

    /**
     * Set the ClientSecret
     *
     * @return void
     *
     * @throws Exception
     */
    protected function setClientSecret($config) {
        if (empty($config['clientSecret'])) {
            throw new Exception('Ops, you have to provide the Sadad "clientSecret".');
        }

        $config['clientSecret'] = trim($config['clientSecret']);
        if (empty($config['clientSecret']) || !is_string($config['clientSecret'])) {
            throw new Exception('Ops, you have to provide the Sadad "clientSecret" and it should be a string.');
        }

        $this->config['clientSecret'] = $config['clientSecret'];
    }

    /**
     * Set the gateway API URL
     *
     * @return void
     */
    public function setGatewayAPIUrl() {
        $this->config['apiURL'] = 'https://' . $this->config['api'] . 'sadadpay.net/api';
    }

    /**
     * Set the gateway Pay URL
     *
     * @return void
     */
    public function setGatewayPayUrl() {
        $this->config['payURL'] = 'https://' . $this->config['sandbox'] . 'sadadpay.net/pay';
    }

    /**
     * Generate refresh token
     *
     * @return void
     */
    public function generateRefreshToken() {
        $endpoint = $this->config['apiURL'] . '/User/GenerateRefreshToken';
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->config['clientId'] . ':' . $this->config['clientSecret']),
        );
        $request = json_encode(array());
        $response = $this->sendRequest($endpoint, $headers, $request);

        if (empty($response['response']['refreshToken'])) {
            throw new Exception('Ops, we could not generate Sadad refresh token. Please make sure to provide the correct Sadad Client ID, Secret and live/test mode as well.');
        }
        $this->refreshToken = $response['response']['refreshToken'];
    }

    /**
     * Generate Access token
     *
     * @param  refreshToken
     * @return array
     *
     * @throws Exception
     */
    public function getAccessToken($refreshToken) {
        $endpoint = $this->config['apiURL'] . '/User/GenerateAccessToken';
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $refreshToken,
        );

        $response = $this->sendRequest($endpoint, $headers);

        if (empty($response['response']['accessToken'])) {
            throw new Exception('Ops, we could not generate Sadad access token. Please make sure to provide the correct Sadad Client ID, Secret and live/test mode as well. Then, try again!!');
        }
        return $response['response']['accessToken'];
    }

    /**
     * SendRequest to SadadPay endpoints
     *
     * @param string $url     SadadPay API endpint to call
     * @param array  $headers CURL headers
     * @param array  $request
     * @param string $type
     *
     * @return array
     * @throws Exception
     */
    public function sendRequest($url, $headers, $request = array(), $type = 'POST') {
        if (!in_array('curl', get_loaded_extensions())) {
            throw new Exception('Ops, it seems that Curl extension is not loaded on your server, please check with server admin. Then, try again!');
        }
        ini_set('precision', 14);
        ini_set('serialize_precision', -1);
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        if ('GET' == $type) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        } else {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        if (false === $response) {
            throw new Exception('Ops, there is a Curl error: ' . curl_error($curl));
        }

        curl_close($curl);

        return json_decode($response, true);
    }

    /**
     * CreateInvoice in SadadPay
     *
     * @param array  $request
     * @param string $refreshToken
     *
     * @return array
     * @throws Exception
     */
    public function createInvoice($request, $refreshToken) {


        $this->writeLog('Create Invoice Request orderId# ' . $request['Invoices'][0]['ref_Number'] . json_encode($request));
        $endpoint = $this->config['apiURL'] . '/Invoice/insert';
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getAccessToken($refreshToken),
        );
        $response = $this->sendRequest($endpoint, $headers, $request);
        if (!empty($response['errorKey'])) {
            throw new Exception($response['errorKey']);
        }
        if (empty($response['response']['invoiceId'])) {
            throw new Exception('Ops, we could not create new Invoice!');
        }

        $this->writeLog('Create Invoice Response orderId# ' . $request['Invoices'][0]['ref_Number'] . json_encode($response));
        $invoiceId = $response['response']['invoiceId'];
        $invoiceInfo = $this->getInvoiceInfo($invoiceId, $refreshToken);

        $key = $invoiceInfo['response']['key'];
        return array(
            'InvoiceId' => $invoiceId,
            'InvoiceURL' => $this->config['payURL'] . '/' . $key,
        );
    }

    /**
     * GetInvoiceInfo from SadadPay
     *
     * @param string $invoiceId
     * @param string $refreshToken
     *
     * @return array
     * @throws Exception
     */
    public function getInvoiceInfo($invoiceId, $refreshToken) {

        $this->writeLog('In Invoice Info inv# ' . $invoiceId);

        $endpoint = $this->config['apiURL'] . '/Invoice/getbyid?id=' . $invoiceId;
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getAccessToken($refreshToken),
        );
        $response = $this->sendRequest($endpoint, $headers, array(), 'GET');
        $this->writeLog('inv# ' . $invoiceId . ' response :' . json_encode($response));

        if (empty($response['response']['key'])) {
            throw new Exception('Ops, we could not get Invoice info. Please try again with correct information!');
        }
        return $response;
    }

    /**
     * RefundInvoice in SadadPay
     *
     * @param array  $request
     * @param string $refreshToken
     *
     * @return array
     * @throws Exception
     */
    public function refundInvoice($request, $refreshToken) {


        $this->writeLog('Refund Invoice Request ' . json_encode($request));
        $endpoint = $this->config['apiURL'] . '/Refund/insert';
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getAccessToken($refreshToken),
        );
        $response = $this->sendRequest($endpoint, $headers, $request);
        $this->writeLog('Refund Invoice response ' . json_encode($response));

        if (empty($response['response']['refund_Id'])) {
            throw new Exception('Ops, we could not refund the invoice amount. Please try again later!');
        }
        return $response;
    }

    /**
     * GetCurrencyList in SadadPay
     *
     * @param bool $isTest
     *
     * @return array
     * @throws Exception
     */
    public static function getCurrencyList($isTest) {
        $sandbox = ( $isTest ) ? 'sandbox' : '';
        $curl = curl_init('https://api' . $sandbox . '.sadadpay.net/api/Common/getcurrencies');
        $option = array(
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_RETURNTRANSFER => true,
        );
        curl_setopt_array($curl, $option);
        $response = curl_exec($curl);
        $responseArr = json_decode($response, true);

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (200 == $http_code) {
            if (!empty($responseArr['errorKey'])) {
                throw new Exception($responseArr['errorKey']);
            }
            return $responseArr['response'];
        } else {
            throw new Exception('Ops, we could not load Sadad currency list.');
        }
    }

    /**
     * GetKWDAmount for SadadPay
     *
     * @param  string $currency
     * @param  float  $totalAmount
     * @param  bool   $isTest
     * @return array
     * @throws Exception
     */
    public static function getKWDAmount($currency, $totalAmount, $isTest = false) {
        $currencyList = self::getCurrencyList($isTest);
        $kwdAmount = 0;
        foreach ($currencyList as $key => $value) {
            if (strtolower($currency) === strtolower($value['code'])) {
                $kwdAmount = number_format($totalAmount * $value['conversionRate'], $value['decimalPlacement'], '.', '');
            }
        }
        if (0 == $kwdAmount) {
            throw new Exception('Ops, Currency ' . $currency . ' is not found in Sadad Currency list. Please try again with a different currency!');
        }
        return $kwdAmount;
    }

    /**
     * ValidatePhone
     *
     * @param  string $phone
     * @return string
     * @throws Exception
     */
    public static function validatePhone($phone) {
        // convert digits
        $num = self::convertNumbertoEnglish($phone);
        // Keep numbers
        $number = preg_replace('/[^0-9]/', '', $num);
        // remove 00
        if (strpos($number, '00') === 0) {
            $number = substr($number, 2);
        }

        if (!$number) {
            return null;
        }

        // check for the allowed length
        $len = strlen($number);
        if ($len < 3 || $len > 14) {
            throw new Exception('Ops, Please provide a Phone Number with length between 3 to 14 digits');
        }

        return $number;
    }

    /**
     * ConvertNumbertoEnglish
     *
     * @param  string $number
     * @return string
     */
    protected static function convertNumbertoEnglish($number) {

        $num = range(0, 9);

        $persianDecimal = array('&#1776;', '&#1777;', '&#1778;', '&#1779;', '&#1780;', '&#1781;', '&#1782;', '&#1783;', '&#1784;', '&#1785;'); // Persian HTML
        $arabicDecimal = array('&#1632;', '&#1633;', '&#1634;', '&#1635;', '&#1636;', '&#1637;', '&#1638;', '&#1639;', '&#1640;', '&#1641;'); // Arabic HTML
        $arabic = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'); // Arabic
        $persian = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'); // Persian

        $str1 = str_replace($persianDecimal, $num, $number);
        $str2 = str_replace($arabicDecimal, $num, $str1);
        $str3 = str_replace($arabic, $num, $str2);
        $str4 = str_replace($persian, $num, $str3);

        return $str4;
    }

    /**
     * WriteLog for SadadPay
     *
     * @param string $message
     *
     * @return void
     */
    public function writeLog($message) {
        if (!empty($this->config['log'])) {
            error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . ' - ' . $message, 3, $this->config['log']);
        }
    }

    /**
     * Filter an input from global variables like $_GET, $_POST, $_REQUEST, $_COOKIE, $_SERVER
     *
     * @param string $name The field name the need to be filter.
     * @param string $type The input type to be filter (GET, POST, REQUEST, COOKIE, SERVER).
     *
     * @return string
     */
    public static function filterInput($name, $type = 'GET') {
        if (isset($GLOBALS["_$type"][$name])) {
            return htmlspecialchars($GLOBALS["_$type"][$name]);
        }
        return null;
    }
}
