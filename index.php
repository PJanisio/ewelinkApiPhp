<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/AceExpert/ewelink-api-python
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

require_once __DIR__ . '/autoloader.php';

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
            $devId = '100142b205';
            $searchRes = $devs->searchDeviceParam($searchKey, $devId);
            echo '<h1>Search Result</h1>';
            echo '<pre>' . print_r($searchRes, true) . '</pre>';

            $liveParam = ['voltage', 'current', 'power'];
            $liveRes = $devs->getDeviceParamLive($devId, $liveParam);
            echo '<h1>Live Device Parameter</h1>';
            echo '<pre>' . print_r($liveRes, true) . '</pre>';
            
            $allLiveParams = $devs->getAllDeviceParamLive($devId);
            echo '<h1>Get All Device Parameters Live</h1>';
            echo '<pre>' . print_r($allLiveParams, true) . '</pre>';

            $multiDevId = '1000663128';
            $multiParams = [
               ['switch' => 'off', 'outlet' => 0],
               ['switch' => 'off', 'outlet' => 1],
               ['switch' => 'off', 'outlet' => 2],
               ['switch' => 'off', 'outlet' => 3]
            ];
            $setMultiRes = $devs->setDeviceStatus($multiDevId, $multiParams);
            echo '<h1>Set Multi-Channel Device Status Result</h1>';
            echo '<pre>' . print_r($setMultiRes, true) . '</pre>';

            $singleDevId = '10011015b6';
            $singleParams = ['switch' => 'off'];
            $setSingleRes = $devs->setDeviceStatus($singleDevId, $singleParams);
            echo '<h1>Set Single-Channel Device Status Result</h1>';
            echo '<pre>' . print_r($setSingleRes, true) . '</pre>';

            $singleParamsMulti = [
                ['colorR' => 0],
                ['colorG' => 153],
                ['colorB' => 0]
            ];
            $setSingleMultiRes = $devs->setDeviceStatus($singleDevId, $singleParamsMulti);
            echo '<h1>Set Single-Channel Device Status Result (Multiple Parameters)</h1>';
            echo '<pre>' . print_r($setSingleMultiRes, true) . '</pre>';

            $devIdent = 'Ledy salon';
            $onlineRes = $devs->isOnline($devIdent);
            echo '<h1>Is Device Online?</h1>';
            echo $devIdent . ' is ' . ($onlineRes ? 'online' : 'offline') . '.';

            $home = $http->getHome();
            $familyData = $home->fetchFamilyData();
            echo '<h1>Family Data</h1>';
            echo '<pre>' . print_r($familyData, true) . '</pre>';
            echo '<p>Current Family ID: ' . htmlspecialchars($home->getCurrentFamilyId()) . '</p>';

            $forceParams = ['current', 'power', 'voltage'];
            $forceRes = $devs->forceGetData($devId, $forceParams);
            echo '<h1>Force Get Data Result</h1>';
            echo '<pre>' . print_r($forceRes, true) . '</pre>';

            $updateParams = ['switch' => 'on'];
            $updateRes = $devs->forceUpdateDevice($devId, $updateParams, 3);
            echo '<h1>Force Update Device Result</h1>';
            echo '<pre>' . print_r($updateRes, true) . '</pre>';

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        $loginUrl = $http->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Authorize ewelinkApiPhp</a>';
    }
}
?>
