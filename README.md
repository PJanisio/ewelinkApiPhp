# eWeLink API PHP

[Download latest release](https://github.com/PJanisio/ewelinkApiPhp/releases)

eWeLink API PHP is a connector for Sonoff / eWeLink devices. This library allows you to interact with your eWeLink-enabled devices using PHP. It supports various functionalities such as device control, retrieving device status, debugging and more.

## Requirements

- PHP 7.4+
- cURL extension enabled

## Current features

- get all devices list with their parameters using **deviceId** or **deviceName** from ewelink app
- saving devices data and other outputs from API to **.json**
- search for **any value** of each device (f.e switch status, productName, MAC etc.)
- set any parameter/state of device using **HTTP gateway** or **websockets**
- set parameter for **multi-channel** devices (like 4CH Pro)
- debug all requests and responses to debug.log

## Documentation

Go to [Wiki Pages](https://github.com/PJanisio/ewelinkApiPhp/wiki) to get started read about possible methods.

## Example

This is a single case example to turn on device. Look at [Wiki Pages](https://github.com/PJanisio/ewelinkApiPhp/wiki) to get knowledge of other methods.

```php
<?php

/*
Example Turn device ON
*/

require_once __DIR__ . '/autoloader.php';

//initialize core classes
$httpClient = new HttpClient();
$token = new Token($httpClient);

    if ($token->checkAndRefreshToken()) {
        // Initialize Devices class which will also initialize Home class and fetch family data
        $devices = new Devices($httpClient);

        // Device ID to be turned on
        $deviceId = '100xxxxxx'; // Replace with your actual device ID

        // Turn on the device
        $param = ['switch' => 'on'];
        $setStatusResult = $devices->setDeviceStatus($deviceId, $param);

    } else {

        //if we have no token, or we are not authorized paste link to authorization
        $loginUrl = $httpClient->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Authorize ewelinkApiPhp</a>';
    }

```

## Example of class usage - device  monitoring

Please see example app written based on this class that checks and update chosen parameters in real time (using asynchronous calls)

[Device Monitoring APP](https://github.com/PJanisio/ewelinkapiphp-device-monitoring)

![screencapture-nastran-org-modules-dev-ewelink-index-html-2024-07-01-18_22_15](https://github.com/PJanisio/ewelinkApiPhp/assets/9625885/7780ac67-3433-4eb0-84e1-9938e9cbe480)


## Tech info

Visit wiki page for devs: [devs-wiki](https://github.com/PJanisio/ewelinkApiPhp/wiki/Developers)
