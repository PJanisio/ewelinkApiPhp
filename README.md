# eWeLink API PHP – Connect to Sonoff / eWeLink devices

[![Packagist](https://img.shields.io/packagist/v/pjanisio/ewelink-api-php?logo=composer)](https://packagist.org/packages/pjanisio/ewelink-api-php)
[![PHP >= 7.4](https://img.shields.io/badge/PHP-7.4%2B-777bb3?logo=php)](https://www.php.net/supported-versions.php)
[![CI](https://github.com/PJanisio/ewelinkApiPhp/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/PJanisio/ewelinkApiPhp/actions/workflows/ci.yml)
[![License](https://img.shields.io/github/license/PJanisio/ewelinkApiPhp)](LICENSE)

`ewelink-api-php` lets you talk to your eWeLink–enabled devices (Sonoff, KingArt, etc.) **directly from PHP**. It wraps the official HTTP & WebSocket endpoints, handles OAuth, and gives you a neat object‑oriented façade.

---

## 📦 Installation

```bash
composer require pjanisio/ewelink-api-php
```

Composer installs the library, creates `vendor/autoload.php`, and you’re ready to go.

---

## 🚀 Quick‑example (after authorization)

```php
$lampId  = '100xxxxxx';
$devices->setDeviceStatus($lampId, ['switch' => 'on']);  // turn it on
```

Full examples live in the **[Wiki](https://github.com/PJanisio/ewelinkApiPhp/wiki)**.

---

## ✅ Features

| Area            | What you can do                                                                                                                     |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| **Discovery**   | • Fetch *all* devices in one call<br>• Search by `deviceid` **or** human name<br>• Persist raw data as `devices.json`               |
| **Status**      | • Read any single or multi params live (`switch`, `voltage`, `power`, …)<br>• Grab *all* live params at once<br>• Check if a device is online |
| **Control**     | • Set one or many params (HTTP)<br>• Multi‑channel helpers (`switches[n]`)<br>• WebSocket realtime control                          |
| **Monitoring**  | • Live power metrics (voltage / current / power)<br>• Device history endpoint (`/v2/device/history`)                                |
| **Maintenance** | • Force wake‑up (handshake + echo params)                                                                                           |
| **Dev tools**   | • PSR‑4 autoloading via Composer<br>• `DEBUG` mode – full request/response log to `debug.log`                                       |

---

## 🖥️ Demo Monitoring App

Need an out‑of‑the‑box dashboard? Check the companion project **[ewelinkapiphp‑device‑monitoring](https://github.com/PJanisio/ewelinkapiphp-device-monitoring)** – asynchronous UI, HTTP + WS under the hood.

![Monitoring screenshot](https://github.com/PJanisio/ewelinkApiPhp/assets/9625885/7658cbe6-cdb9-48bc-9f0d-1a2db4e67147)

---

## 🗂 Documentation

* **Getting started / API reference →** see the [Wiki Pages](https://github.com/PJanisio/ewelinkApiPhp/wiki)
* **Developer notes** (architecture, contribution guide) → [Developers Wiki](https://github.com/PJanisio/ewelinkApiPhp/wiki/Developers)

### Realtime listener

The WebSocket client can now stay connected and stream updates instead of opening a socket for a single request. Once the connection is established, you can subscribe to a device and receive updates through a callback:

```php
$devices = $httpClient->getDevices();
$devices->listenForUpdates('Kitchen light', function (array $message) {
    if (isset($message['params'])) {
        var_dump($message['params']);
    }
}, ['switch', 'online'], 30);
```

This keeps the socket alive for a short listening window and is suitable for real-time dashboards or event-driven monitoring.

---

## ⚙️ Requirements

* PHP **7.4 or newer**
* Extensions: `curl`, `json`, `openssl` ( enabled by default on typical PHP installs)

---

## 📝 License

MIT – do what you want, just keep the copyright notice.

---

## 🔗 Integrations & Use Cases

* **Drupal eWeLink module** – integrate Sonoff devices into your Drupal site via [drupal.org/project/ewelink](https://www.drupal.org/project/ewelink)  
* **Bee Hotel Reservation Access** – powers the Bee Hotel, see [Bee Hotel docs](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/bee-hotel/11-reservation-access-by-beehotel)  
