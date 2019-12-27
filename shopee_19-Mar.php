<?php
require('../db/conn_staff.php');
// function microtime_float(){
// 	list($usec, $sec) = explode(" ", microtime());
// 	return ((float)$usec + (float)$sec);
// }
// $ts = microtime_float();

//read content of Submitted File

$msg = '';
$Data = [
	'outOfStock' => '',
	'itemList' => '',
	'notFound' => '',
	'orders' => ''
];
if(isset($_FILES['file_dataFile'])){
	try{
		try{
			if($_FILES['file_dataFile']['error'] !== 0){
				throw new Exception("file error: " .$_FILES['file_dataFile']['name']);
			}
			require('process/shopee.php');
			$outofstock = '<div class="paper">No Stock: ' .$Formatter->getOutofStock() .'</div>';
			$itemList = '<div class="paper" id="itemList">Checked Results: <br> 
			<button onclick="printPaper()" class="button">Print</button><hr>' 
			.$Formatter->getItemList() 
			.'</div>';
			$notfound = '<div class="paper" id="notfound">Not Found:<hr>' .$Formatter->getNotFound() .'</div>';

			$Data['outOfStock'] = $outofstock;
			$Data['itemList'] = $itemList;
			$Data['notFound'] = $notfound;
			$Data['orders'] = $Formatter->getConclusion($Conclusion->getOutput());
		}finally{
			$con->close();
		}
	}catch(Exception $e){
		$msg = $e->getMessage();
	}
}

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

<?php include('inc/html/nav.html');
echo '<span>' .date('d/M/y l H:i:s',time()+28800).'</span>';
?>

<h1>Shopee</h1>
<body>


<form name="itemlistForm" method="POST" action="shopee" enctype="multipart/form-data">
 <label for="itemlistForm">Select CSV file to submit<br></label>
 <input type="file" name="file_dataFile" accept=".csv" 
 oninput="this.form.submit();this.disabled=true" required>

</form>

<hr>

<?php 
echo $msg;
echo implode("\r\n", $Data);
?>

</body>
</html>