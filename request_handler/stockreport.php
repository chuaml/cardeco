<?php


?>

<!DOCTYPE html>
<html>
<title>CarDeco sys</title>
<head>
	<?php require('view/template/head.php') ?>

	<script src="js/jquery-3.3.1.min"></script>
	<script src="js/myscript"></script>
	<script src="js/function.js"></script>
</head>
<?php include('inc/html/nav.html');?>

<body>

<form id="itemlistForm" method="POST" action="stockreport">
<label for="itemlistForm">Enter items details copied from Excel</label> <br>
<input type="submit" name="submit" />
<table style="width: 64px">
<tr>
	<th>China Code</th><th>China Name</th><th>Item Code</th>
</tr>
<tr>
 <td colspan="4"><textarea name="txtInput" rows="30" cols="64" maxlength="65536" required></textarea></td>
</tr>
</table>
</form>

<hr>

<?php 
function ctime(){
		list($usec, $sec) = explode(' ', microtime());
		return ((float)$usec + (float)$sec);
	}

if(isset($_POST['submit'])){
$txtInput = isset($_POST['txtInput']) ? trim($_POST['txtInput']) : '';
$sm = new StockReport($con);
if(strlen($txtInput) > 0){
	$sm->setQueryArg($txtInput);
	$sm->execute_query();
}


?>


<div class="paper">
Search Result: <br>
	<span id="orderAll_stockreport">
		<label for="txtQuantityAll">Set All Quantity: </label>
		<input type="number" id="txtQuantityAll" step="1" min="0" max="9999" value="5">
		<button onclick="orderAll_stockreport(txtQuantityAll.value)">Set All</button>
		<button onclick="orderAll_stockreport(0,true)">Reset All</button>
	</span>
	<?php echo $sm->getResult();?>
</div>


<div class="paper">
Items Not Found: <br>
	<?php echo $sm->getItems_notfound();?>
</div>

<?php 
}

mysqli_close($con);
?>


</body>
</html>