<?php

declare(strict_types=1);

namespace test\Unit\HTML;

use HTML\HtmlTableCell;
use HTML\HtmlTableRow;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of HTML\HtmlTableRow (src/Legacy/HTML/HtmlTableRow.php).
 */
final class HtmlTableRowTest extends TestCase
{
    public function testToHtmlText_EmptyRow_RendersEmptyTr(): void
    {
        $this->assertSame('<tr></tr>', (new HtmlTableRow())->toHtmlText());
    }

    public function testConstructor_WithCells_RendersThemInOrder(): void
    {
        $row = new HtmlTableRow([new HtmlTableCell('a'), new HtmlTableCell('b')]);

        $this->assertSame('<tr><td>a</td><td>b</td></tr>', $row->toHtmlText());
    }

    public function testAddCell_ReturnsTheCellAndAppendsIt(): void
    {
        $row = new HtmlTableRow();
        $cell = new HtmlTableCell('x');

        $this->assertSame($cell, $row->addCell($cell));
        $this->assertSame($cell, $row->getCell(0));
        $this->assertSame('<tr><td>x</td></tr>', $row->toHtmlText());
    }

    public function testSetCell_GetCell_RoundTrip(): void
    {
        $row = new HtmlTableRow([new HtmlTableCell('a')]);
        $replacement = new HtmlTableCell('b');
        $row->setCell(0, $replacement);

        $this->assertSame($replacement, $row->getCell(0));
        $this->assertSame('<tr><td>b</td></tr>', $row->toHtmlText());
    }

    public function testSetCell_WithGapIndex_documentsCurrentBehaviour_RendersInInsertionOrder(): void
    {
        $row = new HtmlTableRow();
        $row->setCell(2, new HtmlTableCell('c'));
        $row->setCell(0, new HtmlTableCell('a'));

        $this->assertSame('<tr><td>c</td><td>a</td></tr>', $row->toHtmlText());
    }

    public function testGetCell_MissingIndex_ThrowsInvalidArgumentException(): void
    {
        $row = new HtmlTableRow([new HtmlTableCell('a')]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cell index 3 does not exist in this row.');
        $row->getCell(3);
    }

    public function testToHtmlText_WithAttributes_RendersThemOnTheTr(): void
    {
        $row = new HtmlTableRow([new HtmlTableCell('a')]);
        $row->setAttribute('class', 'odd');

        $this->assertSame('<tr class="odd"><td>a</td></tr>', $row->toHtmlText());
    }
}
