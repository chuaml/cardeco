<?php 
require('../inc/class/CSV_Table_Handler.php');
require('../inc/class/Monthly_Sales_Record.php');

if(!isset($_FILES['file_MonthlySales'])){
	die('No selected file');
} else{
	if($_FILES['file_MonthlySales']['error'] !== 0){
		die('error uploading file');
	}
}

$monthly_sales = new Monthly_Sales_Record($_FILES['file_MonthlySales']);
if($monthly_sales->insertToDB($con) === false){
	echo "Error. Only $monthly_sales->inserted_Rows rows recorded. Possible invalid file format";
	die();
}



if(error_get_last() === null && mysqli_errno($con) === 0){
	echo '<script>alert("Courier record Loaded.");
	window.location.href = "../fee_statement";</script>';
}
