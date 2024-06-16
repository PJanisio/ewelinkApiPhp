<?php

class Constants {
    // Your eWeLink application ID, obtained from the eWeLink developer platform
    const APPID = 'your_app_id';
    
    // Your eWeLink application secret, obtained from the eWeLink developer platform
    const APP_SECRET = 'your_app_secret';
    
    // The URL to which eWeLink will redirect after OAuth authentication
    const REDIRECT_URL = 'your_redirect_url';
    
    // Your eWeLink account email
    const EMAIL = 'your_email';
    
    // Your eWeLink account password
    const PASSWORD = 'your_password';
    
    // The region in which your eWeLink account is registered
    // Supported regions:
    // Mainland China: 'cn'
    // Americas: 'us'
    // Europe: 'eu'
    // Asia: 'as'
    const REGION = 'your_region';
    


    //do not change
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

