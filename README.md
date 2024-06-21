# eWeLink API PHP

eWeLink API PHP is a connector for Sonoff / eWeLink devices. This library allows you to interact with your eWeLink-enabled devices using PHP. It supports various functionalities such as device control, retrieving device status, debugging and more.

## Requirements

- PHP 7.4+
- cURL extension enabled

## Current features

- log-in and authorization to ewelink APP via web or websockets
- get devices list with all or chosen parameters
- saving devices and other outputs from API to .json
- search for any value of parameter for each device (f.e switch status, productName, MAC etc.)
- set any parameter/state of device
- check if device has MultiChannel support
- set parameter for Multichannel devices
- check if device is Online
- debug all requests and responses to debug.log
- use Websocket connection to get and update parameters

## Configuration and methods examples

Go to [Wiki Pages](https://github.com/PJanisio/ewelinkApiPhp/wiki) to install and use methods.

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

## Structure

``` rust
ewelinkApiPhp/
│
├── src/
│   ├── Constants.php
│   ├── Devices.php
│   ├── Home.php
│   ├── HttpClient.php
│   ├── Token.php
│   └── Utils.php
│   └── WebSocketsClient.php
│
├── autoloader.php
└── index.php
```

All classes are located in src directory.

Index.php works as a gateway to API and also as a debug for all available methods.

.json files outputs will be saved by default in project_root. You can define directory in Constants.php

## More inside info about current development

- main branch when commited used to be operative.
- enable DEBUG = 1; in Constants to log every get and postRequest with output and parameters to **debug.log**
- with next stable release methods and invoking functions structure can be changed (keep this in mind)
- branch **websockets** will be not maintened anymore
- index.php is a quasi-test file which helps me to check all methods and it is also a great example
