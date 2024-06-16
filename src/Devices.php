<?php

class Devices {
    private $devicesData;
    private $httpClient;
    private $home;

    /**
     * Constructor for the Devices class.
     * 
     * @param HttpClient $httpClient An instance of HttpClient to be used for API requests.
     * @throws Exception If family data is not set.
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
     * Load devices data from the devices.json file.
     */
    private function loadDevicesData() {
        if (file_exists('devices.json')) {
            $this->devicesData = json_decode(file_get_contents('devices.json'), true);
        } else {
            $this->devicesData = null;
        }
    }

    /**
     * Fetch devices data from the API and save to devices.json.
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
     * Get the stored devices data.
     * 
     * @return array|null The devices data or null if not set.
     */
    public function getDevicesData() {
        return $this->devicesData;
    }

    /**
     * Get a specific device by its ID.
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
     * Create a list of devices containing name, deviceid, productModel, online status, channel support, and switch status.
     * The list is an associative array with device names as keys.
     * 
     * @return array The list of devices.
     */
    public function getDevicesList() {
        $devicesList = [];
        if ($this->devicesData && isset($this->devicesData['thingList'])) {
            foreach ($this->devicesData['thingList'] as $device) {
                $itemData = $device['itemData'];
                $deviceStatus = [
                    'deviceid' => $itemData['deviceid'],
                    'productModel' => $itemData['productModel'],
                    'online' => $itemData['online'] == 1,
                    'isSupportChannelSplit' => $this->isMultiChannel($itemData['deviceid'])
                ];
                
                // Get current switch status
                $statusParam = $this->isMultiChannel($itemData['deviceid']) ? 'switches' : 'switch';
                $switchStatus = $this->getDeviceParamLive($itemData['deviceid'], $statusParam);
                $deviceStatus[$statusParam] = $switchStatus;

                $devicesList[$itemData['name']] = $deviceStatus;
            }
        }
        return $devicesList;
    }

    /**
     * Check if a device supports multiple channels.
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
     * Search for a specific parameter within a device's data.
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
     * Get live device parameter using the API.
     * 
     * @param string $deviceId The ID of the device.
     * @param string $param The parameter to get.
     * @param int $type The type (default is 1).
     * @return mixed The specific parameter value from the API response or null if not found.
     * @throws Exception If there is an error in the request.
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
     * Set the device status by updating parameters.
     * 
     * @param string $deviceId The ID of the device.
     * @param array $params The parameters to update.
     * @return string The result message.
     * @throws Exception If the parameter update fails.
     */
    public function setDeviceStatus($deviceId, $params) {
        $device = $this->getDeviceById($deviceId);
        $currentParams = $device['params'] ?? null;

        if ($currentParams === null) {
            return "Device $deviceId does not have any parameters to update.";
        }

        $isMultiChannel = $this->isMultiChannel($deviceId);
        $allSet = true;
        $messages = [];
        $updatedParams = [];

        if (!is_array(reset($params))) {
            $params = [$params];
        }

        foreach ($params as $param) {
            if ($isMultiChannel) {
                $outlet = $param['outlet'];
                foreach ($param as $key => $value) {
                    if ($key == 'outlet') continue;
                    if (isset($currentParams['switches']) && is_array($currentParams['switches'])) {
                        $found = false;
                        foreach ($currentParams['switches'] as &$switch) {
                            if ($switch['outlet'] == $outlet) {
                                $found = true;
                                if ($switch[$key] != $value) {
                                    $switch[$key] = $value;
                                    $allSet = false;
                                    $updatedParams['switches'] = $currentParams['switches'];
                                } else {
                                    $messages[] = "Parameter $key for outlet $outlet is already set to $value for device $deviceId.";
                                }
                                break;
                            }
                        }
                        if (!$found) {
                            return "Outlet $outlet does not exist for device $deviceId.";
                        }
                    }
                }
            } else {
                foreach ($param as $key => $value) {
                    if (!array_key_exists($key, $currentParams)) {
                        return "Parameter $key does not exist for device $deviceId.";
                    }

                    if ($currentParams[$key] != $value) {
                        $currentParams[$key] = $value;
                        $allSet = false;
                        $updatedParams[$key] = $value;
                    } else {
                        $messages[] = "Parameter $key is already set to $value for device $deviceId.";
                    }
                }
            }
        }

        if ($allSet) {
            return implode("\n", $messages);
        }

        $data = [
            'type' => 1,
            'id' => $deviceId,
            'params' => $updatedParams
        ];

        $response = $this->httpClient->postRequest('/v2/device/thing/status', $data, true);

        foreach ($updatedParams as $key => $value) {
            $updatedValue = $this->getDeviceParamLive($deviceId, $key);
            if ($updatedValue != $value) {
                return "Failed to update parameter $key to $value for device $deviceId. Current value is $updatedValue.";
            }
        }

        return "Parameters successfully updated for device $deviceId.";
    }

    /**
     * Check if a device is online.
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

    /**
     * Get device history using the API.
     * 
     * @param string $deviceId The ID of the device.
     * @return array The device history.
     * @throws Exception If there is an error in the request.
     */
    public function getDeviceHistory($deviceId) {
        $endpoint = '/v2/device/history';
        $queryParams = ['deviceid' => $deviceId];

        $response = $this->httpClient->getRequest($endpoint, $queryParams);

        if (isset($response['error']) && $response['error'] != 0) {
            throw new Exception('Error: ' . $response['msg']);
        }

        return $response;
    }
}
