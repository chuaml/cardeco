<?php
require_once('../../db/conn_staff.php');
//header("Cache-Control: no-cache");

$errormsg = [];

if(!isset($_FILES['fileImage'])){
	$errormsg['FILES'] = 'upload file Error';
	var_dump($errormsg);
	die('empty');
}

if(!isset($_POST['item_id'])){
	die('empty');
}

if(!isset($_POST['fileDescription'])){
	die('Empty Desription');
}

//image
$item_id = intval($_POST['item_id']);
$UPLOADED_FILE = $_FILES['fileImage'];
$FILE_SIZE_LIMIT = 10000000;
$IMAGE_DIMENSION_LIMIT = 8640 * 4320;
$description = trim($_POST['fileDescription']);
 

$TARGET_DIR = '../upload/img/';
$FILE_NAME = basename($UPLOADED_FILE['name']);
$target_file = $TARGET_DIR . $FILE_NAME;

$FILE_TYPE = pathinfo($target_file,PATHINFO_EXTENSION);

$newfilename = $FILE_NAME;

//check if $_FILES upload has detected errors
if($UPLOADED_FILE['error'] === 4){
	//noFile uploaded
} else if($UPLOADED_FILE['error'] === 0){
	//uploadOK
} else {
	$errormsg['image'][] = 'image uploading error, $_FILES error code: ' 
					.$UPLOADED_FILE['error'];
}
//

//check uploaded File size
if($UPLOADED_FILE['size'] <= 1){
	$errormsg['image'][] = 'image file size is too small';
} else if($UPLOADED_FILE['size'] > $FILE_SIZE_LIMIT){
	$errormsg['image'][] = 'image file size is too big, file size limited at ' 
						.$FILE_SIZE_LIMIT
						.' bytes.';
}
//

//check file type is image or not.
switch($FILE_TYPE){
	case 'jpg': break;
	case 'jpeg': break;
	case 'png': break;
	case 'gif': break;
	case 'bmp': break;
	default: $errormsg['image'][] = 'invalid image file type, file is not an image';
}
//

//check image Dimension/Size
if(strlen($UPLOADED_FILE['tmp_name']) > 0){
	$imagesize = getimagesize($UPLOADED_FILE['tmp_name']);
	if($imagesize === false){
		$errormsg['image'][] = 'unknown image dimensions, file error or file type not an image.';
	} else {
		$imagepixel = intval($imagesize[0]) * intval($imagesize[1]);
		if($imagepixel > $IMAGE_DIMENSION_LIMIT){
			$errormsg['image'][] = 'image dimension is too big, image size limit at ' 
								.$IMAGE_DIMENSION_LIMIT
								.' pixels.';
		}
	}
} else {
	$errormsg['image'][] = 'file tmp_name is empty. thus, cannot determine image dimension.';
}
//

//check if same file name exists
if(file_exists($target_file) === true){
	$temp_target_file = $target_file;
	while(file_exists($temp_target_file) === true){
		$newfilename = time() .'_' .$FILE_NAME;
		
		$temp_target_file = $TARGET_DIR . $newfilename;
	}
	$target_file = $temp_target_file;
}
//

//Check Image Desription
if(strlen($description) > 255){
	$errormsg['description'][] = "Image's description is too long.";
}
//

if(sizeof($errormsg) !== 0){
	var_dump($errormsg);
	trigger_error('Image Upload error.');
	die('<b>Image uploading error. process is stop.<br>
	*Please try again by uploading proper image file.</b>');
}

//Insert New Image to db
$dir = 'upload/img/';
$sql = "INSERT INTO stock_images(directory, image, description, upload_name, file_type, item) 
VALUES (?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($con,$sql);
if(!$stmt){trigger_error(mysqli_error($con)); die();}
mysqli_stmt_bind_param($stmt, 'ssssss', 
						$dir,
						$newfilename,
						$description,
						$FILE_NAME,
						$FILE_TYPE,
						$item_id);

if(!mysqli_stmt_execute($stmt)){
	trigger_error(mysqli_error($con));
	die();
}
$last_image_id = mysqli_insert_id($con);
mysqli_stmt_close($stmt);

//change image name
$newfilename = $last_image_id .'_' .$FILE_NAME;
	$temp_target_file = $TARGET_DIR . $newfilename;
if(file_exists($temp_target_file)){
	$newfilename = $last_image_id .'_' .time() .'-'.$FILE_NAME;
}

$sql = "UPDATE stock_images SET image = ? WHERE id = ?;";
$stmt = mysqli_prepare($con,$sql);

mysqli_stmt_bind_param($stmt,'si',$newfilename,$last_image_id);

if(!mysqli_stmt_execute($stmt)){
	$errormsg['mysql'][] = mysqli_error($con);
	trigger_error(mysqli_error($con));
}
mysqli_stmt_close($stmt);


//attech image id to stock_items detail
$image_id = '';
$sql = "SELECT image FROM stock_items WHERE id = ?;";
$stmt = mysqli_prepare($con,$sql);
mysqli_stmt_bind_param($stmt,'i',$item_id);
if(!$stmt){
	$errormsg['mysql'][] = mysqli_error($con);
	trigger_error(mysqli_error($con));
	die();
} else {
	mysqli_stmt_execute($stmt);
	$dbresult = $stmt->get_result();
	while($row = $dbresult->fetch_assoc()){
		$image_id = $row['image'];
	}
}
mysqli_stmt_close($stmt);
if($image_id === null){
	$image_id = '';
}


if(strlen($image_id) === 0){
	$image_id = $last_image_id;
} else {
	$image_id .= ',' .$last_image_id;
}
$sql = "UPDATE stock_items SET image = ? WHERE id = ?;";
$stmt = mysqli_prepare($con,$sql);
mysqli_stmt_bind_param($stmt,'si',$image_id,$item_id);
if(!$stmt){
	$errormsg['mysql'][] = mysqli_error($con);
	trigger_error(mysqli_error($con));
	die();
} else {
	mysqli_stmt_execute($stmt);
}
mysqli_stmt_close($stmt);
//
//


//move uploaded image to img folder
$target_file = $TARGET_DIR . $newfilename;
if(mysqli_errno($con) === 0){
	if(!move_uploaded_file($UPLOADED_FILE['tmp_name'], $target_file)){
		$errormsg['mysql'][] = 'Image details has been recorded to db, but Fail to upload image file.';
	}
} else {
	$errormsg['mysql'][] = 'Fail to record Image details to db, image file is not uploaded.';
}
//

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
