<?php

namespace Queue;

use Exception;
use Rentvine\Logger;
use Throwable;

class Worker
{
    public function run()
    {
        while (true) {
            $jobData = Queue::pop();

            $this->process($jobData);
        }
    }

    protected function process($jobData)
    {
        if (is_null($jobData)) {
            return;
        }

        Logger::warning("Processing job: " . json_encode($jobData));

        try {
            $class = $jobData['class'];
            $payload = $jobData['payload'];

            if (!class_exists($class)) {
                throw new Exception("Job class not found: $class");
            }

            $job = new $class();
            $job->handle($payload);

        } catch (Throwable $e) {
            $this->handleFailure($jobData, $e);
        }
    }

    protected function handleFailure($jobData, $e)
    {
        $jobData['retries']++;

        if ($jobData['retries'] < 3) {
            Queue::push($jobData);
        } else {
            // send to failed queue
            Queue::redis()->rPush('queue:failed', json_encode($jobData));
        }
    }
}