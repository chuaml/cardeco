<?php

declare(strict_types=1);

namespace test\Unit\HTML;

use HTML\TableDisplayer;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of HTML\TableDisplayer (src/Legacy/HTML/TableDisplayer.php), the generic
 * `<table>` renderer behind every daily-order page, RecordEditor and StockOutTable.
 */
final class TableDisplayerTest extends TestCase
{
    public function testConstructor_ListOfRows_InfersHeaderFromFirstRowKeys(): void
    {
        $table = new TableDisplayer([
            ['sku' => 'ZZTEST-001', 'qty' => 2],
            ['sku' => 'ZZTEST-002', 'qty' => 5],
        ]);

        $this->assertSame(
            '<table>'
            . '<thead><tr><th id="sku">sku</th><th id="qty">qty</th></tr></thead>'
            . '<tbody><tr><td>ZZTEST-001</td><td>2</td></tr><tr><td>ZZTEST-002</td><td>5</td></tr></tbody>'
            . '</table>',
            $table->getTable()
        );
    }

    public function testConstructor_EmptyList_RendersEmptyHeadAndBody(): void
    {
        $table = new TableDisplayer();

        $this->assertSame('<table><thead><tr></tr></thead><tbody></tbody></table>', $table->getTable());
    }

    public function testConstructor_NonZeroIndexedRows_RendersEmptyHeaderAndEmptyRows(): void
    {
        // the header is only inferred from key 0; other rows still produce (empty) <tr>s
        $table = new TableDisplayer([5 => ['sku' => 'ZZTEST-001']]);

        $this->assertSame('<table><thead><tr></tr></thead><tbody><tr></tr></tbody></table>', $table->getTable());
    }

    public function testSetHead_WithId_EmitsThWithIdAttributeAndLabel(): void
    {
        $table = new TableDisplayer();
        $table->setHead(['sku' => 'SKU', 'qty' => 'Quantity'], true);

        $this->assertStringContainsString(
            '<thead><tr><th id="sku">SKU</th><th id="qty">Quantity</th></tr></thead>',
            $table->getTable()
        );
    }

    public function testSetHead_WithoutId_EmitsPlainTh(): void
    {
        $table = new TableDisplayer();
        $table->setHead(['sku' => 'SKU', 'qty' => 'Quantity'], false);

        $this->assertStringContainsString('<thead><tr><th>SKU</th><th>Quantity</th></tr></thead>', $table->getTable());
    }

    public function testSetHead_DefaultSetIdArgument_IsTrue(): void
    {
        $table = new TableDisplayer();
        $table->setHead(['sku' => 'SKU']);

        $this->assertStringContainsString('<th id="sku">SKU</th>', $table->getTable());
    }

    public function testGetBody_CellsFollowHeaderKeyOrder_NotRowKeyOrder(): void
    {
        $table = new TableDisplayer();
        $table->setHead(['b' => 'B', 'a' => 'A']);
        $table->setBody([['a' => 'first', 'b' => 'second']]);

        $this->assertStringContainsString('<tbody><tr><td>second</td><td>first</td></tr></tbody>', $table->getTable());
    }

    public function testGetBody_RowColumnsNotInHeader_AreNotRendered(): void
    {
        $table = new TableDisplayer();
        $table->setHead(['a' => 'A']);
        $table->setBody([['a' => 'shown', 'hidden' => 'never']]);

        $html = $table->getTable();

        $this->assertStringContainsString('<tr><td>shown</td></tr>', $html);
        $this->assertStringNotContainsString('never', $html);
    }

    public function testFormatCell_String_IsHtmlEscaped(): void
    {
        $table = new TableDisplayer([['v' => '<b>&"\'']]);

        $this->assertStringContainsString('<td>&lt;b&gt;&amp;&quot;&#039;</td>', $table->getTable());
    }

