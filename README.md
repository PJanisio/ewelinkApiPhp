# ewelinkApiPhp

API connector for Sonoff devices.

Based on [ewelink-api-python](https://github.com/AceExpert/ewelink-api-python/tree/master)

PHP 7.4+ required

## Public key and secret

You can generate here: [dev.ewelink](https://dev.ewelink.cc/)

## Structure

All classes are located in src directory.

Index.php works as a gateway to API.

.json outputs will be saved in project_root

`
project_root/
│
├── src/
│   ├── Constants.php
│   ├── HttpClient.php
│   └── Utils.php
│
└── index.php
`

## Example

See **index.php**
