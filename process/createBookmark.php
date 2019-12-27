<?php
require_once('../../db/conn_staff.php');
require('../inc/class/Bookmark.php');
//header("Cache-Control: no-cache");

$errormsg = [];

if(!isset($_POST['btnSubmit'])){
	$errormsg[] = 'no event trigger.';
}

if(!isset($_POST['txtBookmark'])){
	$errormsg[] = 'no name';
} else {
	if(strlen(trim($_POST['txtBookmark'])) === 0){
		$errormsg[] = 'empty bookmark name is not allow';
	}
}

if(sizeof($errormsg) !== 0){
	foreach($errormsg as $msg){
		echo "$msg<br>";
	}
	die();
}

$name = trim($_POST['txtBookmark']);
$Bookmark = new Bookmark($con);

$action = $Bookmark->createNew_bookmark($name);

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

echo '<script>alert("New Bookmark Created.");
	window.location.href = "../bookmarks";</script>';

?>
