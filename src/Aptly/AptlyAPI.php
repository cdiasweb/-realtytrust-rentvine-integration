<?php

namespace Aptly;

use Rentvine\Logger;
use Rentvine\RentvineAPI;

class AptlyAPI
{
    private $baseUrl;
    private $apiBaseUrl;
    private $token = "crdGAMYAMT8hajqCh";
    public const buildingEventKey = 'BUILDING';
    public const RENTVINE_ID_KEY = 'Rentvine ID';

    public const RV_APTLY_FIELD_NAME = 'Attach to rv property result';
    public const RV_APTLY_ACTION_FIELD_NAME = 'Attach to rv property action';

    public const ATTACH_RV_PROPERTY_FIELD = 'AtRqjs5M5gXsBAxYa';
    public const ATTACH_TO_PROPERTY_VALUE = 'Attach to property';
    public const SUMMARY_FIELD = '4Nk6BfGjpK7HD7sQu';

    public const URL_TO_PDF_FIELD = 'tJmppg4PTEdAhRLat';
    public function __construct($baseUrl = "https://app.getaptly.com", $apiBaseUrl = "https://api.getaptly.com") {
        $this->baseUrl = $baseUrl;
        $this->apiBaseUrl = $apiBaseUrl;
    }

    public function makeAptlyApiRequest($endpoint = '', $method = 'GET', $data = [], $useApiSubdomain = false) {
        $url = $useApiSubdomain ? $this->apiBaseUrl : $this->baseUrl . $endpoint . "?x-token=" . $this->token;
        Logger::warning('makeAptlyApiRequest URL: '. $url);
        $httpHeaders = $headers ?? [
            'Content-Type: application/json'
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $httpHeaders);

        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            throw new Exception('CURL Error: ' . curl_error($curl));
        }

        curl_close($curl);

        if ($httpCode >= 400) {
            return $response;
        }

        return $response;
    }

    public function makeAptlyFileRequest($endpoint = '', $method = 'GET', $data = []) {
        $url = $this->baseUrl . $endpoint . "?x-token=" . $this->token;
        Logger::warning('makeAptlyApiRequest URL: '. $url);
        $httpHeaders = $headers ?? [
            'Content-Type: application/json'
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $httpHeaders);

        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            throw new Exception('CURL Error: ' . curl_error($curl));
        }

        curl_close($curl);

        if ($httpCode >= 400) {
            return $response;
        }

        return $response;
    }
    public function getCardById($cardId)
    {
        if (!$cardId) {
            return null;
        }

        return $this->makeAptlyApiRequest("/api/card/$cardId", "GET", []);
    }

    public function updateCardFieldByCardId($cardId, $field) {
        //https://api.getaptly.com/v1/cards/{card_id}
        return $this->makeAptlyApiRequest("/api/card/$cardId/fields/$field", "PUT");
    }

    public function getBuildingRentvineIdFromCard($cardId)
    {
        if (!$cardId) {
            return null;
        }

        $cardData = $this->getCardById($cardId);
        $cardData = json_decode($cardData, true);
        Logger::warning('CARD Data: ' . json_encode($cardData));

        if ($cardData['message']['data']['code'] === 200) {
            return $cardData['message']['data']['message']['data'][self::RENTVINE_ID_KEY] ?? null;
        }
    }

    public function getFieldIdFromAptlyEventWithKeyName($eventObject, $keyName)
    {
        $buildingCard = array_filter($eventObject->fields, function($item) use ($keyName) {
            return $item['label'] === $keyName;
        });
        $buildingCard = reset($buildingCard);
        Logger::warning('FOUND BUILDING CARD: ' . json_encode($buildingCard));

        return $buildingCard['key'] ?? null;
    }

    public function getBuildingCardIdFromAptlyEventByFieldId($eventObject, $fieldId) {
        $data = array_filter($eventObject->data, function($item, $key) use ($fieldId) {
            return $key === $fieldId;
        }, ARRAY_FILTER_USE_BOTH);

        $data = reset($data);
        Logger::warning('FOUND BUILDING CARD: ' . json_encode($data));
        if ($data) {
            return end($data)['_id'] ?? null;
        }
        return null;
    }

    public function getCompleteFieldDataByFieldIdFromChanges($eventObject, $fieldId)
    {
        $data = array_filter($eventObject->changes, function($item, $key) use ($fieldId) {
            return $item['field'] === $fieldId;
        }, ARRAY_FILTER_USE_BOTH);

        $data = reset($data);
        if ($data) {
            return end($data) ?? null;
        }
        return null;
    }

    public function getCompleteFieldDataByFieldIdFromData($eventObject, $fieldId)
    {
        return $eventObject->data[$fieldId] ?? null;
    }

    public function getFileExtensionFromChangesReferencesByFileId($eventObject, $fieldId)
    {
        $data = array_filter($eventObject->changes, function($item, $key) use ($fieldId) {
            if ($item['field'] === 'references') {
                return $item;
            }
            return null;
        }, ARRAY_FILTER_USE_BOTH);

        $data = reset($data);
        if ($data) {
            return end($data) ?? null;
        }
        return null;
    }

    public function getFileExtensionFromDataByFileId($eventObject, $fieldId)
    {
        return $eventObject->data[$fieldId] ?? null;
    }

    public function getFileById($fileId, $extension)
    {
        //https://app.getaptly.com/cdn/storage/AptlyFiles/L3Ypae4yFnHvSf5T2/original/L3Ypae4yFnHvSf5T2.pdf
        return $this->makeAptlyApiRequest("/cdn/storage/AptlyFiles/$fileId/original/$fileId.$extension");
    }

    public function getFileUrlId($fileId, $extension)
    {
        //https://app.getaptly.com/cdn/storage/AptlyFiles/L3Ypae4yFnHvSf5T2/original/L3Ypae4yFnHvSf5T2.pdf
        return "https://app.getaptly.com/cdn/storage/AptlyFiles/$fileId/original/$fileId.$extension";
    }

    public function downloadPublicGoogleDriveFile($url, $destinationPath)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $fileContent = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Error downloading file: ' . curl_error($ch));
        }

        file_put_contents($destinationPath, $fileContent);

        curl_close($ch);
    }


    function extractGoogleDriveFileId($url)
    {
        if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function setFileUploadResult($cardId) {
        if ($cardId) {
            return self::makeAptlyApiRequest("/api/aptlet/fh35FSCxw6KB5xbZG", "POST", [
                "_id" => $cardId,
                AptlyAPI::RV_APTLY_FIELD_NAME => 'Document attached to Property',
                AptlyAPI::RV_APTLY_ACTION_FIELD_NAME => 'Attached to property'
            ]);
        }

        return 'Failed to attach the document to building: No card ID';
    }

    public function sanitizeFileName($string) {
        // Replace spaces and commas with underscores
        $string = str_replace([' ', ',', '$'], '_', $string);

        // Remove any character that is not a letter, number, dash, or underscore
        $string = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $string);

        // Optionally, limit the filename length
        $string = substr($string, 0, 100);

        // Lowercase (optional)
        $string = strtolower($string);

        return $string;
    }

}
