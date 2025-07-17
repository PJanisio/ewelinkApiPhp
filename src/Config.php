<?php

/**
 * Class: Config
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: Handles dynamic configuration (config.json) for eWeLink API.
 */

namespace pjanisio\ewelinkapiphp;

class Config
{
    /** @var array|null The loaded configuration array */
    private static $config = null;

    /**
     * Load configuration from config.json, or fallback to Constants if file is missing or invalid.
     * Logs error via Utils::debugLog if file cannot be read or parsed.
     *
     * @return array The configuration array.
     */
    public static function load()
    {
        if (self::$config === null) {
            $file = Constants::CONFIG_JSON_PATH;
            if (file_exists($file)) {
                $json = @file_get_contents($file);
                if ($json === false) {
                    // Log error
                    Utils::debugLog(
                        __CLASS__,
                        __FUNCTION__,
                        ['file' => $file],
                        [],
                        'Could not read config file',
                        __CLASS__,
                        __FUNCTION__,
                        $file
                    );
                    self::$config = self::fallbackConfig();
                } else {
                    $arr = json_decode($json, true);
                    if (!is_array($arr)) {
                        // Log error
                        Utils::debugLog(
                            __CLASS__,
                            __FUNCTION__,
                            ['file' => $file, 'json' => $json],
                            [],
                            'Invalid JSON in config file',
                            __CLASS__,
                            __FUNCTION__,
                            $file
                        );
                        self::$config = self::fallbackConfig();
                    } else {
                        self::$config = array_merge(self::fallbackConfig(), $arr);
                    }
                }
            } else {
                self::$config = self::fallbackConfig();
            }
        }
        return self::$config;
    }

    /**
     * Get a config value by key, falling back to Constants if not found.
     *
     * @param string $key The config key to get.
     * @return mixed|null The value or null if missing.
     */
    public static function get($key)
    {
        $cfg = self::load();
        return $cfg[$key] ?? null;
    }

    /**
     * Save configuration array to config.json.
     * Logs error via Utils::debugLog if write fails.
     *
     * @param array $data Configuration data to save.
     * @return void
     */
    public static function save(array $data)
    {
        $file = Constants::CONFIG_JSON_PATH;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $result = @file_put_contents($file, $json);
        if ($result === false) {
            // Log error
            Utils::debugLog(
                __CLASS__,
                __FUNCTION__,
                ['file' => $file, 'data' => $data],
                [],
                'Could not write to config file',
                __CLASS__,
                __FUNCTION__,
                $file
            );
        } else {
            self::$config = $data; // update cache
        }
    }

    /**
     * Returns the fallback config array using Constants.
     *
     * @return array
     */
    private static function fallbackConfig()
    {
        return [
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
