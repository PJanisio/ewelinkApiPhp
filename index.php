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

    $deviceId = "your_device_id"; // Replace with actual device ID

    $deviceData = $client->getDeviceData($deviceId);
    var_dump($deviceData);

    $refreshedDeviceData = $client->refreshDeviceParameters($deviceId);
    var_dump($refreshedDeviceData);
    echo '</pre>';

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage();
}
?>
