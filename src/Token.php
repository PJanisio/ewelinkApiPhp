<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/Constants.php';

class Token {
    private $httpClient;
    private $tokenData;
    private $state;

    public function __construct(HttpClient $httpClient, $state = 'ewelinkapiphp') {
        $this->httpClient = $httpClient;
        $this->state = $state;
        $this->loadTokenData();
    }

    /**
     * Load token data from token.json file.
     */
    private function loadTokenData() {
        $tokenFile = Constants::JSON_LOG_DIR . '/token.json';
        if (file_exists($tokenFile)) {
            $this->tokenData = json_decode(file_get_contents($tokenFile), true);
        }
    }

    /**
     * Get the login URL.
     *
     * @return string The login URL.
     */
    public function getLoginUrl() {
        return $this->httpClient->getLoginUrl();
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
        file_put_contents(Constants::JSON_LOG_DIR . '/token.json', json_encode($this->tokenData));
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
        file_put_contents(Constants::JSON_LOG_DIR . '/token.json', json_encode($this->tokenData));
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
     * Clear the content of token.json file.
     */
    public function clearToken() {
        $tokenFile = Constants::JSON_LOG_DIR . '/token.json';
        if (file_exists($tokenFile)) {
            file_put_contents($tokenFile, '');
            $this->tokenData = null;
        }
    }

    /**
     * Redirect to a given URL after a delay.
     *
     * @param string $url The URL to redirect to.
     * @param int $delay The delay in seconds before redirecting.
     */
    public function redirectToUrl($url, $delay = 1) {
        echo '<p>You will be redirected in 1 second...</p>';
        echo '<meta http-equiv="refresh" content="' . $delay . ';url=' . htmlspecialchars($url) . '">';
    }
}
