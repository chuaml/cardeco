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
try {
    if (isset($_FILES['dailyOrders'])) {
        try {
            $L = new BigSellerOrderProcess($con, $_FILES['dailyOrders']['tmp_name']);
            $Data = $L->getData();
        } catch (Exception $e) {
            $msg = $e->getMessage();
        }
    }
} finally {
    $con->close();
}

require 'view/bigseller.html';
