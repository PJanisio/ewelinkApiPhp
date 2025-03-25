<?php
/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */



require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/Token.php';
require_once __DIR__ . '/Devices.php';
require_once __DIR__ . '/Constants.php';
require_once __DIR__ . '/Utils.php';

class EweLinkApiPhp
{
    private $httpClient; // Wraps all HTTP requests + region
    private $token;      // Wraps token logic (getToken, refreshToken, etc.)

    /**
     * In constructor, instantiate HttpClient and Token internally.
     */
    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->token      = new Token($this->httpClient);
    }

    /**
     * Make it easier to handle OAuth code/region from $_GET automatically.
     * If we detect we have code/region, we call getToken() right away, etc.
     * 
     * Returns true if we now have a valid token, false otherwise.
     */
    public function handleAuthorization()
    {
        // If code & region were set, that means user is redirecting back from OAuth
        if (isset($_GET['code']) && isset($_GET['region'])) {
            try {
                $tokenData = $this->token->getToken(); // get & save token
                // Optionally redirect away from the code=... URL to avoid confusion
                $this->token->redirectToUrl(Constants::REDIRECT_URL);
                return true; // we have a valid token now
            } catch (Exception $e) {
                echo 'Error: ' . $e->getMessage();
                return false;
            }
        } else {
            // If no code/region, try to use an existing token (refresh if needed)
            if ($this->token->checkAndRefreshToken()) {
                return true; // token is valid or got refreshed
            } else {
                // token is invalid/no token, user must go to login
                return false;
            }
        }
    }

    /**
     * Expose the login URL (for when we have no valid token).
     */
    public function getLoginUrl()
    {
        return $this->token->getLoginUrl();
    }

    /**
     * Let users retrieve the token data if needed.
     */
    public function getTokenData()
    {
        return $this->token->getTokenData();
    }

    /**
     * A convenience method to return a new `Devices` object, 
     * initialized with our same $httpClient.
     */
    public function getDevices()
    {
        return new Devices($this->httpClient);
    }

    // Optionally expose other pieces like $this->httpClient->getHome() or so,
    // or create convenience methods here that wrap the underlying calls.
}
