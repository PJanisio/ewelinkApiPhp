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
    private $currentFamilyId;

    public function __construct() {
        $utils = new Utils();
        $this->region = Constants::REGION; // Assign region from Constants
        
        list($this->code, $redirectRegion) = $utils->handleRedirect();
        if ($redirectRegion) {
            $this->region = $redirectRegion;
        }
        $this->loadTokenData();
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
     * Make a POST request.
     *
     * @param string $endpoint The endpoint to send the request to.
     * @param array $data The data to send in the request body.
     * @param bool $useTokenAuthorization Whether to use token-based authorization.
     * @return array The response data.
     * @throws Exception If there is an error in the request.
     */
    public function postRequest($endpoint, $data, $useTokenAuthorization = false) {
        $utils = new Utils();
        $url = $this->getGatewayUrl() . $endpoint;
        $headers = [
            "Content-type: application/json; charset=utf-8",
            "X-CK-Appid: " . Constants::APPID,
            "X-CK-Nonce: " . $utils->generateNonce()
        ];

        if ($useTokenAuthorization) {
            $token = $this->tokenData['accessToken'];
            $headers[] = "Authorization: Bearer $token";
        } else {
            $authorization = $utils->sign(json_encode($data), Constants::APP_SECRET);
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

        if ($response === FALSE) {
            throw new Exception('Error making POST request');
        }

        $result = json_decode($response, true);

        if ($result['error'] !== 0) {
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
     * @return array The response data.
     * @throws Exception If the request fails.
     */
    public function getRequest($endpoint, $params = []) {
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
        if (isset($response['error']) && $response['error'] !== 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error $errorCode: $errorMsg");
        }

        return $response['data'];
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
        $this->currentFamilyId = $this->familyData['currentFamilyId'] ?? null;
        file_put_contents('family.json', json_encode($this->familyData));
        return $this->familyData;
    }

    /**
     * Get the stored family data.
     *
     * @return array|null The family data or null if not set.
     */
    public function getFamilyDataFromStorage() {
        return $this->familyData;
    }

    /**
     * Get the current family ID.
     *
     * @return string|null The current family ID.
     */
    public function getCurrentFamilyId() {
        return $this->currentFamilyId;
    }
}
