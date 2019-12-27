<?php 
namespace main;
require_once(__DIR__ .'/../db/conn_staff.php');
require_once('inc/class/Orders/Factory/DateStockOut.php');
require_once('inc/class/Orders/Factory/MonthlyRecord.php');
require_once('inc/class/Orders/RecordUpdater.php');
require_once('inc/class/Orders/StockOutTable.php');

use \Orders\Factory\DateStockOut;
use \Orders\Factory\MonthlyRecord;
use \Orders\RecordUpdater;
use \Orders\StockOutTable;
use \Exception;

function getStockOutTable(\mysqli $con, string $date):string{
    $MF = new MonthlyRecord($con);
    $S = new StockOutTable();
        
    $S->setRecords($MF->getStockOutByDate($date));
    return $S->getTable();
}

function getMRecordsToOverWrite(array $MRecordsToUpdate, array $MRecordsFromDB):array{
    $trackinNumKey = [];
    foreach($MRecordsToUpdate as $r){
        $trackinNumKey[$r->trackingNum] = $r->dateStockOut;
    }

    $MRecordsToOverWrite = [];
    foreach($MRecordsFromDB as $r){
        $dso = trim($r->dateStockOut);
        if($dso !== '' && $dso !== $trackinNumKey[$r->trackingNum]){
            $MRecordsToOverWrite[] = $r;
        }
    }

    return $MRecordsToOverWrite;
}

$error = new Exception();
$msg = '';
$stockOutTable = '';

try{
    try{
        if(isset($_FILES['stockOut'])){
            if($_FILES['stockOut']['error'] !== 0){
                throw new Exception("File has error.");
            }
            
            $DateStocKOut = new DateStockOut($_FILES['stockOut']['tmp_name']);
            $list = $DateStocKOut->generateRecords();
            
            if(count($list) === 0){
                throw new Exception('No records to update dateStockOut.');
            }

            $Updater = new RecordUpdater();

            //check if given each trackingNum has dateStockOut set
            $trackingNumList = array_map(function($M){
                $trackingNum = $M->trackingNum;
                if($trackingNum !== ''){
                    return $trackingNum;
                }
            }, $list);

            $MRecordsFromDB = $Updater->getRecordsByTrackingNum($con, $trackingNumList);
            $MRecordsToOverWrite = getMRecordsToOverWrite($list, $MRecordsFromDB);

            if(count($MRecordsToOverWrite) > 0){
                $emsg = "Error. Some records have dateStockOut set already.<br>\n";
                foreach($MRecordsToOverWrite as $r){
                    $emsg .= "OrderNum: {$r->orderNum}, TrackingNum: {$r->trackingNum}, " 
                        . "dateStockOut: {$r->dateStockOut}<br>\n";
                }
                throw new Exception($emsg);
            }

            //update
            $Updater->update($con, $list);
            
            $stockOutTable = getStockOutTable($con, $list[0]->dateStockOut);
            $msg = 'Done. date stock out updated.';
        }

        //dateStockOut summary table
        if(isset($_GET['dateStockOut'])){
            $stockOutTable = getStockOutTable($con, $_GET['dateStockOut']);
        }
    }finally{
        $con->close();
    }
}catch(Exception $e){
    $error = $e;
}

require('view/RecordUpdater.html');
?>