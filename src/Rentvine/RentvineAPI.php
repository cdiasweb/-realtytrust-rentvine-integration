<?php

namespace Rentvine;
use CURLFile;
use Exception;

class RentvineAPI
{
    private $baseUrl;
    private $userName;
    private $password;

    public function __construct($userName, $password, $baseUrl = 'https://realtytrustservicesllc.rentvine.com/api')
    {
        $this->userName = $userName;
        $this->password = $password;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private function makeRequest($endpoint, $method = 'GET', $data = [])
    {
        $url = $this->baseUrl . $endpoint;
        $authorizationString = "{$this->userName}:{$this->password}";
        $headers = [
            'Authorization: Basic ' . base64_encode($authorizationString),
            'Content-Type: application/json'
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

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

    private function makeFilePostRequest($endpoint, $files)
    {
        $url = $this->baseUrl . $endpoint;
        $authorizationString = "{$this->userName}:{$this->password}";
        $headers = [
            'Authorization: Basic ' . base64_encode($authorizationString),
            'Content-Type: multipart/form-data'
        ];

        // Get the files
        $postFields = [];
        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                foreach ($file['name'] as $index => $filename) {
                    if ($file['error'][$index] === UPLOAD_ERR_OK) {
                        $postFields[$key . "[$index]"] = new CURLFile(
                            $file['tmp_name'][$index],
                            $file['type'][$index],
                            $filename
                        );
                    }
                }
            } else {
                // Handle single file
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $postFields[$key] = new CURLFile(
                        $file['tmp_name'],
                        $file['type'],
                        $file['name']
                    );
                }
            }
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if (!empty($postFields)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            throw new Exception('CURL Error: ' . curl_error($curl));
        }

        curl_close($curl);

        if ($httpCode >= 400) {
            throw new Exception("HTTP Error: $httpCode - Response: $response");
        }

        return $response;
    }

    public function getProperties($filters = [])
    {
        $queryString = http_build_query($filters);
        $endpoint = '/manager/properties' . ($queryString ? '?' . $queryString : '');
        return $this->makeRequest($endpoint);
    }

    public function getProperty($propertyId)
    {
        $endpoint = "/manager/properties/$propertyId";
        return $this->makeRequest($endpoint);
    }

    public function getOwners()
    {
        $endpoint = "/manager/owners/search";
        return $this->makeRequest($endpoint);
    }

    public function createProperty($propertyData)
    {
        $endpoint = '/manager/properties';
        return $this->makeRequest($endpoint, 'POST', $propertyData);
    }

    public function updateProperty($propertyId, $propertyData)
    {
        $endpoint = "/manager/properties/$propertyId";
        return $this->makeRequest($endpoint, 'PUT', $propertyData);
    }

    public function deleteProperty($propertyId)
    {
        $endpoint = "/manager/properties/$propertyId";
        return $this->makeRequest($endpoint, 'DELETE');
    }

    public function createOwnerPortfolioBill($data)
    {
        $endpoint = "/manager/accounting/bills";
        return $this->makeRequest($endpoint, 'POST', $data);
    }

    public function searchVendors()
    {
        $endpoint = "/manager/vendors/search";
        return $this->makeRequest($endpoint);
    }

    public function searchPortfolios()
    {
        $endpoint = "/manager/portfolios/search";
        return $this->makeRequest($endpoint);
    }

    public function searchLedgers($search)
    {
        $name = urlencode($search['name']);
        $endpoint = "/manager/accounting/ledgers/search?search=$name";
        return $this->makeRequest($endpoint);
    }

    public function searchContacts($search)
    {
        $name = urlencode($search['name']);
        $endpoint = "/manager/contacts/search?search=$name";
        return $this->makeRequest($endpoint);
    }

    public function addAttachmentToObject($objectId, $objectTypeId, $files)
    {
        $endpoint = "/manager/files?objectTypeID=$objectTypeId&objectID=$objectId";
        return $this->makeFilePostRequest($endpoint, $files);
    }

    public function deleteAttachmentFromObject($fileAttachmentID)
    {
        $endpoint = "/manager/files/attachments/$fileAttachmentID";
        return $this->makeRequest($endpoint, 'DELETE');
    }
}
