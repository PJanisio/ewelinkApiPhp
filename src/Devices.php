<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */
 

require_once __DIR__ . '/WebSocketClient.php';
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/Constants.php';

class Devices {
    private $devicesData;
    private $httpClient;
    private $home;
    private $wsClient;

    /**
     * Constructor for the Devices class.
     *
     * @param HttpClient $httpClient The HTTP client instance.
     */
    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
        $this->home = new Home($httpClient);
        $this->home->fetchFamilyData();
        //$this->loadDevicesData();
        if ($this->devicesData === null) {
            $this->fetchDevicesData();
        }
    }

    /**
     * Load devices data from a local JSON file.
     */
    private function loadDevicesData() {
        $devicesFile = Constants::JSON_LOG_DIR . '/devices.json';
        if (file_exists($devicesFile)) {
            $this->devicesData = json_decode(file_get_contents($devicesFile), true);
        } else {
            $this->devicesData = null;
        }
    }

    /**
     * Fetch devices data WITHOUT using familyId and store in $this->devicesData.
     * This fully replaces loadDevicesData() for your use-case.
     *
     * @param string $lang The language parameter for the request (default is 'en').
     * @return array The devices data.
     */

     public function fetchDevicesData($lang = 'en') {
        // Make a request but do NOT include 'familyId'
        $params = [
            'lang' => $lang
        ];
        
        $rawData = $this->httpClient->getRequest('/v2/device/thing', $params);
        $this->devicesData = $rawData;
        file_put_contents(Constants::JSON_LOG_DIR . '/devices.json', json_encode($this->devicesData, JSON_PRETTY_PRINT));

        return $this->devicesData;
    }


    /*
     * (Old approach) Fetch devices data from the remote server and save it locally.
     *
     * @param string $lang The language parameter for the request (default is 'en').
     * @return array The devices data.
     * @throws Exception If the current family ID is not set.
     */

     /*
    public function fetchDevicesDataFamily($lang = 'en') {
        $familyId = $this->home->getCurrentFamilyId();
        if (!$familyId) {
            $errorCode = 'NO_FAMILY_ID'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }
        $params = [
            'lang' => $lang,
            'familyId' => $familyId
        ];
        $this->devicesData = $this->httpClient->getRequest('/v2/device/thing', $params);
        file_put_contents(Constants::JSON_LOG_DIR . '/devices.json', json_encode($this->devicesData));
        return $this->devicesData;
    }
    */

    /**
     * Get the loaded devices data.
     *
     * @return array|null The devices data.
     */
    public function getDevicesData() {
        return $this->devicesData;
    }

    /**
     * Get device data by device ID.
     *
     * @param string $deviceId The device ID.
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
     * Get a list of all devices with their status.
     *
     * @return array The list of devices with their status.
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
                
                $statusParam = $this->isMultiChannel($itemData['deviceid']) ? 'switches' : 'switch';
                $switchStatus = $this->getDeviceParamLive($itemData['deviceid'], $statusParam);
                $deviceStatus[$statusParam] = $switchStatus;

                $devicesList[$itemData['name']] = $deviceStatus;
            }
        }
        return $devicesList;
    }

    /**
     * Helper method to get device ID by name or return the ID if already provided.
     *
     * @param string $identifier The device name or ID.
     * @return string|null The device ID or null if not found.
     */
    private function getDeviceIdByIdentifier($identifier) {
        if (isset($this->devicesData['thingList'])) {
            foreach ($this->devicesData['thingList'] as $device) {
                if ($device['itemData']['deviceid'] === $identifier || $device['itemData']['name'] === $identifier) {
                    return $device['itemData']['deviceid'];
                }
            }
        }
        return null;
    }

    /**
     * Check if a device supports multiple channels.
     *
     * @param string $identifier The device name or ID.
     * @return bool True if the device supports multiple channels, false otherwise.
     */
    public function isMultiChannel($identifier) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if ($deviceId && isset($this->devicesData['thingList'])) {
            foreach ($this->devicesData['thingList'] as $device) {
                if ($device['itemData']['deviceid'] === $deviceId && isset($device['itemData']['isSupportChannelSplit'])) {
                    return $device['itemData']['isSupportChannelSplit'] == 1;
                }
            }
        }
        return false;
    }

    /**
     * Search for a specific parameter in the device data.
     *
     * @param string $searchKey The parameter key to search for.
     * @param string $identifier The device name or ID.
     * @return mixed|null The parameter value if found, null otherwise.
     */
    public function searchDeviceParam($searchKey, $identifier) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if ($deviceId && isset($this->devicesData['thingList'])) {
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
     * Get live parameters of a device.
     *
     * @param string $identifier The device name or ID.
     * @param string|array $param The parameter(s) to get.
     * @param int $type The type of the request (default is 1).
     * @return mixed The live parameter(s) value(s).
     * @throws Exception If there is an error in the response.
     */
    public function getDeviceParamLive($identifier, $param, $type = 1) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if (!$deviceId) {
            throw new Exception("Device not found.");
        }
        $endpoint = '/v2/device/thing/status';
        if (is_array($param)) {
            $paramString = implode('|', $param);
        } else {
            $paramString = $param;
        }
        $queryParams = [
            'id' => $deviceId,
            'type' => $type,
            'params' => $paramString
        ];

        $response = $this->httpClient->getRequest($endpoint, $queryParams);

        if (isset($response['error']) && $response['error'] != 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error: $errorMsg");
        }

        $responseParams = $response['params'] ?? [];

        if (is_array($param)) {
            $result = [];
            foreach ($param as $p) {
                $result[$p] = $responseParams[$p] ?? 0; // Default to 0 if the parameter is missing
            }
            return $result;
        } else {
            return $responseParams[$param] ?? 0; // Default to 0 if the parameter is missing
        }
    }


    /**
     * Get all live parameters of a device.
     *
     * @param string $identifier The device name or ID.
     * @param int $type The type of the request (default is 1).
     * @return array|null The live parameters or null if not found.
     * @throws Exception If there is an error in the response.
     */
    public function getAllDeviceParamLive($identifier, $type = 1) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if (!$deviceId) {
            throw new Exception("Device not found.");
        }
        $endpoint = '/v2/device/thing/status';
        $queryParams = [
            'id' => $deviceId,
            'type' => $type
        ];

        $response = $this->httpClient->getRequest($endpoint, $queryParams);

        if (isset($response['error']) && $response['error'] != 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error: $errorMsg");
        }

        if (isset($response['params'])) {
            return $response['params'];
        }

        return null;
    }

    /**
     * Set the status of a device.
     *
     * @param string $identifier The device name or ID.
     * @param array $params The parameters to set.
     * @param int $returnText The flag to determine the return type (1 for detailed report, 0 for boolean).
     * @return mixed The result of the status update, either a string or a boolean.
     * @throws Exception If there is an error in the response or if a parameter does not exist.
     */
    public function setDeviceStatus($identifier, $params, $returnText = 1) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if (!$deviceId) {
            throw new Exception("Device not found.");
        }
        $device = $this->getDeviceById($deviceId);
        $currentParams = $this->getAllDeviceParamLive($deviceId) ?? null;

        if ($currentParams === null) {
            return $returnText ? "Device $deviceId does not have any parameters to update." : false;
        }

        $isMultiChannel = $this->isMultiChannel($deviceId);
        $allSet = true;
        $messages = [];
        $updatedParams = [];
        $changes = [];

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
                                if (is_numeric($value) && is_string($value)) {
                                    $messages[] = "Warning: Parameter $key value is numeric but given as a string. You may want to use an integer for device $deviceId.";
                                }
                                if ($switch[$key] != $value) {
                                    $changes[] = "For device $deviceId, parameter $key for outlet $outlet has changed from {$switch[$key]} to $value.";
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
                            return $returnText ? "Outlet $outlet does not exist for device $deviceId." : false;
                        }
                    }
                }
            } else {
                foreach ($param as $key => $value) {
                    if (!array_key_exists($key, $currentParams)) {
                        return $returnText ? "Parameter $key does not exist for device $deviceId." : false;
                    }

                    if (is_numeric($value) && is_string($value)) {
                        $messages[] = "Warning: Parameter $key value is numeric but given as a string. You may want to use an integer for device $deviceId.";
                    }

                    if ($currentParams[$key] != $value) {
                        $changes[] = "For device $deviceId, parameter $key has changed from {$currentParams[$key]} to $value.";
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
            return $returnText ? implode("\n", $messages) : true;
        }

        $data = [
            'type' => 1,
            'id' => $deviceId,
            'params' => $updatedParams
        ];

        $response = $this->httpClient->postRequest('/v2/device/thing/status', $data, true);

        foreach ($updatedParams as $key => $value) {
            $updatedValue = $this->getDeviceParamLive($deviceId, [$key]);
            if ($updatedValue[$key] != $value) {
                return $returnText ? "Failed to update parameter $key to $value for device $deviceId. Current value is $updatedValue[$key]." : false;
            }
        }

        if ($returnText) {
            return "Parameters successfully updated for device $deviceId.\n" . implode("\n", $changes) . "\n" . implode("\n", $messages);
        } else {
            return true;
        }
    }

    /**
     * Check if a device is online.
     *
     * @param string $identifier The device name or ID.
     * @return bool True if the device is online, false otherwise.
     */
    public function isOnline($identifier) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if (!$deviceId) {
            return false;
        }
        if ($this->devicesData && isset($this->devicesData['thingList'])) {
            foreach ($this->devicesData['thingList'] as $device) {
                if ($device['itemData']['deviceid'] === $deviceId) {
                    return $device['itemData']['online'] == 1;
                }
            }
        }
        return false;
    }

    /**
     * Get the history of a device.
     *
     * @param string $identifier The device name or ID.
     * @return array The device history.
     * @throws Exception If there is an error in the response.
     */
    public function getDeviceHistory($identifier) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if (!$deviceId) {
            throw new Exception("Device not found.");
        }
        $endpoint = '/v2/device/history';
        $queryParams = ['deviceid' => $deviceId];

        $response = $this->httpClient->getRequest($endpoint, $queryParams);

        if (isset($response['error']) && $response['error'] != 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error: $errorMsg");
        }

        return $response;
    }
    
    /**
     * Initialize WebSocket connection and perform handshake.
     *
     * @param string $identifier The device name or ID.
     * @return WebSocketClient The initialized WebSocket client.
     * @throws Exception If the device is not found or handshake fails.
     */
    public function initializeWebSocketConnection($identifier) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if (!$deviceId) {
            throw new Exception("Device not found.");
        }

        $device = $this->getDeviceById($deviceId);
        if (!$device) {
            throw new Exception("Device data not available.");
        }

        $wsClient = new WebSocketClient($this->httpClient);
        $handshakeResponse = $wsClient->handshake($device);

        if (isset($handshakeResponse['error']) && $handshakeResponse['error'] != 0) {
            $errorCode = $handshakeResponse['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Handshake Error: $errorMsg");
        }

        return $wsClient;
    }
    
    /**
     * Get data of a device using WebSocket.
     *
     * @param string $identifier The device name or ID.
     * @param array|string $params The parameters to query.
     * @return array The response data.
     * @throws Exception If there is an error during the process.
     */
    public function getDataWebSocket($identifier, $params) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if (!$deviceId) {
            throw new Exception("Device not found.");
        }
        $device = $this->getDeviceById($deviceId);
        if (!$device) {
            $errorCode = 'DEVICE_NOT_FOUND'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }

        // Ensure WebSocket connection is initialized
        if (!isset($this->wsClient)) {
            $this->wsClient = $this->initializeWebSocketConnection($identifier);
        }

        $data = $this->wsClient->createQueryData($device, $params);
        $this->wsClient->send(json_encode($data));
        $response = json_decode($this->wsClient->receive(), true);

        if (isset($response['error']) && $response['error'] != 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error: $errorMsg");
        }

        return $response['params'];
    }
    
    /**
     * Set data of a device using WebSocket.
     *
     * @param string $identifier The device name or ID.
     * @param array $params The parameters to set.
     * @return array The response data.
     * @throws Exception If there is an error during the process.
     */
    public function setDataWebSocket($identifier, $params) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if (!$deviceId) {
            throw new Exception("Device not found.");
        }
        $device = $this->getDeviceById($deviceId);
        if (!$device) {
            $errorCode = 'DEVICE_NOT_FOUND'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }

        // Ensure WebSocket connection is initialized
        if (!isset($this->wsClient)) {
            $this->wsClient = $this->initializeWebSocketConnection($identifier);
        }

        $data = $this->wsClient->createUpdateData($device, $params, $device['apikey']);
        $this->wsClient->send(json_encode($data));
        $response = json_decode($this->wsClient->receive(), true);

        if (isset($response['error']) && $response['error'] != 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error: $errorMsg");
        }

        return $response['params'];
    }


    /**
     * Force wake up the device by fetching all parameters and setting them back to their current values.
     *
     * @param string $identifier The device name or ID.
     * @return bool True if the operation was successful, false otherwise.
     * @throws Exception If there is an error during the process.
     */
    public function forceWakeUp($identifier) {
        $deviceId = $this->getDeviceIdByIdentifier($identifier);
        if (!$deviceId) {
            throw new Exception("Device not found.");
        }
        $device = $this->getDeviceById($deviceId);
        if (!$device) {
            $errorCode = 'DEVICE_NOT_FOUND'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }

        $currentParams = $this->getAllDeviceParamLive($deviceId);
        if ($currentParams === null) {
            return false;
        }

        $wsClient = new WebSocketClient($this->httpClient);
        $handshakeResponse = $wsClient->handshake($device);

        if (isset($handshakeResponse['error']) && $handshakeResponse['error'] != 0) {
            $errorCode = $handshakeResponse['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Handshake Error: $errorMsg");
        }

        $data = $wsClient->createUpdateData($device, $currentParams, $device['apikey']);
        $wsClient->send(json_encode($data));
        $response = json_decode($wsClient->receive(), true);

        $wsClient->close();

        if (isset($response['error']) && $response['error'] != 0) {
            $errorCode = $response['error'];
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Error: $errorMsg");
        }

        return true;
    }
}
