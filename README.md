# ewelinkApiPhp

API connector for Sonoff devices.

Based on [ewelink-api-python](https://github.com/AceExpert/ewelink-api-python/tree/master)

PHP 7.4+ required

## Public key and secret

You can generate here: [dev.ewelink](https://dev.ewelink.cc/)

## Example (as in index.php)

```php
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
```
