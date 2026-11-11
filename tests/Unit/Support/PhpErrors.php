<?php

declare(strict_types=1);

namespace test\Unit\Support;

use ErrorException;
use Throwable;

/**
 * Runs a callable and returns whatever it throws - with PHP notices/warnings promoted to ErrorException - so that
 * behaviour that is a warning on PHP 7.x but a TypeError / DivisionByZeroError on PHP 8.x can be characterised
 * without PHPUnit's deprecated expectWarning()/expectNotice().
 *
 * Not autoloaded on purpose (tests/bootstrap.php only autoloads tests/Support); test files `require_once` it.
 */
final class PhpErrors
{
    /**
     * @return Throwable|null null when $fn completed without throwing and without raising a notice/warning
     */
    public static function capture(callable $fn): ?Throwable
    {
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
                return true; // swallow: PHP 8.1+ null-to-string deprecations are not the behaviour under test
            }
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        try {
            $fn();

            return null;
        } catch (Throwable $e) {
            return $e;
        } finally {
            restore_error_handler();
        }
    }
}
