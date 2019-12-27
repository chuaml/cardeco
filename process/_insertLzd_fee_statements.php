<?php 
require('../inc/class/CSV_Table_Handler.php');
require('../inc/class/Lazada_Fee_Statement.php');

if(!isset($_FILES['file_LzdFeeStmt'])){
	die('No selected file');
} else{
	if($_FILES['file_LzdFeeStmt']['error'] !== 0){
		die('error uploading file');
	}
}

$lzd_stmt = new Lazada_Fee_Statement($_FILES['file_LzdFeeStmt']);

if(!$lzd_stmt->insertToDB($con)){
	echo "error. only $lzd_stmt->inserted_Rows rows recorded.";
}

if(error_get_last() === null && mysqli_errno($con) === 0){
	echo '<script>alert("Lzd fee statments loaded.");
	window.location.href = "../fee_statement";</script>';
}
