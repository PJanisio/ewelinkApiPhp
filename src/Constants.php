<?php

class Constants {
    const APP_ID = 'YOUR_APP_ID';
    const APP_SECRET = 'YOUR_APP_SECRET';
    const USER_AGENT = 'Your User Agent';
    const DEVICE_MODEL = 'Device Model';
    const ROM_VERSION = 'Rom Version';
    const APP_VERSION = 'App Version';

    const ERROR_CODES = [
        400 => 'Parameter error, usually the parameter required by the interface is missing, or the type or value of the parameter is wrong.',
        401 => 'Access token authentication error. Usually, the account is logged in by others, resulting in the invalidation of the current access token.',
        402 => 'Access token expired.',
        403 => 'The interface cannot be found, usually the interface URL is written incorrectly.',
        405 => 'The resource cannot be found. Usually, the necessary data records cannot be found in the back-end database.',
        406 => 'Reject the operation. Usually, the current user does not have permission to operate the specified resource.',
        407 => 'Appid has no operation permission.',
        412 => 'APPID calls exceed the limit, you can upgrade to the enterprise version by contacting bd@coolkit.cn.',
        500 => 'Server internal error, usually the server program error.',
        4002 => 'Device control failure (Check control parameter transmission or device online status).',
        30022 => 'The device is offline and the operation fails. It will appear in batch updating the device status.'
    ];
}

