<?php

namespace lib;

use OpenAI;
use RedisCache\RedisClient;
use Rentvine\Logger;
use Util\Env;

class openAIClient
{
    private $client;
    private $redisClient;
    public function __construct() {
        $yourApiKey = Env::openAIKey();
        $this->client = OpenAI::client($yourApiKey);
        $this->redisClient = new RedisClient();
    }
    public function getUnitBasedOnAddress($address)
    {
        $cacheKey = str_replace(' ', '', $address);
        $cacheResponse = $this->redisClient->redis->get($cacheKey);
        if (!empty($cacheResponse)) {
            $cacheResponse = json_decode($cacheResponse, true);
            $cacheResponse['from_cache'] = true;
            return json_encode($cacheResponse);
        }
        $unitResponse = $this->client->threads()->createAndRun(
            [
                'assistant_id' => 'asst_Lna6FD7zDWISwM3BUuCjvwZd',
                'thread' => [
                    'messages' =>
                        [
                            [
                                'role' => 'user',
                                'content' => "Find this unit in the file by address, the file is attached to the assistant, return the Rentvine ID as well: $address",
                            ],
                        ],
                ],
            ],
        );

        $runId = $unitResponse['id'];
        $threadId = $unitResponse['thread_id'];

        do {
            sleep(1); // wait 1 second
            $runStatus = $this->client->threads()->runs()->retrieve($threadId, $runId);
        } while ($runStatus['status'] !== 'completed');

        $messages = $this->client->threads()->messages()->list($threadId);

        $response = '';
        foreach ($messages['data'] as $message) {
            if ($message['role'] === 'user') {
                continue;
            }
            $content = $message['content'][0]['text']['value'];
            Logger::warning("Find by address: $address, message: " . json_encode($message));
            if ($this->isJson($content)) {
                $response .= $content;
            }
        }

        // Remove JSON triple backticks
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $response);
        $clean = trim($clean);
        if ($clean !== '') {
            $this->redisClient->redis->set($cacheKey, $clean);
        }

        return $clean;
    }

    public function getAddressesFromText($textContent)
    {
        Logger::warning('Running getAddressesFromText...');
        $cacheKey = str_replace(' ', '', $textContent);
        $cacheResponse = $this->redisClient->redis->get($cacheKey);
        if (!empty($cacheResponse)) {
            $cacheResponse = json_decode($cacheResponse, true);
            $cacheResponse['from_cache'] = true;
            return json_encode($cacheResponse);
        }
        $unitResponse = $this->client->threads()->createAndRun(
            [
                'assistant_id' => 'asst_Lna6FD7zDWISwM3BUuCjvwZd',
                'thread' => [
                    'messages' =>
                        [
                            [
                                'role' => 'user',
                                'content' => "Find all the Property addresses in this text and return it in a json, do not return anything else, example: Location , just the JSON. Make sure to check all Property addresses in this text, run it more than once if needed. The Property address is near the word 'Property Address:' use the field result for the root element in the JSON response and always return the response as an array with the root key as result even when there is only one result: $textContent",
                            ],
                        ],
                ],
            ],
        );

        $runId = $unitResponse['id'];
        $threadId = $unitResponse['thread_id'];

        do {
            sleep(1); // wait 1 second
            $runStatus = $this->client->threads()->runs()->retrieve($threadId, $runId);
        } while ($runStatus['status'] !== 'completed');

        $messages = $this->client->threads()->messages()->list($threadId);
        //Logger::warning('Messages: ' . json_encode($messages));

        $response = '';
        foreach ($messages['data'] as $message) {
            if ($message['role'] === 'user') {
                continue;
            }
            $content = $message['content'][0]['text']['value'];
            //Logger::warning("Response: " . json_encode($message));
            if ($this->isJson($content)) {
                $response .= $content;
            }
        }

        // Remove JSON triple backticks
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $response);
        $clean = trim($clean);
        if ($clean !== '') {
            $this->redisClient->redis->set($cacheKey, $clean);
        }

        return $clean;
    }

    public function getVendorBasedOnSearchText($searchText)
    {
        $cacheKey = str_replace(' ', '', $searchText);
        $cacheResponse = $this->redisClient->redis->get($cacheKey);
        if (!empty($cacheResponse)) {
            $cacheResponse = json_decode($cacheResponse, true);
            $cacheResponse['from_cache'] = true;
            return json_encode($cacheResponse);
        }
        $unitResponse = $this->client->threads()->createAndRun(
            [
                'assistant_id' => 'asst_Lna6FD7zDWISwM3BUuCjvwZd',
                'thread' => [
                    'messages' =>
                        [
                            [
                                'role' => 'user',
                                'content' => "Find this Vendor in the file by search text, the file is attached to the assistant as Vendor.txt, return as JSON: $searchText",
                            ],
                        ],
                ],
            ],
        );

        $runId = $unitResponse['id'];
        $threadId = $unitResponse['thread_id'];

        do {
            sleep(1); // wait 1 second
            $runStatus = $this->client->threads()->runs()->retrieve($threadId, $runId);
        } while ($runStatus['status'] !== 'completed');

        $messages = $this->client->threads()->messages()->list($threadId);

        $response = '';
        foreach ($messages['data'] as $message) {
            if ($message['role'] === 'user') {
                continue;
            }
            $content = $message['content'][0]['text']['value'];
            Logger::warning("Find by address: $searchText, message: " . json_encode($message));
            if ($this->isJson($content)) {
                $response .= $content;
            }
        }

        // Remove JSON triple backticks
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $response);
        $clean = trim($clean);
        if ($clean !== '') {
            $this->redisClient->redis->set($cacheKey, $clean);
        }

        return $clean;
    }

    public function getVendorNameAddressBasedTextContent($searchText)
    {
        $cacheKey = str_replace(' ', '', $searchText);
        $cacheKey = "cache_" . md5($cacheKey);
        $cacheResponse = $this->redisClient->redis->get($cacheKey);
        if (!empty($cacheResponse)) {
            $cacheResponse = json_decode($cacheResponse, true);
            $cacheResponse['from_cache'] = true;
            return json_encode($cacheResponse);
        }
        $unitResponse = $this->client->threads()->createAndRun(
            [
                'assistant_id' => 'asst_MDkJdiCS7ijWyWOk7X8EdtF4',
                'thread' => [
                    'messages' =>
                        [
                            [
                                'role' => 'user',
                                'content' => "$searchText",
                            ],
                        ],
                ],
            ],
        );

        $runId = $unitResponse['id'];
        $threadId = $unitResponse['thread_id'];

        do {
            sleep(1); // wait 1 second
            $runStatus = $this->client->threads()->runs()->retrieve($threadId, $runId);
        } while ($runStatus['status'] !== 'completed');

        $messages = $this->client->threads()->messages()->list($threadId);

        $response = '';
        foreach ($messages['data'] as $message) {
            if ($message['role'] === 'user') {
                continue;
            }
            $content = $message['content'][0]['text']['value'];
            Logger::warning("Find by address: $searchText, message: " . json_encode($message));
            $response .= $content;
        }

        // Remove JSON triple backticks
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $response);
        $clean = trim($clean);
        Logger::warning('CLEAN: ' . $clean);
        if ($clean !== '') {
            $this->redisClient->redis->set($cacheKey, $clean);
        }

        return $clean;
    }

    function isJson($string) {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
}
