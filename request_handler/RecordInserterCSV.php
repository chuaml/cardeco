<?php 

use \Orders\RecordInserter;
use \Orders\RecordDeleter;
use \Orders\Factory\Lazada;
use \Orders\Factory\Shopee;
use \Orders\Lazada\AutoFilling;

function getRecords($Factory):array{
    $list = $Factory->generateRecords();
    if(count($list) === 0){
        throw new Exception("No Records found.");
    }
    return $list;
}


function insertToDB($con, $File, $Inserter, $list):void{
    $Inserter->insertLog($con, $File['tmp_name'], $File['name']);
    $Inserter->insert($con, $list);
    $Inserter->update($con, $list);
}

$errorMsg = '';
$msg = '';


$Deleter = new RecordDeleter();
$insertLogId = $Deleter->selectAllOrdersInsertLog($con);
if(isset($_POST['submit']) && $_POST['submit'] === 'delete' && isset($_POST['insertLogId'])){
    $Deleter->deleteByInsertedId($con, (int) $_POST['insertLogId']);
}

if(isset($_FILES['orders']) && isset($_POST['platform'])){
    $File = $_FILES['orders'];
    try{
        if($File['error'] !== 0){
            throw new Exception('file has error.');
        }

        $list;
        switch($_POST['platform']){
            case 'Lazada': 
                $list = getRecords(new Lazada($File['tmp_name']));
                AutoFilling::setShippingWeightByLzdProduct($con, $list);
                AutoFilling::setShippingFeeByWeight($list);
                break;
            case 'Shopee': 
                $list = getRecords(new Shopee($File['tmp_name']));
                break;
            default: throw new Exception('invalid platform ' .$_POST['platform']);
        }

        insertToDB($con, $File, new RecordInserter($_POST['platform']), $list);
       
        $con->close();
        exit(header('Location: ' .$_SERVER['REQUEST_URI']));
    }catch(Exception $e){
        $errorMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES);
    }
    
    // var_dump($_POST, $_FILES);
}
$con->close();

require('view/RecordInserterCSV.html');
