<?php

namespace Util;

use Dotenv\Dotenv;

class Env
{
    const DEV_ENV = 'dev';
    private static bool $loaded = false;

    private static function setup(): void
    {
        if (!self::$loaded) {
            $dotenv = Dotenv::createImmutable(__DIR__ . "../../");
            $dotenv->load();
            self::$loaded = true;
        }
    }

    public static function isDev(): bool
    {
        self::setup();
        return ($_ENV['APP_ENV'] ?? '') === self::DEV_ENV;
    }

    public static function isProd(): bool
    {
        self::setup();
        return ($_ENV['APP_ENV'] ?? '') !== self::DEV_ENV;
    }
}
