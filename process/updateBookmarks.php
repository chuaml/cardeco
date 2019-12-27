<?php
require_once('../../db/conn_staff.php');
require('../inc/class/Bookmark.php');
//header("Cache-Control: no-cache");

$errormsg = [];

if(!isset($_POST['btnSubmit'])){
	$errormsg[] = 'no event trigger.';
}

if(!isset($_POST['chkBookmarks'])){
	$errormsg[] = 'no selected items';
} 

if(sizeof($errormsg) !== 0){
	foreach($errormsg as $msg){
		echo "$msg<br>";
	}
	die();
}

$SUBMIT = strtolower(trim($_POST['btnSubmit']));
$active = false;
$bk_list = [];
$Bookmark = new Bookmark($con);
$action;

switch($SUBMIT){
	case 'remove':
		$active = 0;
		break;
	case 'restore':
		$active = 1;
		break;
	default:
		$active = false;
}

if($active === false){
	trigger_error('Fail to update bookmarks. submit is neither remove nor restore');
	die();
}

$action = $Bookmark->updateBookmarks($_POST['chkBookmarks'], $active);

if($action === false){
	trigger_error('Fail to create New Bookmark with name: ' .$name);
	die();
}

if(error_get_last() !== null){
	trigger_error('some error occure. process is stopped.');
	die();
}

if(mysqli_errno($con) !== 0){
	trigger_error(mysqli_errno($con));
	die();
}

echo '<script>alert("Bookmarks updated.");
	window.location.href = "../bookmarks";</script>';

?>
