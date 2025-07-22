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
use pjanisio\ewelinkapiphp\Utils;
use Exception;

class Home
{
    private $httpClient;
    private $familyData;
    private $currentFamilyId;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Fetch family data from the API and save to family.json.
     *
     * @param string $lang The language parameter (default: 'en').
     * @return array The family data.
     * @throws Exception If the request fails.
     */
    public function fetchFamilyData($lang = 'en')
    {
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

        file_put_contents(Config::get('JSON_LOG_DIR') . '/family.json', json_encode($this->familyData));
        return $this->familyData;
    }

    /**
     * Get the current family ID.
     *
     * @return string|null The current family ID.
     */
    public function getCurrentFamilyId()
    {
        return $this->currentFamilyId;
    }


    /**
     * Get all homes/families.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getHomes(): array
    {
        return array_map(
            static function ($home) {
                return [
                    'id'   => $home['id'],
                    'name' => $home['name'],
                ];
            },
            $this->familyData['familyList'] ?? []
        );
    }

    /**
     * Get all rooms for a given family/home ID.
     *
     * @param string $familyId  The family/home ID.
     * @return array<int, array{id: string, name: string, index: int}>  Array of rooms, or empty array if not found.
     */
    public function getRooms(string $familyId): array
    {
        if (!$this->familyData) {
            $this->fetchFamilyData();
        }

        foreach ($this->familyData['familyList'] as $home) {
            if ($home['id'] === $familyId) {
                // Return all room data, or [] if roomList missing
                return isset($home['roomList']) ? $home['roomList'] : [];
            }
        }
        return [];
    }
}
