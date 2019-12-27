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
	<form method="POST" action="process/fee_statement">
		<?php echo $num_courier_records;?> 
		<input type="submit" name="btnSubmit[view_courier_record]" value="View All">
		<input type="submit" name="btnSubmit[clear_records][courier_record]" value="Clear Monthly Sales">
	</form>
</span>
<hr>
<form method="POST" action="process/fee_statement" enctype="multipart/form-data">
	<p>
	 Add new: <input type="file" name="file_MonthlySales" accept=".csv" required>
	</p>
	<input type="submit" name="btnSubmit[courier_record]">
</form>
</div>

<hr>

<h2>Lazada</h2>

<div class="paper">
Lazada Fee Statement: 
<span style="float: right;">
<form method="POST" action="process/fee_statement">
	<?php echo $num_lzd_fee_stmt;?> 
	<input type="submit" name="btnSubmit[clear_records][lzd_fee_statements]" value="Clear Lzd Fees">
</form>
</span>
<hr>
<form method="POST" action="LzdFeeChecker" enctype="multipart/form-data">
	<p>
	 Add new: <input type="file" name="file_LzdFeeStmt" accept=".csv" required>
	</p>
	 <input type="submit" name="btnSubmit[uploadLzdFeeStmt]">
</form>
</div>

<div class="paper">
	<form method="POST" action="LzdFeeChecker" >
	Check lazada fee statements: <input type="submit" name="btnSubmit[checkLzdFeeStmt]" value="Check Fees"> <br><br>
	</form>
<form method="POST" action="process/fee_statement">
 Match Monthly Record: <input type="submit" name="btnSubmit[match_fee_lzd]" value="Match Fees">
</form>
</div>

<hr>

<h2>Shopee</h2>

<div class="paper">
Shopee Fee Statement:
<form method="POST" action="process/fee_statement" enctype="multipart/form-data">
	Shopee Statements: <input type="file" name="file_Shopee_Stmt" accept=".csv" required>
 <input type="submit" name="btnSubmit[check_fee_shopee]" value="Check Payment">
</form>
</div>

</body>

</html>

