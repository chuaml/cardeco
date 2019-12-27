<?php 
require('../db/conn_staff.php');

$USER_IP = mysqli_real_escape_string($con, $_SERVER['REMOTE_ADDR']);
$num_courier_records = 'items loaded: ';
$num_lzd_fee_stmt = 'items loaded: ';

$sql = "SELECT COUNT(id)AS count_id FROM courier_record 
WHERE user_ip = '$USER_IP';";
$stmt = mysqli_query($con, $sql);
if($stmt){
	$row = mysqli_fetch_assoc($stmt);
	$num_courier_records .= $row['count_id'];
}

$sql = "SELECT COUNT(id)AS count_id FROM lzd_fee_statements 
WHERE user_ip = '$USER_IP';";
$stmt = mysqli_query($con, $sql);
if($stmt){
	$row = mysqli_fetch_assoc($stmt);
	$num_lzd_fee_stmt .= $row['count_id'];
}
mysqli_close($con);

?>

<!DOCTYPE html>
<html>
<title>CarDeco sys</title>
<head>
	<link rel="stylesheet" href="css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script src="js/jquery-3.3.1.min"></script>
	<script src="js/myscript"></script>
</head>
<?php include('inc/html/nav.html');?>

<body>


<div class="paper">
Monthly Sales Record: 
<span style="float: right;">
	<form method="POST" action="process/lzd_fee_statement">
		<?php echo $num_courier_records;?> 
		<input type="submit" name="btnSubmit[clear_records][courier_record]" value="Clear Monthly Sales">
	</form>
</span>
<hr>
<form method="POST" action="process/lzd_fee_statement" enctype="multipart/form-data">
	<p>
	 Add new: <input type="file" name="file_MonthlySales" accept=".csv" required>
	</p>
	<input type="submit" name="btnSubmit[courier_record]">
</form>
</div>

<div class="paper">
Lazada Fee Statement: 
<span style="float: right;">
<form method="POST" action="process/lzd_fee_statement">
	<?php echo $num_lzd_fee_stmt;?> 
	<input type="submit" name="btnSubmit[clear_records][lzd_fee_statements]" value="Clear Lzd Fees">
</form>
</span>
<hr>
<form method="POST" action="process/lzd_fee_statement" enctype="multipart/form-data">
	<p>
	 Add new: <input type="file" name="file_LzdFeeStmt" accept=".csv" required>
	</p>
	 <input type="submit" name="btnSubmit[lzd_fee_stmt]">
</form>
</div>

<div class="paper">
<form method="POST" action="process/lzd_fee_statement">
 Check lazada fee statements: <input type="submit" name="btnSubmit[check_fee]" value="Check Fees"> <br><br>
 Match Monthly Record: <input type="submit" name="btnSubmit[match_fee]" value="Match Fees">
</form>
</div>

</body>

</html>

