<?php 
require('inc/class/Database_StockImages_Exporter.php');
header("Cache-Control: no-cache");

$db = new Database_StockImages_Exporter($con);

$exportAll_Images = $db->exportAll_Images('', 'public/catalog_pictures/');

if($exportAll_Images === false){
	trigger_error('Fail to export all products pictures.');
	die();
}


if(mysqli_errno($con) !== 0){
	trigger_error(mysqli_errno($con));
	die();
}

if(error_get_last() === null){
	echo '<script>alert("All Image files exported");
	window.location.href = "";</script>';
}

