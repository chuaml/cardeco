<?php 
//var_dump($_POST['row']);

$watermark = isset($_POST['chkWatermark']) ? true : false;
$items_per_page = isset($_POST['items_per_page']) ? intval($_POST['items_per_page']) : 1;
$row_list = $_POST['row'];
$cg = new catalog_generator;

$itemList = $cg->generateCatalog($con, $row_list);
$catalog = $cg->drawCatalog($items_per_page, $watermark);
 $catalog =$catalog !== null ? $catalog : 'No selected item for printing.';
 
 ?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title>Catalog <?php echo date('y-m-d',time()+28800) ?></title>
	<link rel="stylesheet" href="../css/catalog.css">
</head>

<body>
	<?php echo $catalog;?>
</body>
</html>
