<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

class Utils {

    public function __construct() {
        $this->checkDisabledFunctions();
    }

    /**
     * Check for disabled functions and print a warning if any are disabled.
     */
    private function checkDisabledFunctions() {
        $requiredFunctions = ['pcntl_fork', 'posix_kill'];
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);

        foreach ($requiredFunctions as $function) {
            if (in_array($function, $disabledFunctions)) {
                echo "Warning: The function $function is disabled on this server. Some functionality may be limited.\n";
            }
        }
    }

    /**
     * Generate a nonce (an 7-digit alphanumeric random string).
     *
     * @return string The generated nonce.
     */
    public function generateNonce() {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $nonce = '';
        for ($i = 0; $i < 8; $i++) {
            $nonce .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $nonce;
    }

    /**
     * Generate a UUID.
     *
     * @return string The generated UUID.
     */
    public function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Sign the data using HMAC-SHA256 and return a base64 encoded string.
     *
     * @param string $data The data to be signed.
     * @param string $secret The secret key used for signing.
     * @return string The base64 encoded signature.
     */
    public function sign($data, $secret) {
        $hash = hash_hmac('sha256', $data, $secret, true);
        return base64_encode($hash);
    }

    /**
     * Handle redirect and capture code and region from URL.
     *
     * @return array The captured code and region.
     */
    public function handleRedirect() {
        $code = isset($_GET['code']) ? $_GET['code'] : null;
        $region = isset($_GET['region']) ? $_GET['region'] : null;
        return [$code, $region];
    }

    /**
     * Check if JSON files in the specified directory are valid and retrieve their creation timestamps.
     *
     * @return array The validation results and creation timestamps of the JSON files.
     */
    public function checkJsonFiles() {
        $files = glob(Constants::JSON_LOG_DIR . '/*.json');
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
 * @return array Validation results for REDIRECT_URL, EMAIL and REGION.
 */
public function validateConstants(): array
{
    $results = [];

    /* ---------- REDIRECT_URL ---------- */
    $url    = Constants::REDIRECT_URL;
    $scheme = parse_url($url, PHP_URL_SCHEME);
    $urlIsValid = filter_var($url, FILTER_VALIDATE_URL) !== false
               && in_array($scheme, ['http', 'https'], true);

    $results['REDIRECT_URL'] = [
        'value'   => $url,
        'is_valid'=> $urlIsValid,
        'message' => $urlIsValid
            ? 'URL looks syntactically correct.'
            : 'Invalid URL syntax or scheme (must start with http/https).',
    ];

    /* ---------- EMAIL ---------- */
    $email       = Constants::EMAIL;
    $emailIsValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

    $results['EMAIL'] = [
        'value'   => $email,
        'is_valid'=> $emailIsValid,
        'message' => $emailIsValid
            ? 'E-mail address is syntactically valid.'
            : 'Invalid e-mail address syntax.',
    ];

    /* ---------- REGION ---------- */
    $region        = Constants::REGION;
    $validRegions  = ['cn', 'us', 'eu', 'as'];
    $regionIsValid = in_array($region, $validRegions, true);

    $results['REGION'] = [
        'value'   => $region,
        'is_valid'=> $regionIsValid,
        'message' => $regionIsValid
            ? 'Region code is recognised.'
            : 'Invalid region code (allowed: cn, us, eu, as).',
    ];

    return $results;
}

    /**
     * Sanitize a string by removing non-printable characters.
     *
     * @param string $string The string to be sanitized.
     * @return string The sanitized string.
     */
    public function sanitizeString($input) {
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
    public function debugLog($class, $method, $params, $headers, $output, $callerClass, $callerMethod, $url) {
        if (Constants::DEBUG !== 1) {
            return;
        }
        $date = date('Y-m-d H:i:s');
        $output = is_array($output) ? array_map([$this, 'sanitizeString'], $output) : $this->sanitizeString($output);
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
        file_put_contents(Constants::JSON_LOG_DIR . '/debug.log', $logEntry, FILE_APPEND);
    }
}
