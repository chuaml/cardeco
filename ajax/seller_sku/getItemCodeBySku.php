<?php 
if(isset($_GET['sku']) && !empty($_GET['sku'])){
    require_once(__DIR__ .'/../../db/conn_staff.php');
    
    $stmt = $con->prepare('SELECT itemCode FROM seller_sku WHERE sku = ? LIMIT 1;');
    $sku = trim($_GET['sku']);
    $stmt->bind_param('s', $sku);
    if($stmt->execute()){
        $result = $stmt->get_result();
        if(($r = $result->fetch_row()) !== null){
            echo $r[0];
        }
        $stmt->close();
    }
    $con->close();
}else{
    header('HTTP/1.1 204'); //response 204 no content
}