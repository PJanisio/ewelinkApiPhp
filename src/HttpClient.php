<?php

require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/Constants.php';

class HttpClient {
    private $loginUrl;
    private $code;
    private $region;
    private $authorization;
    private $tokenData;
    private $familyData;

    public function __construct($state) {
        $this->region = Constants::REGION; // Assign region from Constants
        $this->loginUrl = $this->createLoginUrl($state);
        $this->handleRedirect();
        $this->loadTokenData();
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
     * Load token data from token.json file.
     */
    private function loadTokenData() {
        if (file_exists('token.json')) {
            $this->tokenData = json_decode(file_get_contents('token.json'), true);
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
    private function request($endpoint, $data = [], $method = 'POST') {
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
                'content' => $method === 'POST' ? $body : null,
            ],
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === FALSE) {
            throw new Exception('Error in request');
        }

        $response = json_decode($result, true);
        if ($response['error'] !== 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error $errorCode: $errorMsg");
        }

        return $response['data'];
    }

    /**
     * Make an HTTP GET request with the given endpoint and token.
     *
     * @param string $endpoint The endpoint to make the request to.
     * @param array $params The query parameters to send in the request.
     * @return array The response data.
     * @throws Exception If the request fails.
     */
    private function getRequest($endpoint, $params = []) {
        $url = $this->getGatewayUrl() . $endpoint . '?' . http_build_query($params);

        $headers = [
            "Authorization: Bearer " . $this->tokenData['accessToken']
        ];

        $options = [
            'http' => [
                'header'  => implode("\r\n", $headers) . "\r\n",
                'method'  => 'GET',
            ],
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === FALSE) {
            throw new Exception('Error in request');
        }

        $response = json_decode($result, true);
        if ($response['error'] !== 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error $errorCode: $errorMsg");
        }

        return $response['data'];
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
        $responseData = $this->request('/v2/user/oauth/token', $data, 'POST');
        $this->tokenData = $responseData;
        file_put_contents('token.json', json_encode($this->tokenData));
        return $this->tokenData;
    }

    /**
     * Refresh the OAuth token using the refresh token.
     *
     * @return array The new token data.
     * @throws Exception If the request fails.
     */
    public function refreshToken() {
        if (!$this->tokenData || !isset($this->tokenData['refreshToken'])) {
            throw new Exception('Refresh token not available. Please call getToken first.');
        }
        $data = [
            'grantType' => 'refresh_token',
            'rt' => strval($this->tokenData['refreshToken'])
        ];
        $responseData = $this->request('/v2/user/refresh', $data, 'POST');
        $this->tokenData = [
            'accessToken' => $responseData['at'],
            'refreshToken' => $responseData['rt'],
            'atExpiredTime' => $this->tokenData['atExpiredTime'],
            'rtExpiredTime' => $this->tokenData['rtExpiredTime']
        ];
        file_put_contents('token.json', json_encode($this->tokenData));
        return $this->tokenData;
    }

    /**
     * Check if the token is valid and refresh if necessary.
     *
     * @return bool True if the token is valid or successfully refreshed, false otherwise.
     */
    public function checkAndRefreshToken() {
        if (!$this->tokenData) {
            return false;
        }
        $currentTime = time() * 1000;
        if ($currentTime < $this->tokenData['atExpiredTime']) {
            return true;
        } elseif ($currentTime < $this->tokenData['rtExpiredTime']) {
            $this->refreshToken();
            return true;
        }
        return false;
    }

    /**
     * Get family data.
     *
     * @param string $lang The language parameter (default: 'en').
     * @return array The family data.
     * @throws Exception If the request fails.
     */
    public function getFamilyData($lang = 'en') {
        $params = ['lang' => $lang];
        $this->familyData = $this->getRequest('/v2/family', $params);
        file_put_contents('family.json', json_encode($this->familyData));
        return $this->familyData;
    }

    /**
     * Redirect to a given URL after a delay.
     *
     * @param string $url The URL to redirect to.
     * @param int $delay The delay in seconds before redirecting.
     */
    public function redirectToUrl($url, $delay = 2) {
        echo '<meta http-equiv="refresh" content="' . $delay . ';url=' . htmlspecialchars($url) . '">';
    }

    /**
     * Get the stored token data.
     *
     * @return array|null The token data or null if not set.
     */
    public function getTokenData() {
        return $this->tokenData;
    }

    /**
     * Get the stored family data.
     *
     * @return array|null The family data or null if not set.
     */
    public function getFamilyDataFromStorage() {
        return $this->familyData;
    }
}
