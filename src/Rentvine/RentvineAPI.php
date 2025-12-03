<?php

namespace Rentvine;
use Aptly\AptlyAPI;
use CURLFile;
use Exception;
use Imagick;
use lib\openAIClient;
use Throwable;
use Util\Env;

class RentvineAPI
{
    private $baseUrl;
    private $userName;
    private $password;

    public const RENTVINE_ID = 'Rentvine ID';
    public const RENTVINE_DOC_UPLOAD_FIELD = 'Rentvine Building documents';
    public const UNIT_FIELD = 'RPYgwSp52dD4tBbrN';
    public const UNIT_MULTIPLE_FIELD = 'XA8oZNqj5hY2NFJSN';
    public const VENDOR_FIELD = 'zGDJ4kpm2Xd54Rqnc';
    public const RAW_DOC_CONTENT_FIELD = 'B9LfuWzKunhanRdCv';
    public const ADDRESS_UNIT_MIRROR = 'XAjpmZSoGLccddEEN';

    public const BILL_DATE_KEY = "zjH7HeQ7RZv7G3Hjh";
    public const BILL_DATE_DUE_KEY = "JRDXwkC4zq4yqL9zy";

    public const BILL_DESCRIPTION_KEY = "k5Mj5r7nHiCjRGav7";
    public const BILL_AMOUNT_KEY = "5a7PBd2u6aEvrgYxi";
    public const BILL_PAYEE_KEY = "raati5ZDsA65bbWYu";
    public const BILL_DUE_AT_KEY = "dueAt";
    public const BILL_ACCOUNT_NUMBER_KEY = "DxabpBjwkoybigoF2";
    public const BILL_WH_AMOUNT_KEY = "ag4mrsBcSAe9E6iWz";
    public const BILL_WH_DATE_KEY = "Za2trj2TFKzkvyBew";
    public const BILL_WH_DESCRIPTION = "8HYE68wv3pGJjBZiP";
    public const BILL_WH_UNIT_RV_ID = "PzJojc9vYXoZqn3Rq";
    public const BILL_WH_BILL_ID_KEY = "BcPkGTN5kiyjcsSWg";
    public const LEASE_CHARGE_APTLET_KEY = '3ujGbHuYhyqqWzsW5';
    public const LEASE_POST_CHARGE_ACTION_KEY = 'icCw6Ydd3YEMcrMm6';
    public const APTLET_UID_FIELD = 'aptletUuid';
    public const LEASE_TRANSACTION_ID_KEY = '3Rzr47giPnyLeRQ3B';
    public const LEASE_CHARGE_LEASE_ID_KEY = 'FibCsmzJZnmAzoLy9';
    public const LEASE_CHARGE_ACCOUNT_ID_KEY = 'PfnYjQagok9RmZjcL';
    public const LEASE_CHARGE_AMOUNT_KEY = 'i5YDiK8KpYoTSGhrA';
    public const LEASE_CHARGE_DATE_POSTED_KEY = 'eSj2vhvteeXLKXjBZ';
    public const LEASE_CHARGE_DESCRIPTION_KEY = 'description';
    public const LEASE_CHARGE_APTLET_ID = 'GqmmePrTucLLgAzoJ';

    public $units = [];
    public $vendors = [];

    const MAKE_WH_SIGNATURE = "YYLKFymLrrkfMyw3R-WCaphN9vZwN2z9PZb";
    const MAKE_URL = "https://hook.us1.make.com/tf4abmmirj1lo8crrhjn3nazh84wn3gi";
    const N8N_URL = "https://n8n-rts.onrender.com/webhook/8816a233-dafa-45de-8ea8-47176d10ec0a";
    const NGROK_URL = "https://egret-glorious-cow.ngrok-free.app/hook";

    public function __construct($userName, $password, $baseUrl = 'https://realtytrustservicesllc.rentvine.com/api')
    {
        $this->userName = $userName;
        $this->password = $password;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->loadUnits();
        $this->loadVendors();
    }

