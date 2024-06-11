# ewelinkApiPhp

API connector for Sonoff devices.

Based on [ewelink-api-python](https://github.com/AceExpert/ewelink-api-python/tree/master)

PHP 7.4+ required

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
│
└── index.php

```

All classes are located in src directory.

Index.php works as a gateway to API.

.json outputs will be saved in project_root

## Example

See **index.php**
