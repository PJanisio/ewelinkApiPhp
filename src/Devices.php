<?php

class Devices {
    private $devicesData;
    private $httpClient;
    private $home;

    /**
     * Constructor for the Devices class.
     * Initializes the class with an instance of HttpClient.
     * Ensures family data is fetched and devices data is loaded.
     * 
     * @param HttpClient $httpClient The HttpClient instance.
     * @throws Exception If devices data is not found.
     */
    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
        $this->home = $this->httpClient->getHome();
        $this->home->fetchFamilyData();
        $this->loadDevicesData();
        if ($this->devicesData === null) {
            $this->fetchDevicesData();
        }
    }

    /**
     * Loads devices data from devices.json file.
     */
    private function loadDevicesData() {
        if (file_exists('devices.json')) {
            $this->devicesData = json_decode(file_get_contents('devices.json'), true);
        } else {
            $this->devicesData = null;
        }
    }

    /**
     * Fetches devices data from the API and saves it to devices.json.
     *
     * @param string $lang The language parameter (default: 'en').
     * @return array The devices data.
     * @throws Exception If the request fails.
     */
    public function fetchDevicesData($lang = 'en') {
        $familyId = $this->home->getCurrentFamilyId();
        if (!$familyId) {
            throw new Exception('Current family ID is not set. Please call getFamilyData first.');
        }
        $params = [
            'lang' => $lang,
            'familyId' => $familyId
        ];
        $this->devicesData = $this->httpClient->getRequest('/v2/device/thing', $params);
        file_put_contents('devices.json', json_encode($this->devicesData));
        return $this->devicesData;
    }

    /**
     * Gets the stored devices data.
     *
     * @return array|null The devices data or null if not set.
     */
    public function getDevicesData() {
        return $this->devicesData;
    }

    /**
     * Gets a specific device by its ID.
     *
     * @param string $deviceId The ID of the device to retrieve.
     * @return array|null The device data or null if not found.
     */
    public function getDeviceById($deviceId) {
        if ($this->devicesData && isset($this->devicesData['thingList'])) {
            foreach ($this->devicesData['thingList'] as $device) {
                if ($device['itemData']['deviceid'] === $deviceId) {
                    return $device['itemData'];
                }
            }
        }
        return null;
    }

    /**
     * Creates a list of devices containing name, deviceid, productModel, online status, and channel support.
     * The list is an associative array with device names as keys.
     *
     * @return array The list of devices.
     */
    public function getDevicesList() {
        $devicesList = [];
        if ($this->devicesData && isset($this->devicesData['thingList'])) {
            foreach ($this->devicesData['thingList'] as $device) {
                $itemData = $device['itemData'];
                $devicesList[$itemData['name']] = [
                    'deviceid' => $itemData['deviceid'],
                    'productModel' => $itemData['productModel'],
                    'online' => $itemData['online'] == 1,
                    'isSupportChannelSplit' => $this->isMultiChannel($itemData['deviceid'])
                ];
            }
        }
        return $devicesList;
    }

    /**
     * Checks if a device supports multiple channels.
     *
     * @param string $deviceId The ID of the device to check.
     * @return bool True if the device supports multiple channels, false otherwise.
     */
    public function isMultiChannel($deviceId) {
        if ($this->devicesData && isset($this->devicesData['thingList'])) {
            foreach ($this->devicesData['thingList'] as $device) {
                if ($device['itemData']['deviceid'] === $deviceId && isset($device['itemData']['isSupportChannelSplit'])) {
                    return $device['itemData']['isSupportChannelSplit'] == 1;
                }
            }
        }
        return false;
    }

    /**
     * Searches for a specific parameter within a device's data.
     *
     * @param string $searchKey The key to search for.
     * @param string $deviceId The ID of the device to search within.
     * @return mixed The value of the found parameter or null if not found.
     */
    public function searchDeviceParam($searchKey, $deviceId) {
        if ($this->devicesData && isset($this->devicesData['thingList'])) {
            foreach ($this->devicesData['thingList'] as $device) {
                if ($device['itemData']['deviceid'] === $deviceId) {
                    if (isset($device['itemData'][$searchKey])) {
                        return $device['itemData'][$searchKey];
                    } else {
                        foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($device['itemData'])) as $key => $value) {
                            if ($key === $searchKey) {
                                return $value;
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Gets live device parameter using the API.
     *
     * @param string $deviceId The ID of the device.
     * @param string $param The parameter to get.
     * @param int $type The type (default is 1).
     * @return mixed The specific parameter value from the API response or null if not found.
     */
    public function getDeviceParamLive($deviceId, $param, $type = 1) {
        $endpoint = '/v2/device/thing/status';
        $queryParams = [
            'id' => $deviceId,
            'type' => $type,
            'params' => urlencode($param)
        ];

        $response = $this->httpClient->getRequest($endpoint, $queryParams);

        if (isset($response['error']) && $response['error'] != 0) {
            throw new Exception('Error: ' . $response['msg']);
        }

        if (isset($response['params'][$param])) {
            return $response['params'][$param];
        }

        return null;
    }

    /**
     * Sets the device status by updating a parameter.
     *
     * @param string $deviceId The ID of the device.
     * @param array $params The parameters to update.
     * @return string The result message.
     * @throws Exception If the parameter update fails.
     */
    public function setDeviceStatus($deviceId, $params) {
        $currentValue = $this->getDeviceParamLive($deviceId, 'switches');
        if ($currentValue === null) {
            return "Device $deviceId does not have any switches.";
        }

        $isMultiChannel = $this->isMultiChannel($deviceId);
        $allSet = true;
        $messages = [];

        if ($isMultiChannel) {
            foreach ($params as $param) {
                $found = false;
                foreach ($currentValue as &$currentSwitch) {
                    if ($currentSwitch['outlet'] == $param['outlet']) {
                        $found = true;
                        if ($currentSwitch['switch'] != $param['switch']) {
                            $currentSwitch['switch'] = $param['switch'];
                            $allSet = false;
                        } else {
                            $messages[] = "Parameter switch for outlet {$param['outlet']} is already set to {$param['switch']} for device $deviceId.";
                        }
                    }
                }
                if (!$found) {
                    return "Parameter switch for outlet {$param['outlet']} does not exist for device $deviceId.";
                }
            }
        } else {
            foreach ($params as $key => $value) {
                if (isset($currentValue[$key]) && $currentValue[$key] != $value) {
                    $currentValue[$key] = $value;
                    $allSet = false;
                } else {
                    $messages[] = "Parameter $key is already set to $value for device $deviceId.";
                }
            }
        }

        if ($allSet) {
            return implode("\n", $messages);
        }

        $data = [
            'type' => 1,
            'id' => $deviceId,
            'params' => ['switches' => $currentValue]
        ];

        $response = $this->httpClient->postRequest('/v2/device/thing/status', $data, true);

        $updatedValue = $this->getDeviceParamLive($deviceId, 'switches');

        if ($isMultiChannel) {
            foreach ($params as $param) {
                foreach ($updatedValue as $updatedSwitch) {
                    if ($updatedSwitch['outlet'] == $param['outlet']) {
                        if ($updatedSwitch['switch'] != $param['switch']) {
                            return "Failed to update parameter switch to {$param['switch']} for outlet {$param['outlet']} for device $deviceId.";
                        }
                    }
                }
            }
        } else {
            foreach ($params as $key => $value) {
                if ($updatedValue[$key] != $value) {
                    return "Failed to update parameter $key to $value for device $deviceId. Current value is {$updatedValue[$key]}.";
                }
            }
        }

        return "Parameters successfully updated for device $deviceId.";
    }

    /**
     * Checks if a device is online.
     *
     * @param string $identifier The device ID or name.
     * @return bool True if the device is online, false otherwise.
     */
    public function isOnline($identifier) {
        $this->fetchDevicesData();
        $deviceList = $this->getDevicesList();

        foreach ($deviceList as $name => $device) {
            if ($device['deviceid'] === $identifier || $name === $identifier) {
                return $device['online'];
            }
        }

        return false;
    }
}
