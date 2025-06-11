<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

require_once __DIR__ . '/autoloader.php';

//Class init
$http = new HttpClient();

//Get token
$token = $http->getToken();

//Check if ouath gave code for authorization
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
        echo '<h1>You are authenticated!</h1>';
        echo '<p>Token expiry time: ' . date('Y-m-d H:i:s', $tokenData['atExpiredTime'] / 1000) . '</p>';

        // ── Debug log link (only when DEBUG=1) ─────────────────────────────────
        if (Constants::DEBUG === 1) {
            echo '<h1>Debug is ON</h1>';
            echo '<ul>';
            echo '<li><a href="debug.log" target="_blank">debug.log</a></li>';
            echo '</ul>';
        }
        // ────────────────────────────────────────────────────────────────────────

        // ── Links to raw JSON files ─────────────────────────────────────────────
        echo '<h1>JSON Files</h1>';
        echo '<ul>';
        echo '<li><a href="devices.json"    target="_blank">devices.json</a></li>';
        echo '<li><a href="family.json"     target="_blank">family.json</a></li>';
        echo '<li><a href="token.json"      target="_blank">token.json</a></li>';
        echo '</ul>';
        echo '-----------------------------------------------------------';
        // ────────────────────────────────────────────────────────────────────────

        try {

            //initialize Devices class and List Devices
            $devs = $http->getDevices();
            $devsList = $devs->getDevicesList();
            echo '<h1>Devices List</h1>';
            echo '<pre>' . print_r($devsList, true) . '</pre>';

            /*

            // Example of retrieving data from API with user devices
            // -----------------------------------------------
           $devId = '100xxxxxxx'; // Single Device ID
           $multiDevId = '100xxxxxxx'; // Multi-Channel Device ID
           $singleDevId = '100xxxxxxx'; // Another Single Device ID
           $devIdent = 'Switch'; // Device Identifier for online check

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
           $updateParams = ['switch' => 'on']; // Parameters for force update device
           // -----------------------------------------------


           // More examples below

                       
            $devsData = $devs->fetchDevicesData();
            echo '<h1>Devices Data</h1>';
            echo '<pre>' . print_r($devsData, true) . '</pre>';

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

            $onlineRes = $devs->isOnline($devIdent);
            echo '<h1>Is Device Online?</h1>';
            echo $devIdent . ' is ' . ($onlineRes ? 'online' : 'offline') . '.';

            $home = $http->getHome();
            $familyData = $home->fetchFamilyData();
            echo '<h1>Family Data</h1>';
            echo '<pre>' . print_r($familyData, true) . '</pre>';
            echo '<p>Current Family ID: ' . htmlspecialchars($home->getCurrentFamilyId()) . '</p>';

            $forceWakeUpRes = $devs->forceWakeUp($devIdent);
            echo '<h1>Force Wake Up Result</h1>';
            echo '<pre>' . print_r($forceWakeUpRes, true) . '</pre>';

            if ($forceWakeUpRes) {
                $allLiveParams = $devs->getAllDeviceParamLive($devIdent);
                echo '<h1>Get All Device Parameters Live After Force Wake Up</h1>';
                echo '<pre>' . print_r($allLiveParams, true) . '</pre>';
            }

            // Initialize WebSocket connection and get data
            $wsClient = $devs->initializeWebSocketConnection($devId);
            $wsParams = 'power';
            $wsData = $devs->getDataWebSocket($devId, $wsParams);
            echo '<h1>WebSocket Data for ' . $devId . '</h1>';
            echo '<pre>' . print_r($wsData, true) . '</pre>';

            */

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }


    } else {
        $loginUrl = $http->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Authorize ewelinkApiPhp</a>';
    }
}