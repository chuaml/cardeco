<?php

namespace test;

use Exception;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    if ($con = mysqli_connect('localhost', 'root', '', 'cardeco_test_dummy')) {
        if (mysqli_connect_errno()) {
            throw new Exception(mysqli_connect_error());
        } else {
            return $con;
        }
    } else {
        throw new Exception(mysqli_connect_error());
    }
} finally {
    //
}
