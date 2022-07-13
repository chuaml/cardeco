<?php

namespace main;

require_once 'inc/class/Orders/Factory/Record.php';

require_once 'inc/class/Orders/PaymentCharges/PlatformCharges.php';
require_once 'inc/class/Lazada/Manager/Fee_StatementsManager.php';
require_once 'inc/class/Lazada/Manager/OrdersManager.php';
require_once 'inc/class/Lazada/Factory/LzdFeeStatementFactory.php';
require_once 'inc/class/Lazada/FeeStatement.php';
require_once 'inc/class/HTML/TableDisplayer.php';
require_once 'inc/class/IO/FileInputStream.php';
require_once 'inc/class/IO/CSVInputStream.php';
require_once __DIR__ . '/db/conn_staff.php';

use \Lazada\Manager\Fee_StatementsManager;
use \Lazada\Manager\OrdersManager;
use \Lazada\Factory\LzdFeeStatementFactory;
use \HTML\TableDisplayer;
use \IO\CSVInputStream;

$errormsg = '';

$tbl = new TableDisplayer();
try {
    if (isset($_POST['btnSubmit']['uploadLzdFeeStmt']) && isset($_FILES['file_LzdFeeStmt']) && $_FILES['file_LzdFeeStmt']['error'] === 0) {
        $file = new CSVInputStream($_FILES['file_LzdFeeStmt']['tmp_name']);
        $list = $file->readLines();

        \array_splice($list, 0, 1);

        $records = LzdFeeStatementFactory::getFeeStatementList($list);

        $con->autoCommit(false);
        Fee_StatementsManager::insertRecords($con, $records, $_SERVER['REMOTE_ADDR']);
        $con->commit();

        header('Location: fee_statement.php');
    }

    if (isset($_POST['btnSubmit']['checkLzdFeeStmt'])) {
        $paymentAmounts = Fee_StatementsManager::getOrderNumPaymentAmount($con, $_SERVER['REMOTE_ADDR']);
        $shippingFeeByCusts = Fee_StatementsManager::getOrderNumShippingFeeByCust($con, $_SERVER['REMOTE_ADDR']);

        $orders = OrdersManager::getForLzdFeeStmtByOrderNums($con, \array_keys($paymentAmounts));
        foreach ($orders as $i => $r) {
            $orders[$i]['grandTotal'] = $r['sellingPrice'] + $r['shippingFeeByCust'] - $r['voucher'] - $r['platformChargesAmount'];

            if (\array_key_exists($r['orderNum'], $paymentAmounts) === true) {
                $orders[$i]['stmtPaymentAmount'] = $paymentAmounts[$r['orderNum']];
            }

            if (\array_key_exists($r['orderNum'], $shippingFeeByCusts) === true) {
                $orders[$i]['stmtShippingFeeByCust'] = $shippingFeeByCusts[$r['orderNum']];
            }

            $orders[$i]['grantTotalDiff'] = $orders[$i]['grandTotal'] - $orders[$i]['stmtPaymentAmount'] - $orders[$i]['stmtShippingFeeByCust'];
        }

        $header = [];
        $header['billno'] = 'Bill no';
        $header['orderNum'] = 'Order Number';
        $header['sellingPrice'] = 'Selling Price';
        $header['shippingFeeByCust'] = 'Courier By Customer';
        $header['voucher'] = 'Voucher';
        $header['platformChargesAmount'] = 'Platform Charges';

        $header['grandTotal'] = 'Grand Total';

        $header['stmtPaymentAmount'] = 'Payment Amount';
        $header['stmtShippingFeeByCust'] = 'Shipping Fee';
        $header['grantTotalDiff'] = 'Grand Total Difference';

        $tbl->setHead($header);

        $tbl->setBody($orders);

        $tbl->setBody($orders);
    }
} catch (\Exception $e) {
    $con->rollback();
    $errormsg = $e->getMessage();
} finally {
    $con->close();
}

require 'view/LzdFeeChecker.html';
