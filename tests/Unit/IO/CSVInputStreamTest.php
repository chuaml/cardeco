<?php

declare(strict_types=1);

namespace test\Unit\IO;

use IO\CSVInputStream;
use PHPUnit\Framework\TestCase;
use test\Support\XlsxFixture;

/**
 * Characterization of IO\CSVInputStream (src/Legacy/IO/CSVInputStream.php), the CSV reader behind
 * the Shopee/TikTok order parsers and the Lazada fee-statement upload.
 */
final class CSVInputStreamTest extends TestCase
{
    public function testConstructor_MissingFile_ThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File does not exist: /no/such/dir/missing.csv.');
        new CSVInputStream('/no/such/dir/missing.csv');
    }

    public function testReadLines_CommaDelimited_ReturnsAllRowsIncludingHeader(): void
    {
        $path = $this->csv("sku,qty\nZZTEST-001,2\nZZTEST-002,3\n");

        $this->assertSame(
            [['sku', 'qty'], ['ZZTEST-001', '2'], ['ZZTEST-002', '3']],
            (new CSVInputStream($path))->readLines()
        );
    }

    public function testReadLines_SemicolonDelimiter_SplitsOnSemicolonOnly(): void
    {
        $path = $this->csv("sku;name\nZZTEST-001;Mat, black\n");

        $this->assertSame(
            [['sku', 'name'], ['ZZTEST-001', 'Mat, black']],
            (new CSVInputStream($path, ';'))->readLines()
        );
    }

    public function testReadLines_QuotedFieldContainingDelimiter_IsKeptAsOneField(): void
    {
        $path = $this->csv("ZZTEST-001,\"Mat, black\",2\n");

        $this->assertSame([['ZZTEST-001', 'Mat, black', '2']], (new CSVInputStream($path))->readLines());
    }

    public function testReadLines_QuotedFieldContainingNewline_IsKeptAsOneRow(): void
    {
        $path = $this->csv("ZZTEST-001,\"line one\nline two\",2\n");

        $this->assertSame([['ZZTEST-001', "line one\nline two", '2']], (new CSVInputStream($path))->readLines());
    }

    public function testReadLines_DoubledQuoteInsideQuotedField_IsUnescaped(): void
    {
        $path = $this->csv("\"a \"\"b\"\" c\",x\n");

        $this->assertSame([['a "b" c', 'x']], (new CSVInputStream($path))->readLines());
    }

    public function testReadLines_FieldsAreNotTrimmed(): void
    {
        $path = $this->csv("577000000000000001\t, Sabah \n");

        $this->assertSame([["577000000000000001\t", ' Sabah ']], (new CSVInputStream($path))->readLines());
    }

    public function testReadLines_Utf8Bom_IsKeptInFirstHeaderCell(): void
    {
        // the TikTok export starts with a BOM; callers skip the header row so it never matters
        $path = $this->csv("\xEF\xBB\xBFOrder ID,Qty\n1,2\n");

        $rows = (new CSVInputStream($path))->readLines();

        $this->assertSame("\xEF\xBB\xBFOrder ID", $rows[0][0]);
        $this->assertSame(['1', '2'], $rows[1]);
    }

    public function testReadLines_BlankLine_YieldsRowWithSingleNullCell(): void
    {
        $path = $this->csv("a,b\n\nc,d\n");

        $this->assertSame([['a', 'b'], [null], ['c', 'd']], (new CSVInputStream($path))->readLines());
    }

    public function testReadLines_TrailingNewline_DoesNotProduceExtraRow(): void
    {
        $withNewline = (new CSVInputStream($this->csv("a,b\nc,d\n")))->readLines();
        $withoutNewline = (new CSVInputStream($this->csv("a,b\nc,d")))->readLines();

        $this->assertSame([['a', 'b'], ['c', 'd']], $withNewline);
        $this->assertSame($withNewline, $withoutNewline);
    }

    public function testReadLines_WindowsLineEndings_AreHandled(): void
    {
        $path = $this->csv("a,b\r\nc,d\r\n");

        $this->assertSame([['a', 'b'], ['c', 'd']], (new CSVInputStream($path))->readLines());
    }

    public function testReadLine_IsAGeneratorYieldingOneRowPerAdvance(): void
    {
        $stream = new CSVInputStream($this->csv("a,b\nc,d\n"));
        $generator = $stream->readLine();

        $this->assertInstanceOf(\Generator::class, $generator);
        $this->assertSame(['a', 'b'], $generator->current());
        $generator->next();
        $this->assertSame(['c', 'd'], $generator->current());
        $generator->next();
        $this->assertFalse($generator->valid());
    }

    public function testReadLine_ConsumesTheStream_SecondCallYieldsNothingMore(): void
    {
        $stream = new CSVInputStream($this->csv("a,b\n"));
        $stream->readLines();

        // at EOF fgetcsv returns false, which the first (unchecked) read yields as a row
        $this->assertSame([false], $stream->readLines());
    }

    public function testReadLines_EmptyFile_documentsCurrentBehaviour_ReturnsSingleFalseRow(): void
    {
        // CSVInputStream.php:37-40 tests for null, but fgetcsv() returns false at EOF
        $path = $this->csv('');

        $this->assertSame([false], (new CSVInputStream($path))->readLines());
    }

    /**
     * @group known-bug
     */
    public function testReadLines_EmptyFile_ThrowsFailToParseException(): void
    {
        // CSVInputStream.php:37-40: the `$r === null` guard never fires (fgetcsv returns false at EOF),
        // so an empty upload silently becomes a [false] row instead of 'Fail to parse CSV file'
        $path = $this->csv('');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Fail to parse CSV file');
        (new CSVInputStream($path))->readLines();
    }

    public function testClose_IsIdempotentAndDestructorDoesNotThrow(): void
    {
        $stream = new CSVInputStream($this->csv("a,b\n"));
        $stream->close();
        $stream->close();
        unset($stream);

        $this->addToAssertionCount(1); // reaching here means no exception was thrown
    }

    public function testSetMaxFieldLength_LargeEnoughLimit_DoesNotChangeParsing(): void
    {
        $stream = new CSVInputStream($this->csv("a,b\nc,d\n"));
        $stream->setMaxFieldLength(4096);

        $this->assertSame([['a', 'b'], ['c', 'd']], $stream->readLines());
    }

    private function csv(string $content): string
    {
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, $content);

        return $path;
    }
}
