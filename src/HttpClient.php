<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

 namespace pjanisio\ewelinkapiphp;

 use pjanisio\ewelinkapiphp\Config;
 use Exception;

class HttpClient
{
    private $loginUrl;
    private $code;
    private $region;
    private $authorization;
    private $home;
    private $token;
    private $devices;
    private $utils;

    public function __construct(array $configOverrides = [])
    {
        //load configuration via arguments
        if (!empty($configOverrides)) {
            Config::setOverrides($configOverrides);
        }
        $this->utils = new Utils();

        // Validate configuration
        $validationResults = $this->utils->validateConfig();
        $errors = [];

        foreach ($validationResults as $key => $result) {
            if (!isset($result['is_valid']) || !$result['is_valid']) {
                $msg = isset($result['message']) ? $result['message'] : 'Invalid config';
                $val = isset($result['value']) ? $result['value'] : 'n/a';
                $errors[] = "{$msg} — {$key}: {$val}";
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo "<p>{$error}</p>";
            }
            exit; //bail out
        }

        $this->region = Config::get('REGION'); // Assign region from Constants
        $this->loginUrl = $this->createLoginUrl('ewelinkapiphp'); // Default state

        list($this->code, $redirectRegion) = $this->utils->handleRedirect();
        if ($redirectRegion) {
            $this->region = $redirectRegion;
        }
        $this->token = new Token($this); // Initialize the Token class
        $this->home = null;
        $this->devices = null;
    }

    /**
     * Create a login URL for OAuth.
     *
     * @param string $state The state parameter for the OAuth flow.
     * @return string The constructed login URL.
     */
    public function createLoginUrl($state)
    {
        $seq = time() * 1000; // current timestamp in milliseconds
        $this->authorization = $this->utils->sign(Config::get('APPID') . '_' . $seq, Config::get('APP_SECRET'));
        $params = [
            'state' => $state,
            'clientId' => Config::get('APPID'),
            'authorization' => $this->authorization,
            'seq' => strval($seq),
            'redirectUrl' => Config::get('REDIRECT_URL'),
            'nonce' => $this->utils->generateNonce(),
            'grantType' => 'authorization_code' // default grant type
        ];

        $queryString = http_build_query($params);
        return "https://c2ccdn.coolkit.cc/oauth/index.html?" . $queryString;
    }


    /**
     * Get the login URL.
     *
     * @return string The login URL.
     */
    public function getLoginUrl()
    {
        return $this->loginUrl;
    }

    /**
     * Get the authorization code.
     *
     * @return string|null The authorization code.
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get the region.
     *
     * @return string|null The region.
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * Get the gateway URL based on the region.
     *
     * @return string The gateway URL.
     * @throws Exception If the region is invalid.
     */
    public function getGatewayUrl()
    {
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
     * Make a POST request.
     *
     * @param string $endpoint The endpoint to send the request to.
     * @param array $data The data to send in the request body.
     * @param bool $useTokenAuthorization Whether to use token-based authorization.
     * @return array The response data.
     * @throws Exception If there is an error in the request.
     */
    public function postRequest($endpoint, $data, $useTokenAuthorization = false)
    {
        $url = $this->getGatewayUrl() . $endpoint;
        $headers = [
            "Content-type: application/json; charset=utf-8",
            "X-CK-Appid: " . Config::get('APPID'),
            "X-CK-Nonce: " . $this->utils->generateNonce()
        ];

        if ($useTokenAuthorization) {
            $token = $this->token->getAccessToken();
            $headers[] = "Authorization: Bearer $token";
        } else {
            $payload = json_encode($data);
            $authorization = $this->utils->sign($payload, Config::get('APP_SECRET'));
            $headers[] = "Authorization: Sign $authorization";
        }

        $options = [
            'http' => [
                'header' => implode("\r\n", $headers),
                'method' => 'POST',
                'content' => json_encode($data),
            ],
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Error making POST request');
        }

        $result = json_decode($response, true);

        // Log debug information
        $backtrace = debug_backtrace();
        $callerClass = $backtrace[1]['class'] ?? 'N/A';
        $callerMethod = $backtrace[1]['function'] ?? 'N/A';
        $this->utils->debugLog(__CLASS__, __FUNCTION__, $data, $headers, $result, $callerClass, $callerMethod, $url);

        if ($result['error'] !== 0) {
            if ($result['error'] === 401) {
                $this->token->clearToken();
                $this->token->redirectToUrl($this->getLoginUrl(), 1);
            }
            $errorCode = $result['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error $errorCode: $errorMsg");
        }

        return $result['data'];
    }

    /**
     * Make an HTTP GET request with the given endpoint and token.
     *
     * @param string $endpoint The endpoint to make the request to.
     * @param array $params The query parameters to send in the request.
     * @param bool $useFullUrl Whether to use the full URL for the request.
     * @return array The response data.
     * @throws Exception If the request fails.
     */
    public function getRequest($endpoint, $params = [], $useFullUrl = false)
    {
        if ($useFullUrl) {
            $url = $endpoint . '?' . http_build_query($params);
        } else {
            $url = $this->getGatewayUrl() . $endpoint . '?' . http_build_query($params);
        }

        $headers = [
            "Authorization: Bearer " . $this->token->getAccessToken()
        ];

        $options = [
            'http' => [
                'header'  => implode("\r\n", $headers) . "\r\n",
                'method'  => 'GET',
            ],
        ];

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === false) {
            throw new Exception('Error in request');
        }

        $response = json_decode($result, true);

        // Log debug information
        $backtrace = debug_backtrace();
        $callerClass = $backtrace[1]['class'] ?? 'N/A';
        $callerMethod = $backtrace[1]['function'] ?? 'N/A';
        $this->utils->debugLog(__CLASS__, __FUNCTION__, $params, $headers, $response, $callerClass, $callerMethod, $url);

        if (isset($response['error']) && $response['error'] !== 0) {
            if ($response['error'] === 401) {
                $this->token->clearToken();
                $this->token->redirectToUrl($this->getLoginUrl(), 1);
            }
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error $errorCode: $errorMsg");
        }

        return $useFullUrl ? $response : $response['data'];
    }

     /**
     * Get the Token instance.
     *
     * @return Token The Token instance.
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Get the Devices instance.
     *
     * @return Devices The Devices instance.
     */
    public function getDevices()
    {
        if ($this->devices === null) {
            // Ensure we have a valid token first
            if ($this->token->checkAndRefreshToken()) {
                $this->devices = new Devices($this);
            } else {
                throw new Exception("Cannot initialize Devices without valid token");
            }
        }
        return $this->devices;
    }


    /**
     * Get the stored token data.
     *
     * @return array|null The token data or null if not set.
     */
    public function getTokenData()
    {
        return $this->token->getTokenData();
    }

    /**
     * Get the Home instance.
     *
     * @return Home The Home instance.
     */
    public function getHome()
    {
        if ($this->home === null) {
            $this->home = new Home($this);
        }
        return $this->home;
    }

    /**
     * Get the current family ID.
     *
     * @return string|null The current family ID.
     */
    public function getCurrentFamilyId()
    {
        return $this->home->getCurrentFamilyId();
    }
}
