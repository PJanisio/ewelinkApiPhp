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

    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
        $this->loadTokenData();

        if ($this->tokenData) {
            $this->httpClient->setTokenData($this->tokenData);
        }
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
    public function getToken(): array
    {
        $data = [
            'grantType'    => 'authorization_code',
            'code'         => $this->httpClient->getCode(),
            'redirectUrl'  => Constants::REDIRECT_URL,
        ];

        $this->tokenData = $this->httpClient->postRequest('/v2/user/oauth/token', $data);
        $this->writeTokenFileIfChanged();

        return $this->tokenData;
    }

    /**
     * Refresh the OAuth token using the refresh token.
     *
     * @return array The new token data.
     * @throws Exception If the request fails.
     */
    public function refreshToken()
    {
        if (empty($this->tokenData['refreshToken'] ?? null)) {
            throw new Exception('Refresh token not available. Call getToken() first.');
        }

        $data  = [
            'grantType' => 'refresh_token',
            'rt'        => (string) $this->tokenData['refreshToken'],
        ];
        $responseData = $this->httpClient->postRequest('/v2/user/refresh', $data);

        // Keep existing expiry times if the endpoint doesn’t return new ones
        $this->tokenData = [
            'accessToken'   => $responseData['at'] ?? $this->tokenData['accessToken'],
            'refreshToken'  => $responseData['rt'] ?? $this->tokenData['refreshToken'],
            'atExpiredTime' => $responseData['atExpiredTime'] ?? $this->tokenData['atExpiredTime'],
            'rtExpiredTime' => $responseData['rtExpiredTime'] ?? $this->tokenData['rtExpiredTime'],
        ];

        $this->writeTokenFileIfChanged();

        return $this->tokenData;
    }

    /**
     * Checks token validity and silently refreshes it if the access-token is stale
     * but the refresh-token is still good.
     */
    public function checkAndRefreshToken(): bool
    {
        if (!$this->tokenData) {
            return false;
        }

        $now = (int) (microtime(true) * 1000); // millis since epoch

        if ($now < $this->tokenData['atExpiredTime']) {
            return true;  // access-token still valid
        }

        if ($now < $this->tokenData['rtExpiredTime']) {
            $this->refreshToken(); // refresh silently
            return true;
        }

        return false; // both tokens expired
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
            file_put_contents($tokenFile, '', LOCK_EX);
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

    /**
     * Write `token.json` only when the contents have genuinely changed.
     * Saves I/O and prolongs SD-card life on Pi deployments.
     */
    private function writeTokenFileIfChanged() {
        $file = Constants::JSON_LOG_DIR . '/token.json';
        $newJson = json_encode($this->tokenData, JSON_UNESCAPED_SLASHES);

        $oldJson = file_exists($file) ? file_get_contents($file) : '';

        if ($newJson !== $oldJson) {
            file_put_contents($file, $newJson, LOCK_EX);
        }
         // Always keep HttpClient’s in-memory copy current
         $this->httpClient->setTokenData($this->tokenData);
    }
}
