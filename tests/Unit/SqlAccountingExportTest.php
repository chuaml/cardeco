<?php

declare(strict_types=1);

namespace test\Unit;

use PHPUnit\Framework\TestCase;
use Sql_Accounting_Export;

/**
 * Characterization of Sql_Accounting_Export (src/Legacy/Sql_Accounting_Export.php): the parser for the
 * tab-delimited stock listing exported from SQL Accounting and uploaded on /updateStock
 * (process/updateStock.php -> Database_Stock_Updater::setItemList()).
 *
 * Pipeline under test: setItemList() -> matrix_ItemList() -> setPage_Index() -> gatherRow_Data() -> getItemList().
 *
 * Layout the parser expects (see tests/Unit/fixtures/sql_accounting.stock_export.sample.tsv):
 *   - a page HEADER row with exactly 17 tab columns: 'Item Code'@0, 'Description'@3, 'UOM'@10,
 *     'Book Qty'@12, 'Physical Qty'@13, 'Remarks'@16
 *   - the row after the header is the item GROUP row
 *   - then 31 rows per page: ITEM rows have exactly 12 columns (code@0, description@3, uom@10, qty@11),
 *     'Sub-Total for' rows are skipped, any other row is treated as a new group
 *   - a trailing 'Total Items  :<TAB>n' row (required) and the last two rows are never parsed as items
 */
final class SqlAccountingExportTest extends TestCase
{
    const FIXTURE = __DIR__ . '/fixtures/sql_accounting.stock_export.sample.tsv';

    // ---------------------------------------------------------------- validateFile() (non-die paths only)

    public function testValidateFile_EmptyName_ReportsNoFileSelected(): void
    {
        $result = (new Sql_Accounting_Export())->validateFile(self::upload(['name' => '']), 'txt');

        $this->assertSame(['valid' => false, 'errormsg' => 'No File selected.'], $result);
    }

    public function testValidateFile_ZeroSize_ReportsNoFileSelected(): void
    {
        $result = (new Sql_Accounting_Export())->validateFile(self::upload(['size' => 0]), 'txt');

        $this->assertSame(['valid' => false, 'errormsg' => 'No File selected.'], $result);
    }

    public function testValidateFile_UploadError_ReportsTheErrorCode(): void
    {
        $result = (new Sql_Accounting_Export())->validateFile(self::upload(['error' => UPLOAD_ERR_NO_FILE]), 'txt');

        $this->assertSame(['valid' => false, 'errormsg' => 'errors: 4'], $result);
    }

    public function testValidateFile_WrongExtension_ReportsExpectedType(): void
    {
        $result = (new Sql_Accounting_Export())->validateFile(self::upload(['name' => 'stock.csv']), 'txt');

        $this->assertSame(['valid' => false, 'errormsg' => 'File must be text .txt file.'], $result);
    }

    public function testValidateFile_ExtensionCheckIsCaseSensitive_documentsCurrentBehaviour(): void
    {
        // Sql_Accounting_Export.php:52-53 compares the raw extension, so Windows' STOCK.TXT is rejected
        $result = (new Sql_Accounting_Export())->validateFile(self::upload(['name' => 'STOCK.TXT']), 'txt');

        $this->assertFalse($result['valid']);
    }

    public function testValidateFile_MatchingExtension_IsValidWithNullMessage(): void
    {
        $result = (new Sql_Accounting_Export())->validateFile(self::upload(['name' => 'stock export.txt']), 'txt');

        $this->assertSame(['valid' => true, 'errormsg' => null], $result);
    }

    public function testValidateFile_WithoutFileType_SkipsExtensionCheck(): void
    {
        $result = (new Sql_Accounting_Export())->validateFile(self::upload(['name' => 'stock.csv']));

        $this->assertSame(['valid' => true, 'errormsg' => null], $result);
    }

    // ---------------------------------------------------------------- setItemList() / matrix_ItemList()

    public function testGetItemList_BeforeParsing_IsEmpty(): void
    {
        $this->assertSame([], (new Sql_Accounting_Export())->getItemList());
    }

    public function testSetItemList_SplitsOnNewlineAfterTrimmingWholeInput(): void
    {
        $export = new Sql_Accounting_Export();

        $export->setItemList("\n\nfirst \n second\n\n");

        $this->assertSame(['first ', ' second'], $export->getItemList());
    }

