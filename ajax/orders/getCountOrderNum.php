<?php 
if(isset($_GET['orderNum']) && strlen($_GET['orderNum']) > 0){
    require_once(__DIR__ .'/../../db/conn_staff.php');
    
    $stmt = $con->prepare('SELECT COUNT(id) FROM orders WHERE orderNum = ?');
    $stmt->bind_param('s', $_GET['orderNum']);
    if($stmt->execute()){
        http_response_code('200'); //ok
        echo $stmt->get_result()->fetch_row()[0];
        $stmt->free_result();
        $stmt->close();
    }
    $con->close();
}