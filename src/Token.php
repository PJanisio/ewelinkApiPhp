<?php

require_once __DIR__ . '/HttpClient.php';

class Token {
    private $httpClient;
    private $tokenData;
    private $state;

    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
        $this->state = 'ewelinkapiphp';
        $this->loadTokenData();
    }

    /**
     * Create a login URL for OAuth.
     *
     * @param string $state The state parameter for the OAuth flow.
     * @return string The constructed login URL.
     */
    public function createLoginUrl($state = null) {
        if ($state === null) {
            $state = $this->state;
        }
        $utils = new Utils();
        $seq = time() * 1000; // current timestamp in milliseconds
        $authorization = $utils->sign(Constants::APPID . '_' . $seq, Constants::APP_SECRET);
        $params = [
            'state' => $state,
            'clientId' => Constants::APPID,
            'authorization' => $authorization,
            'seq' => strval($seq),
            'redirectUrl' => Constants::REDIRECT_URL,
            'nonce' => $utils->generateNonce(),
            'grantType' => 'authorization_code' // default grant type
        ];

        $queryString = http_build_query($params);
        return "https://c2ccdn.coolkit.cc/oauth/index.html?" . $queryString;
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
     * Get the OAuth token using the authorization code.
     *
     * @return array The token data.
     * @throws Exception If the request fails.
     */
    public function getToken() {
        $data = [
            'grantType' => 'authorization_code',
            'code' => $this->httpClient->getCode(),
            'redirectUrl' => Constants::REDIRECT_URL
        ];
        $responseData = $this->httpClient->postRequest('/v2/user/oauth/token', $data);
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
        $responseData = $this->httpClient->postRequest('/v2/user/refresh', $data);
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
     * Get the stored token data.
     *
     * @return array|null The token data or null if not set.
     */
    public function getTokenData() {
        return $this->tokenData;
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
}
