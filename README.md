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

## Public key and secret

Generate here: [dev.ewelink](https://dev.ewelink.cc/)

And put your keys and redirect Url in **Constants.php**

## Example of main features

This is a long content example of almost all main features. If you want to get all of them, run **index.php** in your root directory.

```php
<?php

/*
Example shows main functionality of this class, edit this example for your needs
If you want more debug output, or see more options, run index.php.
*/

//load all classes
require_once __DIR__ . '/autoloader.php';
//initizilze core classes to authorize
$httpClient = new HttpClient();
$token = new Token($httpClient);

//check if we already logged into ewelink app with email and password
if (isset($_GET['code']) && isset($_GET['region'])) {
    //if so - redirect to main page
        $token->redirectToUrl(Constants::REDIRECT_URL);
    //if not we either needs token, or we are still not logged
} else {
    
    //time to get token and start requests to API
    if ($token->checkAndRefreshToken()) {
        $tokenData = $token->getTokenData();
            //at this place you should have token.json in you directory
            //its neccesary to get familyID before we contiunue with devices
            $home = $httpClient->getHome();
            $familyData = $home->fetchFamilyData();
            
            //lets initialize devices class and get all data displayed, because why not
            $devices = new Devices($httpClient);
            $devicesData = $devices->fetchDevicesData();
            echo '<h1>Devices Data</h1>';
            echo '<pre>' . print_r($devicesData, true) . '</pre>';
            
            //that was a complete output, now for better readibility lets focus on devicesId
            //which we will use in next operations:

            $devicesList = $devices->getDevicesList();
            echo '<h1>Devices List</h1>';
            echo '<pre>' . print_r($devicesList, true) . '</pre>';

            //if you want to search through the device parameters and get the value of search, use this functio
            $searchKey = 'productModel'; // example key to search for
            $deviceId = '100xxxxxx'; // example device ID
            
            //search and output
            $searchResult = $devices->searchDeviceParam($searchKey, $deviceId);
            echo '<h1>Search Result</h1>';
            echo '<pre>' . print_r($searchResult, true) . '</pre>';
            
            
            //Set the device status (f.e turn off the device)
            $singleChannelDeviceId = '100xxxxxxxx';
            $singleChannelParams = ['switch' => 'off'];
            
            //get output
            $setStatusResultSingle = $devices->setDeviceStatus($singleChannelDeviceId, $singleChannelParams);
            echo '<h1>Set Single-Channel Device Status Result</h1>';
            echo '<pre>' . print_r($setStatusResultSingle, true) . '</pre>';
            
            
            // Example usage of setDeviceStatus for multi-channel device
            $multiChannelDeviceId = '100xxxxxxxx';
            $multiChannelParams = [
                ['switch' => 'off', 'outlet' => 0],
                ['switch' => 'off', 'outlet' => 1],
                ['switch' => 'off', 'outlet' => 2],
                ['switch' => 'off', 'outlet' => 3]
            ];
            $setStatusResult = $devices->setDeviceStatus($multiChannelDeviceId, $multiChannelParams);
            echo '<h1>Set Multi-Channel Device Status Result</h1>';
            echo '<pre>' . print_r($setStatusResult, true) . '</pre>';


    } else {
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
