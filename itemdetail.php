<?php
require_once('db/conn_staff.php');
require('inc/class/StockItem_detail.php');

$id = isset($_GET['id']) && strlen($_GET['id']) > 0 ? intval($_GET['id']) : false;
$description = '';
$item_code = '';
$image = '';

$su = new StockItem_detail($con);
$su->setQueryArg($id);
$su->execute_query();

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title>CarDeco sys</title>
	<link rel="stylesheet" href="css/style.css">
	<script src="js/jquery-3.3.1.min"></script>
	<script src="js/myscript"></script>
	<script src="js/function"></script>
</head>

<?php include('inc/html/nav.html');?>

<body>
<form name="form_updateItem_image" method="POST" action="process/updateStock_image">
<input type="hidden" value="<?php echo $id;?>" name="item_id">
<div class="paper">
	<?php 
		echo $su->getResult();
	?>
	Remove Selected Images: <input type="submit" value="Remove Images" />
</div>
</form>

<form name="form_uploadItem_image" method="POST" action="process/uploadStock_image" enctype="multipart/form-data">
 <input type="hidden" value="<?php echo $id;?>" name="item_id">

	<div class="paper">
	Upload new image for this product item.<br>
		<input type="file" name="fileImage" accept="image/*" onchange="readURL(this);" required> <br><br>
		<input type="submit" name="submit" /> <br>
		<span id="newimage_container" style="display: none;">
			<label for="fileDescription">Image Description: </label>
			<input type="text" id="fileDescription" name="fileDescription" placeholder="image description..." maxlength="255" /> <br /><br />
			<img src="" id="newimage" />
		</span>
	</div>

</form>

<?php 
mysqli_close($con);
?>


</body>
</html>