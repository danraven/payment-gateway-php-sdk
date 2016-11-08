<?php

/**
 * BIG FISH Payment Gateway (https://www.paymentgateway.hu)
 * PHP SDK
 *
 * @link https://github.com/bigfish-hu/payment-gateway-php-sdk.git
 * @copyright (c) 2015, BIG FISH Internet-technology Ltd. (http://bigfish.hu)
 */

namespace BigFish;

use BigFish\PaymentGateway\Exception;
use BigFish\PaymentGateway\Request\RequestAbstract as Request;
use BigFish\PaymentGateway\Response;
use Bigfish\PaymentGateway\Driver\DriverInterface;

/**
 * BIG FISH Payment Gateway main class (client)
 *
 * @package PaymentGateway
 */
class PaymentGateway {

    /** SDK Name */
    const NAME = 'PHP-SDK';

    /** SDK Version */
    const VERSION = '2.6.2';

    /** Result code constants */
    const RESULT_CODE_SUCCESS = 'SUCCESSFUL';
    const RESULT_CODE_ERROR = 'ERROR';
    const RESULT_CODE_PENDING = 'PENDING';
    const RESULT_CODE_USER_CANCEL = 'CANCELED';
    const RESULT_CODE_TIMEOUT = 'TIMEOUT';
    const RESULT_CODE_OPEN = 'OPEN';

    /** Provider name constants */
    const PROVIDER_ABAQOOS = 'ABAQOOS';
    const PROVIDER_BARION = 'Barion';
    const PROVIDER_BORGUN = 'Borgun';
    const PROVIDER_CIB = 'CIB';
    const PROVIDER_ESCALION = 'Escalion';
    const PROVIDER_FHB = 'FHB';
    const PROVIDER_KHB = 'KHB';
    const PROVIDER_KHB_SZEP = 'KHBSZEP';
    const PROVIDER_MKB_SZEP = 'MKBSZEP';
    const PROVIDER_OTP = 'OTP';
    const PROVIDER_OTP_TWO_PARTY = 'OTP2';
    const PROVIDER_OTP_MULTIPONT = 'OTPMultipont';
    const PROVIDER_OTP_SIMPLE = 'OTPSimple';
    const PROVIDER_OTP_SIMPLE_WIRE = 'OTPSimpleWire';
    const PROVIDER_OTPAY = 'OTPay';
    const PROVIDER_OTPAY_MASTERPASS = 'OTPayMP';
    const PROVIDER_PAYPAL = 'PayPal';
    const PROVIDER_PAYSAFECARD = 'PSC';
    const PROVIDER_PAYU2 = 'PayU2';
    const PROVIDER_SAFERPAY = 'Saferpay';
    const PROVIDER_SMS = 'SMS';
    const PROVIDER_SOFORT = 'Sofort';
    const PROVIDER_UNICREDIT = 'UniCredit';
    const PROVIDER_WIRECARD_QPAY = 'QPAY';

    /** Default character encoding */
    const CHARACTER_ENCODING_DEFAULT = 'UTF-8';

    /** @var string */
    protected $storeName;

    /** @var string */
    protected $apiKey;

    /** @var string */
    protected $encryptionPublicKey;

    /** @var string */
    protected $characterEncoding = self::CHARACTER_ENCODING_DEFAULT;

    /** @var DriverInterface */
    protected $driver;

    /**
     * @param string $storeName
     * @param string $apiKey
     */
    public function __construct($storeName, $apiKey, DriverInterface $driver = null)
    {
        $this->storeName = $storeName;
        $this->apiKey = $apiKey;
        $this->driver = $driver ?: new RestDriver();
    }

