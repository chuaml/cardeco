<?php 
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../db/conn_staff.php');
header("Cache-Control: no-cache");

if(!isset($_POST['row'])){
	die('No selected item, no rows.');
}
if(sizeof($_POST['row']) === 0){
	die('No selected item');
}

if(isset($_POST['btnPrint'])){
	include('_printBookmarks_items.php');
} else if(isset($_POST['btnRemove'])){
	include('_removeBookmarks_items.php');
} else {
	mysqli_close($con);
	die('No event trigger.');
}

?>
