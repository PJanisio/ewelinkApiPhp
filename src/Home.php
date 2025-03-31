<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

class Home {
    private $httpClient;
    private $familyData;
    private $currentFamilyId;

    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
    }

    /**
     * Fetch family data from the API and save to family.json.
     *
     * @param string $lang The language parameter (default: 'en').
     * @return array The family data.
     * @throws Exception If the request fails.
     */
    public function fetchFamilyData($lang = 'en') {
        $params = ['lang' => $lang];
        $response = $this->httpClient->getRequest('/v2/family', $params);

        if (isset($response['error']) && $response['error'] != 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error $errorCode: $errorMsg");
        }

        $this->familyData = [
            'familyList' => $response['familyList'],
            'currentFamilyId' => $response['currentFamilyId'],
            'hasChangedCurrentFamily' => $response['hasChangedCurrentFamily']
        ];

        $this->currentFamilyId = $this->familyData['currentFamilyId'] ?? null;

        file_put_contents(Constants::JSON_LOG_DIR . '/family.json', json_encode($this->familyData));
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
