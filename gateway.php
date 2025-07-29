<?php

/**
 * gateway.php - eWeLink API Example Entry Point
 *
 * Uses Utils helpers for clean, maintainable code.
 * Devices list is always displayed after login.
 */

use pjanisio\ewelinkapiphp\HttpClient;
use pjanisio\ewelinkapiphp\Utils;

// Load Composer autoload via Utils helper
Utils::loadComposerAutoload();

// Optionally: Add config overrides here
// $overrides = [
//     'APPID'        => 'your_app_id',
//     'APP_SECRET'   => 'your_app_secret',
//     'REDIRECT_URL' => 'https://yourdomain.com/ewelinkApiPhp/gateway.php',
//     'EMAIL'        => 'you@domain.com',
//     'PASSWORD'     => 'your_password',
//     'REGION'       => 'eu'
// ];
// $http = new HttpClient($overrides);

$http = new HttpClient();
$token = $http->getToken();

// Main logic after successful authentication
$afterAuth = function($http, $token) {
    try {
        $devsList = $http->getDevices()->getDevicesList();
        Utils::displayInfo('<h1>Devices List</h1><pre>' . print_r($devsList, true) . '</pre>');

        // Add custom logic below (if needed)
        // $http->getDevices()->setDeviceStatus(...);
        // $familyId = $http->getHome()->getCurrentFamilyId();
        // $rooms = $http->getHome()->getRooms($familyId);
        // Utils::displayInfo('<pre>' . print_r($rooms, true) . '</pre>');
    } catch (\Exception $e) {
        Utils::displayError($e->getMessage());
    }
};

// Handles authentication, debug links, config warnings, then calls $afterAuth
Utils::handleAuthFlow($http, $token, $afterAuth);

