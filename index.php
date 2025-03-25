<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / eWeLink devices
 */

require_once __DIR__ . '/autoloader.php';

//class init
$ewelink = new EweLinkApiPhp();

//Let it handle authorization automatically
if ($ewelink->handleAuthorization()) {
    // We have a valid token at this point.
    $tokenData = $ewelink->getTokenData();
    echo '<h1>Token is valid</h1>';
    echo '<p>Token expiry time: ' . date('Y-m-d H:i:s', $tokenData['atExpiredTime'] / 1000) . '</p>';

    try {
        $devs = $ewelink->getDevices();

        //Fetch devices ignoring family (new approach):
        $devsData = $devs->fetchDevicesData();
        echo '<h1>Devices Data</h1>';
        echo '<pre>' . print_r($devsData, true) . '</pre>';

        //If you still want to fetch devices with current family:
        /*
        $devsData = $devs->fetchDevicesData();
        echo '<h1>Devices Data (Family-based)</h1>';
        echo '<pre>' . print_r($devsData, true) . '</pre>';
        */

        /**
         * ----------------------------------------------------------------
         * Example usage of $devs class to interact with your devices:
         * ----------------------------------------------------------------
         */
        /*
           $devId       = '100xxxxxxx';  // Single Device ID
           $multiDevId  = '100xxxxxxx';  // Multi-Channel Device ID
           $singleDevId = '100xxxxxxx';  // Another Single Device ID
           $devIdent    = 'Switch';      // Device name or ID for tests

           // Turn single-channel device OFF:
           $singleParams = ['switch' => 'off'];

           // Turn multi-channel device OFF on all outlets:
           $multiParams = [
               ['switch' => 'off', 'outlet' => 0],
               ['switch' => 'off', 'outlet' => 1],
               ['switch' => 'off', 'outlet' => 2],
               ['switch' => 'off', 'outlet' => 3]
           ];

           // Multiple parameters for single-channel device (e.g., color settings):
           $singleParamsMulti = [
               ['colorR' => 0],
               ['colorG' => 153],
               ['colorB' => 0]
           ];

           // Live data we want to read (voltage, current, etc.)
           $liveParam = ['voltage', 'current', 'power'];

           // Example: Searching a parameter in a device:
           $searchKey = 'productModel';
           $searchRes = $devs->searchDeviceParam($searchKey, $devId);
           echo '<h1>Search Result</h1>';
           echo '<pre>' . print_r($searchRes, true) . '</pre>';

           // Get live device parameter:
           $liveRes = $devs->getDeviceParamLive($devId, $liveParam);
           echo '<h1>Live Device Parameter</h1>';
           echo '<pre>' . print_r($liveRes, true) . '</pre>';

           // Get all device params live:
           $allLiveParams = $devs->getAllDeviceParamLive($devId);
           echo '<h1>All Live Parameters</h1>';
           echo '<pre>' . print_r($allLiveParams, true) . '</pre>';

           // Set multi-channel device status:
           $setMultiRes = $devs->setDeviceStatus($multiDevId, $multiParams);
           echo '<h1>Set Multi-Channel Device Status</h1>';
           echo '<pre>' . print_r($setMultiRes, true) . '</pre>';

           // Set single-channel device status:
           $setSingleRes = $devs->setDeviceStatus($singleDevId, $singleParams);
           echo '<h1>Set Single-Channel Device Status</h1>';
           echo '<pre>' . print_r($setSingleRes, true) . '</pre>';

           // Check if a device is online:
           $onlineRes = $devs->isOnline($devIdent);
           echo '<h1>Is Device Online?</h1>';
           echo $devIdent . ' is ' . ($onlineRes ? 'online' : 'offline') . '.';

           // Access "Home" and see family data if still relevant:
           $home = $devs->getDevicesData(); // or $ewelink->getDevices()->getDevicesData();
           $familyData = $http->getHome()->fetchFamilyData();
           echo '<h1>Family Data</h1>';
           echo '<pre>' . print_r($familyData, true) . '</pre>';

           // Force wake up device:
           $forceWakeUpRes = $devs->forceWakeUp($devIdent);
           echo '<h1>Force Wake Up Result</h1>';
           echo '<pre>' . print_r($forceWakeUpRes, true) . '</pre>';

           if ($forceWakeUpRes) {
               $allLiveParams = $devs->getAllDeviceParamLive($devIdent);
               echo '<h1>All Device Params After Force Wake Up</h1>';
               echo '<pre>' . print_r($allLiveParams, true) . '</pre>';
           }

           // WebSocket usage example:
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
    // If we get here, we have no valid token -> show login link
    $loginUrl = $ewelink->getLoginUrl();
    echo '<a href="' . htmlspecialchars($loginUrl) . '">Authorize ewelinkApiPhp</a>';
}
