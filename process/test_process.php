
<?php
include('../test_form.html');
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="../css/style.css">
    <title>test_form</title>
</head>
<hr>
<?php 
if(!isset($_GET['btnSubmit'])){
    die();
}
$txt = $_GET['txtSearch'];

$txt = trim($txt);
$txt = addslashes($txt);
echo $txt;

$a = explode('|,', $txt);
var_dump($a);
?>