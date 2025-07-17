<?php

/*
Dummy constants test to allow running tests in tests directory
*/

namespace pjanisio\ewelinkapiphp;

final class Constants
{
    public const APPID        = 'TEST_APP_ID';
    public const APP_SECRET   = 'TEST_SECRET';
    public const REDIRECT_URL = 'https://example.com';
    public const REGION       = 'us';
    public const EMAIL        = 'tester@example.com';
    public const PASSWORD = 'test-password';
    public const DEBUG        = 0;
    public const JSON_LOG_DIR = __DIR__;
    public const ERROR_CODES  = [];
    public const CONFIG_JSON_PATH = __DIR__ . '/test-config.json';
}

require dirname(__DIR__) . '/vendor/autoload.php';
