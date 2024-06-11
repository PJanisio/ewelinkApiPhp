<?php

require_once 'Utils.php';
require_once 'Constants.php';

class HttpClient {
    private $loginUrl;
    private $code;
    private $region;
    private $authorization;
    private $tokenData;

    public function __construct($state) {
        $this->loginUrl = $this->createLoginUrl($state);
        $this->handleRedirect();
    }

    /**
     * Create a login URL for OAuth.
     *
     * @param string $state The state parameter for the OAuth flow.
     * @return string The constructed login URL.
     */
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

    /**
     * Sign the data using HMAC-SHA256 and return a base64 encoded string.
     *
     * @param string $data The data to be signed.
     * @param string $secret The secret key used for signing.
     * @return string The base64 encoded signature.
     */
    private function sign($data, $secret) {
        $hash = hash_hmac('sha256', $data, $secret, true);
        return base64_encode($hash);
    }

    /**
     * Handle redirect and capture code and region from URL.
     */
    private function handleRedirect() {
        if (isset($_GET['code']) && isset($_GET['region'])) {
            $this->code = $_GET['code'];
            $this->region = $_GET['region'];
        }
    }

    /**
     * Get the login URL.
     *
     * @return string The login URL.
     */
    public function getLoginUrl() {
        return $this->loginUrl;
    }

    /**
     * Get the authorization code.
     *
     * @return string|null The authorization code.
     */
    public function getCode() {
        return $this->code;
    }

    /**
     * Get the region.
     *
     * @return string|null The region.
     */
    public function getRegion() {
        return $this->region;
    }

    /**
     * Get the gateway URL based on the region.
     *
     * @return string The gateway URL.
     * @throws Exception If the region is invalid.
     */
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

    /**
     * Make an HTTP request with the given endpoint and data.
     *
     * @param string $endpoint The endpoint to make the request to.
     * @param array $data The data to send in the request.
     * @param string $method The HTTP method to use (default is 'POST').
     * @return array The response data.
     * @throws Exception If the request fails.
     */
    private function request($endpoint, $data, $method = 'POST') {
        $url = $this->getGatewayUrl() . $endpoint;
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
                'method'  => $method,
                'content' => $body,
            ],
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === FALSE) {
            throw new Exception('Error in request');
        }

        return json_decode($result, true);
    }

    /**
     * Get the OAuth token using the authorization code.
     *
     * @return array The token data.
     * @throws Exception If the request fails.
     */
    public function getToken() {
        $data = [
            'grantType' => 'authorization_code',
            'code' => $this->getCode(),
            'redirectUrl' => Constants::REDIRECT_URL
        ];
        $this->tokenData = $this->request('/v2/user/oauth/token', $data, 'POST');
        file_put_contents('token.json', json_encode($this->tokenData));
        return $this->tokenData;
    }

    /**
     * Get the stored token data.
     *
     * @return array|null The token data or null if not set.
     */
    public function getTokenData() {
        return $this->tokenData;
    }
}
