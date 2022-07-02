<?php 
if(isset($_GET['itemCode']) && strlen($_GET['itemCode']) > 0){
    require_once(__DIR__ .'/../../db/conn_staff.php');
    
    $stmt = $con->prepare('SELECT item_code FROM stock_items WHERE item_code LIKE (?) LIMIT 8;');
    $itemCode = trim($_GET['itemCode']) . '%';
    $stmt->bind_param('s', $itemCode);
    if($stmt->execute()){
        $result = $stmt->get_result();
        $data = [];
        while(($r = $result->fetch_assoc()) !== null){
            $data[] = $r['item_code'];
        }
        $stmt->close();

        header('Content-Type: application/json');
        echo json_encode($data);
    }
    $con->close();
}else{
    header('HTTP/1.1 204'); //response 204 no content
}
