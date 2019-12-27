<?php 
require_once('../../db/conn_staff.php');
require('../inc/class/Bookmark.php');
require('../inc/class/catalog_generator.php');
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
