<?php 
 echo 'Hi<br>';

if(!$date = date_create('01-11-2018')){
	$date = date_create('01-11-2018');
}

$date = date_format($date,'y-m-d');
echo $date;

$v= 'IC2018060801';
var_dump($v);
$x = intval($v); echo $x;
echo strlen($x);
if(is_numeric($v)){
	echo 'Yes';
} else {
	echo 'NO';
}
error_reporting(0);
trigger_error('GG Team');

var_dump(error_get_last());

echo '<br>finish';

$itemDate = date_create('8/6/2018') ? True : False;
var_dump($itemDate);


$a = [];
$index = isset($_POST['txtInput']) ? $_POST['txtInput'] : false;
//$index = addslashes($index);
$a[$index] = 2;
$a[] = 2;
var_dump($a);

echo '<hr>';

$list = [1,2,3,4,5,6,7,8,9];
var_dump($list);
foreach($list as $k => $n){
	$list[$k] = 0;
}
var_dump($list);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
<form method="POST" action="test">
	<input type="text" name="txtInput">
	<input type="submit">
</form>
	<title>TEST</title>
	<link rel="stylesheet" href="css/style.css">
</head>

<body>

</body>

</html>