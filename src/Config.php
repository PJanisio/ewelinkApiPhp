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

    public static function load()
    {
        if (self::$config === null) {
            $file = Constants::CONFIG_JSON_PATH;
            if (file_exists($file)) {
                self::$config = json_decode(file_get_contents($file), true);
            } else {
                // fallback to constants
                self::$config = [
                    'APPID'        => Constants::APPID,
                    'APP_SECRET'   => Constants::APP_SECRET,
                    'REDIRECT_URL' => Constants::REDIRECT_URL,
                    'EMAIL'        => Constants::EMAIL,
                    'PASSWORD'     => Constants::PASSWORD,
                    'REGION'       => Constants::REGION,
                    'DEBUG'        => Constants::DEBUG,
                    'JSON_LOG_DIR' => Constants::JSON_LOG_DIR,
                ];
            }
        }
        return self::$config;
    }

    public static function get($key)
    {
        $cfg = self::load();
        return $cfg[$key] ?? null;
    }

    public static function save(array $data)
    {
        $file = Constants::CONFIG_JSON_PATH;
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        self::$config = $data; // update cache
    }
}
