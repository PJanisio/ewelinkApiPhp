<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/AceExpert/ewelink-api-python
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

require_once __DIR__ . '/autoloader.php';

$httpClient = new HttpClient();
$token = new Token($httpClient);

if (isset($_GET['code']) && isset($_GET['region'])) {
    try {
        $tokenData = $token->getToken();
        echo '<h1>Token Data</h1>';
        echo '<pre>' . print_r($tokenData, true) . '</pre>';
        $token->redirectToUrl(Constants::REDIRECT_URL);
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    if ($token->checkAndRefreshToken()) {
        $tokenData = $token->getTokenData();
        echo '<h1>Token is valid</h1>';
        echo '<p>Token expiry time: ' . date('Y-m-d H:i:s', $tokenData['atExpiredTime'] / 1000) . '</p>';

        try {
            // Initialize Devices class which will also initialize Home class and fetch family data
            $devices = new Devices($httpClient);
            $devicesData = $devices->fetchDevicesData();
            echo '<h1>Devices Data</h1>';
            echo '<pre>' . print_r($devicesData, true) . '</pre>';

            $devicesList = $devices->getDevicesList();
            echo '<h1>Devices List</h1>';
            echo '<pre>' . print_r($devicesList, true) . '</pre>';

            // Example usage of searchDeviceParam
            $searchKey = 'productModel'; // example key to search for
            $deviceId = '100142b205'; // example device ID
            $searchResult = $devices->searchDeviceParam($searchKey, $deviceId);
            echo '<h1>Search Result</h1>';
            echo '<pre>' . print_r($searchResult, true) . '</pre>';

            // Example usage of getDeviceParamLive
            $liveParam = ['voltage', 'current', 'power']; // example parameter to get
            $liveResult = $devices->getDeviceParamLive($deviceId, $liveParam);
            echo '<h1>Live Device Parameter</h1>';
            echo '<pre>' . print_r($liveResult, true) . '</pre>';
            
            // Example usage of getAllDeviceParamLive
            $refreshResult = $devices->getAllDeviceParamLive($deviceId);
            echo '<h1>Get All Device Parameters Live</h1>';
            echo '<pre>' . print_r($refreshResult, true) . '</pre>';

            // Example usage of setDeviceStatus for multi-channel device
            $multiChannelDeviceId = '1000663128';
            $multiChannelParams = [
               ['switch' => 'off', 'outlet' => 0],
               ['switch' => 'off', 'outlet' => 1],
               ['switch' => 'off', 'outlet' => 2],
               ['switch' => 'off', 'outlet' => 3]
            ];
            $setStatusResult = $devices->setDeviceStatus($multiChannelDeviceId, $multiChannelParams);
            echo '<h1>Set Multi-Channel Device Status Result</h1>';
            echo '<pre>' . print_r($setStatusResult, true) . '</pre>';

            // Example usage of setDeviceStatus for single-channel device
            $singleChannelDeviceId = '10011015b6';
            $singleChannelParams = ['switch' => 'off'];
            $setStatusResultSingle = $devices->setDeviceStatus($singleChannelDeviceId, $singleChannelParams);
            echo '<h1>Set Single-Channel Device Status Result</h1>';
            echo '<pre>' . print_r($setStatusResultSingle, true) . '</pre>';

            // Example usage of setDeviceStatus for single-channel device with multiple parameters
            $singleChannelParamsMultiple = [
                ['colorR' => 0],
                ['colorG' => 153],
                ['colorB' => 0]
            ];
            $setStatusResultSingleMultiple = $devices->setDeviceStatus($singleChannelDeviceId, $singleChannelParamsMultiple);
            echo '<h1>Set Single-Channel Device Status Result (Multiple Parameters)</h1>';
            echo '<pre>' . print_r($setStatusResultSingleMultiple, true) . '</pre>';

            // Example usage of isOnline
            $deviceIdentifier = 'Ledy salon'; // example device name or ID
            $isOnlineResult = $devices->isOnline($deviceIdentifier);
            echo '<h1>Is Device Online?</h1>';
            echo $deviceIdentifier . ' is ' . ($isOnlineResult ? 'online' : 'offline') . '.';

            // Example usage of getFamilyData
            $home = $httpClient->getHome();
            $familyData = $home->fetchFamilyData();
            echo '<h1>Family Data</h1>';
            echo '<pre>' . print_r($familyData, true) . '</pre>';
            echo '<p>Current Family ID: ' . htmlspecialchars($home->getCurrentFamilyId()) . '</p>';

            // Example usage of forceUpdate with three parameters
            $forceUpdateParams = ['current', 'power', 'voltage'];
            $forceUpdateResult = $devices->forceUpdate($deviceId, $forceUpdateParams);
            echo '<h1>Force Update Result</h1>';
            echo '<pre>' . print_r($forceUpdateResult, true) . '</pre>';

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        $loginUrl = $httpClient->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Authorize ewelinkApiPhp</a>';
    }
}
?>
