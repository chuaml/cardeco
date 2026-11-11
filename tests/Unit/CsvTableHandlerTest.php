<?php

declare(strict_types=1);

namespace test\Unit;

use CSV_Table_Handler;
use PHPUnit\Framework\TestCase;
use test\Support\XlsxFixture;

/**
 * Characterization of CSV_Table_Handler (src/Legacy/CSV_Table_Handler.php): loads the CSV behind a
 * `$_FILES`-shaped array into memory and offers positional column extractors (base class of Shopee_Orders).
 */
final class CsvTableHandlerTest extends TestCase
{
    const CSV = "order,sku,qty\nA1,ZZTEST-001, 2 \nA2,,3\nA3,ZZTEST-003,\n";

    public function testSetTable_ReadsEveryRowIncludingHeader(): void
    {
        $handler = self::handler(self::CSV);

        $this->assertTrue($handler->setTable());
        $rows = array_map('array_values', $handler->getFields_Value([0, 1, 2], ['o', 's', 'q']));
        $this->assertSame(
            [['order', 'sku', 'qty'], ['A1', 'ZZTEST-001', ' 2 '], ['A2', '', '3'], ['A3', 'ZZTEST-003', '']],
            $rows
        );
    }

    public function testSetTable_SkipHeader_DropsTheFirstLine(): void
    {
        $handler = self::handler(self::CSV);

        $handler->setTable(true);

        $this->assertSame(['A1', 'A2', 'A3'], $handler->getField_Value(0));
    }

    public function testSetTable_CustomDelimiter(): void
    {
        $handler = self::handler("a;b,c\n1;2,3\n");

        $handler->setTable(false, ';');

        $this->assertSame([['x' => 'a', 'y' => 'b,c'], ['x' => '1', 'y' => '2,3']], $handler->getFields_Value([0, 1], ['x', 'y']));
    }

    public function testSetTable_CalledTwice_ReplacesTheTable(): void
    {
        $handler = self::handler(self::CSV);
        $handler->setTable(false);
        $this->assertCount(4, $handler->getField_Value(0));

        $handler->setTable(true);

        $this->assertCount(3, $handler->getField_Value(0));
    }

    public function testSetTable_MissingFile_RaisesFopenWarning(): void
    {
        // the `return false` at :13 is unreachable in practice: fopen() warns first
        $handler = new CSV_Table_Handler(['tmp_name' => '/no/such/dir/upload.csv']);

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessageMatches('/open stream/i');
        self::callWithErrorsAsExceptions(function () use ($handler) {
            $handler->setTable();
        });
    }

    public function testSetTable_LimitRow_documentsCurrentBehaviour_IsUsedAsMaxLineLength(): void
    {
        // CSV_Table_Handler.php:16-18 pass $LIMIT_ROW to fgetcsv() as the LENGTH argument, so a small limit
        // chops every physical line into $LIMIT_ROW-1 character pieces instead of limiting the row count
        $handler = self::handler("order,sku,qty\nA1,ZZTEST-001,2\nA2,ZZTEST-002,3\nA3,ZZTEST-003,4\n");

        $handler->setTable(false, ',', 25000, 8);

        $this->assertSame(['order', 'u', 'A1', 'T-001', 'A2', 'T-002', 'A3', 'T-003'], $handler->getField_Value(0));
    }

    /**
     * @group known-bug
     */
    public function testSetTable_LimitRow_LimitsTheNumberOfRowsRead(): void
    {
        // CSV_Table_Handler.php:16-18 - $LIMIT_LINE_LENGTH should be the fgetcsv() length, $LIMIT_ROW the row cap
        $handler = self::handler("order,sku,qty\nA1,ZZTEST-001,2\nA2,ZZTEST-002,3\nA3,ZZTEST-003,4\n");

        $handler->setTable(false, ',', 25000, 2);

        $this->assertSame([['o' => 'order', 'q' => 'qty'], ['o' => 'A1', 'q' => '2']], $handler->getFields_Value([0, 2], ['o', 'q']));
    }

