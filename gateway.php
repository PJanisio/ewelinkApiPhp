<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */


 /**
 * gateway.php - eWeLink API Example Entry Point
 *
 * This script demonstrates how to use the ewelinkApiPhp library for authenticating
 * and interacting with Sonoff/eWeLink devices.
 *
 * ─────────────────────────────────────────────────────────────
 * Configuration
 *
 * This script will automatically load configuration in the following priority:
 *   1. Overrides passed to HttpClient() constructor (see below)
 *   2. config.json file (created after first auth, or can be edited manually)
 *   3. src/Constants.php as defaults/fallbacks
 *
 * To use runtime overrides (for example, different credentials for this run only):
 *
 *     $overrides = [
 *         'EMAIL' => 'anotheruser@example.com',
 *         'REGION' => 'us',
 *         // any supported config keys
 *     ];
 *     $http = new HttpClient($overrides);
 *
 * Otherwise, just use the default:
 *
 *     $http = new HttpClient();
 *
 * For more info, see wiki.
 * ─────────────────────────────────────────────────────────────
 */


use pjanisio\ewelinkapiphp\HttpClient;
use pjanisio\ewelinkapiphp\Config;


//Adjust path to composer generated autoload.php if needed
$autoloadCandidates = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../../autoload.php',
];

foreach ($autoloadCandidates as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

if (!class_exists(\Composer\Autoload\ClassLoader::class, false)) {
    echo 'Composer autoloader not found; run `composer install` or when already done - adjust composer autoload.php path in gateway.php.';
    exit(1);
}


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
        $token->redirectToUrl(Config::get('REDIRECT_URL'));
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    if ($token->checkAndRefreshToken()) {
        $tokenData = $token->getTokenData();
        echo '<h1>You are authenticated!</h1>';
        echo '<p>Token expiry time: ' . date('Y-m-d H:i:s', $tokenData['atExpiredTime'] / 1000) . '</p>';

        // ── Debug log link (only when DEBUG=1) ─────────────────────────────────
        if (Config::get('DEBUG') == 1) {
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

        /*
        Inside try block you should put all methods f.e switching on/off devices, listing them etc
        Methods can be checked at wiki pages: https://github.com/PJanisio/ewelinkApiPhp/wiki
        */
        try {

            // List Devices
            $devs = $http->getDevices();
            $devsList = $devs->getDevicesList();
            echo '<h1>Devices List</h1>';
            echo '<pre>' . print_r($devsList, true) . '</pre>';
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } 
    else {
        //Fallback to Authorization URL when token expired or not authorized yet
        $loginUrl = $http->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Authorize ewelinkApiPhp</a>';
    }
}
