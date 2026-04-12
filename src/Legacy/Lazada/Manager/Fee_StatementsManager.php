<?php 
namespace Lazada\Manager;

use Lazada\FeeStatement;

class Fee_StatementsManager{
    
    CONST FEE_NAME_PLATFORM_CHARGES = 'Payment Fee';
    CONST FEE_NAME_ITEM_PRICE = 'Item Price Credit';
    CONST FEE_NAME_SHIPPING_FEE_CUST = 'Shipping Fee (Charged by Lazada)';
    CONST FEE_NAME_SHIPPING_FEE = 'Shipping Fee (Paid By Customer)';

    public static function insertRecords(\mysqli $con, array $list, string $userIP):void{
 
        $stmt = $con->prepare('INSERT INTO `lzd_fee_statements`(`tdate`, `fee_type`, `fee_name`, `description`, `item_code`, '
        .'`lzd_sku`, `amount`, `sdate`, `paid_status`, `order_num`, `item_status`, `user_ip`) '
        .'VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        
        foreach($list as $r){   
            $tdate = $r->getTransactionDate();
            $feeType = $r->getFeeType();
            $feeName = $r->getFeeName();
            $description = $r->getDescription();
            $itemCode = $r->getItemCode();
            $lzdSku = $r->getLzdSku();
            $amount = $r->getAmount();
            $sdate = $r->getStatementDate();
            $paidStatus = $r->getPaidStatus();
            $orderNum = $r->getOrderNum();
            $itemStatus = $r->getItemStatus();

            $stmt->bind_param('ssssssdsssss', $tdate, $feeType, $feeName, $description, 
            $itemCode,$lzdSku,$amount,$sdate, 
            $paidStatus,$orderNum,$itemStatus, $userIP) ;
            
            if($stmt->execute() === false){
                throw new \Exception($stmt->errno . ' ' .$stmt->error);
            }
        }

    }

    public static function getOrderNumPaymentAmount(\mysqli $con,string $userIp):array{
        $stmt = $con->prepare('SELECT order_num, SUM(amount)AS amount FROM `lzd_fee_statements` '
            .'WHERE fee_name IN(?,?) AND user_ip = ? GROUP BY order_num');

        if($stmt === false){
            throw new \Exception($con->error);
        }
        $feeName = 'Payment Fee';
        $feeName2 = 'Item Price Credit';
        $stmt->bind_param('sss', $feeName, $feeName2, $userIp);

        if($stmt->execute() === false){
            throw new \Exception($stmt->errno . ' ' .$stmt->error);
        }

        $map = [];
        $rs = $stmt->get_result();
        while(($r =$rs->fetch_array()) !== null){
            $map[$r[0]] = \doubleval($r[1]);
        } 
        return $map;
    }

    public static function getOrderNumShippingFeeByCust(\mysqli $con,string $userIp):array{
        $stmt = $con->prepare('SELECT order_num, SUM(amount)AS amount FROM `lzd_fee_statements` '
            .'WHERE fee_name = ? AND user_ip = ? GROUP BY order_num');

        if($stmt === false){
            throw new \Exception($con->error);
        }

        $feeName = 'Shipping Fee (Paid By Customer)';
        $stmt->bind_param('ss', $feeName, $userIp);

        if($stmt->execute() === false){
            throw new \Exception($stmt->errno . ' ' .$stmt->error);
        }

        $map = [];
        $rs = $stmt->get_result();
        while(($r =$rs->fetch_array()) !== null){
            $map[$r[0]] = \doubleval($r[1]);
        } 
        return $map;
    }
}