    public function testMatrixItemList_WithoutTotalItemsLine_ReturnsFalseAndLeavesLines(): void
    {
        // setTotal_Items() (:180-199) gates the whole parse on a trailing 'Total Items  :' row
        $export = new Sql_Accounting_Export();
        $export->setItemList(implode("\n", [self::headerRow(), 'GROUP', self::itemRow('ZZTEST-0001', 'D', 'PCS', '1'), 'Grand Total']));

        $this->assertFalse($export->matrix_ItemList());
        $this->assertSame([self::headerRow(), 'GROUP', self::itemRow('ZZTEST-0001', 'D', 'PCS', '1'), 'Grand Total'], $export->getItemList());
    }

    public function testMatrixItemList_SplitsLinesIntoTabColumnsAndDropsBlankLines(): void
    {
        $export = new Sql_Accounting_Export();
        $export->setItemList("a\tb\tc\n\n   \nZZTEST-0001\t\t\tDesc\n\nTotal Items  :\t1");

        $this->assertTrue($export->matrix_ItemList());
        $this->assertSame([
            ['a', 'b', 'c'],
            ['ZZTEST-0001', '', '', 'Desc'],
            ['Total Items  :', '1'],
        ], $export->getItemList());
    }

    public function testMatrixItemList_TrimsEachLine_SoTrailingEmptyColumnsDisappear(): void
    {
        // a trailing tab is trimmed away before explode(), changing the column count the page/item detection relies on
        $export = new Sql_Accounting_Export();
        $export->setItemList("ZZTEST-0001\tDesc\t\t\r\nTotal Items  :\t1\r\n");

        $export->matrix_ItemList();

        $this->assertSame([['ZZTEST-0001', 'Desc'], ['Total Items  :', '1']], $export->getItemList());
    }

    public function testMatrixItemList_ReadsTotalItemsFromLastMatchingRow(): void
    {
        $export = new Sql_Accounting_Export();
        $export->setItemList(file_get_contents(self::FIXTURE));

        $this->assertTrue($export->matrix_ItemList());
        $this->assertSame(31, self::readPrivate($export, 'total_items'));
    }

    // ---------------------------------------------------------------- setPage_Index()

    public function testSetPageIndex_FindsEveryPageHeaderRow(): void
    {
        $export = new Sql_Accounting_Export();
        $export->setItemList(file_get_contents(self::FIXTURE));
        $export->matrix_ItemList();

        $this->assertTrue($export->setPage_Index());
        // the two report-title lines precede the first header; the second page starts 33 rows later
        $this->assertSame([2, 35], self::readPrivate($export, 'page_index'));
    }

    public function testSetPageIndex_NoHeaderRow_ReturnsFalse(): void
    {
        $export = new Sql_Accounting_Export();
        $export->setItemList(implode("\n", ['GROUP', self::itemRow('ZZTEST-0001', 'D', 'PCS', '1'), 'x', "Total Items  :\t1"]));
        $export->matrix_ItemList();

        $this->assertFalse($export->setPage_Index());
        $this->assertSame([], self::readPrivate($export, 'page_index'));
    }

    public function testSetPageIndex_HeaderWithWrongLabel_IsNotAPage(): void
    {
        $header = explode("\t", self::headerRow());
        $header[12] = 'Qty';
        $export = new Sql_Accounting_Export();
        $export->setItemList(implode("\n", [implode("\t", $header), 'GROUP', self::itemRow('ZZTEST-0001', 'D', 'PCS', '1'), 'x', "Total Items  :\t1"]));
        $export->matrix_ItemList();

        $this->assertFalse($export->setPage_Index());
    }

    public function testSetPageIndex_HeaderLabelsAreComparedTrimmed(): void
    {
        $header = explode("\t", self::headerRow());
        $header[0] = ' Item Code ';
        $header[16] = 'Remarks ';
        $export = new Sql_Accounting_Export();
        $export->setItemList(implode("\n", [implode("\t", $header), 'GROUP', self::itemRow('ZZTEST-0001', 'D', 'PCS', '1'), 'x', "Total Items  :\t1"]));
        $export->matrix_ItemList();

        $this->assertTrue($export->setPage_Index());
        $this->assertSame([0], self::readPrivate($export, 'page_index'));
    }

    // ---------------------------------------------------------------- gatherRow_Data() / getItemList()

    public function testGatherRowData_Fixture_ProducesOneEntryPerItemRow(): void
    {
        $items = self::parse(file_get_contents(self::FIXTURE));

        $this->assertCount(31, $items);
        $this->assertSame([
            'item_code' => 'ZZTEST-0001',
            'description' => 'Test Seat Cover Black',
            'uom' => 'PCS',
            'quantity' => 1250,
            'item_group' => 'ACCESSORIES',
        ], $items[0]);
        $this->assertSame([
            'item_code' => 'ZZTEST-0031',
            'description' => 'Test Glass Cleaner',
            'uom' => 'BTL',
            'quantity' => 9,
            'item_group' => 'CAR CARE',
        ], $items[30]);
    }

