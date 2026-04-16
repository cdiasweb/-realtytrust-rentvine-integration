<?php

namespace Jobs;

use Queue\Job;
use Rentvine\Logger;
use Rentvine\RentvineAPI;
use Util\Env;

class UpdateBillDueJob extends Job
{

    public function handle(array $payload)
    {
        Logger::warning("Bill Due Info payload received: " . json_encode($payload));
        $userName = Env::getRentvineApiUsername();
        $password = Env::getRentvineApiPassword();
        $rentvine = new RentvineAPI($userName, $password);
        $rentvine->handleBillDueInfo($payload);
    }
}