<?php
/**
 * gateway.php – eWeLink API example entry point
 *
 * Loads Composer first, then runs a minimal OAuth flow.
 * After login it prints the devices list.
 */

/* -------------------------------------------------------------------------
   1.  Find and include Composer’s autoloader
   --------------------------------------------------------------------- */
(function () {
    $candidates = [
        __DIR__ . '/../vendor/autoload.php',  // when this file lives in vendor/…/
        __DIR__ . '/vendor/autoload.php',     // project root
        __DIR__ . '/../../../autoload.php',   // nested / symlinked installs
        __DIR__ . '/../../autoload.php',
    ];
    foreach ($candidates as $file) {
        if (file_exists($file)) { require $file; return; }
    }
    http_response_code(500);
    echo 'Composer autoloader not found. Run <b>composer install</b> or fix the path in gateway.php.';
    exit(1);
})();

/* -------------------------------------------------------------------------
   2.  Library classes are now autoloaded – create the client
   --------------------------------------------------------------------- */
use pjanisio\ewelinkapiphp\HttpClient;
use pjanisio\ewelinkapiphp\Utils;

/*
// Uncomment to override default config from Constants.php:
$overrides = [
    'APPID'        => 'your_app_id',
    'APP_SECRET'   => 'your_app_secret',
    'REDIRECT_URL' => 'https://yourdomain.com/ewelinkApiPhp/gateway.php',
    'EMAIL'        => 'you@domain.com',
    'PASSWORD'     => 'your_password',
    'REGION'       => 'eu',
];
$http = new HttpClient($overrides);
*/

$http  = new HttpClient();
$token = $http->getToken();

/* -------------------------------------------------------------------------
   3.  After‑auth logic: show devices or an error
   --------------------------------------------------------------------- */
$afterAuth = function (HttpClient $http, $token) {
    try {
        $devsList = $http->getDevices()->getDevicesList();
        echo '<h1>Devices List</h1><pre>' . print_r($devsList, true) . '</pre>';
        // Add custom logic below (if needed)
        // $http->getDevices()->setDeviceStatus(...);
        // $familyId = $http->getHome()->getCurrentFamilyId();
        // $rooms = $http->getHome()->getRooms($familyId);
        // Utils::displayInfo('<pre>' . print_r($rooms, true) . '</pre>');
    } catch (\Exception $e) {
        echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
    }
};

/* -------------------------------------------------------------------------
   4.  Kick off the OAuth / refresh flow
   --------------------------------------------------------------------- */
Utils::handleAuthFlow($http, $token, $afterAuth);
