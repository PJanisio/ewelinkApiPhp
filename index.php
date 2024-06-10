<?php

require 'Constants.php';
require 'HttpClient.php';

$username = "your_username";
$password = "your_password";
$region = "eu"; // or other region like "eu", "cn", etc.

$client = new HttpClient($password, $username, null, $region);

try {
    $user = $client->login();
    echo '<pre>';
    var_dump($user);

    $devices = $client->getDevices();
    var_dump($devices);

    $deviceId = $devices["Lights-bedroom"]; // Replace with actual device name from mobile app

    $deviceData = $client->getDeviceData($deviceId);
    var_dump($deviceData);

    $refreshedDeviceData = $client->refreshDeviceParameters($deviceId);
    var_dump($refreshedDeviceData);
    echo '</pre>';

} catch (Exception $e) {
    echo '<pre>An error occurred: ' . $e->getMessage() . '</pre>';
}
?>
