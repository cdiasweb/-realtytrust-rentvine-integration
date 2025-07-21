<?php

// router.php

require_once './vendor/autoload.php';

use Rentvine\RentvineAPI;

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

$userName = '221a34acd58d40138ddfbf9ba18ce2cf';
$password = '64024e6e27a940358794c7c413887aae';
$rentvine = new RentvineAPI($userName, $password);

header('Content-Type: application/json');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("HTTP/1.1 200 OK");
    exit(0);
}

// Define routes
$routes = [
    'GET' => [
        '/status' => function () use ($rentvine) {
            echo 'Routes working!';
        },
        '/owners' => function () use ($rentvine) {
            echo $rentvine->getOwners();
        },
        '/search-vendors' => function () use ($rentvine) {
            echo $rentvine->searchVendors();
        },
        '/search-portfolios' => function () use ($rentvine) {
            echo $rentvine->searchPortfolios();
        },
        '/get-property-by-id/{id}' => function ($id) use ($rentvine) {
            echo $rentvine->getProperty($id);
        },
        '/get-portfolio-by-id-including-owners/{id}' => function ($id) use ($rentvine) {
            echo $rentvine->getPortfolioByIdIncludingOwners($id);
        },
        '/link-unit-id-to-card/{unitId}/{cardId}' => function ($unitId, $cardId) use ($rentvine) {
            echo $rentvine->linkUnitIdToCard($unitId, $cardId);
        }
    ],
    'POST' => [
        '/hook' => function () use ($rentvine) {
            header("HTTP/1.1 202 OK");
            header("Content-Type: text/plain");
            header("Connection: close");
            $size = ob_get_length();
            header("Content-Length: $size");
            echo 'Accepted';

            $data = getJsonBody();
            $rentvine->handleWebhook($data);
        },
        '/add-billing' => function () use ($rentvine) {
            $data = getJsonBody();
            echo $rentvine->createOwnerPortfolioBill($data);
        },
        '/search-ledgers' => function () use ($rentvine) {
            $data = getJsonBody();
            echo $rentvine->searchLedgers($data);
        },
        '/search-contacts' => function () use ($rentvine) {
            $data = getJsonBody();
            echo $rentvine->searchContacts($data);
        },
        '/add-attachment-to-object' => function () use ($rentvine) {
            $objectId = $_GET['objectID'];
            $objectTypeId = $_GET['objectTypeID'];
            echo $rentvine->addAttachmentToObject($objectId, $objectTypeId, $_FILES);
        },
        '/update-units-json' => function () use ($rentvine) {
            echo $rentvine->updatePropertyUnitsJson();
        },
        '/find-unit' => function () use ($rentvine) {
            $data = getJsonBody();
            echo $rentvine->findUnitInJson($data);
        },
        '/find-unit-using-ai-with-address' => function () use ($rentvine) {
            $data = getJsonBody();
            echo $rentvine->findUnitInJsonUsingAI($data);
        },
        '/find-vendor-using-ai' => function () use ($rentvine) {
            $data = getJsonBody();
            echo $rentvine->findVendorInJson($data);
        },
        '/getUnitFromPdf' => function () use ($rentvine) {
            $data = getJsonBody();
            echo $rentvine->handleGetDriveFileTextContent($data);
        }
    ],
    'DELETE' => [
        '/delete-attachment-from-object' => function () use ($rentvine) {
            $fileAttachmentID = $_GET['fileAttachmentID'];
            echo $rentvine->deleteAttachmentFromObject($fileAttachmentID);
        }
    ]
];

// Route matching
if (isset($routes[$requestMethod])) {
    foreach ($routes[$requestMethod] as $route => $callback) {
        // Convert the route to a regex pattern
        $routeRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $route);
        $routeRegex = "#^" . $routeRegex . "$#";

        if (preg_match($routeRegex, $requestUri, $matches)) {
            // Remove the first match (full pattern match) and pass the rest as arguments
            array_shift($matches);

            // Call the corresponding callback with extracted parameters
            call_user_func_array($callback, $matches);
            exit; // Stop further execution
        }
    }
}

// If no route matches, send a 404 response
http_response_code(404);
echo '404 Not Found';

function getJsonBody()
{
    // Get JSON data
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    // Validate JSON
    if (json_last_error() === JSON_ERROR_NONE) {
        // Process the data
        return $data;
    } else {
        http_response_code(400);
        echo "Invalid JSON received!";
        return '';
    }
}

