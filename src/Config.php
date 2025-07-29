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
    private static $overrides = [];

    /**
     * Allow passing in an override array.
     */
    public static function setOverrides(array $overrides)
    {
        self::$overrides = $overrides;
        // reset config to re-merge
        self::$config = null;
    }

    /**
     * Load configuration from config.json, or fallback to Constants if file is missing or invalid.
     * @return array The configuration array.
     */
    public static function load(): array
    {
        if (self::$config === null) {
            $file = Constants::CONFIG_JSON_PATH;
            $jsonConfig = [];
            if (file_exists($file)) {
                $json = file_get_contents($file);
                if ($json === false) {
                    trigger_error('Could not read config file: ' . $file, E_USER_WARNING);
                }
                $jsonConfig = is_string($json) ? json_decode($json, true) : [];
            }
            // merge order: overrides > config.json > constants
            self::$config = array_merge(
                self::fallbackConfig(),
                is_array($jsonConfig) ? $jsonConfig : [],
                self::$overrides // highest priority!
            );
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
     *
     * @param array $data Configuration data to save.
     * @return void
     */
    public static function save(array $data)
    {
        $file = Constants::CONFIG_JSON_PATH;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $result = file_put_contents($file, $json);
        if ($result === false) {
            trigger_error('Could not write to config file: ' . $file, E_USER_WARNING);
            return;
        }
    }

    /**
     * Returns the fallback config array using Constants.
     *
     * @return array
     */
    public static function fallbackConfig(): array
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

    /**
     * Warn the user if config.json is stored in a public web directory.
     * No action is taken if config.json does not exist.
     *
     * @return void
     */

    public static function warnIfConfigExposed()
    {
        // Try to resolve to the correct config.json path
        $configPath = self::get('CONFIG_JSON_PATH') ?? Constants::CONFIG_JSON_PATH;

        if (!file_exists($configPath)) {
            return; // No config.json to expose
        }

        // Try to resolve to real path
        $realConfig = realpath($configPath);
        $webRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : null;

        if ($realConfig && $webRoot && strpos($realConfig, $webRoot) === 0) {
            echo '<div style="color: red; font-weight: bold; margin: 16px 0;">';
            echo '⚠️ <b>Security Warning:</b> <code>config.json</code> is stored inside your web server\'s public directory: <code>' . htmlspecialchars($realConfig) . '</code><br>';
            echo 'Anyone could access it from the internet if not protected!<br>';
            echo 'Move it outside the web root, update <code>CONFIG_JSON_PATH</code> in <code>Constants.php</code>, or restrict access with permissions or web server rules.<br>';
            echo '</div>';
        }
    }
}
