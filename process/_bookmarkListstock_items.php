<?php 
$errormsg = [];

if(!isset($_POST['bookmark_id'])){
	$errormsg[] = 'no bookmark id';
} else {
	$id = trim($_POST['bookmark_id']);
	if(strlen($id) === 0){
		$errormsg[] = 'no bookmark id';
	}
	
	if(!is_numeric($id)){
		$errormsg[] = 'invalid bookmark id value';
	}
}

if(!isset($_POST['row'])){
	$errormsg[] = 'no row.';
}

if(sizeof($errormsg) !== 0){
	die('Error: <br> ' .implode('<br>', $errormsg));
}

$id = intval(trim($_POST['bookmark_id']));
$row_list = $_POST['row']; 
$Bookmark = new Bookmark($con);

$action = $Bookmark->setBookmarks_items($id, $row_list);

if($action === false){
	trigger_error('Fail to add to bookmark');
	die();
}

if(error_get_last() !== null){
	trigger_error('some error has occure. process is stopped.');
	die();
}

if(mysqli_errno($con) !== 0){
	trigger_error(mysqli_errno($con));
	die();
}

echo '<script>alert("Bookmarks added.");
	window.location.href = "../liststock";</script>';

