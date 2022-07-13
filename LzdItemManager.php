<?php 
namespace main;

require_once('inc/class/Lazada/Item.php');
require_once('inc/class/Lazada/Manager/ItemManager.php');
require_once('inc/class/Lazada/Factory/LzdItemFactory.php');
require_once(__DIR__ .'/db/conn_staff.php');

use Lazada\Item;
use Lazada\Factory\LzdItemFactory;
use Lazada\Manager\ItemManager;

$msg = '';
$errorMsg = '';
try{
    try{
        if(isset($_FILES['LzdProducts'])){
            if($_FILES['LzdProducts']['error'] !== 0){
                throw new Exception("File has error.");
            }
            $file = $_FILES['LzdProducts']['tmp_name'];
            $Fac = new LzdItemFactory($file);
            $list = $Fac->generateRecords();

            $M = new ItemManager($con);
            $existingLzdSku_temp = $M->selectAll('lzd_sku');
            $existingLzdSku = [];
            foreach($existingLzdSku_temp as $r){
                $existingLzdSku[$r['lzd_sku']] = null;
            }
            unset($existingLzdSku_temp);

            //split
            $listToUpdate = [];
            $listToInsert = [];
            foreach($list as $Item){
                if(\array_key_exists($Item->lzdSku, $existingLzdSku)){
                    $listToUpdate[] = $Item;
                } else {
                    $listToInsert[] = $Item;
                }
            }
        
            //update
            $con->begin_transaction();
            try{
                $M->updateByLzdSku($listToUpdate);
                $M->insert($listToInsert);
                $con->commit();
                $msg = 'Product info updated.';
            }catch(\Exception $e){
                $con->rollback();
                throw $e;
            }
        }
    }finally{
        $con->close();
    }
}catch(\Exception $e){
    $errorMsg = \htmlspecialchars($e->getMessage(), ENT_QUOTES);
}
require('view/LzdItemManager.html');
?>