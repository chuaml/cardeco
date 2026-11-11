<?php

declare(strict_types=1);

namespace test\Unit\HTML;

use HTML\HtmlObject;
use HTML\HtmlTableCell;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of HTML\HtmlTableCell (src/Legacy/HTML/HtmlTableCell.php).
 */
final class HtmlTableCellTest extends TestCase
{
    public function testConstructor_DefaultValue_IsEmptyString(): void
    {
        $cell = new HtmlTableCell();

        $this->assertSame('', $cell->getValue());
        $this->assertSame('<td></td>', $cell->toHtmlText());
    }

    public function testSetValue_GetValue_RoundTripKeepsRawValue(): void
    {
        $cell = new HtmlTableCell('old');
        $cell->setValue('<new>');

        $this->assertSame('<new>', $cell->getValue());
    }

    public function testGetFormattedValue_String_IsTrimmedAndEscaped(): void
    {
        $cell = new HtmlTableCell("  <b>&\"'</b>  ");

        $this->assertSame('&lt;b&gt;&amp;&quot;&#039;&lt;/b&gt;', $cell->getFormattedValue());
    }

    public function testGetFormattedValue_Null_RendersNullMarker(): void
    {
        $cell = new HtmlTableCell(null);

        $this->assertSame('<i data-type="null"></i>', $cell->getFormattedValue());
        $this->assertSame('<td><i data-type="null"></i></td>', $cell->toHtmlText());
    }

    /**
     * @dataProvider scalarProvider
     * @param mixed $value
     */
    public function testGetFormattedValue_Scalars_AreCastToStringWithoutNumberFormatting($value, string $expected): void
    {
        $this->assertSame($expected, (new HtmlTableCell($value))->getFormattedValue());
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public function scalarProvider(): array
    {
        return [
            'int' => [5, '5'],
            'float keeps PHP string form (no 2dp formatting, unlike TableDisplayer)' => [1234.5, '1234.5'],
            'float whole number drops the decimals' => [12.0, '12'],
            'true' => [true, '1'],
            'false' => [false, ''],
        ];
    }

    public function testGetFormattedValue_ObjectWithToString_documentsCurrentBehaviour_IsNotEscaped(): void
    {
        // HtmlTableCell.php:37-38 casts objects to string without escaping (allows nested HtmlObjects)
        $cell = new HtmlTableCell((new HtmlObject('input'))->setAttribute('name', 'x'));

        $this->assertSame('<input name="x"/>', $cell->getFormattedValue());
    }

    public function testGetFormattedValue_WithChildNodes_RendersChildrenAndIgnoresValue(): void
    {
        $cell = new HtmlTableCell('ignored value');
        $cell->addChild(new HtmlObject('input'))->setAttribute('name', 'r[1]');
        $cell->addChild(new HtmlObject('br'));

        $this->assertSame('<input name="r[1]"/><br/>', $cell->getFormattedValue());
        $this->assertSame('<td><input name="r[1]"/><br/></td>', $cell->toHtmlText());
    }

    public function testToHtmlText_WithAttributes_RendersThemOnTheTd(): void
    {
        $cell = new HtmlTableCell('v');
        $cell->setAttribute('class', 'num');

        $this->assertSame('<td class="num">v</td>', $cell->toHtmlText());
    }

    public function testToString_RendersTd(): void
    {
        $this->assertSame('<td>ZZTEST-001</td>', (string) new HtmlTableCell('ZZTEST-001'));
    }
}
