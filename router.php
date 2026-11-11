<?php
require 'vendor/autoload.php';

// Fallback PSR-4 autoloader for App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use App\Routing\Route;
use App\Routing\RouteResolver;
use Exception\HttpException;

$_exception = null;
try {
    require(__DIR__ . '/db/conn_staff.php');

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $_view_html_path = ''; // *.html view to be loaded, if any

    // Tier resolution (see AGENTS.md section 3): Tier 1 App\Handler class > Tier 2 request_handler/ script
    // > Tier 3 legacy_pages/ page > 404. The rules live in App\Routing\RouteResolver so they are unit-tested.
    $_route = (new RouteResolver(__DIR__))->resolve($path);

    switch ($_route->tier) {
        case Route::TIER_HANDLER:
            $handlerClass = $_route->target;
            $handler = new $handlerClass($con);
            $response = $handler->handle($_REQUEST, $_FILES);
            $_view_html_path = $response->viewPath;
            extract($response->data);

            if ($_view_html_path !== '') {
                require 'view/_layout.main.html';
            }
            break;

        case Route::TIER_SCRIPT:
            require $_route->target;  // runs in this scope: sees $con, $_isProduction; sets $_view_html_path
            if ($_view_html_path !== '') {
                require 'view/_layout.main.html';
            }
            break;

        case Route::TIER_LEGACY:
            $_view_html_path = $_route->target;  // these legacy pages run and output in <body> part after <head>
            require 'view/_layout.main.html';
            break;

        default:
            throw new HttpException(404, 'page not found: ' . $_route->target);
    }

    $err = error_get_last();
    if ($err !== null) {
        if ($err['file'] !== 'xdebug://debug-eval') {
            http_response_code(500);
            try {
                // for triggerring IDE debugger, to view in IDE only
                throw new RuntimeException('type=' . $err['type'] . "\n" . $err['message'] . "\n" . $err['file'] . "\nline: " . $err['line'] . "\n\n");
            } catch (RuntimeException $err) {
                // log only, prevent showing error page for handled error to frontpage 
                error_log($err->getMessage());
            }
        }
    }
} catch (HttpException $ex) {
    $_exception = $ex;
    $statusCode = $ex->getStatusCode();
    http_response_code($statusCode);
    if ($statusCode === 400) {
        echo text($ex->getMessage());
        exit();
    } else if ($statusCode === 404) {
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
