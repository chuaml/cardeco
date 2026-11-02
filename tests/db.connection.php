<?php

declare(strict_types=1);

namespace test;

use Exception;
use mysqli;

try {
    $DB_ADDRESS = getenv('MYSQL_HOST');
    $DB_USERNAME = getenv('MYSQL_USER');
    $DB_PASSWORD = getenv('MYSQL_PASSWORD');
    $DB_NAME = getenv('MYSQL_DB');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    /** @var mysqli */
    $con = mysqli_connect($DB_ADDRESS, $DB_USERNAME, $DB_PASSWORD, $DB_NAME);
    if ($con !== false) {
        $error_code = mysqli_connect_errno();
        if ($error_code !== 0) {
            throw new Exception("mysqli_connect_errno: $error_code");
        } else {
            return $con;
        }
    } else {
        throw new Exception(mysqli_connect_error());
    }
} finally {
    //
}
