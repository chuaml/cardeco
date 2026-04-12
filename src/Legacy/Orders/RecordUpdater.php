<?php 
namespace Orders;

require_once(__DIR__ .'/../Database/Updatable.php');

use \Orders\MonthlyRecord;
use \Database\Updatable;

//update MonthlyRecord dateStockOut
class RecordUpdater implements Updatable{

    const DATE_FORMAT = 'Y-m-d';

    public function getRecordsByTrackingNum(\mysqli $con, array $trackingNum):array{
        $count = count($trackingNum);
        if($count === 0){
            return [];
        }

        $param = implode(',', array_fill(0, $count, '?'));
        $dataType = implode('', array_fill(0, $count, 's'));

        $stmt = $con->prepare(
            "SELECT orderNum, dateStockOut, trackingNum FROM orders WHERE trackingNum IN ({$param})"
        );
        $stmt->bind_param($dataType, ...$trackingNum);

        if(!($stmt->execute())){
            throw new \Exception($stmt->error);
        }

        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return array_map(function(array $r){
            $M = new MonthlyRecord();
            $M->setOrderNum($r['orderNum']);
            $M->setDateStockOut($r['dateStockOut']);
            $M->setTrackingNum($r['trackingNum']);
            
            return $M;
        }, $result);
    }

    public function update(\mysqli $con, array &$MonthlyRecords):void{
        $stmt = $con->prepare(
            'UPDATE orders SET dateStockOut = ? WHERE trackingNum = ?'
        );

        $dateStockOut;
        $trackingNum;
        $stmt->bind_param('ss',
            $dateStockOut,
            $trackingNum
        );

        foreach($MonthlyRecords as $M){
            $dateStockOut = date_format(date_create($M->dateStockOut), self::DATE_FORMAT);
            $trackingNum = $M->trackingNum;

            if(!($stmt->execute())){
                throw new \Exception($stmt->error);
            }
        }
        $stmt->close();
    }
}
