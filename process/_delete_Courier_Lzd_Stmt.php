<?php 
$a = $_POST['btnSubmit']['clear_records'];
$USER_IP = mysqli_real_escape_string($con, $_SERVER['REMOTE_ADDR']);

if(isset($a['courier_record'])){
	$sql = "DELETE FROM courier_record WHERE user_ip = '$USER_IP';";
	$stmt = mysqli_query($con, $sql);
	if(!$stmt){
		throw new Exception(mysqli_error($con));
	}
} else if(isset($a['lzd_fee_statements'])){
	$sql = "DELETE FROM lzd_fee_statements WHERE user_ip = '$USER_IP';";
	$stmt = mysqli_query($con, $sql);
	if(!$stmt){
		throw new Exception(mysqli_error($con));
	}
} 

if(error_get_last() === null && mysqli_errno($con) === 0){
	echo '<script>alert("Courier Record and Lzd fee statments deleted.");
	window.location.href = "../fee_statement";</script>';
}



