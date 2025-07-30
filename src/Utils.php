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
    /** Generate an 8‑character alphanumeric nonce. */
    public function generateNonce(): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $nonce = '';
        for ($i = 0; $i < 8; $i++) {
            $nonce .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $nonce;
    }

    /** Return base64‑encoded HMAC‑SHA256 signature. */
    public function sign(string $data, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $data, $secret, true));
    }

    /** Capture ?code & ?region from OAuth redirect. */
    public function handleRedirect(): array
    {
        return [$_GET['code'] ?? null, $_GET['region'] ?? null];
    }

    /** Validate JSON dump files (debug only). */
    public function checkJsonFiles(): array
    {
        $files   = glob(Config::get('JSON_LOG_DIR') . '/*.json');
        $results = [];
        foreach ($files as $file) {
            $results[] = [
                'file'          => $file,
                'validation'    => json_decode(file_get_contents($file)) !== null,
                'creation_time' => filectime($file),
            ];
        }
        return $results;
    }

    /** Validate critical config values and filesystem permissions. */
    public function validateConfig(): array
    {
        $out = [];

        //REDIRECT_URL
        $url   = Config::get('REDIRECT_URL');
        $valid = filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https']);
        $out['REDIRECT_URL'] = [
            'value'    => $url,
            'is_valid' => $valid,
            'message'  => $valid ? 'URL looks syntactically correct.' : 'Invalid URL or scheme.',
        ];

        //EMAIL
        $email = Config::get('EMAIL');
        $valid = filter_var($email, FILTER_VALIDATE_EMAIL);
        $out['EMAIL'] = [
            'value'    => $email,
            'is_valid' => $valid,
            'message'  => $valid ? 'E‑mail syntax is valid.' : 'Invalid e‑mail address.',
        ];

        //REGION
        $region = Config::get('REGION');
        $valid  = in_array($region, ['cn', 'us', 'eu', 'as'], true);
        $out['REGION'] = [
            'value'    => $region,
            'is_valid' => $valid,
            'message'  => $valid ? 'Region code recognised.' : 'Invalid region code.',
        ];

        //CONFIG_JSON_PATH permissions
        $path = Constants::CONFIG_JSON_PATH;
        $dir  = dirname($path);
        if (file_exists($path)) {
            $ok  = is_writable($path);
            $msg = $ok ? 'config.json exists and is writable.' : 'config.json exists but is NOT writable!';
        } else {
            $ok  = is_writable($dir);
            $msg = $ok ? 'Directory for config.json is writable.' : 'Directory for config.json is NOT writable!';
        }
        $out['CONFIG_JSON_PATH'] = ['value' => $path, 'is_valid' => $ok, 'message' => $msg];

        return $out;
    }

    /** Strip non‑printable characters (debug helper). */
    public static function sanitizeString($input): string
    {
        return is_string($input) ? preg_replace('/[[:^print:]]/', '', $input) : $input;
    }

    /** Write a verbose request/response debug log when DEBUG = 1. */
    public static function debugLog($class, $method, $params, $headers, $output, $callerClass, $callerMethod, $url): void
    {
        if (Config::get('DEBUG') != 1) {
            return;
        }
        $date   = date('Y-m-d H:i:s');
        $output = is_array($output) ? array_map([self::class, 'sanitizeString'], $output) : self::sanitizeString($output);
        $log    = sprintf(
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
        file_put_contents(Config::get('JSON_LOG_DIR') . '/debug.log', $log, FILE_APPEND);
    }

    /** Show quick links to debug log + raw JSON dumps. */
    public static function showDebugAndJsonLinks(): void
    {
        if (Config::get('DEBUG') == 1) {
            echo '<h2>Debug mode is ON</h2><ul>';
            if (file_exists(Config::get('JSON_LOG_DIR') . '/debug.log')) {
                echo '<li><a href="debug.log" target="_blank">debug.log</a></li>';
            }
            echo '</ul>';
        }

        $jsonFiles = ['devices.json', 'family.json', 'token.json'];
        $hasFiles  = array_filter($jsonFiles, fn($f) => file_exists(Config::get('JSON_LOG_DIR') . '/' . $f));
        if ($hasFiles) {
            echo '<h2>JSON Files</h2><ul>';
            foreach ($jsonFiles as $f) {
                if (file_exists(Config::get('JSON_LOG_DIR') . '/' . $f)) {
                    echo '<li><a href="' . htmlspecialchars($f) . '" target="_blank">' . htmlspecialchars($f) . '</a></li>';
                }
            }
            echo '</ul><hr />';
        }
    }

    /* ------------------------------------------------------------------ */
    /*  OAuth / flow helper                                               */
    /* ------------------------------------------------------------------ */

    /** Handle full OAuth flow and invoke $afterAuthCallback after login. */
    public static function handleAuthFlow($http, $token, $afterAuthCallback = null): void
    {
        // 1. Return from provider ----------------------------------------------
        if (isset($_GET['code'], $_GET['region'])) {
            try {
                $tokenData = $token->getToken();
                echo '<h2>Token Data</h2><pre>' . print_r($tokenData, true) . '</pre>';
                $token->redirectToUrl(Config::get('REDIRECT_URL'));
            } catch (\Exception $e) {
                echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
            }
            exit;
        }

        // 2. Already authorised -------------------------------------------------
        if ($token->checkAndRefreshToken()) {
            $tokenData = $token->getTokenData();
            echo '<h2>You are authenticated!</h2><p>Token expiry: ' .
                 date('Y-m-d H:i:s', $tokenData['atExpiredTime'] / 1000) . '</p>';
            Config::warnIfConfigExposed();

            if (is_callable($afterAuthCallback)) {
                $afterAuthCallback($http, $token);
            }
            self::showDebugAndJsonLinks();
        } else {
            // 3. Not authorised yet -------------------------------------------
            $loginUrl = htmlspecialchars($http->getLoginUrl(), ENT_QUOTES, 'UTF-8');
            echo '<a href="' . $loginUrl . '">Authorize ewelinkApiPhp</a>';
        }
        exit;
    }
}
