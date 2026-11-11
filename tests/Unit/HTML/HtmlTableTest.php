<?php

declare(strict_types=1);

namespace test\Unit\HTML;

use HTML\HtmlTable;
use HTML\HtmlTableCell;
use HTML\HtmlTableRow;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of HTML\HtmlTable (src/Legacy/HTML/HtmlTable.php).
 */
final class HtmlTableTest extends TestCase
{
    public function testToHtmlText_EmptyTable_documentsCurrentBehaviour_HasSpaceBeforeClosingBracket(): void
    {
        // streamHtmlText() always yields '<table ' + attributes + '>'
        $this->assertSame('<table ></table>', (new HtmlTable())->toHtmlText());
    }

    public function testToHtmlText_WithAttribute_RendersItOnTheTableTag(): void
    {
        $table = new HtmlTable();
        $table->setAttribute('cd-editable-sheet', '');

        $this->assertSame('<table cd-editable-sheet=""></table>', $table->toHtmlText());
    }

    public function testAddHeader_ReturnsCellAndRendersTheadInOrder(): void
    {
        $table = new HtmlTable();
        $first = $table->addHeader('Item Code');
        $table->addHeader('UOM');

        $this->assertInstanceOf(HtmlTableCell::class, $first);
        $this->assertSame('Item Code', $first->getValue());
        $this->assertSame(
            '<table ><thead><tr><th >Item Code</th><th >UOM</th></tr></thead></table>',
            $table->toHtmlText()
        );
    }

    public function testAddHeader_CellAttributesAndEscaping_AreApplied(): void
    {
        $table = new HtmlTable();
        $table->addHeader('<Qty>')->setAttribute('class', 'num');

        $this->assertStringContainsString('<th class="num">&lt;Qty&gt;</th>', $table->toHtmlText());
    }

    public function testSetHeader_ByIndex_documentsCurrentBehaviour_RendersInInsertionOrderNotIndexOrder(): void
    {
        $table = new HtmlTable();
        $table->setHeader(1, new HtmlTableCell('B'));
        $table->setHeader(0, new HtmlTableCell('A'));

        $this->assertStringContainsString('<thead><tr><th >B</th><th >A</th></tr></thead>', $table->toHtmlText());
    }

    public function testSetHeader_SameIndexTwice_ReplacesTheCell(): void
    {
        $table = new HtmlTable();
        $table->setHeader(0, new HtmlTableCell('old'));
        $table->setHeader(0, new HtmlTableCell('new'));

        $this->assertStringContainsString('<thead><tr><th >new</th></tr></thead>', $table->toHtmlText());
    }

    public function testAddRow_GetRow_RoundTrip(): void
    {
        $table = new HtmlTable();
        $row = new HtmlTableRow([new HtmlTableCell('a')]);
        $table->addRow($row);

        $this->assertSame($row, $table->getRow(0));
    }

    public function testToHtmlText_RowsAreWrappedInTbody(): void
    {
        $table = new HtmlTable();
        $table->addRow(new HtmlTableRow([new HtmlTableCell('a'), new HtmlTableCell('b')]));
        $table->addRow(new HtmlTableRow([new HtmlTableCell('c')]));

        $this->assertSame(
            '<table ><tbody><tr><td>a</td><td>b</td></tr><tr><td>c</td></tr></tbody></table>',
            $table->toHtmlText()
        );
    }

    public function testSetRow_ByIndex_ReplacesThatRow(): void
    {
        $table = new HtmlTable();
        $table->addRow(new HtmlTableRow([new HtmlTableCell('old')]));
        $replacement = new HtmlTableRow([new HtmlTableCell('new')]);
        $table->setRow(0, $replacement);

        $this->assertSame($replacement, $table->getRow(0));
        $this->assertStringContainsString('<tbody><tr><td>new</td></tr></tbody>', $table->toHtmlText());
    }

    public function testSetBody_documentsCurrentBehaviour_ReplacesAllRowsAndIgnoresIndex(): void
    {
        // HtmlTable.php:21-24 assigns the whole list regardless of $index
        $table = new HtmlTable();
        $table->addRow(new HtmlTableRow([new HtmlTableCell('dropped')]));
        $r1 = new HtmlTableRow([new HtmlTableCell('r1')]);
        $r2 = new HtmlTableRow([new HtmlTableCell('r2')]);
        $table->setBody(99, [$r1, $r2]);

        $this->assertSame($r1, $table->getRow(0));
        $this->assertSame($r2, $table->getRow(1));
        $this->assertSame('<table ><tbody><tr><td>r1</td></tr><tr><td>r2</td></tr></tbody></table>', $table->toHtmlText());
    }

    public function testSetFooter_documentsCurrentBehaviour_CellsAreRenderedDirectlyInsideTfootWithoutTr(): void
    {
        $table = new HtmlTable();
        $table->setFooter(0, new HtmlTableCell('Total'));
        $table->setFooter(1, new HtmlTableCell('3'));

        $this->assertSame('<table ><tfoot><td>Total</td><td>3</td></tfoot></table>', $table->toHtmlText());
    }

    public function testToHtmlText_FullTable_RendersHeadBodyFootInThatOrder(): void
    {
        $table = new HtmlTable();
        $table->setAttribute('id', 't');
        $table->addHeader('SKU');
        $table->addHeader('Qty');
        $table->addRow(new HtmlTableRow([new HtmlTableCell('ZZTEST-001'), new HtmlTableCell(2)]));
        $table->setFooter(0, new HtmlTableCell('Total'));

        $this->assertSame(
            '<table id="t">'
            . '<thead><tr><th >SKU</th><th >Qty</th></tr></thead>'
            . '<tbody><tr><td>ZZTEST-001</td><td>2</td></tr></tbody>'
            . '<tfoot><td>Total</td></tfoot>'
            . '</table>',
            $table->toHtmlText()
        );
    }

    public function testStreamHtmlText_ConcatenatedEqualsToHtmlText(): void
    {
        $table = new HtmlTable();
        $table->addHeader('SKU');
        $table->addRow(new HtmlTableRow([new HtmlTableCell('ZZTEST-001')]));

        $this->assertInstanceOf(\Generator::class, $table->streamHtmlText());
        $this->assertSame($table->toHtmlText(), implode('', iterator_to_array($table->streamHtmlText(), false)));
    }

    public function testToString_RendersTable(): void
    {
        $this->assertSame('<table ></table>', (string) new HtmlTable());
    }
}
