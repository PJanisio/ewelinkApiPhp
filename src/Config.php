<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

namespace pjanisio\ewelinkapiphp;

class Config
{
    private static $config = null;
    private static $configFile = __DIR__ . '/../config.json';

    public static function load()
    {
        if (self::$config === null) {
            if (file_exists(self::$configFile)) {
                self::$config = json_decode(file_get_contents(self::$configFile), true);
            } else {
                // Fallback to constants
                self::$config = [
                    'APPID' => Constants::APPID,
                    'APP_SECRET' => Constants::APP_SECRET,
                    'REDIRECT_URL' => Constants::REDIRECT_URL,
                    'EMAIL' => Constants::EMAIL,
                    'PASSWORD' => Constants::PASSWORD,
                    'REGION' => Constants::REGION,
                    'DEBUG' => Constants::DEBUG,
                    'JSON_LOG_DIR' => Constants::JSON_LOG_DIR,
                ];
            }
        }
        return self::$config;
    }

    public static function get($key)
    {
        $config = self::load();
        return $config[$key] ?? null;
    }

    public static function set($key, $value)
    {
        self::load();
        self::$config[$key] = $value;
    }

    public static function save()
    {
        file_put_contents(self::$configFile, json_encode(self::$config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
