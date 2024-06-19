<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/AceExpert/ewelink-api-python
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

    /**
     * Constructor for the Devices class.
     *
     * @param HttpClient $httpClient The HTTP client instance.
     */
    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
        $this->home = new Home($httpClient);
        $this->home->fetchFamilyData();
        $this->loadDevicesData();
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
     * Fetch devices data from the remote server and save it locally.
     *
     * @param string $lang The language parameter for the request (default is 'en').
     * @return array The devices data.
     * @throws Exception If the current family ID is not set.
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
        file_put_contents(Constants::JSON_LOG_DIR . '/devices.json', json_encode($this->devicesData));
        return $this->devicesData;
    }

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
     * Check if a device supports multiple channels.
     *
     * @param string $deviceId The device ID.
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
     * Search for a specific parameter in the device data.
     *
     * @param string $searchKey The parameter key to search for.
     * @param string $deviceId The device ID.
     * @return mixed|null The parameter value if found, null otherwise.
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
     * Get live parameters of a device.
     *
     * @param string $deviceId The device ID.
     * @param string|array $param The parameter(s) to get.
     * @param int $type The type of the request (default is 1).
     * @return mixed The live parameter(s) value(s).
     * @throws Exception If there is an error in the response.
     */
    public function getDeviceParamLive($deviceId, $param, $type = 1) {
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
            throw new Exception('Error: ' . $response['msg']);
        }

        $responseParams = $response['params'] ?? [];

        if (is_array($param)) {
            $result = [];
            foreach ($param as $p) {
                if (isset($responseParams[$p])) {
                    $result[$p] = $responseParams[$p];
                } else {
                    $result[$p] = null;
                }
            }
            return $result;
        } else {
            if (isset($responseParams[$param])) {
                return $responseParams[$param];
            }
            return null;
        }
    }

    /**
     * Get all live parameters of a device.
     *
     * @param string $deviceId The device ID.
     * @param int $type The type of the request (default is 1).
     * @return array|null The live parameters or null if not found.
     * @throws Exception If there is an error in the response.
     */
    public function getAllDeviceParamLive($deviceId, $type = 1) {
        $endpoint = '/v2/device/thing/status';
        $queryParams = [
            'id' => $deviceId,
            'type' => $type
        ];

        $response = $this->httpClient->getRequest($endpoint, $queryParams);

        if (isset($response['error']) && $response['error'] != 0) {
            throw new Exception('Error: ' . $response['msg']);
        }

        if (isset($response['params'])) {
            return $response['params'];
        }

        return null;
    }

    /**
     * Set the status of a device.
     *
     * @param string $deviceId The device ID.
     * @param array $params The parameters to set.
     * @return string The result of the status update.
     * @throws Exception If there is an error in the response or if a parameter does not exist.
     */
    public function setDeviceStatus($deviceId, $params) {
        $device = $this->getDeviceById($deviceId);
        $currentParams = $this->getAllDeviceParamLive($deviceId) ?? null;

        if ($currentParams === null) {
            return "Device $deviceId does not have any parameters to update.";
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
                            return "Outlet $outlet does not exist for device $deviceId.";
                        }
                    }
                }
            } else {
                foreach ($param as $key => $value) {
                    if (!array_key_exists($key, $currentParams)) {
                        return "Parameter $key does not exist for device $deviceId.";
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
            return implode("\n", $messages);
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
                return "Failed to update parameter $key to $value for device $deviceId. Current value is $updatedValue[$key].";
            }
        }

        return "Parameters successfully updated for device $deviceId.\n" . implode("\n", $changes) . "\n" . implode("\n", $messages);
    }

    /**
     * Check if a device is online.
     *
     * @param string $identifier The device name or ID.
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
     * Get the history of a device.
     *
     * @param string $deviceId The device ID.
     * @return array The device history.
     * @throws Exception If there is an error in the response.
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

    /**
     * Force get data of a device using WebSocket.
     *
     * @param string $deviceId The device ID.
     * @param array|string $params The parameters to query.
     * @return array The response data.
     * @throws Exception If there is an error during the process.
     */
    public function forceGetData($deviceId, $params) {
        $device = $this->getDeviceById($deviceId);
        if (!$device) {
            throw new Exception('Device not found.');
        }

        $wsClient = new WebSocketClient($this->httpClient);
        $handshakeResponse = $wsClient->handshake($device);

        if (isset($handshakeResponse['error']) && $handshakeResponse['error'] != 0) {
            throw new Exception('Handshake Error: ' . $handshakeResponse['msg']);
        }

        $data = $wsClient->createQueryData($device, $params);
        $wsClient->send(json_encode($data));
        $response = json_decode($wsClient->receive(), true);

        $wsClient->close();

        if (isset($response['error']) && $response['error'] != 0) {
            throw new Exception('Error: ' . $response['msg']);
        }

        return $response['params'];
    }

    /**
     * Force update the status of a device using WebSocket.
     *
     * @param string $deviceId The device ID.
     * @param array $params The parameters to update.
     * @param int $sleepSec The number of seconds to wait before verifying the update (default is 3 seconds).
     * @return array The response data.
     * @throws Exception If there is an error during the process.
     */
    public function forceUpdateDevice($deviceId, $params, $sleepSec = 3) {
        $device = $this->getDeviceById($deviceId);
        if (!$device) {
            throw new Exception('Device not found.');
        }

        $wsClient = new WebSocketClient($this->httpClient);
        $handshakeResponse = $wsClient->handshake($device);

        if (isset($handshakeResponse['error']) && $handshakeResponse['error'] != 0) {
            throw new Exception('Handshake Error: ' . $handshakeResponse['msg']);
        }

        $data = $wsClient->createUpdateData($device, $params, $device['apikey']);
        $wsClient->send(json_encode($data));

        // Keep heartbeat running and verify changes
        $response = json_decode($wsClient->receive(), true);

        if (isset($response['error']) && $response['error'] != 0) {
            throw new Exception('Error: ' . $response['msg']);
        }

        sleep($sleepSec); // Wait for a while to let the changes take effect

        // Check if the parameters have been updated
        $updatedParams = $this->forceGetData($deviceId, array_keys($params));

        $wsClient->close();

        return $updatedParams;
    }
}
?>
