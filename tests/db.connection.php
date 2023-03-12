<?php

namespace test;

use Exception;

try {
    if ($con = mysqli_connect('localhost', 'root', '', 'cardeco_dev')) {
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
