<?php

namespace Controller;

use Exception;
use OrderProcess\LazadaOrderProcess;

$Data = [
    'toRestock' => '',
    'toCollect' => '',
    'notFound' => '',
    'orders' => ''
];
$msg = '';

$jsonOrders = [];
$dailyOrderFile_Sha1Hash = '';

try {
    if (isset($_FILES['lzdOrders'])) {
        try {
            $L = new LazadaOrderProcess($con, $_FILES['lzdOrders']['tmp_name']);
            $Data = $L->getData();

            $jsonOrders = json_encode($L->getOrders());
            $dailyOrderFile_Sha1Hash = sha1_file($_FILES['lzdOrders']['tmp_name']);
        } catch (Exception $e) {
            $msg = $e->getMessage();
        }
    }
} finally {
    $con->close();
}

require 'view/lazada.html';
