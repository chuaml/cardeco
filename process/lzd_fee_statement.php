<?php 
require('../../db/conn_staff.php');
header("Cache-Control: no-cache");

if(!isset($_POST['btnSubmit'])){
	die('No event trigger');
} else {
	if(!is_array($_POST['btnSubmit'])){
		die('No specified event');
	}
}

$action = '/';
foreach($_POST['btnSubmit'] as $event_type => $v){
	switch($event_type){
		case 'courier_record':
			$action = '_insertCourier_record.php';
			break;
		case 'lzd_fee_stmt':
			$action = '_insertLzd_fee_statements.php';
			break;
		case 'check_fee':
			$action = '_checkLzd_fee_statements.php';
			break;
		case 'clear_records':
			$action = '_delete_Courier_Lzd_Stmt.php';
			break;
		case 'match_fee':
			$action = '_matchLzd_fee_statemnts.php';
	}
	break;
}

?>


<!DOCTYPE html>
<html>
<title>CarDeco sys</title>
<head>
	<link rel="stylesheet" href="../css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1">

</head>
<body>
	<?php include($action);?>
</body>
</html>