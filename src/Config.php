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
            $file = self::configFilePath();
            $jsonConfig = [];

            // One switch for simplicity: when true we also read from config.json
            if (Constants::SAVE_CONFIG_JSON === true && file_exists($file)) {
                $json = file_get_contents($file);
                if ($json === false) {
                    trigger_error('Could not read config file: ' . $file, E_USER_WARNING);
                }
                $jsonConfig = is_string($json) ? json_decode($json, true) : [];
            }

            // merge order: overrides > json (optional) > constants
            self::$config = array_merge(
                self::fallbackConfig(),
                is_array($jsonConfig) ? $jsonConfig : [],
                self::$overrides
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
    public static function save(array $data): void
    {
        // Same switch controls saving
        if (Constants::SAVE_CONFIG_JSON !== true) {
            return; // do nothing when disabled
        }

        $file = self::configFilePath();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $result = file_put_contents($file, $json);
        if ($result === false) {
            trigger_error('Could not write to config file: ' . $file, E_USER_WARNING);
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
        $configPath = self::configFilePath();

        if (!file_exists($configPath)) {
            return; // No config.json to talk about
        }

        // Resolve paths (best-effort)
        $realConfig = realpath($configPath) ?: $configPath;
        $webRoot    = isset($_SERVER['DOCUMENT_ROOT']) ? (realpath($_SERVER['DOCUMENT_ROOT']) ?: null) : null;

        // If saving is disabled but the file exists, inform the user
        if (Constants::SAVE_CONFIG_JSON !== true) {
            echo '<div style="color: red; margin: 16px 0;">';
            echo '⚠️ <b>Security Warning:</b> <code>SAVE_CONFIG_JSON=false</code>, but <code>config.json</code> still exists at ';
            echo '<code>' . htmlspecialchars($realConfig) . '</code>.<br>';
            echo 'The library will ignore it, but the file may still be accessible on disk (or via the web server).<br>';
            echo 'Please delete <code>config.json</code>.';
            echo '</div>';
            // Continue with the exposure warning below (in case it sits in webroot)
        }

        // Existing security check: warn if the file sits under the public web root
        if ($webRoot && strpos($realConfig, $webRoot) === 0 && Constants::SAVE_CONFIG_JSON === true) {
            echo '<div style="color: red; margin: 16px 0;">';
            echo '⚠️ <b>Security Warning:</b> <code>config.json</code> is stored inside your web server\'s public directory: ';
            echo '<code>' . htmlspecialchars($realConfig) . '</code><br>';
            echo 'Anyone could access it from the internet if not protected!<br>';
            echo 'Move it outside the web root by changing <code>JSON_LOG_DIR</code> in <code>Constants.php</code>, ';
            echo 'or restrict access with permissions or web server rules.';
            echo '</div>';
        }
    }

    /**
     * Get the path to the config.json file.
     * Uses overrides first, then falls back to Constants.
     *
     * @return string The path to config.json.
     */
    private static function configFilePath(): string
    {
        // Use overrides first (per-site), then constants
        $dir = self::$overrides['JSON_LOG_DIR'] ?? Constants::JSON_LOG_DIR;
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config.json';
    }
}
