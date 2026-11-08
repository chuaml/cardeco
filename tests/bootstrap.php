<?php

declare(strict_types=1);

/*
 * Shared PHPUnit bootstrap (see phpunit.xml.dist).
 *
 * - loads the Composer autoloader (src/ classmap + App\ PSR-4 + tests/ classmap)
 * - autoloads tests/Support/* without requiring `composer dumpautoload` after every new helper
 * - pins the working directory to the project root: the pre-existing tests use CWD-relative
 *   fixture paths such as 'tests/lazada/data.input/...'
 */

require __DIR__ . '/../vendor/autoload.php';

if (!defined('CARDECO_ROOT')) {
    define('CARDECO_ROOT', dirname(__DIR__));
}
chdir(CARDECO_ROOT);

spl_autoload_register(function (string $class): void {
    $prefix = 'test\\Support\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/Support/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
