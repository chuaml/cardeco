<?php

declare(strict_types=1);

namespace test\Unit\IO;

use IO\FileInputStream;
use PHPUnit\Framework\TestCase;
use test\Support\XlsxFixture;

/**
 * Characterization of IO\FileInputStream (src/Legacy/IO/FileInputStream.php).
 */
final class FileInputStreamTest extends TestCase
{
    public function testConstructor_MissingFile_ThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File does not exist: /no/such/file.txt.');
        new FileInputStream('/no/such/file.txt');
    }

    public function testRead_ReturnsOneByteAtATimeThenNullAtEof(): void
    {
        $stream = new FileInputStream($this->file('ab'));

        $this->assertSame('a', $stream->read());
        $this->assertSame('b', $stream->read());
        $this->assertNull($stream->read());
    }

    public function testReadSize_ReturnsRequestedBytesAndEmptyStringAtEof(): void
    {
        $stream = new FileInputStream($this->file('abcdef'));

        $this->assertSame('abc', $stream->readSize(3));
        $this->assertSame('def', $stream->readSize(10));
        $this->assertSame('', $stream->readSize(1));
    }

    public function testReadLine_documentsCurrentBehaviour_YieldsOnlyTheFirstLine(): void
    {
        // FileInputStream.php:40-46 does a single fgets() and yields once, unlike CSVInputStream::readLine()
        $stream = new FileInputStream($this->file("one\ntwo\nthree\n"));

        $this->assertSame(["one\n"], iterator_to_array($stream->readLine(), false));
    }

    /**
     * @group known-bug
     */
    public function testReadLine_YieldsEveryLineOfTheFile(): void
    {
        // FileInputStream.php:40-46: generator should loop until fgets() returns false
        $stream = new FileInputStream($this->file("one\ntwo\nthree\n"));

        $this->assertSame(["one\n", "two\n", "three\n"], iterator_to_array($stream->readLine(), false));
    }

    public function testReadLine_EmptyFile_YieldsNothing(): void
    {
        $stream = new FileInputStream($this->file(''));

        $this->assertSame([], iterator_to_array($stream->readLine(), false));
    }

    public function testReadLine_KeepsTheLineTerminator(): void
    {
        $stream = new FileInputStream($this->file("crlf\r\nnext"));

        $this->assertSame(["crlf\r\n"], iterator_to_array($stream->readLine(), false));
    }

    public function testSetBufferLength_LimitsALineToLengthMinusOneBytes(): void
    {
        $stream = new FileInputStream($this->file("abcdefgh\n"));
        $stream->setBufferLength(5);

        $this->assertSame(['abcd'], iterator_to_array($stream->readLine(), false));
    }

    public function testSetBufferLength_ZeroOrNegative_IsIgnored(): void
    {
        $stream = new FileInputStream($this->file("abcdefgh\n"));
        $stream->setBufferLength(0);
        $stream->setBufferLength(-1);

        $this->assertSame(["abcdefgh\n"], iterator_to_array($stream->readLine(), false));
    }

    public function testClose_ThenDestruct_DoesNotThrow(): void
    {
        $stream = new FileInputStream($this->file('x'));
        $stream->close();
        $stream->close();
        unset($stream);

        $this->addToAssertionCount(1);
    }

    private function file(string $content): string
    {
        $path = XlsxFixture::tempPath('txt');
        file_put_contents($path, $content);

        return $path;
    }
}
