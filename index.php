<?php

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
            $home = $httpClient->getHome();
            $familyData = $home->fetchFamilyData();
            echo '<h1>Family Data</h1>';
            echo '<pre>' . print_r($familyData, true) . '</pre>';
            echo '<p>Current Family ID: ' . htmlspecialchars($home->getCurrentFamilyId()) . '</p>';

            $devices = new Devices($httpClient);
            $devicesData = $devices->fetchDevicesData();
            echo '<h1>Devices Data</h1>';
            echo '<pre>' . print_r($devicesData, true) . '</pre>';

            echo '<h1>Loaded Devices Data</h1>';
            echo '<pre>' . print_r($devices->getDevicesData(), true) . '</pre>';

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
            $liveResult = $devices->getDeviceParamLive($deviceId, $liveParam);
            echo '<h1>Live Device Parameter</h1>';
            echo '<pre>' . print_r($liveResult, true) . '</pre>';

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

            // Example usage of isOnline
            $deviceIdentifier = 'Ledy salon'; // example device name or ID
            $isOnlineResult = $devices->isOnline($deviceIdentifier);
            echo '<h1>Is Device Online?</h1>';
            echo $deviceIdentifier . ' is ' . ($isOnlineResult ? 'online' : 'offline') . '.';

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        $loginUrl = $httpClient->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Login with OAuth</a>';
    }
}
