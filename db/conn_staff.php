<?php 
// if(true || $_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR']){
// 	error_reporting(0);	
// }
// mb_internal_encoding('UTF-8');
// mb_http_output('UTF-8');

$_dbName = 'cardeco';
$_isProduction = true;
define('_CURRENT_BRANCH_NAME', exec('git branch --show-current', $_output, $_result));
if($_result === 0 && _CURRENT_BRANCH_NAME !== 'main'){
    $_dbName .= '_' . _CURRENT_BRANCH_NAME;
    $_isProduction = false;
}

define('IS_PRODUCTION', $_isProduction);

define('DB_ADDRESS','localhost');
define('DB_USERNAME','root');
define('DB_PASSWORD','');
define('DB_NAME', $_dbName);

if ($con=mysqli_connect(DB_ADDRESS,DB_USERNAME,DB_PASSWORD,DB_NAME)) {//connection to database, phpmyadmin.
    //check connection
    if (mysqli_connect_errno()) { //If(!$conn)
    throw new Exception(mysqli_connect_error());
    }else{
    //echo 'database connection succeed <br>';
    }
} else {
    trigger_error('Triggered_Error: invalid database info!');
    throw new Exception(mysqli_connect_error());
}
