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
try {
    if (isset($_FILES['orderFile'])) {
        try {
            $L = new TikTokOrder($con, $_FILES['orderFile']['tmp_name']);
            $Data = $L->getData();
        } catch (Exception $e) {
            $msg = $e->getMessage();
        }
    }
} finally {
    $con->close();
}

require 'view/tiktok.html';
