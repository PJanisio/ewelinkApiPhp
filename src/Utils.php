<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/AceExpert/ewelink-api-python
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

class Utils {
    /**
     * Generate a nonce (an 8-digit alphanumeric random string).
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
     * Check if JSON files in the root directory are valid and retrieve their creation timestamps.
     *
     * @return array The validation results and creation timestamps of the JSON files.
     */
    public function checkJsonFiles() {
        $files = glob('*.json');
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
     * @return array The validation results for REDIRECT_URL, EMAIL, and REGION.
     */
    public function validateConstants() {
        $results = [];

        // Validate REDIRECT_URL
        $url = Constants::REDIRECT_URL;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Set timeout to 5 seconds
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $results['REDIRECT_URL'] = [
            'value' => $url,
            'is_valid' => $httpCode >= 200 && $httpCode < 400 // Consider 2xx and 3xx responses as valid
        ];

        // Validate EMAIL
        $email = Constants::EMAIL;
        $results['EMAIL'] = [
            'value' => $email,
            'is_valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ];

        // Validate REGION
        $region = Constants::REGION;
        $validRegions = ['cn', 'us', 'eu', 'as'];
        $results['REGION'] = [
            'value' => $region,
            'is_valid' => in_array($region, $validRegions)
        ];

        return $results;
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
     */
    public function debugLog($class, $method, $params, $headers, $output, $callerClass, $callerMethod) {
        $date = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] %s::%s invoked by %s::%s\nParameters: %s\nHeaders: %s\nOutput: %s\n\n", 
                            $date, $class, $method, $callerClass, $callerMethod, json_encode($params), json_encode($headers), var_export($output, true));
        file_put_contents('debug.log', $logEntry, FILE_APPEND);
    }
}
