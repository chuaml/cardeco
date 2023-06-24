<?php

namespace Controller;

use Exception;
use OrderProcess\BigSellerOrderProcess;

$Data = [
    'toRestock' => '',
    'toCollect' => '',
    'notFound' => '',
    'orders' => ''
];
$msg = '';

$jsonOrders = '';
$dailyOrderFile_Sha1Hash = '';

try {
    if (isset($_FILES['dailyOrders'])) {
        try {
            $L = new BigSellerOrderProcess($con, $_FILES['dailyOrders']['tmp_name']);
            $Data = $L->getData();

            $jsonOrders = json_encode($L->getOrders());
            $dailyOrderFile_Sha1Hash = sha1_file($_FILES['dailyOrders']['tmp_name']);
        } catch (Exception $e) {
            $msg = $e->getMessage();
        }
    }
} finally {
    $con->close();
}

require 'view/bigseller.html';
