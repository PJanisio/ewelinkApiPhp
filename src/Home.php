<?php

require_once __DIR__ . '/HttpClient.php';

class Home {
    private $httpClient;
    private $familyData;
    private $currentFamilyId;

    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
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
        $response = $this->httpClient->getRequest('/v2/family', $params);

        if (isset($response['error']) && $response['error'] != 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error $errorCode: $errorMsg");
        }

        $this->familyData = $response;
        $this->currentFamilyId = $this->familyData['currentFamilyId'] ?? null;
        file_put_contents('family.json', json_encode($this->familyData));
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
