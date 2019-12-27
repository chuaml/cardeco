<?php
$bk = new Bookmark($con);
$bk_id = isset($_POST['id']) ? intval(trim($_POST['id'])) : 0;
$row_list = $_POST['row'];
$action = $bk->removeBookmarks_items($bk_id, $row_list, 'id');
$errormsg = [];

if($bk_id === 0){
	$errormsg[] = 'No bookmark id';
}
if($action === false){ 
	$errormsg[] = 'Fail to remove bookmark items';
}

if(sizeof($errormsg) !== 0){
	echo '<br>Error: <br>' .implode('<br>', $errormsg);
	die();
}


if(mysqli_errno($con) === 0){
	echo '<script>alert("items have been removed.");
	window.location.href = "../bookmarks";</script>';
} 
