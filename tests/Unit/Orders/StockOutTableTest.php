<?php

declare(strict_types=1);

namespace test\Unit\Orders;

use DOMDocument;
use DOMXPath;
use Orders\StockOutTable;
use PHPUnit\Framework\TestCase;
use test\Support\HtmlTableReader;

/**
 * Characterization of Orders\StockOutTable (src/Legacy/Orders/StockOutTable.php): the "stock out by date" summary
 * table of /RecordUpdater. Rows have the shape of Orders\Factory\MonthlyRecord::getStockOutByDate():
 * ['sku' => ..., 'description' => ... (nullable, LEFT JOIN), 'quantity' => int].
 */
final class StockOutTableTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    const TWO_ROWS = [
        ['sku' => 'ZZTEST-001', 'description' => 'Zz Boot Tray', 'quantity' => 2],
        ['sku' => 'ZZTEST-002', 'description' => 'Zz Cover', 'quantity' => 3],
    ];

    // ---------------------------------------------------------------- structure

    public function testGetTable_twoRecords_rendersRenamedHeaderOneRowPerRecordAndTotalFooter(): void
    {
        $html = $this->render(self::TWO_ROWS);
        $table = new HtmlTableReader($html);

        $this->assertSame(['SKU', 'Description', 'Quantity'], $table->headers());
        $this->assertSame([
            ['ZZTEST-001', 'Zz Boot Tray', '2'],
            ['ZZTEST-002', 'Zz Cover', '3'],
        ], $table->rows());
        $this->assertSame(['', 'total', '5'], $this->footerCells($html));
    }

    public function testGetTable_headerCells_carryTheFieldNameAsId(): void
    {
        $xpath = $this->xpath($this->render(self::TWO_ROWS));

        $ids = [];
        foreach ($xpath->query('//thead//th') as $th) {
            $ids[] = $th->getAttribute('id');
        }

        $this->assertSame(['sku', 'description', 'quantity'], $ids);
    }

    public function testGetTable_tableAttributes_areIdRecordEditorAndBorder(): void
    {
        $html = $this->render(self::TWO_ROWS);

        $this->assertStringStartsWith('<table id="RecordEditor" border="1">', $html);
        $this->assertStringEndsWith('</table>', $html);
    }

    public function testGetTable_singleRecord_fullMarkupSample(): void
    {
        $html = $this->render([['sku' => 'ZZTEST-001', 'description' => 'Zz Boot Tray', 'quantity' => 2]]);

        $this->assertSame(
            '<table id="RecordEditor" border="1">'
            . '<thead><tr><th id="sku">SKU</th><th id="description">Description</th><th id="quantity">Quantity</th></tr></thead>'
            . '<tbody><tr><td>ZZTEST-001</td><td>Zz Boot Tray</td><td>2</td></tr></tbody>'
            . '<tfoot><tr><td></td><td>total</td><td>2</td></tr></tfoot>'
            . '</table>',
            $html
        );
    }

    public function testGetTable_noRecords_rendersEmptyBodyAndZeroTotal(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('<tbody></tbody>', $html);
        $this->assertSame(['', 'total', '0'], $this->footerCells($html));
    }

    public function testGetTable_withoutSetRecords_rendersEmptyBodyAndZeroTotal(): void
    {
        $html = (new StockOutTable())->getTable();

        $this->assertStringContainsString('<tbody></tbody>', $html);
        $this->assertSame(['', 'total', '0'], $this->footerCells($html));
    }

    public function testSetRecords_calledTwice_replacesThePreviousRecords(): void
    {
        $table = new StockOutTable();
        $table->setRecords(self::TWO_ROWS);
        $table->setRecords([['sku' => 'ZZTEST-009', 'description' => 'Zz Mat', 'quantity' => 1]]);

        $reader = new HtmlTableReader($table->getTable());

        $this->assertSame([['ZZTEST-009', 'Zz Mat', '1']], $reader->rows());
    }

    public function testGetTable_extraKeysInRows_areNotRendered(): void
    {
        $html = $this->render([['sku' => 'ZZTEST-001', 'description' => 'Zz', 'quantity' => 1, 'id' => 77]]);

        $this->assertSame([['ZZTEST-001', 'Zz', '1']], (new HtmlTableReader($html))->rows());
        $this->assertStringNotContainsString('77', $html);
    }

    // ---------------------------------------------------------------- footer total

    /** @return array<string, array<int, mixed>> */
    public function provideQuantities(): array
    {
        return [
            'ints' => [[2, 3, 4], '9'],
            'numeric strings' => [['2', '3'], '5'],
            'decimal string is truncated' => [['2.9', 1], '3'],
            'non numeric counts as zero' => [['n/a', 4], '4'],
        ];
    }

    /**
     * @dataProvider provideQuantities
     */
    public function testGetTable_footerTotal_isTheIntegerSumOfQuantities(array $quantities, string $expectedTotal): void
    {
        $rows = [];
        foreach ($quantities as $i => $q) {
            $rows[] = ['sku' => 'ZZTEST-00' . $i, 'description' => 'Zz', 'quantity' => $q];
        }

        $this->assertSame(['', 'total', $expectedTotal], $this->footerCells($this->render($rows)));
    }

    public function testGetTable_footerIsClosed(): void
    {
        // regression guard for the former TableDisplayer::getFoot() generator-return bug (fixed in 7541332)
        $html = $this->render(self::TWO_ROWS);

        $this->assertStringEndsWith('<td>total</td><td>5</td></tr></tfoot></table>', $html);
    }

    // ---------------------------------------------------------------- escaping

    public function testGetTable_specialCharacters_documentsCurrentBehaviour_areEscapedTwice(): void
    {
        // setRecords() escapes every value and the inherited TableDisplayer::formatCell() escapes again,
        // so the browser shows the entity text ("&amp;", "&lt;Mat&gt;") instead of the characters
        $html = $this->render([['sku' => 'ZZTEST-001', 'description' => 'Zz Tray & <Mat> "X"', 'quantity' => 1]]);

        $this->assertStringContainsString('<td>Zz Tray &amp;amp; &amp;lt;Mat&amp;gt; &amp;quot;X&amp;quot;</td>', $html);
        $this->assertSame('Zz Tray &amp; &lt;Mat&gt; &quot;X&quot;', (new HtmlTableReader($html))->rows()[0][1]);
    }

    /**
     * @group known-bug
     */
    public function testGetTable_specialCharacters_areEscapedExactlyOnce(): void
    {
        // StockOutTable.php setRecords() + TableDisplayer.php formatCell(): a description containing '&' or '<'
        // must be displayed verbatim (escaped once in the markup)
        $html = $this->render([['sku' => 'ZZTEST-001', 'description' => 'Zz Tray & <Mat>', 'quantity' => 1]]);

        $this->assertStringContainsString('<td>Zz Tray &amp; &lt;Mat&gt;</td>', $html);
        $this->assertSame('Zz Tray & <Mat>', (new HtmlTableReader($html))->rows()[0][1]);
    }

    public function testGetTable_nullDescriptionFromLeftJoin_rendersEmptyCell(): void
    {
        $html = $this->render([['sku' => 'ZZTEST-404', 'description' => null, 'quantity' => 1]]);

        $this->assertSame([['ZZTEST-404', '', '1']], (new HtmlTableReader($html))->rows());
    }

    public function testSetRecords_keyMissingInALaterRow_documentsCurrentBehaviour_valueCarriesOverFromThePreviousRow(): void
    {
        // StockOutTable.php setRecords(): $cleanRecord is declared outside the row loop and only overwritten per key
        $html = $this->render([
            ['sku' => 'ZZTEST-001', 'description' => 'Zz First', 'quantity' => 2],
            ['sku' => 'ZZTEST-002', 'quantity' => 1],
        ]);

        $this->assertSame([
            ['ZZTEST-001', 'Zz First', '2'],
            ['ZZTEST-002', 'Zz First', '1'],
        ], (new HtmlTableReader($html))->rows());
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function render(array $rows): string
    {
        $table = new StockOutTable();
        $table->setRecords($rows);

        return $table->getTable();
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    /** @return array<int, string> */
    private function footerCells(string $html): array
    {
        $cells = [];
        foreach ($this->xpath($html)->query('//tfoot//td') as $td) {
            $cells[] = $td->textContent;
        }

        return $cells;
    }
}
