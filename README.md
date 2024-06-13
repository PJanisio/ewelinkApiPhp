# ewelinkApiPhp

API connector for Sonoff/ewelink devices using simple webapi based on OAuth2.

PHP 7.4+, no other dependiencies required.

Version **0.10.7** is currently operative and ***pre released***, but not yet tested in 100%, so feedback is appreciated  **create issues / PR** if needed.

## Current features

- login and authorization to ewelink (with refresh token)
- get devices list with all parameters
- saving devices and other outputs from API to .json
- get any value of parameter for each device (f.e switch status, productName, MAC etc.)
- update parameter of device (switch on, off)
- check if device has MultiChannel support

## Public key and secret

Generate here: [dev.ewelink](https://dev.ewelink.cc/)

And put your keys and redirect Url in **Constants.php**

## Structure

```

project_root/
│
├── src/
│ ├── Constants.php
│ ├── HttpClient.php
│ └── Utils.php
| └── Devices.php
│
└── index.php

```

All classes are located in src directory.

Index.php works as a gateway to API and also as a debug for all availablemethods.

.json files outputs will be saved in project_root (ensure writeability)

## Example

Examples will follow with stable release, for now - see **index.php**

## More inside info about current development

- with next stable release methods and invoking functions structure can be changed (keep this in mind)
- branch **websockets** will probably be not maintened anymore
- there could be some incosistency in the code still, like f.e handling error messages
