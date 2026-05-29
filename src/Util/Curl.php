<?php

namespace Util;

use Exception;
use Rentvine\Logger;
use Throwable;

class Curl
{
    public static function makeRequest(string $method, string $url, array $data = [], array $headers = [], $authorizationString = ""): ?string
    {
        try {
            Logger::warning("Make request: " . $method . " " . $url . " " . $authorizationString);
            if (!$authorizationString) {
                throw new Exception("Authorization string is required");
            }

            $defaultHeaders = [
                'Authorization: Bearer ' . $authorizationString
            ];

            $isJsonBody = !empty($data) && strtoupper($method) !== 'GET';
            if ($isJsonBody) {
                $defaultHeaders[] = 'Content-Type: application/json';
            }

            $httpHeaders = $headers ? array_merge($defaultHeaders, $headers) : $defaultHeaders;

            $curl = curl_init();

            // If GET and $data provided, append as a query string
            if (!empty($data) && strtoupper($method) === 'GET') {
                $query = is_array($data) ? http_build_query($data) : $data;
                $url .= (strpos($url, '?') === false ? '?' : '&') . $query;
            }

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $httpHeaders,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FAILONERROR => false
            ]);

            if ($isJsonBody) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }

            $response = curl_exec($curl);
            $errno = curl_errno($curl);
            $error = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            if ($errno) {
                throw new Exception('CURL Error (' . $errno . '): ' . $error);
            }

            if ($httpCode >= 400) {
                throw new Exception("HTTP Error: {$httpCode} - Response: {$response}");
            }

            return $response;
        } catch (Throwable $t) {
            Logger::warning("CURL Error: " . $t->getMessage());
            return null;
        }
    }
}