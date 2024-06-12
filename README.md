# ewelinkApiPhp

API connector for Sonoff devices using OAuth2.

PHP 7.4+ required, no other dependencies.

## Public key and secret

Generate here: [dev.ewelink](https://dev.ewelink.cc/)

And put your keys and redirect Url in **Constants.php**

## Structure

```

project_root/
│
├── src/
│   ├── Constants.php
│   ├── HttpClient.php
│   └── Utils.php
|   └── Devices.php
│
└── index.php

```

All classes are located in src directory.

Index.php works as a gateway to API and also as a debug for all methods.

.json outputs will be saved in project_root

## Example

See **index.php**
