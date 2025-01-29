<?php
require_once('../db/conn_staff.php');
require('../inc/class/catalog_generator.php');
//header("Cache-Control: no-cache");

$errormsg = [];

if(!isset($_POST['btnSubmit'])){
	$errormsg[] = 'no event trigger.';
}

if(!isset($_POST['row'])){
	$errormsg[] = 'no row.';
}

if(sizeof($errormsg) !== 0){
	foreach($errormsg as $msg){
		echo "$msg<br>";
	}
	die();
}

//var_dump($_POST['row']);

$watermark = isset($_POST['chkWatermark']) ? true : false;
$row_list = $_POST['row'];
$cg = new catalog_generator;

$itemList = $cg->generateCatalog($con, $row_list);
$catalog = $cg->drawCatalog($watermark);
 $catalog =$catalog !== null ? $catalog : 'No selected item for printing.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title>Catalog <?php echo date('y-m-d',time()+28800) ?></title>
	<link rel="stylesheet" href="../css/catalog.css">
	<script src="js/jquery-3.3.1.min"></script>
	<script src="js/myscript"></script>
	<script src="js/function.js"></script>
</head>

<body>
	<?php echo $catalog;?>
</body>
</html>
