# ewelinkApiPhp

API connector for Sonoff devices.

Based on [ewelink-api-python](https://github.com/AceExpert/ewelink-api-python/tree/master)

PHP 7.4+ required

## Public key and secret

You can generate here: [dev.ewelink](https://dev.ewelink.cc/)

## Example (as in index.php)

```php
<?php


require_once 'Constants.php';
require_once 'HttpClient.php';

try {
    $client = new HttpClient();
    $user = $client->login();
    
    $devices = $client->getDevices();
    $gateway = $client->getGateway();

    echo '<pre>';
    var_dump($user);
    var_dump($devices);
    var_dump($gateway);
    echo '</pre>';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}


?>

```
