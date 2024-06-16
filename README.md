# ewelinkApiPhp

API connector for Sonoff/ewelink devices using simple webapi based on OAuth2.

PHP 7.4+, no other dependiencies required.

Current version from branch **main** should be operative, but not yet tested in 100% and in active development, so please keep this in mind.
For pre released versions look at recent released  [Tagged versions](https://github.com/PJanisio/ewelinkApiPhp/tags)

## Current features

- login and authorization to ewelink (with refresh token)
- get devices list with all parameters
- saving devices and other outputs from API to .json
- get any value of parameter for each device (f.e switch status, productName, MAC etc.)
- set parameter of device (switch on, off)
- check if device has MultiChannel support
- set parameter for Multichannel devices
- check if device is Online

## Public key and secret

Generate here: [dev.ewelink](https://dev.ewelink.cc/)

And put your keys and redirect Url in **Constants.php**

## Example

This is a single case example to turn on device, look at **index.php** in your root directory to get all possible methods.

```php
<?php

/*
Example Turn device ON
*/

//load all classes
require_once __DIR__ . '/autoloader.php';

//initizilze core classes to authorize
$httpClient = new HttpClient();
$token = new Token($httpClient);

//check if we already logged into ewelink app with email and password
if (isset($_GET['code']) && isset($_GET['region'])) {
    //if yes - redirect to main page
        $token->redirectToUrl(Constants::REDIRECT_URL);
} else {
    
    //time to check token or get token if neccessary and start requests to API
    if ($token->checkAndRefreshToken()) {
        $tokenData = $token->getTokenData();

            //at this place you should have token.json in you directory
            //its neccesary to get familyID before we contiunue with devices
            $home = $httpClient->getHome();
            $familyData = $home->fetchFamilyData();
            
            //lets initialize devices class 
            $devices = new Devices($httpClient);
            $devicesData = $devices->fetchDevicesData();

            //Set the device status (f.e turn off the device)
            $deviceId = '100xxxxxxxx'; //put here your DeviceID
            $params = ['switch' => 'on'];
            
            //get output
            $setStatusResultSingle = $devices->setDeviceStatus($deviceId, $params);
            echo '<h1>Set Single-Channel Device Status Result</h1>';
            echo '<pre>' . print_r($setStatusResultSingle, true) . '</pre>';

    } else {

            //if we have not valid token or not authenticated - put link to log in

        $loginUrl = $httpClient->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Login with OAuth</a>';
    }
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
│
├── autoloader.php
└── index.php
```

All classes are located in src directory.

Index.php works as a gateway to API and also as a debug for all availablemethods.

.json files outputs will be saved in project_root (ensure writeability)

## More inside info about current development

- released version is expected to be ready on **01.07.2024** latest
- main branch when commited used to be operative.
- with next stable release methods and invoking functions structure can be changed (keep this in mind)
- branch **websockets** will probably be not maintened anymore
- there could be some incosistency in the code still, like f.e handling error messages or orphan methods
- index.php is a quasi-test file which helps me to check new methods and it is also a great example
