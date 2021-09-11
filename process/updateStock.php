<?php 
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require('../../db/conn_staff.php');
require('../inc/class/Sql_Accounting_Export.php');
require('../inc/class/Database_Stock_Updater.php');

$msg = '';

if(!isset($_FILES['fileTextdata'])){
	die('no file');
}

try{
	$file_export = new Sql_Accounting_Export();
	$db = new Database_Stock_Updater($con);

	$db->setItemList($file_export, $_FILES['fileTextdata']);

	$db->doAllStuff();

	if(mysqli_errno($con) !== 0){
		trigger_error(mysqli_errno($con));
	}

	if(error_get_last() !== null){
		trigger_error('some error has occure. process is stopped.');
		throw new Exception(error_get_last());
	}

	$msg = '<script>alert("Stock updated.");
	window.location.href = "../updateStock";</script>';

}catch(Exception $e){
	$msg = 'Error: Fail to update stock. <br>
	Please try again with proper .txt text data file.';
}
mysqli_close($con);

echo $msg;

?>