    public function testGatherRowData_ItemCodesAreUniqueAndInFileOrder(): void
    {
        $codes = array_column(self::parse(file_get_contents(self::FIXTURE)), 'item_code');

        $expected = [];
        for ($i = 1; $i <= 31; ++$i) {
            $expected[] = sprintf('ZZTEST-%04d', $i);
        }
        $this->assertSame($expected, $codes);
    }

    public function testGatherRowData_SubTotalGrandTotalAndTotalItemsRowsAreNotItems(): void
    {
        $codes = array_column(self::parse(file_get_contents(self::FIXTURE)), 'item_code');

        $this->assertNotContains('Sub-Total for', $codes);
        $this->assertNotContains('Grand Total', $codes);
        $this->assertNotContains('Total Items  :', $codes);
        $this->assertNotContains('Item Code', $codes);
    }

    public function testGatherRowData_QuantityWithThousandsSeparator_BecomesInteger(): void
    {
        $items = self::parse(file_get_contents(self::FIXTURE));

        $this->assertSame(1250, $items[0]['quantity']);
        $this->assertSame(0, $items[2]['quantity']);
    }

    public function testGatherRowData_EveryEntryHasExactlyTheFiveKeys(): void
    {
        foreach (self::parse(file_get_contents(self::FIXTURE)) as $item) {
            $this->assertSame(['item_code', 'description', 'uom', 'quantity', 'item_group'], array_keys($item));
            $this->assertIsInt($item['quantity']);
        }
    }

    public function testGatherRowData_SecondPage_UsesItsOwnGroupRow(): void
    {
        $items = self::parse(file_get_contents(self::FIXTURE));

        $this->assertSame('CAR CARE', $items[29]['item_group']);
        $this->assertSame('CAR CARE', $items[30]['item_group']);
    }

    public function testGatherRowData_SecondGroupOnSamePage_documentsCurrentBehaviour_KeepsOnlyFirstCharacter(): void
    {
        // Sql_Accounting_Export.php:155 stores the group row as a STRING, so :168 `$current_group[0]` is its first char
        $items = self::parse(file_get_contents(self::FIXTURE));

        $this->assertSame('ZZTEST-0004', $items[3]['item_code']);
        $this->assertSame('C', $items[3]['item_group']);
        $this->assertSame('C', $items[4]['item_group']);
    }

    /**
     * @group known-bug
     */
    public function testGatherRowData_SecondGroupOnSamePage_UsesFullGroupName(): void
    {
        // Sql_Accounting_Export.php:155/168 - items under the 2nd+ group of a page should carry the full group name
        $items = self::parse(file_get_contents(self::FIXTURE));

        $this->assertSame('CAR CARE', $items[3]['item_group']);
    }

    public function testGatherRowData_NegativeQuantity_documentsCurrentBehaviour_SignIsDropped(): void
    {
        // Sql_Accounting_Export.php:161 strips every non-digit, including the minus sign
        $items = self::parse(file_get_contents(self::FIXTURE));

        $this->assertSame('ZZTEST-0004', $items[3]['item_code']);
        $this->assertSame(3, $items[3]['quantity']);
    }

    /**
     * @group known-bug
     */
    public function testGatherRowData_NegativeQuantity_IsKeptNegative(): void
    {
        // Sql_Accounting_Export.php:161 - an over-sold item (-3 in SQL Accounting) must not import as +3
        $items = self::parse(file_get_contents(self::FIXTURE));

        $this->assertSame(-3, $items[3]['quantity']);
    }

    public function testGatherRowData_WindowsLineEndings_AreHandled(): void
    {
        $items = self::parse(implode("\r\n", [
            self::headerRow(),
            'GROUP',
            self::itemRow('ZZTEST-0001', 'Desc one', 'PCS', '1'),
            self::itemRow('ZZTEST-0002', 'Desc two', 'SET', '2'),
            "Grand Total\t\t\t\t\t\t\t\t\t\t\t3",
            "Total Items  :\t2",
        ]) . "\r\n");

        $this->assertSame([
            ['item_code' => 'ZZTEST-0001', 'description' => 'Desc one', 'uom' => 'PCS', 'quantity' => 1, 'item_group' => 'GROUP'],
            ['item_code' => 'ZZTEST-0002', 'description' => 'Desc two', 'uom' => 'SET', 'quantity' => 2, 'item_group' => 'GROUP'],
        ], $items);
    }

