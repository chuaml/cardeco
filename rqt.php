<?php 
require('../db/conn_staff.php');

//capture the POST data sent from client with jQuery ajax
$txtInput = $_POST['txtInput'] ?? '';
$txtInput = mysqli_real_escape_string($con, $txtInput);

//perform some action to database with the data
//select data
$list = [];
$sql = "SELECT description FROM stock_items WHERE description LIKE '%$txtInput%' LIMIT 50;";
$stmt = mysqli_query($con,$sql);
if($stmt){
	while($row = mysqli_fetch_assoc($stmt)){
		$list[] = $row['description'];
	}
}
mysqli_free_result($stmt);

//insert data
$sql= "INSERT INTO dd(cc) VALUES('$txtInput');";
$stmt = mysqli_query($con,$sql);
if($stmt){}

mysqli_close($con);

//return output to client request
echo implode('<br>', $list);

?>