    /**
     * @throws Exception
     */
    private function makeRequest($endpoint, $method = 'GET', $data = [], $headers = null)
    {
        $url = $this->baseUrl . $endpoint;
        $authorizationString = "{$this->userName}:{$this->password}";

        $defaultHeaders = [
            'Authorization: Basic ' . base64_encode($authorizationString)
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

    public function loadUnits()
    {
        $filePath = __DIR__ . '/units.json';

        // Load file content
        $jsonString = file_get_contents($filePath);

        // Decode JSON into PHP array or object
        $data = json_decode($jsonString, true); // true = associative array

        // Optional: handle error
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        $this->units = $data;
    }

    public function loadVendors()
    {
        $filePath = __DIR__ . '/vendors.json';

        $jsonString = file_get_contents($filePath);

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        $this->vendors = $data;
    }

    public function getUnitFromNumberAndStreetAddress($address)
    {
        if (!empty($this->units)) {
            foreach ($this->units as $unit) {
                if (str_starts_with(strtolower($unit['Title']), strtolower($address))) {
                    return $unit;
                }
            }
        }
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

    public function linkUnitIdToCard($unitId, $cardId)
    {
        $aptly = new AptlyAPI();
        $aptly->updateCardData($cardId, [
            'UNIT' => $unitId
        ]);

        header("Content-Type: text/html");
        echo '<script>window.close()</script> <button onclick="window.close()">Close Tab</button>';
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

    /**
     * @throws Exception
     */
    public function createOwnerPortfolioBill($data, $eventObject)
    {
        Logger::warning("createOwnerPortfolioBill data: " . $data['unitId'] . " type: " . gettype($data));
        Logger::warning("createOwnerPortfolioBill" . json_encode($data));
        Logger::warning("eventObjectData: " . json_encode($eventObject->data));
        if (!empty($eventObject->data[self::BILL_WH_BILL_ID_KEY])) {
            Logger::warning("createOwnerPortfolioBill: Bill ID already exists: " . $data['unitId']);
            return;
        }
        $data = $this->retrieveBillDataFromAutomation($data);
        $endpoint = "/manager/accounting/bills";

        $unitId = $data['unitId'] ?? null;
        $ledgerId = $data['charges']['ledgerID'] ?? null;
        $leaseId = $data['charges'][0]['leaseID'] ?? null;

        Logger::warning("UNIT ID: " . $unitId);

        if ($unitId) {
            if (!$leaseId) {
                $leases = $this->findLeaseInJson("unitID", $unitId);
                $leaseId = $leases[0]['lease']['leaseID'] ?? null;
            }

            // Find Unit
            $units = $this->findUnitInJson(["unit_id" => $unitId]) ?? "[]";
            $units = json_decode($units, true);

            Logger::warning("UNITS: " . json_encode($units));

            if ($units[0]) {
                $unitTitle = $units[0]['Title'];
                if ($unitTitle) {
                    $ledgers = $this->searchLedgers(["name" => $unitTitle]);
                    $ledgers = json_decode($ledgers, true);
                    $ledgerId = $ledgers[0]["ledger"]["ledgerID"] ?? null;
                    Logger::warning('Found ledger ID: ' . $ledgerId);
                }
            }
        }

        if (!$ledgerId) {
            throw new Exception("Ledger ID not found");
        }

        unset($data['unitId']);

        $data['charges'][0]['ledgerID'] = $ledgerId;
        $data['charges'][0]['leaseID'] = $leaseId;

        $response = $this->makeRequest($endpoint, 'POST', $data);
        Logger::warning("createOwnerPortfolioBill RESPONSE" . json_encode($response));
        $responseArray = json_decode($response, true);
        $aptly = new AptlyAPI();
        $cardId = $eventObject->data['_id'];
        $aptly->updateCardData($cardId, [
            AptlyAPI::BILL_WH_TO_OWNER_RESULT => $responseArray["bill"]["billID"] ?? "",
        ], "o4jZzWcwWs6wR6B6E");

        return $response;
    }

    public function getOwnerPortfolioBillById($billId)
    {
        $endpoint = "/manager/accounting/bills/$billId";
        return $this->makeRequest($endpoint);
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

    public function getPortfolioByIdIncludingOwners($portfolioId)
    {
        $endpoint = "/manager/portfolios/$portfolioId?includes=owners,properties,posting,statementSetting,ledger";
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

        // Handle events
        $this->handleBuildingAttachment($data);
        $this->handleLeaseAttachment($data);
        $this->handlePostOwnerBillToPortfolio($data);
        $this->handleGetUnitFromDescription($data);
        $this->handleGetUnitFromPDF($data);
        $this->handleGetVendor($data);
        $this->handleWhPostOwnerBillToPortfolio($data);
        $this->handleWhPostLeaseCharge($data);

        // Forward events
        $this->forwardWebhookEvent($data, self::MAKE_URL);
        $this->forwardWebhookEvent($data, self::N8N_URL);

        $isProd = Env::isProd();
        //Logger::warning("Is prod: $isProd");
        if ($isProd) {
            $this->forwardWebhookEvent($data, self::NGROK_URL);
        } else {
            //Logger::warning('Dev env: Do not forward webhook to ngrok.');
        }

        return $webhookEventInfo;
    }

    public function forwardWebhookEvent($data, $whUrl = null)
    {
        Logger::warning("forwardWebhookEvent URL: $whUrl");
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
        curl_exec($ch);

        curl_close($ch);
    }

    public function handleBuildingAttachment($event) {
        $eventObject = (object) $event;

        $urlToPdf = $eventObject->data[AptlyAPI::URL_TO_PDF_FIELD] ?? null;
        $attachToPropertyAction = $eventObject->data[AptlyAPI::ATTACH_RV_PROPERTY_FIELD] ?? null;
        if (!$urlToPdf || $attachToPropertyAction !== AptlyAPI::ATTACH_TO_PROPERTY_VALUE) {
            return;
        }

        $aptly = new AptlyAPI();
        if ($eventObject->action === 'update') {
            $fieldId = $aptly->getFieldIdFromAptlyEventWithKeyName($eventObject, AptlyAPI::buildingEventKey);
            $buildingRentvineCardId = $aptly->getBuildingCardIdFromAptlyEventByFieldId($eventObject, $fieldId);
            if ($buildingRentvineCardId) {
                $buildingRentvineId = $aptly->getBuildingRentvineIdFromCard($buildingRentvineCardId);

                $propertyDetails = $this->getProperty($buildingRentvineId);

                $attachmentsFieldId = $aptly->getFieldIdFromAptlyEventWithKeyName($eventObject, self::RENTVINE_DOC_UPLOAD_FIELD);

                $drivePdfFileLink = $aptly->getCompleteFieldDataByFieldIdFromData($eventObject, AptlyAPI::URL_TO_PDF_FIELD);

                $attachToBuildingAction = $aptly->getCompleteFieldDataByFieldIdFromChanges($eventObject, AptlyAPI::ATTACH_RV_PROPERTY_FIELD);
                if ($attachToBuildingAction !== AptlyAPI::ATTACH_TO_PROPERTY_VALUE) {
                    Logger::warning('Do not attach file to property.');
                    return;
                }

                $this->createFilePostObject($drivePdfFileLink, $eventObject);

                $objectTypeId = 6;
                // Check if we have units
                $units = $this->searchUnitsByPropertyId($buildingRentvineId);
                $units = json_decode($units, true);
                if (count($units) === 1) {
                    $buildingRentvineId = $units[0]['unit']['unitID'] ?? $buildingRentvineId;
                    $objectTypeId = $units[0]['unit']['unitID'] ? 7 : 6;
                }

                $this->addAttachmentToObject($buildingRentvineId, $objectTypeId, $_FILES);

                // Set the result to card
                $aptly->updateCardData($eventObject->data['_id'] ?? null, [
                    AptlyAPI::RV_APTLY_FIELD_NAME => 'Document attached to Property',
                    AptlyAPI::RV_APTLY_ACTION_FIELD_NAME => 'Attached to property'
                ]);
            }
        }
    }

    public function handleLeaseAttachment($event) {
        $eventObject = (object) $event;
        $attachToLeaseAction = $eventObject->data[AptlyAPI::ATTACH_TO_RV_LEASE_ACTION_FIELD] ?? null;
        $urlToPDF = $eventObject->data[AptlyAPI::URL_TO_PDF_FIELD] ?? null;
        $shareWithTenant = $eventObject->data[AptlyAPI::SHARE_WITH_TENANT_FIELD] ?? null;
        $leaseCardId = $eventObject->data[AptlyAPI::LEASE_CARD_ID_FIELD][0]['_id'] ?? null;
        if ($attachToLeaseAction !== AptlyAPI::ATTACH_TO_RV_LEASE_ACTION_VALUE || !$urlToPDF && !$shareWithTenant || !$leaseCardId) {
            return;
        }

        $aptlyApi = new AptlyAPI();
        $leaseCard = $aptlyApi->getCardById($leaseCardId);
        $cardData = json_decode($leaseCard, true);
        $leaseRvId = $cardData['message']['data']['message']['data'][AptlyAPI::RENTVINE_ID_KEY] ?? null;
        $drivePdfFileLink = $aptlyApi->getCompleteFieldDataByFieldIdFromData($eventObject, AptlyAPI::URL_TO_PDF_FIELD);

        $this->createFilePostObject($drivePdfFileLink, $eventObject);

        // Object type id: 4
        $fileUploadedData = $this->addAttachmentToObject($leaseRvId, 4, $_FILES);
        $fileUploadedData = json_decode($fileUploadedData, true);

        // Share with tenant if applicable
        $fileAttachmentId = $fileUploadedData['fileAttachment']['fileAttachmentID'] ?? null;
        if ($fileAttachmentId) {
            $shareWithTenant = !($shareWithTenant === 'False');
            $shareFileResult = $this->shareFile($fileAttachmentId, $shareWithTenant);
            Logger::warning("Share file result: " . json_encode($shareFileResult));
        }

        // Set the result to card
        $aptlyApi->updateCardData($eventObject->data['_id'] ?? null, [
            AptlyAPI::ATTACH_TO_RV_LEASE_ACTION => 'Attached to lease',
            AptlyAPI::ATTACH_TO_RV_LEASE_RESULT => 'Document attached to Lease',
            'Attach to rv lease id' => $fileAttachmentId
        ]);
    }

    public function createFilePostObject($drivePdfFileLink, $eventObject) {
        $aptly = new AptlyAPI();
        $googleDriveFileId = $aptly->extractGoogleDriveFileId($drivePdfFileLink);
        if (!$googleDriveFileId) {
            Logger::warning("Could not find the File ID for this URL: $drivePdfFileLink");
            return;
        }
        $tempPath = sys_get_temp_dir() . '/' . uniqid('gdrive_') . '.pdf';
        $aptly->downloadPublicGoogleDriveFile("https://drive.usercontent.google.com/uc?id=$googleDriveFileId&export=download", $tempPath);

        $fileName = $eventObject->data[AptlyAPI::SUMMARY_FIELD] ?? basename($tempPath);
        $fileName = $aptly->sanitizeFileName($fileName);
        // Step 2: Mock the $_FILES array
        $_FILES['file'] = [
            'name' => $fileName,
            'type' => mime_content_type($tempPath),
            'tmp_name' => $tempPath,
            'error' => 0,
            'size' => filesize($tempPath)
        ];
    }

    public function handlePostOwnerBillToPortfolio($event)
    {
        $eventObject = (object) $event;
        $postOwnerBillAction = $eventObject->data[AptlyAPI::POST_TO_OWNER_PORTFOLIO_FIELD] ?? null;
        $portfolioCardId = $eventObject->data[AptlyAPI::PORTFOLIO_FIELD][0]['_id'] ?? null;
        $aptly = new AptlyAPI();
        $portfolioCard = $aptly->getCardById($portfolioCardId);

        if (!$portfolioCard) {
            return;
        }

        $portfolioCard = json_decode($portfolioCard, true);
        $portfolioRvId = $portfolioCard['message']['data']['message']['data'][AptlyAPI::RENTVINE_ID_KEY] ?? null;
        $rentvinePortfolioData = $this->getPortfolioByIdIncludingOwners($portfolioRvId);
        $rentvinePortfolioData = json_decode($rentvinePortfolioData, true);
        $ownerContactId = $rentvinePortfolioData['owners'][0]['owner']['contactID'] ?? null;
        $ledgerId = $rentvinePortfolioData['ledger']['ledgerID'] ?? null;

        if ($postOwnerBillAction !== AptlyAPI::POST_TO_OWNER_PORTFOLIO_VALUE) {
            return;
        }

        $billOriginalDate = $eventObject->data[AptlyAPI::OWNER_PORTFOLIO_BILL_DATE_FIELD] ?? '';
        $billDate = date("Y-m-d", strtotime($billOriginalDate));

        $billOriginalDateDue = $eventObject->data[AptlyAPI::OWNER_PORTFOLIO_BILL_DATE_DUE_FIELD] ?? '';
        $dateDue = date("Y-m-d", strtotime($billOriginalDateDue));

        $billData = [
            "billDate" => $billDate,
            "dateDue" => $dateDue,
            "charges" => [[
                "description" => $eventObject->data[AptlyAPI::OWNER_PORTFOLIO_BILL_DESCRIPTION_FIELD] ?? '',
                "amount" => $eventObject->data[AptlyAPI::OWNER_PORTFOLIO_BILL_AMOUNT_FIELD]['amount'] ?? 0,
                "ledgerID" => $ledgerId,
                "chargeAccountID" => "11",
                "fromPayer" => 1,
                "toPayee" => 1
            ]],
            "payeeContactID" => $ownerContactId,
            "leaseCharges" => [],
            "reference" => "Bill from Aptly."
        ];

        $this->createOwnerPortfolioBill($billData);

        $cardId = $eventObject->data['_id'];
        Logger::warning("CARD ID: " . $cardId . " - Update card bill date result." . AptlyAPI::BILL_WH_TO_OWNER_RESULT);
        $aptly->updateCardData($cardId, [
            AptlyAPI::BILL_TO_OWNER_RESULT => "Bill added to portfolio owner.",
            AptlyAPI::BILL_WH_TO_OWNER_RESULT => "Bill added to portfolio owner."
        ]);
    }

    public function handleWhPostOwnerBillToPortfolio($event)
    {
        $eventObject = (object) $event;
        $postOwnerBillAction = $eventObject->data[AptlyAPI::ADD_AS_BILL_KEY] ?? null;

        if ($eventObject->action === "update" && $postOwnerBillAction) {
            Logger::warning("handleWhPostOwnerBillToPortfolio " . $postOwnerBillAction);

            $billDate = $eventObject->data[self::BILL_WH_DATE_KEY] ?? '';
            $amount = $eventObject->data[self::BILL_WH_AMOUNT_KEY] ?? '';
            $accountNumber = $eventObject->data[self::BILL_ACCOUNT_NUMBER_KEY] ?? '';
            $dueDate = $eventObject->data[self::BILL_DUE_AT_KEY] ?? '';
            $payeeContactId = $eventObject->data[self::BILL_PAYEE_KEY] ?? '';
            $description = $eventObject->data[self::BILL_WH_DESCRIPTION] ?? '';
            $unitRvId = $eventObject->data[self::BILL_WH_UNIT_RV_ID] ?? '';

            Logger::warning("handleWhPostOwnerBillToPortfolio " . json_encode([
                "billDate" => $billDate,
                "amount" => $amount['amount'],
                "accountNumber" => $accountNumber,
                "dueDate" => $dueDate,
                "payeeContactId" => $payeeContactId,
                "description" => $description,
                "unitRvId" => $unitRvId
            ]));

            $data = [
                "billDate" => $billDate,
                "dateDue" => $dueDate,
                "unitId" => $unitRvId,
                "charges" => [[
                    "description" => $description,
                    "chargeAccountID" => $accountNumber,
                    "amount" => $amount['amount'],
                    "fromPayer" => '',
                    "toPayee" => ''
                ]],
                "leaseCharges" => [],
                "payeeContactID" => $payeeContactId,
                "reference" => "",
                "tenantAmount" => 0,
                "paymentMemo" => "",
                "description" => $description
            ];

            $this->createOwnerPortfolioBill($data, $eventObject);
        }
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

    public function updatePropertyUnitsJson()
    {
        $json = $this->makeRequest('/manager/properties/units/export');
        file_put_contents('unis.json', $json);
        return "updated.";
    }

    public function deleteBillById($billId)
    {
        $endpoint = "/manager/accounting/bills/$billId";
        return $this->makeRequest($endpoint, "DELETE", [
            "voidLinkedBills" => 0
        ]);
    }

    public function findUnitInJson($data, $encodeJson = true)
    {
        $searchText = $data['searchText'] ?? '';
        $unitId = $data['unit_id'] ?? '';
        $jsonPath = __DIR__ . '/units.json';
        $json = file_get_contents($jsonPath);
        $data = json_decode($json, true);

        if ($unitId) {
            $filtered = array_filter($data, function($item) use ($unitId) {
                $target = strtolower($item['Rentvine ID']);
                return $target == $unitId;
            });
        } else {
            // Prepare search words
            $searchWords = array_filter(explode(' ', strtolower($searchText)));

            $filtered = array_filter($data, function($item) use ($searchWords) {
                $target = strtolower($item['Title']);

                // Check if all search words are in the target string
                foreach ($searchWords as $word) {
                    if (stripos($target, $word) === false) {
                        return false;
                    }
                }
                return true;
            });
        }

        if (!empty($filtered)) {
            return json_encode(array_values($filtered));
        } else {
            return "Not found.";
        }
    }

    public function findLeaseInJson($field, $content)
    {
        $jsonPath = __DIR__ . '/leases.json';
        $json = file_get_contents($jsonPath);
        $data = json_decode($json, true);
        return array_values(array_filter($data, function($item) use ($field, $content) {
            return $item['lease'][$field] == $content;
        }));
    }

    public function findUnitInJsonUsingAI($data)
    {
        $client = new openAIClient();
        $address = $data['address'] ?? '';
        return $client->getUnitBasedOnAddress($address);
    }

    public function findVendorInJson($data)
    {
        $client = new openAIClient();
        $searchText = $data['searchText'] ?? '';
        return $client->getVendorBasedOnSearchText($searchText);
    }

    function getPropertyAddressFromDescription(string $description): ?string
    {
        if (preg_match('/Property address:\s*(.+?)(?=\n|$)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    function handleGetUnitFromDescription($event)
    {
        $eventObject = (object) $event;
        $changes = $eventObject->changes ?? null;
        if (!$changes || $changes[0]['field'] !== 'description') {
            return;
        }

        $this->getPropertyAddressFromDescription($changes[0]['value']);
    }

    function getUnitsFromDriveFile($driveUrl)
    {
        $outputText = $this->getDriveFileTextContent($driveUrl['driveUrl']);
        preg_match_all('/\d{1,8}\s\w+/', $outputText, $matches);

        $units = [];
        foreach ($matches[0] as $possibleUnit) {
            if (str_starts_with('3407 W', $possibleUnit)) {
                continue;
            }
            $units[] = $this->getUnitFromNumberAndStreetAddress($possibleUnit);
        }

        $aptlyIds = [];
        foreach ($units as $unit) {
            if ($unit['Aptly ID'] ?? null) {
                $aptlyIds[] = [
                    "_id" => $unit['Aptly ID'],
                    "name" => $unit['Title'],
                    "duogram" => "1D"
                ];
            }
        }

        return json_encode($aptlyIds);
    }

    function handleGetUnitFromPDF($data, $force = false) {
        try {
            $unit = $data['data'][self::UNIT_FIELD] ?? [];
            $multipleUnit = $data['data'][self::UNIT_MULTIPLE_FIELD] ?? '';
            $pdfUrl = $data['data'][AptlyAPI::URL_TO_PDF_FIELD] ?? '';
            if (empty($unit) && empty($multipleUnit) && $pdfUrl || $force) {
                $units = $this->getUnitsFromDriveFile(['driveUrl' => $pdfUrl]);
                if (is_string($units)) {
                    $units = json_decode($units, true);
                }

                if (count($units) > 1) {
                    $projectUrl = Env::getProjectUrl();
                    $cardId = $data['data']['_id'];
                    preg_match('/(?<=\/d\/)(.*?)(?=\/)/', $pdfUrl, $matches);
                    $driveId = $matches[0] ?? '';
                    $refreshCardIdEncoded = $cardId;
                    $refreshLink = "$projectUrl/refresh-unit-options?cardId=$refreshCardIdEncoded&driveFileCode=$driveId";
                    $unitOptions = "<a href='$refreshLink'>Refresh options</a> <br><br>";
                    foreach ($units as $unitOption) {
                        $unitId = $unitOption['_id'];
                        $unitOptions .= "<b>Card ID</b>: " . $unitId . "<br>";
                        $unitOptions .= "<b>Name:</b> " . $unitOption['name'] . "<br><br>";
                        $link = "$projectUrl/link-unit-id-to-card/$unitId/$cardId";
                        $unitOptions .= "<a href='$link'>Link Unit to Card</a>" . "<br><br>";
                    }
                    $aptly = new AptlyAPI();
                    $aptly->updateCardData($data['data']['_id'], [
                        'Unit multiple found' => $unitOptions
                    ]);
                } else if (count($units) === 1) {
                    $unitOption = $units[0];
                    $aptly = new AptlyAPI();

                    $aptly->updateCardData($data['data']['_id'], [
                        'Unit' => $unitOption['_id']
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Logger::warning('Error while attempting to get Units: ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
        }
    }

    function handleGetVendor($data)
    {
        $vendor = $data['data'][self::VENDOR_FIELD] ?? [];
        $pdfUrl = $data['data'][AptlyAPI::URL_TO_PDF_FIELD] ?? '';
        $textRawOfDocument = $data['data'][self::RAW_DOC_CONTENT_FIELD] ?? '';
        if (!$textRawOfDocument) {
            return;
        }
        if (empty($vendor) && $pdfUrl) {
            $outputText = $textRawOfDocument ?? $this->getDriveFileTextContent($pdfUrl);
            $client = new openAIClient();
            $output = $client->getVendorNameAddressBasedTextContent($outputText);
            $json = json_decode($output, true);
            $billerName = $json['biller_name'] ?? null;
            $json['biller_address'] ?? null;
            $vendorAptlyId = '';
            foreach ($this->vendors as $vendorItem) {
                $name = $vendorItem['Title'] ?? '';
                if (!$name) { continue; }
                $match = $this->names_loose_match($billerName, $name);
                if ($match) {
                    Logger::warning('Test names match: ' . $billerName . " - " . $name . ' There is a MATCH? ' . json_encode($match));
                }
                if ($match) {
                    $vendorAptlyId = $vendorItem['Aptly ID'];
                    break;
                }
            }

            if ($vendorAptlyId) {
                $vendorAptlyId = trim($vendorAptlyId);
                $aptly = new AptlyAPI();
                $aptly->updateCardData($data['data']['_id'], [
                    'VENDOR' => $vendorAptlyId
                ]);
            }
        }
    }

    function getGoogleDriveDownloadUrl($shareUrl) {
        if (preg_match('/\/d\/(.*?)\//', $shareUrl, $matches)) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }
        return false;
    }

    /**
     * @param $driveUrl1
     * @return array
     * @throws \ImagickException
     */
    private function getDriveFileTextContent($driveUrl1): string
    {
        $downloadUrl = $this->getGoogleDriveDownloadUrl($driveUrl1);
        $uid = uniqid();
        $pdfPath = __DIR__ . "/temp.$uid.pdf";
        $imagePath = __DIR__ . "/page.$uid.jpg";
        $ocrOutputPath = __DIR__ . "/ocr_output.$uid";

        // 1. Download the PDF
        file_put_contents($pdfPath, file_get_contents($downloadUrl));

        // 2. Convert first page to image
        $imagick = new Imagick();
        $imagick->setResolution(300, 300); // High resolution for better OCR
        $imagick->readImage($pdfPath . '[0]'); // First page only

        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        $imagick->setImageFormat('jpeg');
        $imagick->writeImage($imagePath);
        exec("chmod 644 $imagePath");

        // 3. Run Tesseract OCR
        exec("tesseract " . escapeshellarg($imagePath) . " " . escapeshellarg($ocrOutputPath));

        $outputText = file_get_contents("$ocrOutputPath.txt");
        exec("rm -rf " . escapeshellarg($pdfPath) . " " . escapeshellarg($imagePath) . " " . escapeshellarg("$ocrOutputPath.txt"));
        return $outputText;
    }

    function normalize_name($string) {
        $string = strtolower($string); // lowercase
        $string = preg_replace('/[^\p{L}\p{N}\s]/u', '', $string); // remove punctuation

        // Normalize common business abbreviations
        $abbreviations = [
            'co' => 'company',
            'co.' => 'company',
            'corp' => 'corporation',
            'corp.' => 'corporation',
            'inc' => 'incorporated',
            'inc.' => 'incorporated',
            'ltd' => 'limited',
            'ltd.' => 'limited',
            'llc' => '',
        ];

        foreach ($abbreviations as $abbr => $full) {
            $string = str_ireplace($abbr, $full, $string);
        }

        // Tokenize and normalize
        $words = preg_split('/\s+/', $string);
        $words = array_filter($words);
        $words = array_unique($words);
        sort($words);

        return $words;
    }

    function names_loose_match($name1, $name2) {
        $words1 = $this->normalize_name($name1);
        $words2 = $this->normalize_name($name2);

        // Check if all words in name1 are found in name2
        return empty(array_diff($words1, $words2)) || empty(array_diff($words2, $words1));
    }

    public function findWorkOrderNumberFromText($data)
    {
        $client = new openAIClient();
        $searchText = $data['searchText'] ?? '';
        return $client->getWorkOrderNumberFromText($searchText);
    }

    /**
     * @throws Exception
     */
    public function retrieveBillDataFromAutomation($payload)
    {
        $apiAction = ($payload['action'] ?? '') === 'automation';
        Logger::warning('apiAction: ' . $apiAction);
        if (!$apiAction) {
            return $payload;
        }
        Logger::warning("retrieveBillDataFromAutomation $apiAction");
        if ($apiAction) {
            $data = [
                "billDate" => "",
                "dateDue" => "",
                "charges" => [
                    [
                        "description" => "",
                        "chargeAccountID" => "",
                        "amount" => null,
                        "fromPayer" => "",
                        "toPayee" => ""
                    ]
                ],
                "leaseCharges" => [],
                "payeeContactID" => $payload['payeeContactID'] ?? "702",
                "reference" => "",
                "tenantAmount" => 0,
                "paymentMemo" => "",
                "description" => "",
                "workOrderID" => "",
                "workOrderStatusID" => "",
                "allocation" => false,
                "serviceAccountNumber" => null,
                "utilityPeriodStartDate" => null,
                "utilityPeriodEndDate" => null
            ];

            $unitAddress = $payload['data'][self::ADDRESS_UNIT_MIRROR] ?? '';
            Logger::warning("unitAddress: " . json_encode($unitAddress));
            $unitAddress = trim(mb_substr($unitAddress['address'], 0, 30, 'UTF-8'));
            Logger::warning("retrieveBillDataFromAutomation lookup address $unitAddress");
            $unitData = $this->getUnitFromNumberAndStreetAddress($unitAddress);
            Logger::warning("unitData: " . json_encode($unitData));
            Logger::warning("retrieveBillDataFromAutomation unitId " . $unitData[self::RENTVINE_ID]);
            $unitId = $unitData[self::RENTVINE_ID] ?? null;

            if (!$unitId) {
                throw new Exception('Could not find unit with id ' . $unitId);
            }

            $data['unitId'] = $unitId;
            $data['billDate'] = $payload['data'][self::BILL_DATE_KEY];
            $data['dateDue'] = $payload['data'][self::BILL_DATE_DUE_KEY];
            $data['charges'][0]['description'] = $payload['data'][self::BILL_DESCRIPTION_KEY];
            $data['charges'][0]['amount'] = $payload['data'][self::BILL_AMOUNT_KEY];

            if (empty($data['workOrderID'])) {
                unset($data['workOrderID']);
                unset($data['workOrderStatusID']);
            }

            return $data;

        }

        return $payload;
    }

    public function createLeaseCharge($leaseId, $data)
    {
        $endpoint = "/manager/accounting/leases/$leaseId/charges";
        return $this->makeRequest($endpoint, "POST", $data);
    }

    public function handleWhPostLeaseCharge($event)
    {
        $eventObject = (object) $event;
        $data = $eventObject->data ?? [];

        $postLeaseChargeAptlet = ($data[self::APTLET_UID_FIELD] ?? '') === self::LEASE_CHARGE_APTLET_KEY;
        $postLeaseChargeAction = ($data[self::LEASE_POST_CHARGE_ACTION_KEY] ?? false) === true;

        Logger::warning('postLeaseChargeAptlet: ' . json_encode($postLeaseChargeAptlet));
        Logger::warning('postLeaseChargeAction: ' . json_encode($postLeaseChargeAction));

        if (!$postLeaseChargeAptlet || !$postLeaseChargeAction) {
            Logger::warning('DO NOT RUN handleWhPostLeaseCharge');
            return;
        }

        if (!empty($data[self::LEASE_TRANSACTION_ID_KEY])) {
            Logger::warning('DO NOT RUN handleWhPostLeaseCharge, charge already added: Charge: ' . $data[self::LEASE_TRANSACTION_ID_KEY]);
            return;
        }

        $leaseId = $data[self::LEASE_CHARGE_LEASE_ID_KEY] ?? null;
        if (!$leaseId) {
            Logger::warning('handleWhPostLeaseCharge missing lease id');
            return;
        }

        $payload = [
            "chargeAccountID" => $data[self::LEASE_CHARGE_ACCOUNT_ID_KEY] ?? null,
            "amount" => $data[self::LEASE_CHARGE_AMOUNT_KEY]['amount'] ?? null,
            "datePosted" => $data[self::LEASE_CHARGE_DATE_POSTED_KEY] ?? null,
            "description" => $data[self::LEASE_CHARGE_DESCRIPTION_KEY] ?? null
        ];

        Logger::warning('RUN IT handleWhPostLeaseCharge ' . json_encode([
            'leaseId' => $leaseId,
            'chargeAccountId' => $payload['chargeAccountID'],
            'amount' => $payload['amount'],
            'datePosted' => $payload['datePosted']
        ]));

        try {
            $createChargeResult = $this->createLeaseCharge($leaseId, $payload);
            $resultArray = json_decode($createChargeResult, true) ?: [];
            Logger::warning('createLeaseCharge result: ' . json_encode($resultArray));

            $transactionId = $resultArray['transaction']['transactionID'] ?? null;
            Logger::warning('Transaction Id: ' . $transactionId);

            $cardId = $data['_id'] ?? null;
            if ($cardId) {
                $aptly = new AptlyAPI();
                $aptly->updateCardData($cardId, [
                    "Rentvine posted charge ID" => $transactionId
                ], self::LEASE_CHARGE_APTLET_ID);
            }
        } catch (Throwable $e) {
            Logger::warning('handleWhPostLeaseCharge error: ' . $e->getMessage());
        }
    }
}