    public function getStoreName()
    {
        return $this->storeName;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function getEncryptionPublicKey()
    {
        return $this->encryptionPublicKey;
    }

    public function setEncryptionPublicKey($key)
    {
        $this->encryptionPublicKey = $key;

        return $this;
    }

    public function isTestMode()
    {
        return $this->driver->isTestMode();
    }

    public function setTestMode($testMode = true)
    {
        $this->driver->setTestMode($testMode);

        return $this;
    }

    public function getCharacterEncoding()
    {
        return $this->characterEncoding;
    }

    public function setCharacterEncoding($encoding)
    {
        $this->characterEncoding = $encoding;

        return $this;
    }

    public function getDriver()
    {
        return $this->driver;
    }

    public function setDriver(DriverInterface $driver)
    {
        $this->driver = $driver;

        return $this;
    }

    public function request(RequestInterface $request)
    {
        return $this->driver->sendRequest($request);
    }

    public function requestAndRedirect(RedirectRequestInterface $request)
    {
        header('Location: ' . $this->driver->sendRequest($request)->getRedirectUrl());
        exit;
    }

    /**
     * Uppercase object properties
     *
     * @param object $object
     * @return void
     * @access protected
     * @static
     */
    public static function ucfirstProps($object) {
        foreach (get_object_vars($object) as $key => $value) {
            unset($object->{$key});

            $object->{ucfirst($key)} = $value;
        }
    }

    /**
     * Send request
     *
     * @param string $method
     * @param \BigFish\PaymentGateway\Request\RequestAbstract $request
     * @return \BigFish\PaymentGateway\Response
     * @access private
     * @static
     * @throws \BigFish\PaymentGateway\Exception
     */
    private static function sendRequest($method, Request $request) {
        switch (self::getConfig()->useApi) {
            case self::API_SOAP:
                return self::sendSoapRequest($method, $request);
            case self::API_REST:
                return self::sendRestRequest($method, $request);
            default:
                throw new Exception(sprintf('Invalid API type (%s)', self::getConfig()->useApi));
        }
    }

    /**
     * Send SOAP request
     *
     * @param string $method
     * @param \BigFish\PaymentGateway\Request\RequestAbstract $request
     * @return \BigFish\PaymentGateway\Response
     * @access private
     * @static
     * @throws \BigFish\PaymentGateway\Exception
     */
    private static function sendSoapRequest($method, Request $request) {
        if (!class_exists('SoapClient')) {
            throw new Exception('SOAP PHP module is not loaded');
        }

        $wsdl = self::getUrl() . '/api/soap/?wsdl';

        try {
            $client = new \SoapClient($wsdl, array(
                'soap_version' => SOAP_1_2,
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'exceptions' => true,
                'trace' => true,
                'login' => self::getConfig()->storeName,
                'password' => self::getConfig()->apiKey,
                'user_agent' => self::getUserAgent($method),
            ));

            self::prepareRequest($method, $request);

            $soapResult = $client->__call($method, array(array('request' => $request)));

            $soapResponse = $soapResult->{$method . 'Result'};

            self::ucfirstProps($soapResponse);

            return new Response($soapResponse);
        } catch (\SoapFault $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Send REST request
     *
     * @param string $method
     * @param \BigFish\PaymentGateway\Request\RequestAbstract $request
     * @return \BigFish\PaymentGateway\Response
     * @access private
     * @static
     * @throws \BigFish\PaymentGateway\Exception
     */
    private static function sendRestRequest($method, Request $request) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL PHP module is not loaded');
        }

        $url = self::getUrl() . '/api/rest/';

        self::prepareRequest($method, $request);

        $ch = curl_init();

        if (!$ch) {
            throw new Exception('cURL initialization error');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(self::getAuthorizationHeader()));

        if ($method == self::REQUEST_CLOSE || $method == self::REQUEST_REFUND) {
            /**
             * OTPay close and refund (extra timeout)
             *
             */
            curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        } else {
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        }

        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_REFERER, self::getHttpHost());

        $postData = array(
            'method' => $method,
            'json' => json_encode(get_object_vars($request)),
        );

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_USERAGENT, self::getUserAgent($method));

        $httpResponse = curl_exec($ch);

        if ($httpResponse === false) {
            $e = new Exception(sprintf('Communication error: %s', curl_error($ch)));

            curl_close($ch);

            throw $e;
        }

        curl_close($ch);

        return new Response($httpResponse);
    }

    /**
     * Prepare request
     *
     * @param string $method
     * @param \BigFish\PaymentGateway\Request\RequestAbstract $request
     * @return void
     * @access private
     * @static
     */
    private static function prepareRequest($method, Request $request) {
        $request->encodeValues();

        if ($method == self::REQUEST_INIT) {
            /** @var \BigFish\PaymentGateway\Request\Init $request */
            $request->setExtra();
        }

        if (self::getConfig()->useApi == self::API_REST) {
            self::ucfirstProps($request);
        }
    }

    /**
     * Get authorization header
     *
     * @return string
     * @access private
     * @static
     */
    private static function getAuthorizationHeader() {
        return 'Authorization: Basic ' . base64_encode(self::getConfig()->storeName . ':' . self::getConfig()->apiKey);
    }

    /**
     * Get user agent string
     *
     * @param string $method
     * @return string
     * @access private
     * @static
     */
    private static function getUserAgent($method) {
        switch (self::getConfig()->useApi) {
            case self::API_SOAP:
                $clientType = 'SOAP';
                break;
            case self::API_REST:
                $clientType = 'Rest';
                break;
        }

        return sprintf('BIG FISH Payment Gateway %s Client v%s (%s - %s)', $clientType, self::VERSION, $method, self::getHttpHost());
    }

    /**
     * Get HTTP host
     *
     * @return string
     * @access private
     * @static
     */
    private static function getHttpHost() {
        return $_SERVER['HTTP_HOST'];
    }

}
