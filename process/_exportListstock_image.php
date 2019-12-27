<?php 
$errormsg = [];

if(!isset($_POST['row'])){
	$errormsg[] = 'no selected row';
} else {
	if(sizeof($_POST['row']) === 0){
		$errormsg[] = 'no selected item';
	}
}

if(!isset($_POST['bookmark_id'])){
	$errormsg[] = 'no selected bookmark';
} else {
	if(strlen($_POST['bookmark_id']) === 0){
		$errormsg[] = 'no bookmark id';
	}
}

if(sizeof($errormsg) !== 0){
	die('Error: <br>' . implode('<br>', $errormsg));
}

$row_list = $_POST['row'];
$bookmark_id = trim($_POST['bookmark_id']);

$db = new Database_StockImages_Exporter($con);

//to do
if($db->setBookmark_name($bookmark_id) === false){
	die('fail to set bookmark name');
}

if($db->setTarget_Items_Images($row_list) === false){
	die('fail to set row list, no item selected.');
}

$exportFile = $db->exportFile('../', '../public/catalog_pictures/');

if($exportFile === false){
	trigger_error('Fail to export file.');
	die();
}

if(mysqli_errno($con) !== 0){
	trigger_error(mysqli_errno($con));
	die();
}

if(error_get_last() === null){
	echo '<script>alert("Image files exported");
	window.location.href = "../liststock";</script>';
}


