<?php

require_once 'Utils.php';
require_once 'Constants.php';

class HttpClient {
    private $loginUrl;
    private $code;
    private $region;
    private $authorization;

    public function __construct($state) {
        $this->loginUrl = $this->createLoginUrl($state);
        $this->handleRedirect();
    }

    private function createLoginUrl($state) {
        $utils = new Utils();
        $seq = time() * 1000; // current timestamp in milliseconds
        $this->authorization = $this->sign(Constants::APPID . '_' . $seq, Constants::APP_SECRET);
        $params = [
            'state' => $state,
            'clientId' => Constants::APPID,
            'authorization' => $this->authorization,
            'seq' => strval($seq),
            'redirectUrl' => Constants::REDIRECT_URL,
            'nonce' => $utils->generateNonce(),
            'grantType' => 'authorization_code' // default grant type
        ];

        $queryString = http_build_query($params);
        return "https://c2ccdn.coolkit.cc/oauth/index.html?" . $queryString;
    }

    private function sign($data, $secret) {
        $hash = hash_hmac('sha256', $data, $secret, true);
        return base64_encode($hash);
    }

    private function handleRedirect() {
        if (isset($_GET['code']) && isset($_GET['region'])) {
            $this->code = $_GET['code'];
            $this->region = $_GET['region'];
        }
    }

    public function getLoginUrl() {
        return $this->loginUrl;
    }

    public function getCode() {
        return $this->code;
    }

    public function getRegion() {
        return $this->region;
    }

    public function getGatewayUrl() {
        switch ($this->region) {
            case 'cn':
                return 'https://cn-apia.coolkit.cn';
            case 'as':
                return 'https://as-apia.coolkit.cc';
            case 'us':
                return 'https://us-apia.coolkit.cc';
            case 'eu':
                return 'https://eu-apia.coolkit.cc';
            default:
                throw new Exception('Invalid region');
        }
    }

    public function getToken() {
        $url = $this->getGatewayUrl() . '/v2/user/oauth/token';
        $data = [
            'grantType' => 'authorization_code',
            'code' => $this->getCode(),
            'redirectUrl' => Constants::REDIRECT_URL
        ];

        $utils = new Utils();
        $nonce = $utils->generateNonce();
        $body = json_encode($data);
        $authorization = 'Sign ' . $this->sign($body, Constants::APP_SECRET);
        $headers = [
            "Content-type: application/json; charset=utf-8",
            "X-CK-Appid: " . Constants::APPID,
            "Authorization: " . $authorization,
            "X-CK-Nonce: " . $nonce
        ];

        $options = [
            'http' => [
                'header'  => implode("\r\n", $headers) . "\r\n",
                'method'  => 'POST',
                'content' => $body,
            ],
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === FALSE) {
            throw new Exception('Error getting token');
        }

        return json_decode($result, true);
    }
}

