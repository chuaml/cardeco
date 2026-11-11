<?php

declare(strict_types=1);

namespace test\Integration;

use Database_Stock_Updater;
use Sql_Accounting_Export;
use test\Support\DatabaseTestCase;
use test\Support\XlsxFixture;

/**
 * Characterization of the /updateStock import (process/updateStock.php):
 *   Sql_Accounting_Export   (src/Legacy/Sql_Accounting_Export.php)   - parses the SQL Accounting tab-delimited export
 *   Database_Stock_Updater  (src/Legacy/Database_Stock_Updater.php)  - diffs it against stock_items and writes stock
 *
 * Database_Stock_Updater drives autocommit()/commit() itself, so this class runs WITHOUT the rollback wrapper and
 * deletes its ZZTEST- rows explicitly in tearDown().
 *
 * Page layout understood by the parser (Sql_Accounting_Export.php:17-30,138-173):
 *   header row   17 columns: 0 'Item Code', 3 'Description', 10 'UOM', 12 'Book Qty', 13 'Physical Qty', 16 'Remarks'
 *   group row    1 column (item group name)
 *   item rows    12 columns: 0 item_code, 3 description, 10 uom, 11 quantity
 *   'Sub-Total for' rows are skipped; the LAST TWO lines of the file are never read (ENDLINE = count - 3)
 *   a 'Total Items  :' line must exist somewhere near the end or nothing is parsed
 */
final class DatabaseStockUpdaterTest extends DatabaseTestCase
{
    /** @var bool */
    protected $useTransaction = false;

    /** @var string */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    protected function tearDown(): void
    {
        if ($this->con !== null) {
            $this->con->autocommit(true); // in case the class under test bailed out between autocommit(false) and commit()
            $this->con->query("DELETE FROM bigseller_sku_map WHERE item_id IN (SELECT id FROM stock_items WHERE item_code LIKE 'ZZTEST-%')");
            $this->con->query("DELETE FROM stock WHERE id IN (SELECT id FROM stock_items WHERE item_code LIKE 'ZZTEST-%')");
            $this->con->query("DELETE FROM stock_items WHERE item_code LIKE 'ZZTEST-%'");
        }
        parent::tearDown();
    }