    public function testGetFieldValue_TrimsEveryCell(): void
    {
        $handler = self::handler(self::CSV);
        $handler->setTable();

        $this->assertSame(['qty', '2', '3', ''], $handler->getField_Value(2));
    }

    public function testGetFieldValue_AutoFillEmpty_RepeatsPreviousNonEmptyValue(): void
    {
        $handler = self::handler(self::CSV);
        $handler->setTable(true);

        $this->assertSame(['ZZTEST-001', 'ZZTEST-001', 'ZZTEST-003'], $handler->getField_Value(1, true));
        $this->assertSame(['ZZTEST-001', '', 'ZZTEST-003'], $handler->getField_Value(1));
    }

    public function testGetFieldValue_AutoFillEmpty_LeadingEmptyCellsStayEmpty(): void
    {
        $handler = self::handler("a,\nb,\nc,X\n");
        $handler->setTable();

        $this->assertSame(['', '', 'X'], $handler->getField_Value(1, true));
    }

    public function testGetFieldValue_ColumnMissingInSomeRow_ThrowsUndefinedOffset(): void
    {
        $handler = self::handler("a,b\n1\n");
        $handler->setTable();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Undefined offset, possible invalid file.');
        $handler->getField_Value(1);
    }

    public function testGetFieldValue_BlankLine_ThrowsUndefinedOffsetEvenForColumnZero(): void
    {
        // fgetcsv() returns [null] for a blank line and isset(null) is false (:40)
        $handler = self::handler("a,b\n\n1,2\n");
        $handler->setTable();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Undefined offset, possible invalid file.');
        $handler->getField_Value(0);
    }

    public function testGetFieldsValue_RenamesColumnsAndSkipsBlankLines(): void
    {
        $handler = self::handler("order,sku,qty\n\nA1,ZZTEST-001,2\n , , \nA2,ZZTEST-002,3\n");
        $handler->setTable(true);

        $this->assertSame([
            ['id' => 'A1', 'quantity' => '2'],
            ['id' => 'A2', 'quantity' => '3'],
        ], $handler->getFields_Value([0, 2], ['id', 'quantity']));
    }

    public function testGetFieldsValue_DoesNotTrimCells(): void
    {
        $handler = self::handler(self::CSV);
        $handler->setTable(true);

        $this->assertSame(' 2 ', $handler->getFields_Value([2], ['q'])[0]['q']);
    }

    public function testGetFieldsValue_MismatchedNameCount_Throws(): void
    {
        $handler = self::handler(self::CSV);
        $handler->setTable();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('getFields_Value() each retrieved indexes must has a new index name.');
        $handler->getFields_Value([0, 1], ['only-one']);
    }

    public function testGetFieldsValue_MissingColumn_Throws(): void
    {
        $handler = self::handler(self::CSV);
        $handler->setTable();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Undefined offset, possible invalid file.');
        $handler->getFields_Value([0, 7], ['a', 'b']);
    }

    public function testGetFieldsValue_BeforeSetTable_ReturnsEmptyArray(): void
    {
        $this->assertSame([], self::handler(self::CSV)->getFields_Value([0], ['a']));
        $this->assertSame([], self::handler(self::CSV)->getField_Value(0));
    }

    public function testGetFileContent_ReturnsRawFileBytes(): void
    {
        $this->assertSame(self::CSV, self::handler(self::CSV)->getFile_Content());
    }

    private static function handler(string $csv): CSV_Table_Handler
    {
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, $csv);

        return new CSV_Table_Handler([
            'name' => 'orders.csv',
            'type' => 'text/csv',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($csv),
        ]);
    }

    /**
     * Runs $fn with a handler that turns PHP notices/warnings into \ErrorException, so a test can assert on
     * them without PHPUnit 9.6's deprecated expectWarning()/expectNotice().
     *
     * @return mixed
     */
    private static function callWithErrorsAsExceptions(callable $fn)
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (($severity & (E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE)) === 0) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }
}
