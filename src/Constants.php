<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/AceExpert/ewelink-api-python
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

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

    // Enable or disable debug logging
    const DEBUG = 0; // Change to 1 to enable debug logging

    // Path for JSON logs directory
    const JSON_LOG_DIR = __DIR__ . '/..';

    // Error codes
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
        30003 => 'Failed to notify the device to disconnect from the temporary persistent connection, when adding a GSM device.',
        30007 => 'Failed to add the GSM device, because it has been added by another user before.',
        30008 => 'When you are sharing devices, the shared user does not exist.',
        30009 => 'You have exceeded the limit of groups you can have for your current subscription plan.',
        30010 => 'The device ID format is wrong for the device being added.',
        30011 => 'The factory data cannot be found in the device being added.',
        30012 => 'The "extra" field of factory data cannot be found in the device being added.',
        30013 => 'The brand info of factory data cannot be found.',
        30014 => 'There is an error with the chipid.',
        30015 => 'There is a digest error when a device is being added.',
        30016 => 'The appid could not be found when a device is being added.',
        30017 => 'This appid is not allowed to add the devices of the current brand.',
        30018 => 'No device can be found with current deviceid.',
        30019 => 'The product model of factory data cannot be found.',
        30022 => 'The device is offline and the operation fails. It will appear in batch updating the device status.'
    ];
}