    private function code(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    // ------------------------------------------------------------------ fixture builders

    private static function headerLine(): string
    {
        $cols = array_fill(0, 17, '');
        $cols[0] = 'Item Code';
        $cols[3] = 'Description';
        $cols[10] = 'UOM';
        $cols[12] = 'Book Qty';
        $cols[13] = 'Physical Qty';
        $cols[16] = 'Remarks';

        return implode("\t", $cols);
    }

    private static function itemLine(string $code, string $description, string $uom, string $quantity): string
    {
        $cols = array_fill(0, 12, '');
        $cols[0] = $code;
        $cols[3] = $description;
        $cols[10] = $uom;
        $cols[11] = $quantity;

        return implode("\t", $cols);
    }

    /**
     * @param array<int, string> $bodyLines group / item / sub-total lines of the single page
     */
    private static function exportText(array $bodyLines): string
    {
        $lines = array_merge([self::headerLine()], $bodyLines, ["Total Items  :\t" . count($bodyLines), 'Page 1 of 1']);

        return implode("\r\n", $lines) . "\r\n";
    }

    /** @return array<string, mixed> $_FILES-shaped entry for a .txt upload */
    private static function uploadedTxt(string $content, string $name = 'stock.txt'): array
    {
        $path = XlsxFixture::tempPath('txt');
        file_put_contents($path, $content);

        return ['name' => $name, 'type' => 'text/plain', 'tmp_name' => $path, 'error' => 0, 'size' => filesize($path)];
    }

    /** Runs setItemList() on a fresh parser and returns the parsed list. */
    private function parse(string $content): array
    {
        $export = new Sql_Accounting_Export();
        $updater = new Database_Stock_Updater($this->con);
        $updater->setItemList($export, self::uploadedTxt($content));

        return $export->getItemList();
    }

    /** @return array<string, mixed>|null stock_items ⋈ stock row by item code */
    private function stockRow(string $code): ?array
    {
        $rows = $this->fetchAll(
            'SELECT si.item_code, si.description, si.uom, si.item_group, s.quantity FROM stock_items si LEFT JOIN stock s ON s.id = si.id WHERE si.item_code = ?',
            's',
            $code
        );

        return count($rows) === 0 ? null : $rows[0];
    }

    // ------------------------------------------------------------------ parser (Sql_Accounting_Export)

    public function testSetItemList_ParsesItemRowsWithGroupAndIntegerQuantity(): void
    {
        $list = $this->parse(self::exportText([
            'ZZTEST GROUP',
            self::itemLine($this->code('A'), 'ALPHA', 'SET', '7'),
            self::itemLine($this->code('B'), 'BRAVO', 'PCS', '1,234'),
            "Sub-Total for\tZZTEST GROUP\t\t\t\t\t\t\t\t\t\t1,241",
        ]));

        $this->assertSame(
            [
                ['item_code' => $this->code('A'), 'description' => 'ALPHA', 'uom' => 'SET', 'quantity' => 7, 'item_group' => 'ZZTEST GROUP'],
                ['item_code' => $this->code('B'), 'description' => 'BRAVO', 'uom' => 'PCS', 'quantity' => 1234, 'item_group' => 'ZZTEST GROUP'],
            ],
            $list,
            'thousands separators are stripped, sub-total rows skipped'
        );
    }

    public function testSetItemList_WithoutTotalItemsTrailer_ParsesNothing(): void
    {
        $text = implode("\r\n", [self::headerLine(), 'ZZTEST GROUP', self::itemLine($this->code('A'), 'ALPHA', 'SET', '7'), 'x', 'y']);

        $this->assertSame([], $this->parse($text), 'matrix_ItemList() bails out when "Total Items  :" is missing (Sql_Accounting_Export.php:70)');
    }

    public function testSetItemList_LastTwoLinesAreNeverRead_documentsCurrentBehaviour(): void
    {
        // an item on the second-to-last line is lost: ENDLINE = count - 1 - 2 (Sql_Accounting_Export.php:142)
        $text = implode("\r\n", [self::headerLine(), 'ZZTEST GROUP', self::itemLine($this->code('A'), 'ALPHA', 'SET', '7'), "Total Items  :\t1"]);

        $this->assertSame([], $this->parse($text));
    }

    public function testSetItemList_SecondGroupOnAPage_TruncatesItemGroupToItsFirstCharacter_documentsCurrentBehaviour(): void
    {
        $list = $this->parse(self::exportText([
            'ZZTEST GROUP ONE',
            self::itemLine($this->code('A'), 'ALPHA', 'SET', '1'),
            'ZZTEST GROUP TWO',
            self::itemLine($this->code('B'), 'BRAVO', 'SET', '1'),
        ]));

        $this->assertSame(['ZZTEST GROUP ONE', 'Z'], array_column($list, 'item_group'));
    }

    /**
     * @group known-bug
     * Sql_Accounting_Export.php:155 stores later group rows as a STRING, so :168 `$current_group[0]` yields its first
     * character instead of the name used for the first group of the page. Intended: every item carries its full group.
     */
    public function testSetItemList_SecondGroupOnAPage_KeepsTheFullGroupName(): void
    {
        $list = $this->parse(self::exportText([
            'ZZTEST GROUP ONE',
            self::itemLine($this->code('A'), 'ALPHA', 'SET', '1'),
            'ZZTEST GROUP TWO',
            self::itemLine($this->code('B'), 'BRAVO', 'SET', '1'),
        ]));

        $this->assertSame(['ZZTEST GROUP ONE', 'ZZTEST GROUP TWO'], array_column($list, 'item_group'));
    }

    public function testValidateFile_RejectsWrongExtensionAndEmptyUploads(): void
    {
        $export = new Sql_Accounting_Export();

        $this->assertSame(
            ['valid' => false, 'errormsg' => 'File must be text .txt file.'],
            $export->validateFile(['name' => 'stock.csv', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 10], 'txt')
        );
        $this->assertSame(
            ['valid' => false, 'errormsg' => 'No File selected.'],
            $export->validateFile(['name' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0], 'txt')
        );
        $this->assertSame(
            ['valid' => false, 'errormsg' => 'errors: 1'],
            $export->validateFile(['name' => 'stock.txt', 'tmp_name' => '/tmp/x', 'error' => 1, 'size' => 10], 'txt')
        );
        $this->assertSame(
            ['valid' => true, 'errormsg' => null],
            $export->validateFile(['name' => 'stock.txt', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 10], 'txt')
        );
    }

    // ------------------------------------------------------------------ database writes

    public function testDoAllStuff_UpdatesQuantityOfExistingItems_InsertsNewOnes_LeavesOthersAlone(): void
    {
        $this->insertStockItem($this->code('UPD'), 'OLD DESCRIPTION', 5, 'SET', 'OLD GROUP');
        $this->insertStockItem($this->code('KEEP'), 'UNTOUCHED', 9, 'PCS', 'OLD GROUP');

        $export = new Sql_Accounting_Export();
        $updater = new Database_Stock_Updater($this->con);
        $updater->setItemList($export, self::uploadedTxt(self::exportText([
            'ZZTEST GROUP',
            self::itemLine($this->code('UPD'), 'NEW DESCRIPTION', 'UNIT', '7'),
            self::itemLine($this->code('NEW'), 'BRAND NEW', 'PCS', '1,234'),
        ])));
        $updater->doAllStuff();

        $this->assertSame(
            ['item_code' => $this->code('UPD'), 'description' => 'OLD DESCRIPTION', 'uom' => 'SET', 'item_group' => 'OLD GROUP', 'quantity' => 7],
            $this->stockRow($this->code('UPD')),
            'existing item: only stock.quantity is updated, description/uom/group are NOT refreshed'
        );
        $this->assertSame(
            ['item_code' => $this->code('NEW'), 'description' => 'BRAND NEW', 'uom' => 'PCS', 'item_group' => 'ZZTEST GROUP', 'quantity' => 1234],
            $this->stockRow($this->code('NEW')),
            'new item: stock_items + stock rows inserted (image column left at its default)'
        );
        $this->assertSame(
            ['item_code' => $this->code('KEEP'), 'description' => 'UNTOUCHED', 'uom' => 'PCS', 'item_group' => 'OLD GROUP', 'quantity' => 9],
            $this->stockRow($this->code('KEEP')),
            'item absent from the export is not zeroed'
        );
        $this->assertCount(1, $this->fetchAll('SELECT id FROM stock_items WHERE item_code = ?', 's', $this->code('UPD')), 'no duplicate stock_items row');
    }

    public function testDoAllStuff_RestoresAutocommitAndPersistsOutsideAnyTransaction(): void
    {
        $this->insertStockItem($this->code('UPD'), 'X', 5);

        $export = new Sql_Accounting_Export();
        $updater = new Database_Stock_Updater($this->con);
        $updater->setItemList($export, self::uploadedTxt(self::exportText(['ZZTEST GROUP', self::itemLine($this->code('UPD'), 'X', 'SET', '8')])));
        $updater->doAllStuff();

        $this->assertSame(1, (int) $this->fetchAll('SELECT @@autocommit AS a')[0]['a'], 'autocommit(true) restored after commit()');
        $this->con->rollback(); // nothing to roll back: the change was committed by the class
        $this->assertSame(8, $this->stockRow($this->code('UPD'))['quantity']);
    }

    public function testDoAllStuff_MatchesExistingItemCodesCaseInsensitively(): void
    {
        $this->insertStockItem($this->code('UPD'), 'X', 5);

        $export = new Sql_Accounting_Export();
        $updater = new Database_Stock_Updater($this->con);
        $updater->setItemList($export, self::uploadedTxt(self::exportText(['ZZTEST GROUP', self::itemLine(strtolower($this->code('UPD')), 'X', 'SET', '3')])));
        $updater->doAllStuff();

        $this->assertSame(3, $this->stockRow($this->code('UPD'))['quantity']);
        $this->assertCount(1, $this->fetchAll('SELECT id FROM stock_items WHERE item_code LIKE ?', 's', $this->code('UPD')), 'no lowercase duplicate created');
    }

    public function testDoAllStuff_EmptyParsedList_WritesNothing(): void
    {
        $this->insertStockItem($this->code('KEEP'), 'X', 5);

        $export = new Sql_Accounting_Export();
        $updater = new Database_Stock_Updater($this->con);
        $updater->setItemList($export, self::uploadedTxt("garbage\r\nno header\r\n"));
        $updater->doAllStuff();

        $this->assertSame(5, $this->stockRow($this->code('KEEP'))['quantity']);
        $this->assertSame([], $export->getItemList());
    }

    public function testSetItemList_InvalidUpload_ReturnsFalseAndLeavesListEmpty(): void
    {
        $export = new Sql_Accounting_Export();
        $updater = new Database_Stock_Updater($this->con);

        $result = @$updater->setItemList($export, self::uploadedTxt('irrelevant', 'stock.csv')); // trigger_error + false

        $this->assertFalse($result);
        $this->assertSame([], $export->getItemList());
    }
}
