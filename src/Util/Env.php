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
            try {
                $dotenv = Dotenv::createImmutable(dirname(__DIR__));
                $dotenv->load();
            } catch (\Throwable $e) {
                // .env not found or unreadable — fall back to system env vars
            }
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

    public static function openAIKey(): string
    {
        self::setup();
        return $_ENV['OPEN_AI_KEY'] ?? '';
    }

    public static function getProjectUrl(): string
    {
        self::setup();
        return $_ENV['PROJECT_URL'] ?? '';
    }

    public static function getRedisPort(): string
    {
        self::setup();
        return $_ENV['REDIS_PORT'] ?? '';
    }

    public static function getRedisHost(): string
    {
        self::setup();
        return $_ENV['REDIS_HOST'] ?? '';
    }

    public static function getRentvineApiUsername(): string {
        self::setup();
        return $_ENV['RENTVINE_API_USERNAME'] ?? '';
    }

    public static function getRentvineApiPassword(): string {
        self::setup();
        return $_ENV['RENTVINE_API_PASSWORD'] ?? '';
    }

    public static function getAutoDBApiUrl(): string {
        self::setup();
        return $_ENV['AUTODB_API_URL'] ?? '';
    }

    public static function getAutoDBApiToken(): string {
        self::setup();
        return $_ENV['AUTODB_API_TOKEN'] ?? '';
    }

    public static function getAppEmailUsername(): string {
        self::setup();
        return $_ENV['GMAIL_APP_EMAIL_USER'] ?? '';
    }

    public static function getAppEmailFrom(): string {
        self::setup();
        return $_ENV['GMAIL_APP_EMAIL_FROM'] ?? '';
    }

    public static function getAppEmailPassword(): string {
        self::setup();
        return $_ENV['GMAIL_APP_PASSWORD'] ?? '';
    }

    public static function getBatchBillsNotificationEmail(): string {
        self::setup();
        return $_ENV['BILLS_NOTIFICATION_EMAIL'] ?? '';
    }
}
