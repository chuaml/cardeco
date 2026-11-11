<?php 
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../db/conn_staff.php');

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
		$lastError = error_get_last();
		trigger_error('some error has occure. process is stopped.');
		// Exception::__construct() takes a string; passing the error_get_last() array was a TypeError
		// that escaped the catch(Exception) below and produced a blank 500 instead of the message.
		throw new Exception(
			'type=' . $lastError['type'] . ' ' . $lastError['message']
			. ' in ' . $lastError['file'] . ':' . $lastError['line']
		);
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
