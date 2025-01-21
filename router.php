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

    $err = error_get_last();
    if ($err !== null) {
        if ($err['file'] !== 'xdebug://debug-eval') {
            http_response_code(500);
            throw new RuntimeException('type=' . $err['type'] . "\n" . $err['message'] . "\n" . $err['file'] . "\nline: " . $err['line'] . "\n\n");
        }
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
    throw $ex;
} catch (Throwable $ex) {
    header("HTTP/1.1 500 Internal Server Error");
    $_exception = $ex;
    include 'view/500.php';
    throw $ex;
}
