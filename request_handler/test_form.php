
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title> sys</title>
	<link rel="stylesheet" href="css/style.css">

	<script src="js/jquery-3.3.1.min"></script>
	<script src="js/myscript"></script>
</head>

<?php 
$a = '2';
$a = explode(',',$a);
var_dump($a);
foreach($a as $v){
	echo $v;
}

$val = 2.01;
var_dump($val);

$val = ceil($val);
var_dump($val);




?>


<body>

<form name="test_form" method="GET" action="test_form" enctype="multipart/form-data">
 <label for="productsForm">Search Products<br></label>
 <input type="search" class="txtInput" name="txtDescription" placeholder="description / itemcode...">
 
 <label for="chkItem01"><img class="liststock"></label>
 <input type="checkbox" name="row[id][chkItem]" id="chkItem01" value="2323ee">
 
 <input type="checkbox" name="row[id][chkItem]" id="chkItem02" value="BBBA">
 <input type="submit" name="btnSubmit">

</form>

<hr>

<span style="position: relative;">
	<img style="position: absolute;" src="upload/img/13_exora front grill bold.jpg" />
	<img style="position: absolute;" src="upload/img/logo.png" />
</span>

<hr>

<script src="js/test_form.js"></script>


<?php 

if(!isset($_GET['btnSubmit'])){
	die();
}

var_dump($_GET['row']);

?>



</body>
</html>





