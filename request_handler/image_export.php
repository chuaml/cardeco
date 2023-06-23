<?php


if(isset($_POST['btnSubmit'])){
	include('process/exportAll_StockImages.php');
}

?>

<!DOCTYPE html>
<html>
<title>CarDeco sys</title>
<head>
	<link rel="stylesheet" href="css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<script src="js/jquery-3.3.1.min"></script>
	<script src="js/table-sorter.min"></script>
	<script src="js/myscript.js"></script>
	<script src="js/function.js"></script>
</head>
<?php include('inc/html/nav.html');?>

<body>

Export All products images: <br>
<form method="POST" action="image_export.php">
	<input type="checkbox" id="chkConfirm" required />
	<label for="chkConfirm">Export All Products Images</label>
	<input type="submit" name="btnSubmit" value="Export" /> 
</form>



<?php 

mysqli_close($con);

?>


</body>
</html>