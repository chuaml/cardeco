<?php 
require_once('../db/conn_staff.php');
require('../inc/class/Bookmark.php');
require('../inc/class/Database_StockImages_Exporter.php');
header("Cache-Control: no-cache");

if(!isset($_POST['row'])){
	die('No selected item, no rows.');
}
if(sizeof($_POST['row']) === 0){
	die('No selected item');
}


if(isset($_POST['btnSubmit'])){
	include('_bookmarkListstock_items.php');
} else if(isset($_POST['btnSubmit_exportImg'])){
	include('_exportListstock_image.php');
} else {
	die('No event trigger.');
}

?>
