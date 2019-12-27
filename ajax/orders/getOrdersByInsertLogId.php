<?php 
if(isset($_GET['insertLogId']) && !empty(trim($_GET['insertLogId']))){
    require_once(__DIR__ .'/../../../db/conn_staff.php');
    
    $stmt = $con->prepare(
        'SELECT orderNum, date, dateStockOut, sku, trackingNum, sellingPrice, status, platformCharges '
        .'FROM orders WHERE insertLogId = ?'
    );
    $stmt->bind_param('s', $_GET['insertLogId']);
    if($stmt->execute()){
        header('Content-Type: application/json');
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

        $stmt->free_result();
        $stmt->close();
    }
    $con->close();
}