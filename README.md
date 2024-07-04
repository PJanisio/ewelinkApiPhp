# eWeLink API PHP

:link: [Download latest release](https://github.com/PJanisio/ewelinkApiPhp/releases)

eWeLink API PHP is a connector for Sonoff / eWeLink devices. This library allows you to interact with your eWeLink-enabled devices from your browser.

## Requirements

- PHP 7.4+
- cURL extension enabled

## Current features

- get all devices list with their parameters using **deviceId** or **deviceName** from ewelink app
- saving devices data and other outputs from API to **.json**
- search for **any value** of each device (f.e switch status, productName, MAC etc.)
- set any parameter/state of device using **HTTP gateway** or **websockets**
- set parameter for **multi-channel** devices (like 4CH Pro)
- update power parameters like **current, voltage, power** for electricity monitoring devices
- debug all requests and responses to **debug.log**

## Documentation

Go to [Wiki Pages](https://github.com/PJanisio/ewelinkApiPhp/wiki) to get started read about possible methods.

## Example

This is a single case example to turn on device.

Look at [Wiki Pages](https://github.com/PJanisio/ewelinkApiPhp/wiki) to get knowledge of how to start and other methods.

```php
<?php
$deviceId = 'your_device_id';

$params = ['switch' => 'on']; 
$statusUpdateResult = $devices->setDeviceStatus($deviceId, $params);

echo $statusUpdateResult;

```

## Ready to deploy Device Monitoring application

Please see example app written based on this class that checks and update chosen parameters in real time (using asynchronous calls) using both HTTP and websocket method alltogether.

[Device Monitoring APP](https://github.com/PJanisio/ewelinkapiphp-device-monitoring)

![screencapture-nastran-org-modules-dev-ewelink-index-html-2024-07-02-20_59_48](https://github.com/PJanisio/ewelinkApiPhp/assets/9625885/a515edf8-edd0-440c-90b3-77d8d5b398d0)

## Tech info

Visit wiki page for devs: [devs-wiki](https://github.com/PJanisio/ewelinkApiPhp/wiki/Developers)
