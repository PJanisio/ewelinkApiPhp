<?php

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
     * Redirect to a given URL after a delay.
     *
     * @param string $url The URL to redirect to.
     * @param int $delay The delay in seconds before redirecting.
     */
    public function redirectToUrl($url, $delay = 2) {
        echo '<meta http-equiv="refresh" content="' . $delay . ';url=' . htmlspecialchars($url) . '">';
    }
}
