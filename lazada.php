<?php

namespace Controller;

require_once 'inc/class/Product/Manager/ItemManager.php';
require_once 'inc/class/Lazada/Manager/ItemManager.php';
require_once 'inc/class/Product/Item.php';
require_once 'inc/class/Orders/Record.php';
require_once 'inc/class/Orders/Lazada/AutoFilling.php';
require_once 'inc/class/HTML/TableDisplayer.php';
require_once 'inc/class/IO/FileInputStream.php';
require_once 'inc/class/IO/CSVInputStream.php';
require 'db/conn_staff.php';


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
