<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

namespace pjanisio\ewelinkapiphp;

use pjanisio\ewelinkapiphp\Config;

class Utils
{
    public function __construct()
    {
        //not needed for now
    }


    /**
     * Generate a nonce (an 7-digit alphanumeric random string).
     *
     * @return string The generated nonce.
     */
    public function generateNonce()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $nonce = '';
        for ($i = 0; $i < 8; $i++) {
            $nonce .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $nonce;
    }

    /**
     * Sign the data using HMAC-SHA256 and return a base64 encoded string.
     *
     * @param string $data The data to be signed.
     * @param string $secret The secret key used for signing.
     * @return string The base64 encoded signature.
     */
    public function sign($data, $secret)
    {
        $hash = hash_hmac('sha256', $data, $secret, true);
        return base64_encode($hash);
    }

    /**
     * Handle redirect and capture code and region from URL.
     *
     * @return array The captured code and region.
     */
    public function handleRedirect()
    {
        $code = isset($_GET['code']) ? $_GET['code'] : null;
        $region = isset($_GET['region']) ? $_GET['region'] : null;
        return [$code, $region];
    }

    /**
     * Check if JSON files in the specified directory are valid and retrieve their creation timestamps.
     * Can be used for debugging - not used in any checks
     * @return array The validation results and creation timestamps of the JSON files.
     */
    public function checkJsonFiles()
    {
        $files = glob(Config::get('JSON_LOG_DIR') . '/*.json');
        $results = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $isValidJson = json_decode($content) !== null;
            $creationTime = filectime($file);
            $results[] = [
                'file' => $file,
                'validation' => $isValidJson,
                'creation_time' => $creationTime
            ];
        }

        return $results;
    }

    /**
     * Validate the constants in the Constants class.
     *
     * @return array Validation results for REDIRECT_URL, EMAIL, REGION, CONFIG_JSON PATH.
     */
    public function validateConfig(): array
    {
        $results = [];

        /* ---------- REDIRECT_URL ---------- */
        $url = Config::get('REDIRECT_URL');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $urlIsValid = filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array($scheme, ['http', 'https'], true);

        $results['REDIRECT_URL'] = [
            'value'   => $url,
            'is_valid' => $urlIsValid,
            'message' => $urlIsValid
                ? 'URL looks syntactically correct.'
                : 'Invalid URL syntax or scheme (must start with http/https).',
        ];

        /* ---------- EMAIL ---------- */
        $email = Config::get('EMAIL');
        $emailIsValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        $results['EMAIL'] = [
            'value'   => $email,
            'is_valid' => $emailIsValid,
            'message' => $emailIsValid
                ? 'E-mail address is syntactically valid.'
                : 'Invalid e-mail address syntax.',
        ];

        /* ---------- REGION ---------- */
        $region = Config::get('REGION');
        $validRegions  = ['cn', 'us', 'eu', 'as'];
        $regionIsValid = in_array($region, $validRegions, true);

        $results['REGION'] = [
            'value'   => $region,
            'is_valid' => $regionIsValid,
            'message' => $regionIsValid
                ? 'Region code is recognised.'
                : 'Invalid region code (allowed: cn, us, eu, as).',
        ];

        // CONFIG_JSON_PATH writability check
        $configPath = Constants::CONFIG_JSON_PATH;
        $dir = dirname($configPath);

        if (file_exists($configPath)) {
            $isWritable = is_writable($configPath);
            $message = $isWritable
                ? 'config.json exists and is writable.'
                : 'config.json exists but is NOT writable!';
        } else {
            $isWritable = is_writable($dir);
            $message = $isWritable
                ? 'Directory for config.json is writable.'
                : 'Directory for config.json is NOT writable!';
        }
        $results['CONFIG_JSON_PATH'] = [
            'value'    => $configPath,
            'is_valid' => $isWritable,
            'message'  => $message,
        ];

        return $results;
    }

    /**
     * Sanitize a string by removing non-printable characters.
     *
     * @param string $input The string to be sanitized.
     * @return string The sanitized string.
     */
    public static function sanitizeString($input): string
    {
        if (!is_string($input)) {
            return $input;
        }
        return preg_replace('/[[:^print:]]/', '', $input);
    }

    /**
     * Log debug information to a file.
     *
     * @param string $class The class name.
     * @param string $method The method name.
     * @param array $params The parameters sent in the request.
     * @param array $headers The headers sent in the request.
     * @param mixed $output The output of the request.
     * @param string $callerClass The calling class name.
     * @param string $callerMethod The calling method name.
     * @param string $url The URL of the request.
     */
    public static function debugLog($class, $method, $params, $headers, $output, $callerClass, $callerMethod, $url)
    {
        if (Config::get('DEBUG') != 1) {
            return;
        }
        $date = date('Y-m-d H:i:s');
        $output = is_array($output) ? array_map([self::class, 'sanitizeString'], $output) : self::sanitizeString($output);
        $logEntry = sprintf(
            "[%s] %s::%s invoked by %s::%s\nParameters: %s\nHeaders: %s\nOutput: %s\nURL: %s\n\n",
            $date,
            $class,
            $method,
            $callerClass,
            $callerMethod,
            json_encode($params),
            json_encode($headers),
            var_export($output, true),
            $url
        );
        file_put_contents(Config::get('JSON_LOG_DIR') . '/debug.log', $logEntry, FILE_APPEND);
    }

    /**
 * Print HTML with links to debug log and raw JSON files if they exist.
 *
 * @return void
 */
    public static function showDebugAndJsonLinks()
    {
        // Show debug link if DEBUG is enabled
        if (Config::get('DEBUG') == 1) {
            echo '<h1>Debug is ON</h1>';
            echo '<ul>';
            if (file_exists(Config::get('JSON_LOG_DIR') . '/debug.log')) {
                echo '<li><a href="debug.log" target="_blank">debug.log</a></li>';
            }
            echo '</ul>';
        }

        // List raw JSON files if they exist
        $jsonFiles = [
        'devices.json',
        'family.json',
        'token.json',
        ];
        $foundAny = false;
        foreach ($jsonFiles as $filename) {
            if (file_exists(Config::get('JSON_LOG_DIR') . '/' . $filename)) {
                $foundAny = true;
                break;
            }
        }
        if ($foundAny) {
            echo '<h1>JSON Files</h1><ul>';
            foreach ($jsonFiles as $filename) {
                if (file_exists(Config::get('JSON_LOG_DIR') . '/' . $filename)) {
                    echo '<li><a href="' . htmlspecialchars($filename) . '" target="_blank">' . htmlspecialchars($filename) . '</a></li>';
                }
            }
            echo '</ul>';
            echo '-----------------------------------------------------------';
        }
    }
}
