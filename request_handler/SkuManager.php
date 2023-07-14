<?php 
namespace main;




use Orders\SkuManager;
use \Exception;

$errormsg = '';
//insert
try{
    if(isset($_POST['submit'])){
        try{
            if($_POST['submit'] === 'add'){
                if(isset($_POST['itemCode']) && isset($_POST['sku'])){
                    $itemCode = trim($_POST['itemCode']);
                    $sku = trim($_POST['itemCode']);
                    if(!empty($itemCode) && !empty($sku)){
                        $M = new SkuManager();
                        $data = [
                            $_POST['sku'] => $_POST['itemCode']
                        ];
                        $M->insert($con, $data);    
                    }
                }
            }else if($_POST['submit'] === 'delete'){
                if(isset($_POST['skuList']) && 
                \is_array($_POST['skuList']) && !empty($_POST['skuList'])){
                    $M = new SkuManager();
                    $M->delete($con, $_POST['skuList']);
                }
            }
        }finally{
            $con->close();
        }
        exit(header('Location: ' .$_SERVER['REQUEST_URI']));
    }
}catch(Exception $e){
    $errormsg = $e->getMessage();
}


require('view/SkuManager.html');
?>
