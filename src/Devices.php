<?php

class Devices {
    private $devicesData;
    private $httpClient;

    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
        if (file_exists('devices.json')) {
            $this->devicesData = json_decode(file_get_contents('devices.json'), true);
        }
    }

    /**
     * Load devices data from devices.json file.
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
        $familyId = $this->httpClient->getCurrentFamilyId();
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
     * Create a list of devices containing name, deviceid, productModel, online status, and channel support.
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
     * Set the device status by updating a parameter.
     *
     * @param string $deviceId The ID of the device.
     * @param array $params The parameters to update.
     * @return string The result message.
     * @throws Exception If the parameter update fails.
     */
    public function setDeviceStatus($deviceId, $params) {
        // Check if the parameter exists and get the current value
        foreach ($params as $param => $newValue) {
            $currentValue = $this->getDeviceParamLive($deviceId, $param);
            if ($currentValue === null) {
                return "Parameter $param does not exist for device $deviceId.";
            }

            // Compare the current value with the new value
            if ($currentValue == $newValue) {
                return "Parameter $param is already set to $newValue for device $deviceId.";
            }

            // Update the parameter
            $data = [
                'type' => 1,
                'id' => $deviceId,
                'params' => [$param => $newValue]
            ];
            $response = $this->httpClient->postRequest('/v2/device/thing/status', $data, true);

            // Verify the update
            $updatedValue = $this->getDeviceParamLive($deviceId, $param);
            if ($updatedValue == $newValue) {
                return "Parameter $param successfully updated to $newValue for device $deviceId.";
            } else {
                return "Failed to update parameter $param to $newValue for device $deviceId. Current value is $updatedValue.";
            }
        }
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
}
