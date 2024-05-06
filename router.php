<?php
require 'vendor/autoload.php';

use Exception\HttpException;

$_exception = null;
try {
    require(__DIR__ . '/db/conn_staff.php');

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // default route
    if ($path === '/') {
        require 'request_handler/lazada.php';
        return;
    }

    // other route
    $_requestUri = 'request_handler' . $path;
    if (is_dir($_requestUri) === true) {
        $_requestUri .= 'index.php';
    } else {
        $_requestUri .= '.php';
    }

    if (file_exists($_requestUri) === true) {
        require $_requestUri;
    } else {
        throw new HttpException(404, 'page not found: ' . $_requestUri);
    }

    $error_get_last = error_get_last();
    if ($error_get_last !== null) {
        http_response_code(500);
        throw new Exception('Some unknown notice/warning/error has occoured.');
    }
} catch (HttpException $ex) {
    $_exception = $ex;
    $statusCode = $ex->getStatusCode();
    if ($statusCode === 404) {
        header("HTTP/1.1 404 Not Found");
        include 'view/404.php';
    } else if ($statusCode === 500) {
        header("HTTP/1.1 500 Internal Server Error");
        include 'view/500.php';
    } else {
        header("HTTP/1.1 500 Internal Server Error");
        include 'view/500.php';
    }
} catch (Throwable $ex) {
    header("HTTP/1.1 500 Internal Server Error");
    $_exception = $ex;
    include 'view/500.php';
}
