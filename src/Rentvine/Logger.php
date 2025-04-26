<?php

namespace Rentvine;
class Logger
{
    public static function warning($message)
    {
        $logFile = './api.log';
        if (!file_exists($logFile)) {
            touch($logFile);
        }

        file_put_contents($logFile, "$message\n", FILE_APPEND);
    }
}
