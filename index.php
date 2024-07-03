<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/AceExpert/ewelink-api-python
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

require_once __DIR__ . '/autoloader.php';

// User Inputs
// -----------------------------------------------
$devId = '100xxxxxxx'; // Single Device ID
$multiDevId = '100xxxxxxx'; // Multi-Channel Device ID
$singleDevId = '100xxxxxxx'; // Another Single Device ID
$devIdent = 'Name of device'; // Device Identifier for online check

$singleParams = ['switch' => 'off']; // Parameters for single-channel device
$multiParams = [
    ['switch' => 'off', 'outlet' => 0],
    ['switch' => 'off', 'outlet' => 1],
    ['switch' => 'off', 'outlet' => 2],
    ['switch' => 'off', 'outlet' => 3]
]; // Parameters for multi-channel device
$singleParamsMulti = [
    ['colorR' => 0],
    ['colorG' => 153],
    ['colorB' => 0]
]; // Multiple parameters for single-channel device
$liveParam = ['voltage', 'current', 'power']; // Parameters to get live data
$forceParams = ['current', 'power', 'voltage']; // Parameters for force get data
$updateParams = ['switch' => 'on']; // Parameters for force update device
// -----------------------------------------------

$http = new HttpClient();
$token = new Token($http);

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
            $devs = new Devices($http);
            $devsData = $devs->fetchDevicesData();
            echo '<h1>Devices Data</h1>';
            echo '<pre>' . print_r($devsData, true) . '</pre>';

            $devsList = $devs->getDevicesList();
            echo '<h1>Devices List</h1>';
            echo '<pre>' . print_r($devsList, true) . '</pre>';

            $searchKey = 'productModel';
            $searchRes = $devs->searchDeviceParam($searchKey, $devId);
            echo '<h1>Search Result</h1>';
            echo '<pre>' . print_r($searchRes, true) . '</pre>';

            $liveRes = $devs->getDeviceParamLive($devId, $liveParam);
            echo '<h1>Live Device Parameter</h1>';
            echo '<pre>' . print_r($liveRes, true) . '</pre>';
            
            $allLiveParams = $devs->getAllDeviceParamLive($devId);
            echo '<h1>Get All Device Parameters Live</h1>';
            echo '<pre>' . print_r($allLiveParams, true) . '</pre>';

            $setMultiRes = $devs->setDeviceStatus($multiDevId, $multiParams);
            echo '<h1>Set Multi-Channel Device Status Result</h1>';
            echo '<pre>' . print_r($setMultiRes, true) . '</pre>';

            $setSingleRes = $devs->setDeviceStatus($singleDevId, $singleParams);
            echo '<h1>Set Single-Channel Device Status Result</h1>';
            echo '<pre>' . print_r($setSingleRes, true) . '</pre>';

            $setSingleMultiRes = $devs->setDeviceStatus($singleDevId, $singleParamsMulti);
            echo '<h1>Set Single-Channel Device Status Result (Multiple Parameters)</h1>';
            echo '<pre>' . print_r($setSingleMultiRes, true) . '</pre>';

            $onlineRes = $devs->isOnline($devIdent);
            echo '<h1>Is Device Online?</h1>';
            echo $devIdent . ' is ' . ($onlineRes ? 'online' : 'offline') . '.';

            $home = $http->getHome();
            $familyData = $home->fetchFamilyData();
            echo '<h1>Family Data</h1>';
            echo '<pre>' . print_r($familyData, true) . '</pre>';
            echo '<p>Current Family ID: ' . htmlspecialchars($home->getCurrentFamilyId()) . '</p>';

            $forceRes = $devs->forceGetData('Gniazdko biuro', $forceParams);
            echo '<h1>Force Get Data Result</h1>';
            echo '<pre>' . print_r($forceRes, true) . '</pre>';

            $updateRes = $devs->forceUpdateDevice($devIdent, $updateParams, 3);
            echo '<h1>Force Update Device Result</h1>';
            echo '<pre>' . print_r($updateRes, true) . '</pre>';

            // Initialize WebSocket connection and get data
            $wsClient = $devs->initializeWebSocketConnection($devId);
            $wsParams = 'power';
            $wsData = $devs->getDataWebSocket($devId, $wsParams);
            echo '<h1>WebSocket Data for ' . $devId . '</h1>';
            echo '<pre>' . print_r($wsData, true) . '</pre>';

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        $loginUrl = $http->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Authorize ewelinkApiPhp</a>';
    }
}
?>
