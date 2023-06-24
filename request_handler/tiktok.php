<?php

namespace Controller;




use Exception;
use OrderProcess\TikTokOrder;

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
    if (isset($_FILES['orderFile'])) {
        try {
            $L = new TikTokOrder($con, $_FILES['orderFile']['tmp_name']);
            $Data = $L->getData();

            $jsonOrders = json_encode($L->getOrders());
            $dailyOrderFile_Sha1Hash = sha1_file($_FILES['orderFile']['tmp_name']);
        } catch (Exception $e) {
            $msg = $e->getMessage();
        }
    }
} finally {
    $con->close();
}

require 'view/tiktok.html';
