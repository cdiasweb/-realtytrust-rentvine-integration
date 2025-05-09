<?php

namespace Rentvine;
use Aptly\AptlyAPI;
use CURLFile;
use Exception;
use Util\Env;

class RentvineAPI
{
    private $baseUrl;
    private $userName;
    private $password;

    public const OWNER_BILLS_FIELD = 'Owner Bills';
    public const RENTVINE_ID = 'Rentvine ID';
    public const RENTVINE_DOC_UPLOAD_FIELD = 'Rentvine Building documents';

    const MAKE_WH_SIGNATURE = "YYLKFymLrrkfMyw3R-WCaphN9vZwN2z9PZb";
    const MAKE_URL = "https://hook.us1.make.com/tf4abmmirj1lo8crrhjn3nazh84wn3gi";
    const NGROK_URL = "https://egret-glorious-cow.ngrok-free.app/hook";

    public function __construct($userName, $password, $baseUrl = 'https://realtytrustservicesllc.rentvine.com/api')
    {
        $this->userName = $userName;
        $this->password = $password;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private function makeRequest($endpoint, $method = 'GET', $data = [], $headers = null)
    {
        $url = $this->baseUrl . $endpoint;
        $authorizationString = "{$this->userName}:{$this->password}";
        $httpHeaders = $headers ?? [
            'Authorization: Basic ' . base64_encode($authorizationString),
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

    public function searchUnitsByPropertyId($propertyId): string
    {
        $endpoint = "/manager/properties/$propertyId/units";
        return $this->makeRequest($endpoint);
    }

    public function addAttachmentToObject($objectId, $objectTypeId, $files)
    {
        $endpoint = "/manager/files?objectTypeID=$objectTypeId&objectID=$objectId";
        Logger::warning('addAttachmentToObject: ' . $endpoint);
        return $this->makeFilePostRequest($endpoint, $files);
    }

    public function deleteAttachmentFromObject($fileAttachmentID)
    {
        $endpoint = "/manager/files/attachments/$fileAttachmentID";
        return $this->makeRequest($endpoint, 'DELETE');
    }

    public function handleWebhook($data)
    {
        $webhookEventInfo = 'Webhook Received: ' . json_encode($data);
        Logger::warning($webhookEventInfo);

        // Handle event
        $this->handleBuildingAttachment($data);

        // Handle event
        $this->handleLeaseAttachment($data);

        // Forward events
        $this->forwardWebhookEvent($data, self::MAKE_URL);

        if (Env::isProd()) {
            $this->forwardWebhookEvent($data, self::NGROK_URL);
        } else {
            Logger::warning('Dev env: Do not forward webhook to ngrok.');
        }

        return $webhookEventInfo;
    }

    public function forwardWebhookEvent($data, $whUrl = null)
    {
        if (!$whUrl) {
            return;
        }
        $ch = curl_init($whUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Signature: ' . self::MAKE_WH_SIGNATURE
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // 3. Execute cURL request
        $forwardResponse = curl_exec($ch);

        if (curl_errno($ch)) {
            //Logger::warning('cURL error: ' . curl_error($ch));
        } else {
            //Logger::warning('Webhook forwarded successfully. Response: ' . $forwardResponse);
        }

        curl_close($ch);
        //Logger::warning("Forward to Make response: $forwardResponse");
    }

    public function handleBuildingAttachment($event) {
        $eventObject = (object) $event;

        $urlToPdf = $eventObject->data[AptlyAPI::URL_TO_PDF_FIELD];
        $attachToPropertyAction = $eventObject->data[AptlyAPI::ATTACH_RV_PROPERTY_FIELD];
        if (!$urlToPdf || $attachToPropertyAction !== AptlyAPI::ATTACH_TO_PROPERTY_VALUE) {
            return;
        }
        Logger::warning('Handle Building attachment: ' . json_encode($event));
        Logger::warning('Object event: ' . $eventObject->action ?? $eventObject->action ?? '');

        $aptly = new AptlyAPI();
        if ($eventObject->action === 'update') {
            $fieldId = $aptly->getFieldIdFromAptlyEventWithKeyName($eventObject, AptlyAPI::buildingEventKey);
            Logger::warning('FIELD ID: ' . $fieldId);
            $buildingRentvineCardId = $aptly->getBuildingCardIdFromAptlyEventByFieldId($eventObject, $fieldId);
            Logger::warning('BUILDING RENTVINE CARD ID: ' . $buildingRentvineCardId);
            if ($buildingRentvineCardId) {
                $buildingRentvineId = $aptly->getBuildingRentvineIdFromCard($buildingRentvineCardId);
                Logger::warning('BUILDING RENTVINE ID: ' . $buildingRentvineId);

                $propertyDetails = $this->getProperty($buildingRentvineId);
                Logger::warning('PROPERTY DETAILS: ' . $propertyDetails);

                $attachmentsFieldId = $aptly->getFieldIdFromAptlyEventWithKeyName($eventObject, self::RENTVINE_DOC_UPLOAD_FIELD);
                Logger::warning('Attachments FIELD ID: ' . $attachmentsFieldId);

                $drivePdfFileLink = $aptly->getCompleteFieldDataByFieldIdFromData($eventObject, AptlyAPI::URL_TO_PDF_FIELD);
                Logger::warning('$drivePdfFileLink' . json_encode($drivePdfFileLink));

                $attachToBuildingAction = $aptly->getCompleteFieldDataByFieldIdFromChanges($eventObject, AptlyAPI::ATTACH_RV_PROPERTY_FIELD);
                Logger::warning('$attachToBuildingAction: ' . $attachToBuildingAction);
                if ($attachToBuildingAction !== AptlyAPI::ATTACH_TO_PROPERTY_VALUE) {
                    Logger::warning('Do not attach file to property.');
                    return;
                }

                $this->createFilePostObject($drivePdfFileLink, $eventObject);

                $objectTypeId = 6;
                // Check if we have units
                $units = $this->searchUnitsByPropertyId($buildingRentvineId);
                Logger::warning("units found: $units");
                $units = json_decode($units, true);
                if (count($units) === 1) {
                    $buildingRentvineId = $units[0]['unit']['unitID'] ?? $buildingRentvineId;
                    $objectTypeId = $units[0]['unit']['unitID'] ? 7 : 6;
                    Logger::warning('$buildingRentvineId UNITS: ' . $buildingRentvineId);
                }

                $fileUploadedData = $this->addAttachmentToObject($buildingRentvineId, $objectTypeId, $_FILES);
                Logger::warning('$fileUploadedData: ' . $fileUploadedData);

                // Set the result to card
                $updateFeedbackResult = $aptly->setFileUploadResult($eventObject->data['_id'] ?? null, [
                    AptlyAPI::RV_APTLY_FIELD_NAME => 'Document attached to Property',
                    AptlyAPI::RV_APTLY_ACTION_FIELD_NAME => 'Attached to property'
                ]);
                Logger::warning('$updateFeedbackResult: ' . $updateFeedbackResult);
            }
        }
    }

    public function handleLeaseAttachment($event) {
        $eventObject = (object) $event;
        $attachToLeaseAction = $eventObject->data[AptlyAPI::ATTACH_TO_RV_LEASE_ACTION_FIELD];
        $urlToPDF = $eventObject->data[AptlyAPI::URL_TO_PDF_FIELD];
        $shareWithTenant = $eventObject->data[AptlyAPI::SHARE_WITH_TENANT_FIELD];
        $leaseCardId = $eventObject->data[AptlyAPI::LEASE_CARD_ID_FIELD][0]['_id'] ?? null;
        if ($attachToLeaseAction !== AptlyAPI::ATTACH_TO_RV_LEASE_ACTION_VALUE || !$urlToPDF && !$shareWithTenant || !$leaseCardId) {
            return;
        }

        $aptlyApi = new AptlyAPI();
        $leaseCard = $aptlyApi->getCardById($leaseCardId);
        $cardData = json_decode($leaseCard, true);
        Logger::warning('$leaseCard: ' . json_encode($cardData));
        $leaseRvId = $cardData['message']['data']['message']['data'][AptlyAPI::RENTVINE_ID_KEY] ?? null;
        Logger::warning('$leaseRvId: ' . $leaseRvId);

        $drivePdfFileLink = $aptlyApi->getCompleteFieldDataByFieldIdFromData($eventObject, AptlyAPI::URL_TO_PDF_FIELD);
        Logger::warning('$drivePdfFileLink' . json_encode($drivePdfFileLink));

        $this->createFilePostObject($drivePdfFileLink, $eventObject);

        // Object type id: 4
        $fileUploadedData = $this->addAttachmentToObject($leaseRvId, 4, $_FILES);
        $fileUploadedData = json_decode($fileUploadedData, true);
        Logger::warning('Lease file upload result: ' . json_encode($fileUploadedData));


        // Share with tenant if applicable
        $fileAttachmentId = $fileUploadedData['fileAttachment']['fileAttachmentID'] ?? null;
        Logger::warning('$fileAttachmentId: ' . $fileAttachmentId);
        if ($fileAttachmentId) {
            $shareWithTenant = !($shareWithTenant === 'False');
            $shareFileResult = $this->shareFile($fileAttachmentId, $shareWithTenant);
            Logger::warning('$shareFileResult: ' . $shareFileResult);
        }

        // Set the result to card
        $updateFeedbackResult = $aptlyApi->setFileUploadResult($eventObject->data['_id'] ?? null, [
            AptlyAPI::ATTACH_TO_RV_LEASE_ACTION => 'Attached to lease',
            AptlyAPI::ATTACH_TO_RV_LEASE_RESULT => 'Document attached to Lease'

        ]);
        Logger::warning('$updateFeedbackResult: ' . $updateFeedbackResult);
    }

    public function createFilePostObject($drivePdfFileLink, $eventObject) {
        $aptly = new AptlyAPI();
        $googleDriveFileId = $aptly->extractGoogleDriveFileId($drivePdfFileLink);
        Logger::warning('$googleDriveFileId: ' . $googleDriveFileId);
        if (!$googleDriveFileId) {
            Logger::warning("Could not find the File ID for this URL: $drivePdfFileLink");
            return;
        }
        $tempPath = sys_get_temp_dir() . '/' . uniqid('gdrive_') . '.pdf';
        $aptly->downloadPublicGoogleDriveFile("https://drive.usercontent.google.com/uc?id=$googleDriveFileId&export=download", $tempPath);

        $fileName = $eventObject->data[AptlyAPI::SUMMARY_FIELD] ?? basename($tempPath);
        $fileName = $aptly->sanitizeFileName($fileName);
        Logger::warning('$fileName: ' . $fileName);
        // Step 2: Mock the $_FILES array
        $_FILES['file'] = [
            'name' => $fileName,
            'type' => mime_content_type($tempPath),
            'tmp_name' => $tempPath,
            'error' => 0,
            'size' => filesize($tempPath)
        ];
    }

    public function shareFile($fileAttachmentId, $isSharedWithTenant = false, $isSharedWithOwner = false, $sendNotification = false) {
        if (!$fileAttachmentId) {
            return;
        }
        return $this->makeRequest('/manager/files/attachments/share/multi', 'POST', [
            "pathIDs" => [],
            "fileAttachmentIDs" => ["$fileAttachmentId"],
            "isSharedWithTenant" => (int)$isSharedWithTenant,
            "isSharedWithOwner" => (int)$isSharedWithOwner,
            "sendNotification" => (int)$sendNotification
        ]);
    }
}
