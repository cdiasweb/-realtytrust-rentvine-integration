<?php

require_once './vendor/autoload.php';

use Rentvine\Logger;
use Rentvine\RentvineAPI;

$run = false;

if (!$run) {
    echo "Do not run it.";
    return;
}

$startBillId = 26728;
$endBillId = 26724;

$userName = '221a34acd58d40138ddfbf9ba18ce2cf';
$password = '64024e6e27a940358794c7c413887aae';
$rentvine = new RentvineAPI($userName, $password);

foreach (range($startBillId, $endBillId, -1) as $id) {
    $billData = $rentvine->getOwnerPortfolioBillById($id);
    $billData = json_decode($billData, true);
    $description = $billData["bill"]["description"] ?? "";
    $billDate = $billData["bill"]["billDate"] ?? "";
    Logger::warning("Found $description - $billDate - $id");
    if ($description === "Line Item Description" && $billDate === "2025-11-03") {
        deleteBillId($id, $rentvine);
    }
}

function deleteBillId($billId, $rentvine)
{
    echo "Delete bill ID: $billId\n";
    $result = $rentvine->deleteBillById($billId);
    Logger::warning("RESULT: " . json_encode($result));
}
