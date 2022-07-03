<?php 
// if(true || $_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR']){
// 	error_reporting(0);	
// }
// mb_internal_encoding('UTF-8');
// mb_http_output('UTF-8');

define('DB_ADDRESS','localhost');
define('DB_USERNAME','root');
define('DB_PASSWORD','');
define('DB_NAME','cardeco');

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
