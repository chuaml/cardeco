<?php
require_once('../db/conn_staff.php');

function microtime_float(){
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}
$ts = microtime_float();



//getData
function selectProducts($con, string $v){
	$result=[];
	$criteria = '1=1';
	$w = trim($v);

	if(strlen($w) !== 0){
		//escape words
		$words = explode(' ',trim($v));
		$clean_words = [];
		$w = '';
		unset($v);
		foreach($words as $v){
			$w = trim($v);
			if(strlen($w) === 0){continue;}
			$clean_words[] = mysqli_real_escape_string($con,$w);
		}
		unset($v);
		//

		//create $criteria
		$arg = [];
		foreach($clean_words as $v){
			$arg[] = "(seller_sku LIKE '%$v%' OR name LIKE '%$v%')";
		}
		$criteria = implode(' AND ', $arg);
		//
	}

	$sql = "SELECT * FROM lzd_products WHERE $criteria LIMIT 50;";
	$stmt = mysqli_query($con,$sql);
	if($stmt){ 
		$num_rows = mysqli_affected_rows($con);
		if($num_rows === 0){return false;}
		while($row = mysqli_fetch_array($stmt)){
			$temp_row = [];
			foreach($row as $k => $col){
				if(is_numeric($k)){continue;}
				$temp_row[$k] = $col;
			}
			unset($col); unset($k);
			$result[] = $temp_row;
		}
	} else {
		die('Error: ' .mysqli_error($con));
	}
	return $result;
}
//


//Outputs
$words = '';
$resultmsg = '';
if(isset($_GET['submit'])){
	$words = isset($_GET['txtDescription']) ? $_GET['txtDescription'] : false;
	$words = trim($words);
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
</head>

<?php include('inc/html/nav.html');
//echo '<span>' .date('d/M/y l H:i:s',time()+28800).'</span>';
?>


<body BGCOLOR="">
<font color="black">



Cardeco: lzd Catalog
<form name="productsForm" method="GET" action="catalog" enctype="multipart/form-data">
 <label for="productsForm">Search Products<br></label>
 <input type="search" class="txtInput" name="txtDescription" placeholder="description / itemcode...">
 <input type="submit" name="submit">

</form>

<hr>

<?php
//display output
$result = selectProducts($con,$words);

$output = '';
if($result !== false){
	$output = '<div class="catalog_container">';
	foreach($result as $row){
	$row['color'] = strlen($row['color']) !== 0 ? $row['color'] : 'N/A';

	$output .= '<div class="catalog_item">'
	.'<img src="'	.$row['image1']	.'" alt="image"></image>'
	.'<small>id:</small> '				.$row['id']			.'<br>'
	.'<small>Lzd SKU:</small> '		.$row['lzd_sku']	.'<br>'
	.'<small>Seller SKU:</small> '		.$row['seller_sku']	.'<br>'
	.'<small>Item Name:</small> '		.$row['name']	.'<br>'
	.'<small>Family Color:</small> '	.$row['color']		.'<br>'
	.'<small>Price:</small> '			.$row['price']		.'<br>'
	.'<br /></div>';
	}
	$output .= '</div>';
} else {
	$output = 'No Results.';
}


echo $output;
	









$con = null; //CLose database connection


echo '<p>load time: ' 
	.(microtime_float() - $ts)
	.' seconds</p>';

?>






</font>
</body>
</html>