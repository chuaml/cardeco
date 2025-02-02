<?php

namespace test;

use Exception;

try {
    $DB_ADDRESS = getenv('MYSQL_HOST');
    $DB_USERNAME = getenv('MYSQL_USER');
    $DB_PASSWORD = getenv('MYSQL_PASSWORD');
    $DB_NAME = getenv('MYSQL_DB');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    if ($con = mysqli_connect($DB_ADDRESS, $DB_USERNAME, $DB_PASSWORD, $DB_NAME)) { //connection to database, phpmyadmin.
        //check connection
        if (mysqli_connect_errno()) { //If(!$conn)
            throw new Exception(mysqli_connect_error());
        } else {
            return $con;
        }
    } else {
        trigger_error('Triggered_Error: invalid database info!');
        throw new Exception(mysqli_connect_error());
    }
} finally {
    //
}
