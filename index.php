<?php

require_once __DIR__ . '/src/HttpClient.php';
require_once __DIR__ . '/src/Utils.php';
require_once __DIR__ . '/src/Devices.php';
require_once __DIR__ . '/src/Constants.php';

// Function to get query parameter
function getQueryParam($name) {
    return isset($_GET[$name]) ? $_GET[$name] : null;
}

$state = 'your_state_here';
$httpClient = new HttpClient($state);
$utils = new Utils();

$code = getQueryParam('code');
$region = getQueryParam('region');

if ($code && $region) {
    try {
        $tokenData = $httpClient->getToken();
        echo '<h1>Token Data</h1>';
        echo '<pre>' . print_r($tokenData, true) . '</pre>';
        $utils->redirectToUrl(Constants::REDIRECT_URL);
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    if ($httpClient->checkAndRefreshToken()) {
        $tokenData = $httpClient->getTokenData();
        echo '<h1>Token is valid</h1>';
        echo '<p>Token expiry time: ' . date('Y-m-d H:i:s', $tokenData['atExpiredTime'] / 1000) . '</p>';

        try {
            $familyData = $httpClient->getFamilyData();
            echo '<h1>Family Data</h1>';
            echo '<pre>' . print_r($familyData, true) . '</pre>';
            echo '<p>Current Family ID: ' . htmlspecialchars($httpClient->getCurrentFamilyId()) . '</p>';

            $devices = new Devices($httpClient, $httpClient->getCurrentFamilyId());
            $devicesData = $devices->fetchDevicesData();
            echo '<h1>Devices Data</h1>';
            echo '<pre>' . print_r($devicesData, true) . '</pre>';

            $devicesList = $devices->getDevicesList();
            echo '<h1>Devices List</h1>';
            echo '<pre>' . print_r($devicesList, true) . '</pre>';

            // Example usage of searchDeviceParam
            $searchKey = 'productModel'; // example key to search for
            $deviceId = '10011015b6'; // example device ID
            $searchResult = $devices->searchDeviceParam($searchKey, $deviceId);
            echo '<h1>Search Result</h1>';
            echo '<pre>' . print_r($searchResult, true) . '</pre>';

            // Example usage of getDeviceParamLive
            $liveParam = 'switch'; // example parameter to get
            $liveResult = $devices->getDeviceParamLive($httpClient, $deviceId, $liveParam);
            echo '<h1>Live Device Parameter</h1>';
            echo '<pre>' . print_r($liveResult, true) . '</pre>';

            // Example usage of setDeviceStatus
            $params = ['switch' => 'on']; // example parameters to update
            $setStatusResult = $devices->setDeviceStatus($httpClient, $deviceId, $params);
            echo '<h1>Set Device Status Result</h1>';
            echo '<pre>' . print_r($setStatusResult, true) . '</pre>';

            // Example usage of isOnline
            $identifier = 'Ledy salon'; // example device name or ID
            $isOnline = $devices->isOnline($identifier);
            echo '<h1>Device Online Status</h1>';
            echo '<p>Device ' . $identifier . ' is ' . ($isOnline ? 'online' : 'offline') . '.</p>';

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        $loginUrl = $httpClient->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Login with OAuth</a>';
    }
}
?>
