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
try {
    if (isset($_FILES['lzdOrders'])) {
        try {
            $L = new LazadaOrderProcess($con, $_FILES['lzdOrders']['tmp_name']);
            $Data = $L->getData();
        } catch (Exception $e) {
            $msg = $e->getMessage();
        }
    }
} finally {
    $con->close();
}

require 'view/lazada.html';
