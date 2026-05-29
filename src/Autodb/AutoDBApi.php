<?php

namespace Autodb;

use Rentvine\Logger;
use Throwable;
use Util\Curl;
use Util\Env;

class AutoDBApi
{
    public static function runRawQuery(array $query): ?string
    {
        try {
            Logger::warning("Run raw query: " . json_encode($query));
            $endpoint = Env::getAutoDBApiUrl() . "/api/raw_query";
            $authorizationString = Env::getAutoDBApiToken();

            return Curl::makeRequest("POST", $endpoint, $query, [], $authorizationString);
        } catch (Throwable $t) {
            Logger::warning("Error while attempting to run raw query: " . $t->getMessage());
            return null;
        }
    }
}