    public function testGatherRowData_LastTwoRowsAreNeverParsed_documentsCurrentBehaviour(): void
    {
        // Sql_Accounting_Export.php:142 ($ENDLINE = count - 3): with only ONE footer row the last item is lost
        $items = self::parse(implode("\n", [
            self::headerRow(),
            'GROUP',
            self::itemRow('ZZTEST-0001', 'Desc one', 'PCS', '1'),
            self::itemRow('ZZTEST-0002', 'Desc two', 'SET', '2'),
            "Total Items  :\t2",
        ]));

        $this->assertSame(['ZZTEST-0001'], array_column($items, 'item_code'));
    }

    public function testGatherRowData_PageShorterThan31Rows_documentsCurrentBehaviour_NextPageItemsAreDuplicated(): void
    {
        // Sql_Accounting_Export.php:138-147 always scans 31 rows per header, so a short page runs into the next one
        $items = self::parse(implode("\n", [
            self::headerRow(),
            'GROUP A',
            self::itemRow('ZZTEST-0001', 'Desc one', 'PCS', '1'),
            self::headerRow(),
            'GROUP B',
            self::itemRow('ZZTEST-0002', 'Desc two', 'SET', '2'),
            "Grand Total\t\t\t\t\t\t\t\t\t\t\t3",
            "Total Items  :\t2",
        ]));

        $this->assertSame(['ZZTEST-0001', 'ZZTEST-0002', 'ZZTEST-0002'], array_column($items, 'item_code'));
        $this->assertSame(['GROUP A', 'G', 'GROUP B'], array_column($items, 'item_group'));
    }

    public function testGatherRowData_WithoutPageIndex_ProducesEmptyList(): void
    {
        $export = new Sql_Accounting_Export();
        $export->setItemList(file_get_contents(self::FIXTURE));
        $export->matrix_ItemList();

        $export->gatherRow_Data();

        $this->assertSame([], $export->getItemList());
    }

    public function testGatherRowData_RowWithOtherColumnCount_IsTreatedAsGroupNotItem(): void
    {
        // 11 or 13 columns -> group row; only exactly 12 columns is an item
        $eleven = implode("\t", array_merge(['ZZTEST-0009'], array_fill(0, 10, '')));
        $thirteen = implode("\t", array_merge(['ZZTEST-0008'], array_fill(0, 11, ''), ['x']));
        $items = self::parse(implode("\n", [
            self::headerRow(),
            'GROUP',
            $eleven,
            $thirteen,
            self::itemRow('ZZTEST-0001', 'Desc one', 'PCS', '1'),
            "Grand Total\t\t\t\t\t\t\t\t\t\t\t1",
            "Total Items  :\t1",
        ]));

        $this->assertSame(['ZZTEST-0001'], array_column($items, 'item_code'));
    }

    // ---------------------------------------------------------------- helpers

    /** full pipeline as run by Database_Stock_Updater::setItemList() */
    private static function parse(string $fileData): array
    {
        $export = new Sql_Accounting_Export();
        $export->setItemList(trim($fileData));
        $export->matrix_ItemList();
        $export->setPage_Index();
        $export->gatherRow_Data();

        return $export->getItemList();
    }

    private static function headerRow(): string
    {
        $cells = array_fill(0, 17, '');
        $cells[0] = 'Item Code';
        $cells[3] = 'Description';
        $cells[10] = 'UOM';
        $cells[12] = 'Book Qty';
        $cells[13] = 'Physical Qty';
        $cells[16] = 'Remarks';

        return implode("\t", $cells);
    }

    private static function itemRow(string $code, string $description, string $uom, string $quantity): string
    {
        $cells = array_fill(0, 12, '');
        $cells[0] = $code;
        $cells[3] = $description;
        $cells[10] = $uom;
        $cells[11] = $quantity;

        return implode("\t", $cells);
    }

    /** a complete `$_FILES` entry (validateFile() die()s when any key is missing) */
    private static function upload(array $overrides): array
    {
        return array_merge([
            'name' => 'stock.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/php-upload-not-read-by-validateFile',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ], $overrides);
    }

    /** @return mixed */
    private static function readPrivate(Sql_Accounting_Export $export, string $property)
    {
        $ref = new \ReflectionProperty($export, $property);
        $ref->setAccessible(true);

        return $ref->getValue($export);
    }
}
