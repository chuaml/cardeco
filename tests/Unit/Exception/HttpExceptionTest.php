<?php

declare(strict_types=1);

namespace test\Unit\Exception;

use Exception\HttpException;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of Exception\HttpException (src/Exception/HttpException.php), the exception
 * router.php maps to HTTP 400 / 404 / 500 responses.
 */
final class HttpExceptionTest extends TestCase
{
    public function testDefaults_Status500EmptyMessageCodeZeroNoPrevious(): void
    {
        $e = new HttpException();

        $this->assertSame(500, $e->getStatusCode());
        $this->assertSame('', $e->getMessage());
        $this->assertSame(0, $e->getCode());
        $this->assertNull($e->getPrevious());
    }

    public function testConstructor_StoresStatusMessageCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('db down');

        $e = new HttpException(404, 'Item not found', 7, $previous);

        $this->assertSame(404, $e->getStatusCode());
        $this->assertSame('Item not found', $e->getMessage());
        $this->assertSame(7, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testIsARuntimeExceptionSoGenericCatchBlocksStillSeeIt(): void
    {
        $e = new HttpException(400, 'bad request');

        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertInstanceOf(\Throwable::class, $e);
    }

    public function testStatusCode_IsNotCoerced_documentsCurrentBehaviour(): void
    {
        // the parameter is untyped (HttpException.php:12); router.php compares with === against ints
        $e = new HttpException('404');

        $this->assertSame('404', $e->getStatusCode());
    }

    public function testClassIsFinal(): void
    {
        $this->assertTrue((new \ReflectionClass(HttpException::class))->isFinal());
    }
}
