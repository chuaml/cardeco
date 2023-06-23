<body BGCOLOR="BLACK">
<font color="white" size="24">
<div style="width: 60vw; margin: auto; padding-top: 50px;">

<?php 
function HANDLE_ALL_ERROR(string $errno, string $errstr){
    echo "<br>$errstr<br>gg Some errors have occured. ErrorNo: $errno <br>";
}

set_error_handler('HANDLE_ALL_ERROR');

$x = 5;
$y = 0;

$ans = $x * $y;
try{
    $ans = 1 / 0;
    if(error_get_last() !== false){
        throw new Exception('gg');
    }
} catch(Exception $e){
    echo $e->getMessage();
}
var_dump(E_USER_WARNING);

echo '$ans = ' .$ans;

trigger_error('WAT');
?>