    /**
     * @dataProvider floatFormattingProvider
     */
    public function testFormatCell_Float_IsFormattedWithTwoDecimalsAndThousandsSeparator(float $value, string $expectedCell): void
    {
        $table = new TableDisplayer([['v' => $value]]);

        $this->assertStringContainsString('<td>' . $expectedCell . '</td>', $table->getTable());
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public function floatFormattingProvider(): array
    {
        return [
            'thousands and rounding' => [1234.5, '1,234.50'],
            'zero' => [0.0, '0.00'],
            'whole number float' => [2.0, '2.00'],
            'rounds half up to 2dp' => [10.005, '10.01'],
            'negative' => [-1234.567, '-1,234.57'],
            'millions' => [1000000.0, '1,000,000.00'],
        ];
    }

    public function testFormatCell_IntAndNumericString_AreRenderedUntouched(): void
    {
        $table = new TableDisplayer([['i' => 1234, 's' => '1234.5']]);

        $this->assertStringContainsString('<tr><td>1234</td><td>1234.5</td></tr>', $table->getTable());
    }

    public function testFormatCell_Null_RendersEmptyCell(): void
    {
        // formatCell() casts to string: no "passing null to htmlspecialchars()" deprecation on PHP >= 8.1
        $table = new TableDisplayer([['v' => null]]);

        $this->assertStringContainsString('<tbody><tr><td></td></tr></tbody>', $table->getTable());
    }

    public function testSetHead_Null_RendersAnEmptyTheadAndTheRawRows(): void
    {
        // setHead(?array) accepts null: array_keys(null) was a warning on PHP 7 (leaving no column keys) but a
        // TypeError on PHP 8. Without keys the rows are imploded as they are - unescaped and unformatted.
        $table = new TableDisplayer([['a' => 'ZZTEST-001', 'b' => '<b>2</b>']]);
        $table->setHead(null);

        $this->assertSame(
            '<table><thead></thead><tbody><tr><td>ZZTEST-001</td><td><b>2</b></td></tr></tbody></table>',
            $table->getTable()
        );
    }

    public function testSetAttributes_Null_RendersTheTableTagWithoutAttributes(): void
    {
        // setAttributes(?string) accepts null; getTable() measures the attribute string with strlen()
        $table = new TableDisplayer([['sku' => 'ZZTEST-001']], 'zz-table');
        $table->setAttributes(null);

        $this->assertStringStartsWith('<table><thead>', $table->getTable());
    }

    public function testSetAttributes_RawAttributeString_IsPlacedInsideTableTag(): void
    {
        $table = new TableDisplayer([['v' => 1]]);
        $table->setAttributes('id="orders" class="sortable"');

        $this->assertStringStartsWith('<table id="orders" class="sortable"><thead>', $table->getTable());
    }

    public function testSetAttributes_EmptyString_RendersBareTableTag(): void
    {
        $table = new TableDisplayer([], 'willBeReplaced');
        $table->setAttributes('');

        $this->assertStringStartsWith('<table><thead>', $table->getTable());
    }


    public function testConstructor_TableId_RendersQuotedIdAttribute(): void
    {
        // TableDisplayer.php:32 concatenates 'id=' . $tableId without quotes
        $table = new TableDisplayer([], 'myId');

        $this->assertStringStartsWith('<table id="myId">', $table->getTable());
    }

    public function testStreamHTML_YieldsTheSameMarkupAsGetTable(): void
    {
        $table = new TableDisplayer([['sku' => 'ZZTEST-001', 'price' => 12.5]]);
        $table->setAttributes('id="x"');
        $table->setFoot(['Total', 12.5]);

        $streamed = implode('', iterator_to_array($table->streamHTML(), false));

        $this->assertSame($table->getTable(), $streamed);
    }

    public function testStreamHTML_ReturnsGenerator(): void
    {
        $table = new TableDisplayer();

        $this->assertInstanceOf(\Generator::class, $table->streamHTML());
    }

    public function testSetBody_ReplacesPreviousRows(): void
    {
        $table = new TableDisplayer([['v' => 'old']]);
        $table->setBody([['v' => 'new1'], ['v' => 'new2']]);

        $html = $table->getTable();

        $this->assertStringNotContainsString('old', $html);
        $this->assertStringContainsString('<tr><td>new1</td></tr><tr><td>new2</td></tr>', $html);
    }

    public function testGetFoot_WithoutFoot_EmitsNothing(): void
    {
        $table = new TableDisplayer([['v' => 1]]);

        $this->assertSame([], iterator_to_array($table->getFoot(), false));
        $this->assertStringEndsWith('</tbody></table>', $table->getTable());
    }


    public function testSetFoot_OutputEndsWithClosedTfoot(): void
    {
        // TableDisplayer.php:164 `return '</tr></tfoot>'` inside a generator is never yielded (also :156)
        $table = new TableDisplayer([['v' => 1]]);
        $table->setFoot(['Total', 3]);

        $this->assertStringEndsWith('<tfoot><tr><td>Total</td><td>3</td></tr></tfoot></table>', $table->getTable());
    }

    public function testSetFoot_Cells_documentsCurrentBehaviour_AreNotEscapedOrFormatted(): void
    {
        $table = new TableDisplayer([['v' => 1]]);
        $table->setFoot(['<b>', 1234.5]);

        $this->assertStringContainsString('<tfoot><tr><td><b></td><td>1234.5</td>', $table->getTable());
    }


    public function testGetHead_HeaderLabelsAreHtmlEscaped(): void
    {
        // TableDisplayer.php:137 and :141 interpolate $v into <th> without htmlspecialchars
        $table = new TableDisplayer();
        $table->setHead(['a' => '<b>Bold & Co</b>']);

        $this->assertStringContainsString('<th id="a">&lt;b&gt;Bold &amp; Co&lt;/b&gt;</th>', $table->getTable());
    }

    public function testFormatCell_CanBeOverriddenBySubclass(): void
    {
        // RecordEditor and StockOutTable rely on this protected seam
        $table = new class([['sku' => 'ZZTEST-001']]) extends TableDisplayer {
            protected function formatCell($value, $ofColumnIndex): string
            {
                return '<td data-col="' . $ofColumnIndex . '">' . strtolower((string) $value) . '</td>';
            }
        };

        $this->assertStringContainsString('<tbody><tr><td data-col="sku">zztest-001</td></tr></tbody>', $table->getTable());
    }
}
