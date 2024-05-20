<?php
// if(true || $_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR']){
// 	error_reporting(0);	
// }
// mb_internal_encoding('UTF-8');
// mb_http_output('UTF-8');

$_dbName = 'cardeco';
$_isProduction = false;

exec('cd 2>&1', $_output, $_result);

$_current_branch_name = exec('git branch --show-current 2>&1', $_output, $_result);
if ($_result !== 0) {
    $rootDir = $_SERVER['CONTEXT_DOCUMENT_ROOT'];
    $_cmd = 'git config --global --add safe.directory "' . $rootDir . '" 2>&1';
    exec($_cmd, $_output, $_result);

    throw new Error('"git branch"  command failed, auto added safe.directory  ' . $_cmd);
}

define('_CURRENT_BRANCH_NAME', $_current_branch_name);
if ($_result === 0 && _CURRENT_BRANCH_NAME === 'main') {
    // $_dbName .= '_' . _CURRENT_BRANCH_NAME;
    $_isProduction = true;
} else {
    $_dbName = 'cardeco_dev';
    $_isProduction = false;
}

define('IS_PRODUCTION', $_isProduction);

define('DB_ADDRESS', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', $_dbName);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if ($con = mysqli_connect(DB_ADDRESS, DB_USERNAME, DB_PASSWORD, DB_NAME)) { //connection to database, phpmyadmin.
    //check connection
    if (mysqli_connect_errno()) { //If(!$conn)
        throw new Exception(mysqli_connect_error());
    } else {
        //echo 'database connection succeed <br>';
    }
} else {
    trigger_error('Triggered_Error: invalid database info!');
    throw new Exception(mysqli_connect_error());
}
