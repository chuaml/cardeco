<?php
namespace Lazada\Factory;

use \Lazada\FeeStatement;

class LzdFeeStatementFactory
{
    public static function getFeeStatementList(array $csvList):array
    {
        return array_map(function (array $r) {
            $FeeStmt = new FeeStatement();
            $FeeStmt->setTransactionDate($r[0]);
            $FeeStmt->setFeeType($r[1]);
            $FeeStmt->setFeeName($r[2]);
            $FeeStmt->setDescription($r[4]);
            $FeeStmt->setItemCode($r[5]);
            $FeeStmt->setlzdSku($r[6]);
            $FeeStmt->setAmount($r[7]);
            $FeeStmt->setStatementDate($r[11]);
            $FeeStmt->setPaidStatus($r[12]);
            $FeeStmt->setOrderNum($r[13]);
            $FeeStmt->setItemStatus($r[15]);

            return $FeeStmt;
        }, $csvList);
    }
}
