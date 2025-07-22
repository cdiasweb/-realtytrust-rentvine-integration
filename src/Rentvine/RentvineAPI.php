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

    public const OWNER_BILLS_FIELD = 'Owner Bills';
    public const RENTVINE_ID = 'Rentvine ID';
    public const RENTVINE_DOC_UPLOAD_FIELD = 'Rentvine Building documents';
    public const UNIT_FIELD = 'RPYgwSp52dD4tBbrN';
    public const UNIT_MULTIPLE_FIELD = 'XA8oZNqj5hY2NFJSN';

    public $units = [];

    const MAKE_WH_SIGNATURE = "YYLKFymLrrkfMyw3R-WCaphN9vZwN2z9PZb";
    const MAKE_URL = "https://hook.us1.make.com/tf4abmmirj1lo8crrhjn3nazh84wn3gi";
    const NGROK_URL = "https://egret-glorious-cow.ngrok-free.app/hook";

    public function __construct($userName, $password, $baseUrl = 'https://realtytrustservicesllc.rentvine.com/api')
    {
        $this->userName = $userName;
        $this->password = $password;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->loadUnits();
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

    public function getUnitFromNumberAndStreetAddress($address)
    {
        Logger::warning('getUnitFromNumberAndStreetAddress: ' . $address);
        if (!empty($this->units)) {
            foreach ($this->units as $unit) {
                if (str_starts_with($unit['Title'], $address)) {
                    Logger::warning('UNIT found');
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
        Logger::warning('linkUnitIdToCard $unitId: ' . $unitId . ' $cardId: ' . $cardId);
        $aptly = new AptlyAPI();
        $unitData = $aptly->getCardById($unitId);
        $unitCard = json_decode($unitData, true);
        $unitCardData = $unitCard['message']['data']['message']['data'] ?? null;
        $result = $aptly->updateCardData($cardId, [
            'UNIT' => $unitId
        ]);
        Logger::warning('$unitCardData: ' . json_encode($unitCardData));
        Logger::warning('Unit FIELD update result: ' . $result);

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

    public function getPortfolioByIdIncludingOwners($portfolioId)
    {
        Logger::warning('Portfolio ID: ' . $portfolioId);
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
        /*Logger::warning($webhookEventInfo);*/

        /*foreach($data['changes'] as $change) {
            if ($change['field'] === 'name') {
                Logger::warning('Do not run it.');
                return;
            }
        }*/

        Logger::warning('Run it.');

        // Handle events
        $this->handleBuildingAttachment($data);
        $this->handleLeaseAttachment($data);
        $this->handlePostOwnerBillToPortfolio($data);
        $this->handleGetUnitFromDescription($data);
        $this->handleGetUnitFromPDF($data);

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

        curl_close($ch);
    }

    public function handleBuildingAttachment($event) {
        $eventObject = (object) $event;

        $urlToPdf = $eventObject->data[AptlyAPI::URL_TO_PDF_FIELD] ?? null;
        $attachToPropertyAction = $eventObject->data[AptlyAPI::ATTACH_RV_PROPERTY_FIELD] ?? null;
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
                //Logger::warning("units found: $units");
                $units = json_decode($units, true);
                if (count($units) === 1) {
                    $buildingRentvineId = $units[0]['unit']['unitID'] ?? $buildingRentvineId;
                    $objectTypeId = $units[0]['unit']['unitID'] ? 7 : 6;
                    Logger::warning('$buildingRentvineId UNITS: ' . $buildingRentvineId);
                }

                $fileUploadedData = $this->addAttachmentToObject($buildingRentvineId, $objectTypeId, $_FILES);
                Logger::warning('$fileUploadedData: ' . $fileUploadedData);

                // Set the result to card
                $updateFeedbackResult = $aptly->updateCardData($eventObject->data['_id'] ?? null, [
                    AptlyAPI::RV_APTLY_FIELD_NAME => 'Document attached to Property',
                    AptlyAPI::RV_APTLY_ACTION_FIELD_NAME => 'Attached to property'
                ]);
                Logger::warning('$updateFeedbackResult: ' . $updateFeedbackResult);
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
        $updateFeedbackResult = $aptlyApi->updateCardData($eventObject->data['_id'] ?? null, [
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

    public function handlePostOwnerBillToPortfolio($event)
    {
        $eventObject = (object) $event;
        $postOwnerBillAction = $eventObject->data[AptlyAPI::POST_TO_OWNER_PORTFOLIO_FIELD] ?? null;
        $portfolioCardId = $eventObject->data[AptlyAPI::PORTFOLIO_FIELD][0]['_id'] ?? null;
        //Logger::warning('Portfolio Card ID: ' . json_encode($portfolioCardId));
        $aptly = new AptlyAPI();
        $portfolioCard = $aptly->getCardById($portfolioCardId);
        //Logger::warning('$portfolioCard: ' . $portfolioCard);

        if (!$portfolioCard) {
            return;
        }

        $portfolioCard = json_decode($portfolioCard, true);
        $portfolioRvId = $portfolioCard['message']['data']['message']['data'][AptlyAPI::RENTVINE_ID_KEY] ?? null;
        //Logger::warning('$portfolioCard: ' . json_encode($portfolioCard));
        //Logger::warning('$portfolioRvId: ' . $portfolioRvId);
        $rentvinePortfolioData = $this->getPortfolioByIdIncludingOwners($portfolioRvId);
        $rentvinePortfolioData = json_decode($rentvinePortfolioData, true);
        //Logger::warning('$rentvinePortfolioData: ' . json_encode($rentvinePortfolioData));
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
        Logger::warning('RUN handlePostOwnerBillToPortfolio: ' . json_encode($billData));

        Logger::warning('Bill data: ' . json_encode($billData));
        $result = $this->createOwnerPortfolioBill($billData);
        Logger::warning('$result: ' . json_encode($result));

        $cardId = $eventObject->data['_id'];
        $updateCardResult = $aptly->updateCardData($cardId, [
            AptlyAPI::BILL_TO_OWNER_RESULT => "Bill added to portfolio owner."
        ]);
        Logger::warning('$updateCardResult: ' . $updateCardResult);
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

    public function findUnitInJson($data)
    {
        $searchText = $data['searchText'] ?? '';
        $json = file_get_contents('units.json');
        $data = json_decode($json, true);

        // Prepare search words
        $searchWords = array_filter(explode(' ', strtolower($searchText)));

        $filtered = array_filter($data, function($item) use ($searchWords) {
            $target = strtolower($item['unit']['name']);

            // Check if all search words are in the target string
            foreach ($searchWords as $word) {
                if (stripos($target, $word) === false) {
                    return false;
                }
            }
            return true;
        });

        if (!empty($filtered)) {
            echo json_encode($filtered);
        } else {
            echo "Not found.";
        }
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
        Logger::warning('Changes: ' . json_encode($changes));
        if (!$changes || $changes[0]['field'] !== 'description') {
            return;
        }

        $propertyAddress = $this->getPropertyAddressFromDescription($changes[0]['value']);
        Logger::warning('Property address: ' . $propertyAddress);
    }

    function handleGetDriveFileTextContent($driveUrl)
    {
        $downloadUrl = $this->getGoogleDriveDownloadUrl($driveUrl['driveUrl']);
        Logger::warning('Download URL: ' . $downloadUrl);
        $pdfPath = __DIR__ . '/temp.pdf';
        $imagePath = __DIR__ . '/page.jpg';
        $ocrOutputPath = __DIR__ . '/ocr_output.txt';

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

        // 3. Run Tesseract OCR
        exec("tesseract " . escapeshellarg($imagePath) . " " . escapeshellarg($ocrOutputPath));

        $outputText = file_get_contents($ocrOutputPath . '.txt');

        $client = new OpenAIClient();
        $addresses = $client->getAddressesFromText($outputText);
        Logger::warning('Addresses ' . json_encode($addresses));

        // 4. Read and print the extracted text
        $textContent = file_get_contents($ocrOutputPath . '.txt');
        $possibleUnits = $client->getAddressesFromText($textContent);
        $possibleUnits = json_decode($possibleUnits, true);

        $units = [];
        foreach ($possibleUnits['result'] as $possibleUnit) {
            preg_match('/\d{1,10}\s{0,1}\w/', $possibleUnit, $matches);
            $unitAddress = end($matches);
            $units[] = $this->getUnitFromNumberAndStreetAddress($unitAddress);
            Logger::warning('Address found: ' . json_encode($unitAddress));
        }

        // Units found
        //Logger::warning('$units: ' . json_encode($units));
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
        Logger::warning('$aptlyIds: ' . json_encode($aptlyIds));

        return json_encode($aptlyIds);
    }

    function handleGetUnitFromPDF($data) {
        try {
            Logger::warning('handleGetUnitFromPDF: START...');
            $unit = $data['data'][self::UNIT_FIELD] ?? [];
            $multipleUnit = $data['data'][self::UNIT_MULTIPLE_FIELD] ?? '';
            $pdfUrl = $data['data'][AptlyAPI::URL_TO_PDF_FIELD] ?? '';
            if (empty($unit) && empty($multipleUnit) && $pdfUrl) {
                $units = $this->handleGetDriveFileTextContent(['driveUrl' => $pdfUrl]);
                if (is_string($units)) {
                    $units = json_decode($units, true);
                }

                if (count($units) > 1) {
                    $cardId = $data['data']['_id'];
                    $unitOptions = "";
                    foreach ($units as $unitOption) {
                        $unitId = $unitOption['_id'];
                        $unitOptions .= "<b>Card ID</b>: " . $unitId . "<br>";
                        $unitOptions .= "<b>Name:</b> " . $unitOption['name'] . "<br><br>";
                        $projectUrl = Env::getProjectUrl();
                        $link = "$projectUrl/link-unit-id-to-card/$unitId/$cardId";
                        $unitOptions .= "<a href='$link'>Link Unit to Card</a>" . "<br><br><br><br>";
                    }
                    $aptly = new AptlyAPI();
                    Logger::warning('UPDATE MULTIPLE: ' . json_encode($unitOptions));
                    $updateResult = $aptly->updateCardData($data['data']['_id'], [
                        'Unit multiple found' => $unitOptions
                    ]);
                    Logger::warning('$updateResult MULTIPLE: ' . json_encode($updateResult));
                } else {
                    $unitOption = $units[0];
                    $unitOptions = "<b>Card ID</b>: " . $unitOption['_id'] . "<br>";
                    $unitOptions .= "<b>Name:</b> " . $unitOption['name'] . "<br><br>";
                    $aptly = new AptlyAPI();
                    Logger::warning('UPDATE UNIQUE: ' . json_encode($unitOptions));
                    $updateResult = $aptly->updateCardData($data['data']['_id'], [
                        self::UNIT_FIELD => $unitOption['_id']
                    ]);

                    Logger::warning('$updateResult: ' . json_encode($updateResult));
                }
            }
        } catch (Throwable $exception) {
            Logger::warning('Error while attempting to get Units: ' . $exception->getMessage());
        }
    }

    function getGoogleDriveDownloadUrl($shareUrl) {
        if (preg_match('/\/d\/(.*?)\//', $shareUrl, $matches)) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }
        return false;
    }
}
