<?php 
namespace Lazada\Manager;

class OrdersManager{
    public static function getForLzdFeeStmtByOrderNums(\mysqli $con, array $orderNums):array{
        $count = count($orderNums);
        $dataType = implode('',\array_fill(0,$count,'s'));
        $placeHolders = implode(',', \array_fill(0,$count,'?'));
        $stmt = $con->prepare('SELECT `billno`,`orderNum`,`sellingPrice`,`shippingFeeByCust`,`voucher`, '
            .'`platformChargesAmount`, (0.00)AS stmtPaymentAmount, (0.00)AS stmtShippingFeeByCust FROM `orders` ' 
            ."WHERE orderNum IN({$placeHolders})");

        if($stmt === false){
            throw new \Exception($con->error);
        }
        
        $stmt->bind_param($dataType, ...$orderNums);

        if($stmt->execute() === false){
            throw new \Exception($stmt->errno . ' ' .$stmt->error);
        }

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}