<?php 
namespace main;

require_once('inc/class/Product/ItemManager.php');
require_once('inc/class/Product/ItemEditor.php');
require_once('inc/class/Product/Item.php');
require_once(__DIR__ .'/db/conn_staff.php');

use \Orders\MonthlyRecord;
use \Product\ItemEditor;
use \Product\ItemManager;
use \Product\Item;

$itemEditor = '';
$error = '';
try{
    try{
        if(isset($_GET['itemCode'])){
            $ItemM = new ItemManager($con);
            $ItemEditor = new ItemEditor();

            $ItemEditor->setItems(
                $ItemM->getItemLikeItemCode($_GET['itemCode']), 
                0
            );

            $itemEditor = $ItemEditor->getTable();
        }

        if(isset($_POST['r'])){
            $ItemM = new ItemManager($con);
            $Items = [];
            foreach($_POST['r'] as $itemId => $r){
                $Items[] = new Item($itemId, null, $r['description']);
            }
            $ItemM->update($Items);
            header('HTTP/1.1 205');
        }
    }finally{
        $con->close();
    }
}catch(\Exception $e){
    $error = $e->getMessage();
}

require('view/ItemManager.html');
?>