<?php
require_once('../db/conn_staff.php');
header("Cache-Control: no-cache");

$errormsg = [];

if(!isset($_POST['row'])){
	exit('<script>alert("No Images were removed.");
	window.location.href = "../liststock";</script>');
}

if(!isset($_POST['item_id'])){
	$errormsg[] = 'undefined item_id';
}

if(!isset($_POST['row'][0]['image'])){
	$errormsg[] = 'Error: undefined offset for images';
}

if(sizeof($errormsg) !== 0){
	var_dump($errormsg);
	die();
}

$delimiter = ',';
$images_i = isset($_POST['row'][0]['image']) ? $_POST['row'][0]['image'] : [] ;
$image_id = [];
$remove_i = [];
$old_item = [];
$new_item = [];
$item_id = mysqli_real_escape_string($con,intval(trim($_POST['item_id'])));

foreach($images_i as $img){
	$remove_i[$img] = intval($img);
}
unset($img);

//get image
$sql = "SELECT stock_items.id, (stock_images.id)AS image_id, GROUP_CONCAT(stock_images.item)AS item FROM stock_items 
INNER JOIN stock_images ON stock_items.id IN (stock_images.item) 
WHERE stock_items.id = $item_id 
GROUP BY stock_items.id, stock_images.id;";
$stmt = mysqli_query($con, $sql);
if(!$stmt){
	trigger_error(mysqli_error($con));
	die();
}

while($row = mysqli_fetch_assoc($stmt)){
	$image_id[] = $row['image_id'];
	$old_item[] = $row['item'];
}
unset($row);
mysqli_free_result($stmt);

$temp_old = [];
$temp_row = [];
$temp_item = [];
$len = sizeof($old_item);
for($i=0;$i<$len;++$i){ 
	if(!isset($remove_i[$i])){
		continue;
	}
	$temp_row = explode($delimiter, trim($old_item[$i]));
	$temp_item = [];
	foreach($temp_row as $item){
		if(trim($item) == trim($_POST['item_id'])){
			continue;
		}
		if(strlen(trim($item)) === 0){
			continue;
		}

		$temp_item[] = trim($item);
	}
	unset($item);

	$new_item[$i] = implode($delimiter, $temp_item);
}

//var_dump($new_item,$old_item);


//update image with new item id
$sql = "UPDATE stock_images SET item = ? WHERE id = ? ;";
$stmt = mysqli_prepare($con, $sql);
foreach($new_item as $k => $item){
	mysqli_stmt_bind_param($stmt, 'ss', $item, $image_id[$remove_i[$k]]);
	if(!mysqli_stmt_execute($stmt)){
		trigger_error("FAIL to UPDATE at: $k");
		trigger_error(mysqli_error($con));
		die();
	}
	
}
unset($item); unset($k);
mysqli_stmt_close($stmt);

//record for stock_item (optional) to avoid future slow left join query 
$wanted_images_id = [];
foreach($image_id as $img_id){
	if(isset($remove_i[$img_id])){
		continue;
	}
	
	$wanted_images_id[] = mysqli_real_escape_string($con, intval(trim($img_id)));
}
unset($img_id);

$wanted_images_id = implode(',', $wanted_images_id);

$sql = "UPDATE stock_items SET image = '$wanted_images_id' WHERE id = $item_id;";
$stmt = mysqli_query($con, $sql);
if(!$stmt){
	trigger_error(mysqli_error($con));
	die();
}
//

if(mysqli_errno($con) !== 0){
	trigger_error(mysqli_errno($con));
	die();
}

//alert successful update & redirect
if(sizeof($errormsg) === 0){
	echo '<script>alert("Update successful");
	window.location.href = "../liststock";</script>';
}



/*
//redirect for no javascript
if(isset($_SERVER['HTTP_REFERER'])){
	header('location: ' .$_SERVER['HTTP_REFERER']);
}
*/
?>
