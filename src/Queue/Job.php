<?php

namespace Queue;

use Rentvine\Logger;

abstract class Job
{
    public static function dispatch(array $payload = [])
    {
        $job = [
            'class' => static::class,
            'payload' => $payload,
            'retries' => 0,
        ];
        Logger::warning("Dispatch JOB: " . json_encode($job));

        Queue::push($job);
    }

    abstract public function handle(array $payload);
}