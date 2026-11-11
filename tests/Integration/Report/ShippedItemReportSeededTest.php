<?php

declare(strict_types=1);

namespace test\Integration\Report;

use DateTimeImmutable;
use Report\ShippedItemReport;
use test\Mock;
use test\Support\CsvFixture;
use test\Support\DatabaseTestCase;
use test\Support\HtmlTableReader;
use test\Support\XlsxFixture;

/**
 * Drives Report\ShippedItemReport (src/OrderProcess/ShippedItemReport.php) with a SYNTHETIC BigSeller
 * "all status orders" xlsx and ZZTEST- stock rows, with a fixed "now" so the full rows can be asserted.
 *
 * BigSeller columns read (Orders\DailyStockOutItem_Factory + ShippedItemReport::getMonthlyRecord):
 *   0 order number, 7 shipping status, 23 sku, 25 product name, 30 quantity, 51 tracking number
 */
final class ShippedItemReportSeededTest extends DatabaseTestCase
{
    const COLUMNS = 65;
    const DATE = '03/05/2024'; // 2024-03-05 formatted m/d/Y

    /** @var string */
    private $suffix;
    /** @var int */
    private $idA;
    /** @var int */
    private $idC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $this->idA = $this->insertStockItem($this->code('A'), 'DESC A FROM DB', 5);
        $this->insertStockItem($this->code('B'), 'DESC B FROM DB', 1);
        $this->idC = $this->insertStockItem($this->code('C'), 'DESC C FROM DB', 2);
        $this->insertBigSellerSkuMap($this->bsSku('C'), $this->idC); // C is only known to BigSeller by its mapped sku
    }

    private function code(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    private function bsSku(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-BS-' . $letter;
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: int}> $lines [order suffix, status, sku, quantity]
     * @return array<string, array<string, mixed>> $_FILES-shaped array
     */
    private function bigSellerFile(array $lines): array
    {
        $rows = [array_fill(0, self::COLUMNS, 'H')];
        foreach ($lines as $line) {
            $rows[] = CsvFixture::row(self::COLUMNS, [
                0 => 'ZZTEST-ORD-' . $line[0],
                7 => $line[1],
                23 => $line[2],
                25 => 'file name ' . $line[0],
                30 => (string) $line[3],
                51 => 'ZZTEST-TRK-' . $line[0],
            ]);
        }

        return Mock::newFile('bigseller_all_status_orders', XlsxFixture::write($rows, self::COLUMNS));
    }

    /** @return array<int, array{0: string, 1: string, 2: string, 3: int}> */
    private function defaultLines(): array
    {
        return [
            ['1', 'Shipped', $this->code('A'), 2],
            ['2', 'Shipped', $this->code('A'), 3],       // same sku -> quantities summed (5)
            ['3', 'Unpaid', $this->code('B'), 1],        // not shipped -> excluded everywhere
            ['4', 'Shipped', $this->bsSku('C'), 1],      // resolved through bigseller_sku_map
            ['5', 'Shipped', 'ZZTEST-UNKNOWN', 4],       // not in stock_items at all
        ];
    }

    private function report(array $lines): ShippedItemReport
    {
        return new ShippedItemReport($this->con, $this->bigSellerFile($lines), new DateTimeImmutable('2024-03-05'));
    }

    // ------------------------------------------------------------------ construction

    public function testConstructor_WithoutUpload_HasNoReportToGenerate(): void
    {
        $report = new ShippedItemReport($this->con, [], new DateTimeImmutable('2024-03-05'));

        $this->assertFalse($report->isToGenerateReport());
    }

    public function testConstructor_WithUpload_HasReportToGenerate(): void
    {
        $this->assertTrue($this->report($this->defaultLines())->isToGenerateReport());
    }

    public function testConstructor_UploadWithError_Throws(): void
    {
        $files = $this->bigSellerFile($this->defaultLines());
        $files['bigseller_all_status_orders']['error'] = UPLOAD_ERR_PARTIAL;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File has error.');

        new ShippedItemReport($this->con, $files, new DateTimeImmutable('2024-03-05'));
    }

    // ------------------------------------------------------------------ getShippedItemReport

    public function testGetShippedItemReport_GroupsShippedLinesBySkuAndResolvesCodesFromDb(): void
    {
        $html = $this->report($this->defaultLines())->getShippedItemReport()->toHtmlText();
        $table = new HtmlTableReader($html);

        $this->assertSame(['Date', 'Item Code', 'Product Name', 'Quantity'], $table->headers());
        $this->assertSame(
            [
                [self::DATE, $this->code('A'), 'DESC A FROM DB', '5'],
                [self::DATE, $this->code('C'), 'DESC C FROM DB', '1'],
                [self::DATE, 'ZZTEST-UNKNOWN', 'file name 5', '4'],
            ],
            $table->rows(),
            'first-seen order of the sku; B (unshipped) absent; unknown sku falls back to the file values'
        );
        $this->assertStringContainsString(
            '<tbody>'
            . '<tr is-empty-item-code="false"><td>' . self::DATE . '</td><td>' . $this->code('A') . '</td><td>DESC A FROM DB</td><td>5</td></tr>'
            . '<tr is-empty-item-code="false"><td>' . self::DATE . '</td><td>' . $this->code('C') . '</td><td>DESC C FROM DB</td><td>1</td></tr>'
            . '<tr is-empty-item-code="true"><td>' . self::DATE . '</td><td>ZZTEST-UNKNOWN</td><td>file name 5</td><td>4</td></tr>'
            . '</tbody>',
            $html,
            'full rows incl. the is-empty-item-code marker used by the view'
        );
    }

    public function testGetShippedItemReport_FooterHoldsTheGrandTotalOfShippedUnits(): void
    {
        $html = $this->report($this->defaultLines())->getShippedItemReport()->toHtmlText();

        $this->assertStringContainsString('<tfoot><td></td><td></td><td>Total:</td><td>10</td></tfoot>', $html, '5 + 1 + 4; footer cells are not wrapped in <tr>');
    }

    public function testGetShippedItemReport_NothingShipped_RendersHeaderAndZeroTotalOnly(): void
    {
        $html = $this->report([['1', 'Unpaid', $this->code('A'), 2], ['2', 'Cancelled', $this->code('A'), 1]])->getShippedItemReport()->toHtmlText();

        $this->assertSame([], (new HtmlTableReader($html))->rows());
        $this->assertStringNotContainsString('<tbody>', $html);
        $this->assertStringContainsString('<tfoot><td></td><td></td><td>Total:</td><td>0</td></tfoot>', $html);
    }

    public function testGetShippedItemReport_StatusMatchIsCaseSensitiveExactMatch_documentsCurrentBehaviour(): void
    {
        $html = $this->report([
            ['1', 'shipped', $this->code('A'), 2],
            ['2', 'Shipped ', $this->code('A'), 2],   // ExcelReader trims cell values -> counts
            ['3', 'Delivered', $this->code('A'), 2],
        ])->getShippedItemReport()->toHtmlText();

        $this->assertSame([[self::DATE, $this->code('A'), 'DESC A FROM DB', '2']], (new HtmlTableReader($html))->rows(), 'only the literal "Shipped" status counts (DailyStockOutItem.php:29)');
    }

    /**
     * fetchItemDetails() (ShippedItemReport.php:68-72) keys a mapped item by its bigseller_sku ONLY, so an item that
     * has a mapping can no longer be resolved by its own item_code.
     */
    public function testGetShippedItemReport_MappedItemOrderedByItemCode_IsNotResolved_documentsCurrentBehaviour(): void
    {
        $html = $this->report([['1', 'Shipped', $this->code('C'), 1]])->getShippedItemReport()->toHtmlText();

        $this->assertStringContainsString(
            '<tr is-empty-item-code="true"><td>' . self::DATE . '</td><td>' . $this->code('C') . '</td><td>file name 1</td><td>1</td></tr>',
            $html
        );
    }

    /**
     * @group known-bug
     * Intended: a stock item is resolved by its item_code whether or not it also has bigseller_sku_map rows
     * (ShippedItemReport.php:68-72 drops the item_code key as soon as a mapping exists).
     */
    public function testGetShippedItemReport_MappedItemOrderedByItemCode_IsResolvedFromDb(): void
    {
        $html = $this->report([['1', 'Shipped', $this->code('C'), 1]])->getShippedItemReport()->toHtmlText();

        $this->assertSame([[self::DATE, $this->code('C'), 'DESC C FROM DB', '1']], (new HtmlTableReader($html))->rows());
    }

    // ------------------------------------------------------------------ getMonthlyRecord

    public function testGetMonthlyRecord_ListsEveryShippedLineWithTrackingAndFixedDate(): void
    {
        $html = $this->report($this->defaultLines())->getMonthlyRecord()->toHtmlText();
        $table = new HtmlTableReader($html);

        $this->assertSame(['Order Status', 'Tracking Number', 'Date of Send Out (MM/dd/yyyy)', 'Order Number'], $table->headers());
        $this->assertSame(
            [
                ['Shipped', 'ZZTEST-TRK-1', self::DATE, 'ZZTEST-ORD-1'],
                ['Shipped', 'ZZTEST-TRK-2', self::DATE, 'ZZTEST-ORD-2'],
                ['Shipped', 'ZZTEST-TRK-4', self::DATE, 'ZZTEST-ORD-4'],
                ['Shipped', 'ZZTEST-TRK-5', self::DATE, 'ZZTEST-ORD-5'],
            ],
            $table->rows(),
            'one row per shipped LINE (no grouping), file order, unshipped line 3 excluded'
        );
        $this->assertStringContainsString('<th title="MM/dd/yyyy">Date of Send Out (MM/dd/yyyy)</th>', $html);
        $this->assertStringContainsString(
            '<tr data-order-number="ZZTEST-ORD-4"><td>Shipped</td><td>ZZTEST-TRK-4</td><td>' . self::DATE . '</td><td>ZZTEST-ORD-4</td></tr>',
            $html
        );
        $this->assertStringNotContainsString('<tfoot>', $html);
    }

    public function testGetMonthlyRecord_NothingShipped_HasHeaderButNoBody(): void
    {
        $html = $this->report([['1', 'Unpaid', $this->code('A'), 2]])->getMonthlyRecord()->toHtmlText();

        $this->assertSame(['Order Status', 'Tracking Number', 'Date of Send Out (MM/dd/yyyy)', 'Order Number'], (new HtmlTableReader($html))->headers());
        $this->assertStringNotContainsString('<tbody>', $html);
    }

    public function testBothReports_CanBeGeneratedFromTheSameInstance(): void
    {
        // each getter re-reads the workbook (ShippedItemReport.php:84,119) - the second call must not see an exhausted iterator
        $report = $this->report($this->defaultLines());

        $this->assertCount(4, (new HtmlTableReader($report->getMonthlyRecord()->toHtmlText()))->rows());
        $this->assertCount(3, (new HtmlTableReader($report->getShippedItemReport()->toHtmlText()))->rows());
        $this->assertCount(4, (new HtmlTableReader($report->getMonthlyRecord()->toHtmlText()))->rows());
    }
}
