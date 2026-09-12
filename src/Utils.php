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

        // REDIRECT_URL
        $url   = Config::get('REDIRECT_URL');
        $valid = filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
        $out['REDIRECT_URL'] = [
            'value'    => $url,
            'is_valid' => (bool)$valid,
            'message'  => $valid ? 'URL looks syntactically correct.' : 'Invalid URL or scheme.',
        ];

        // EMAIL
        $email = Config::get('EMAIL');
        $emailOk = (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
        $out['EMAIL'] = [
            'value'    => $email,
            'is_valid' => $emailOk,
            'message'  => $emailOk ? 'E-mail syntax is valid.' : 'Invalid e-mail address.',
        ];

        // REGION
        $region = Config::get('REGION');
        $regionOk  = in_array($region, ['cn', 'us', 'eu', 'as'], true);
        $out['REGION'] = [
            'value'    => $region,
            'is_valid' => $regionOk,
            'message'  => $regionOk ? 'Region code recognised.' : 'Invalid region code.',
        ];

        // OPTIONAL: ensure credentials are present (non-empty strings)
        $appid      = Config::get('APPID');
        $appSecret  = Config::get('APP_SECRET');
        $appidOk    = is_string($appid) && $appid !== '';
        $secretOk   = is_string($appSecret) && $appSecret !== '';
        $out['APPID'] = [
            'value'    => $appid,
            'is_valid' => $appidOk,
            'message'  => $appidOk ? 'APPID set.' : 'APPID is empty.',
        ];
        $out['APP_SECRET'] = [
            'value'    => $appSecret ? substr($appSecret, 0, 4) . '…' : '',
            'is_valid' => $secretOk,
            'message'  => $secretOk ? 'APP_SECRET set.' : 'APP_SECRET is empty.',
        ];

        // JSON_LOG_DIR + config.json permissions
        $logDirRaw = (string)(Config::get('JSON_LOG_DIR') ?? '');
        $logDir    = rtrim($logDirRaw, "/\\"); // normalize
        if ($logDir === '') {
            $out['JSON_LOG_DIR'] = [
                'value'    => $logDirRaw,
                'is_valid' => false,
                'message'  => 'JSON_LOG_DIR is empty.',
            ];
        } elseif (file_exists($logDir) && !is_dir($logDir)) {
            // Edge case: path exists but is a file
            $out['JSON_LOG_DIR'] = [
                'value'    => $logDir,
                'is_valid' => false,
                'message'  => 'JSON_LOG_DIR points to a file, not a directory.',
            ];
        } elseif (!is_dir($logDir)) {
            $parent = dirname($logDir) ?: '.';
            $ok  = is_writable($parent) && !file_exists($logDir); // can create dir here?
            $msg = $ok
                ? 'JSON_LOG_DIR does not exist but parent is writable (will be created on first save).'
                : 'JSON_LOG_DIR does not exist and parent directory is NOT writable!';
            $out['JSON_LOG_DIR'] = ['value' => $logDir, 'is_valid' => $ok, 'message' => $msg];
        } else {
            $ok  = is_writable($logDir);
            $msg = $ok ? 'JSON_LOG_DIR exists and is writable.' : 'JSON_LOG_DIR exists but is NOT writable!';
            $out['JSON_LOG_DIR'] = ['value' => $logDir, 'is_valid' => $ok, 'message' => $msg];
        }

        // Use a safe default if constant missing in older installs
        $saveEnabled = \defined(\pjanisio\ewelinkapiphp\Constants::class . '::SAVE_CONFIG_JSON')
            ? \pjanisio\ewelinkapiphp\Constants::SAVE_CONFIG_JSON
            : true;

        // config.json checks
        $cfgFile = ($logDir === '' ? 'config.json' : $logDir . DIRECTORY_SEPARATOR . 'config.json');
        if ($saveEnabled === true) {
            if (file_exists($cfgFile)) {
                $ok2  = is_writable($cfgFile);
                $msg2 = $ok2 ? 'config.json is writable.' : 'config.json exists but is NOT writable!';
            } else {
                // Directory must be writable to create config.json
                $ok2  = $logDir !== '' && is_dir($logDir) && is_writable($logDir);
                $msg2 = $ok2 ? 'Directory writable; config.json can be created.' : 'Directory NOT writable; cannot create config.json!';
            }
            $out['CONFIG_JSON_FILE'] = ['value' => $cfgFile, 'is_valid' => $ok2, 'message' => $msg2];
        } else {
            $out['CONFIG_JSON_FILE'] = [
                'value'    => $cfgFile,
                'is_valid' => true,
                'message'  => 'Saving disabled (SAVE_CONFIG_JSON=false); config.json will not be read or written.',
            ];
        }

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
                date('Y-m-d H:i:s', intdiv((int) $tokenData['atExpiredTime'], 1000)) . '</p>';
            Config::warnIfConfigExposed();
            self::showDebugAndJsonLinks();

            if (is_callable($afterAuthCallback)) {
                $afterAuthCallback($http, $token);
            }
        } else {
            // 3. Not authorised yet -------------------------------------------
            $loginUrl = htmlspecialchars($http->getLoginUrl(), ENT_QUOTES, 'UTF-8');
            echo '<a href="' . $loginUrl . '">Authorize ewelinkApiPhp</a>';
        }
        exit;
    }
}
