<?php

require 'vendor/autoload.php';


// define routes
// $routes = require 'routes.php';

// get requested URI
// $requestUri = $_SERVER['REQUEST_URI'];

// // check if the requested URI exists in the routes array
// if (array_key_exists($requestUri, $routes) === true) {
//     // if it does, require the corresponding PHP file

//     require $routes[$requestUri];
// } else {
//     // if it doesn't, show a 404 error page
//     header('HTTP/1.0 404 Not Found');
//     echo '404 Not Found';
// }

use Exception\HttpException;

try {

    if ($_SERVER['REQUEST_URI'] === '/') {
        return require 'public/request_handler/lazada.php';
    }

    $_requestUri = 'public/request_handler' . $_SERVER['REQUEST_URI'];
    if (is_dir($_requestUri) === true) {
        $_requestUri .= 'index.php';
    } else {
        $_requestUri .= '.php';
    }

    if (file_exists($_requestUri) === true) {
        require $_requestUri;
    } else {
        throw new HttpException(404, 'page not found!!!!');
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

    // error 
    var_dump($ex);
}

var_dump($_SERVER);
