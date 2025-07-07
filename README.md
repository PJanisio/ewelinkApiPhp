# eWeLink API PHP – Connect to Sonoff / eWeLink devices

[![Packagist](https://img.shields.io/packagist/v/pjanisio/ewelink-api-php?logo=composer)](https://packagist.org/packages/pjanisio/ewelink-api-php)
[![PHP >= 7.4](https://img.shields.io/badge/PHP-7.4%2B-777bb3?logo=php)](https://www.php.net/supported-versions.php)
[![License](https://img.shields.io/github/license/PJanisio/ewelinkApiPhp)](LICENSE)

`ewelink-api-php` lets you talk to your eWeLink–enabled devices (Sonoff, KingArt, etc.) **directly from PHP**. It wraps the official HTTP & WebSocket endpoints, handles OAuth, and gives you a neat object‑oriented façade.

---

## 📦 Installation

```bash
composer require pjanisio/ewelink-api-php
```

Composer installs the library, creates `vendor/autoload.php`, and you’re ready to go.

---

## 🚀 Quick‑start

```php
<?php
require __DIR__.'/vendor/autoload.php';

use pjanisio\ewelinkapiphp\HttpClient;

$http = new HttpClient();  // takes creds from constants / env
$token = $http->getToken(); // OAuth flow (auto‑refreshes)

$devices = $http->getDevices(); // Devices façade
$list    = $devices->getDevicesList();

print_r($list);  // see everything at once

$lampId  = '100xxxxxx';
$devices->setDeviceStatus($lampId, ['switch' => 'on']);  // turn it on
```

Full examples live in the **[Wiki](https://github.com/PJanisio/ewelinkApiPhp/wiki)**.

---

## ✅ Features

| Area            | What you can do                                                                                                                     |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| **Discovery**   | • Fetch *all* devices in one call<br>• Search by `deviceid` **or** human name<br>• Persist raw data as `devices.json`               |
| **Status**      | • Read any single param live (`switch`, `voltage`, `power`, …)<br>• Grab *all* live params at once<br>• Check if a device is online |
| **Control**     | • Set one or many params (HTTP)<br>• Multi‑channel helpers (`switches[n]`)<br>• WebSocket realtime control                          |
| **Monitoring**  | • Live power metrics (voltage / current / power)<br>• Device history endpoint (`/v2/device/history`)                                |
| **Maintenance** | • Force wake‑up (handshake + echo params)<br>• Family/home helper (`getCurrentFamilyId`)                                            |
| **Dev tools**   | • PSR‑4 autoloading via Composer<br>• `DEBUG` mode – full request/response log to `debug.log`                                       |

---

## 🖥️ Demo Monitoring App

Need an out‑of‑the‑box dashboard? Check the companion project **[ewelinkapiphp‑device‑monitoring](https://github.com/PJanisio/ewelinkapiphp-device-monitoring)** – asynchronous UI, HTTP + WS under the hood.

![Monitoring screenshot](https://github.com/PJanisio/ewelinkApiPhp/assets/9625885/7658cbe6-cdb9-48bc-9f0d-1a2db4e67147)

---

## 🗂 Documentation

* **Getting started / API reference →** see the [Wiki Pages](https://github.com/PJanisio/ewelinkApiPhp/wiki)
* **Developer notes** (architecture, contribution guide) → [Developers Wiki](https://github.com/PJanisio/ewelinkApiPhp/wiki/Developers)

---

## ⚙️ Requirements

* PHP **7.4 or newer**
* Extensions: `curl`, `json` (both enabled by default on typical PHP installs)

---

## 📝 License

MIT – do what you want, just keep the copyright notice.

---

**Happy hacking & enjoy your smart devices!**
