<?php
require 'vendor/autoload.php';

use Exception\HttpException;

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
} catch (HttpException $ex) {

    $statusCode = $ex->getStatusCode();
    if ($statusCode === 404) {
        header("HTTP/1.1 404 Not Found");
    } else if ($statusCode === 500) {
        header("HTTP/1.1 500 Internal Server Error");
    } else {
        header("HTTP/1.1 500 Internal Server Error");
    }
} catch (Throwable $ex) {
    header("HTTP/1.1 500 Internal Server Error");
}
