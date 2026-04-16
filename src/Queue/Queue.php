<?php

namespace Queue;

use RedisCache\RedisClient;
use Rentvine\Logger;

class Queue
{
    private static $redis = null;

    public static function redis()
    {
        if (!self::$redis) {
            self::$redis = new RedisClient();
        }

        return self::$redis;
    }

    public static function reset()
    {
        self::$redis = null;
    }

    public static function push(array $job)
    {
        Logger::warning("PUSHING JOB TO QUEUE: " . json_encode($job));
        self::redis()->rPush('queue:default', json_encode($job));
    }

    public static function pop()
    {
        try {
            $data = self::redis()->blPop(['queue:default'], 5);

            if (!$data) {
                return null;
            }

            return json_decode($data[1], true);

        } catch (\RedisException $e) {
            self::reset();

            echo "Redis error: " . $e->getMessage() . PHP_EOL;

            sleep(1);
            return null;
        }
    }
}