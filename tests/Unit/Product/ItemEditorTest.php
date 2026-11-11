<?php

declare(strict_types=1);

namespace test\Unit\Product;

use PHPUnit\Framework\TestCase;
use Product\Item;
use Product\ItemEditor;

/**
 * Characterization of Product\ItemEditor (src/Legacy/Product/ItemEditor.php): renders the editable
 * item table of /ItemManager (Item[] -> HTML\HtmlTable with <input> cells for the BigSeller SKU mapping
 * and the description).
 */
final class ItemEditorTest extends TestCase
{
    public function testGetTable_ReturnsAGeneratorOfHtmlChunks(): void
    {
        $table = (new ItemEditor([], 0))->getTable();

        $this->assertInstanceOf(\Generator::class, $table);
        $this->assertStringStartsWith('<table', implode('', iterator_to_array($table, false)));
    }

    public function testGetTable_TableCarriesTheEditableSheetMarkerAttribute(): void
    {
        $this->assertStringStartsWith('<table cd-editable-sheet="">', self::render([]));
    }

    public function testGetTable_NoItems_RendersHeaderOnlyWithoutTbody(): void
    {
        $html = self::render([]);

        $this->assertStringContainsString('</thead></table>', $html);
        $this->assertStringNotContainsString('<tbody>', $html);
    }

    public function testGetTable_RendersTheFiveColumnHeadersInOrder(): void
    {
        $this->assertSame(
            ['Item Code', 'Big Seller SKU', 'Description', 'UOM', 'Group'],
            self::cellTexts('th', self::render([]))
        );
    }

    public function testGetTable_OneRowPerItem_CodeUomGroupAsTextAndTwoInputs(): void
    {
        $html = self::render([self::item(5, 'ZZTEST-001', 'Test Seat Cover', 'PCS', 'ACCESSORIES', 9, 'BS-001')]);

        $this->assertSame(1, substr_count($html, '<tr>') - 1, 'one body row besides the header row');
        $cells = self::cellTexts('td', $html);
        $this->assertCount(5, $cells);
        $this->assertSame('ZZTEST-001', $cells[0]);
        $this->assertStringStartsWith('<input', $cells[1]);
        $this->assertStringStartsWith('<input', $cells[2]);
        $this->assertSame('PCS', $cells[3]);
        $this->assertSame('ACCESSORIES', $cells[4]);
    }

    public function testGetTable_MappingInput_IsNamedByItemIdAndMappingIdAndPrefilledWithTheSku(): void
    {
        // bigseller_sku_map[<item id>][<mapping id>] - the controller pairs mappings with items by item id, never by position
        $html = self::render([self::item(5, 'ZZTEST-001', 'Test Seat Cover', 'PCS', 'ACC', 9, 'BS-001')]);

        $this->assertMatchesRegularExpression('~<input [^>]*name="bigseller_sku_map\[5\]\[9\]"~', $html);
        $this->assertMatchesRegularExpression('~<input [^>]*name="bigseller_sku_map\[5\]\[9\]"[^>]*value="BS-001"~', $html);
        $this->assertMatchesRegularExpression('~<input [^>]*name="bigseller_sku_map\[5\]\[9\]"[^>]*maxlength="255"~', $html);
    }

    public function testGetTable_DescriptionInput_IsNamedByItemIdAndPrefilledWithTheDescription(): void
    {
        $html = self::render([self::item(5, 'ZZTEST-001', 'Test Seat Cover', 'PCS', 'ACC', 9, 'BS-001')]);

        $this->assertMatchesRegularExpression('~<input [^>]*name="r\[5\]"[^>]*value="Test Seat Cover"[^>]*maxlength="255"~', $html);
    }

    public function testGetTable_OnlyTheFirstMappingOfAnItemIsRendered(): void
    {
        // ItemEditor.php:34 "only take 1st mapping"
        $item = self::item(5, 'ZZTEST-001', 'd', 'PCS', 'ACC', 9, 'BS-FIRST');
        $item->bigseller_sku_map[] = ['id' => 10, 'bigseller_sku' => 'BS-SECOND'];

        $html = self::render([$item]);

        $this->assertStringContainsString('bigseller_sku_map[5][9]', $html);
        $this->assertStringNotContainsString('[10]', $html);
        $this->assertStringNotContainsString('BS-SECOND', $html);
    }

    public function testGetTable_EscapesTextAndAttributeValues(): void
    {
        $html = self::render([self::item(5, 'ZZ<1>', 'Desc <b>"x"</b> & y', 'P&C', 'A"G', 9, 'BS-"1"')]);

        $this->assertStringContainsString('<td>ZZ&lt;1&gt;</td>', $html);
        $this->assertStringContainsString('value="Desc &lt;b&gt;&quot;x&quot;&lt;/b&gt; &amp; y"', $html);
        $this->assertStringContainsString('value="BS-&quot;1&quot;"', $html);
        $this->assertStringContainsString('<td>P&amp;C</td>', $html);
        $this->assertStringContainsString('<td>A&quot;G</td>', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function testGetTable_NullUomAndGroup_RenderTheNullMarker(): void
    {
        $html = self::render([self::item(6, 'ZZTEST-002', 'Plain', null, null, 1, 'BS')]);

        $cells = self::cellTexts('td', $html);
        $this->assertSame('<i data-type="null"></i>', $cells[3]);
        $this->assertSame('<i data-type="null"></i>', $cells[4]);
    }

    public function testGetTable_MappingFromLeftJoinWithNulls_RendersNewMappingKeyAndEmptyValue(): void
    {
        // Product\ItemManager::getItem() always appends one mapping, with NULLs when no bigseller_sku_map row exists
        $html = self::render([self::item(6, 'ZZTEST-002', 'Plain', 'PCS', 'ACC', null, null)]);

        $this->assertMatchesRegularExpression('~<input [^>]*name="bigseller_sku_map\[6\]\[new\]"[^>]*value=""~', $html);
    }

    public function testGetTable_MultipleItems_KeepListOrder(): void
    {
        $html = self::render([
            self::item(1, 'ZZTEST-001', 'one', 'PCS', 'A', 11, 'BS-1'),
            self::item(2, 'ZZTEST-002', 'two', 'PCS', 'A', 12, 'BS-2'),
            self::item(3, 'ZZTEST-003', 'three', 'PCS', 'A', 13, 'BS-3'),
        ]);

        $this->assertSame(3, preg_match_all('~<td>(ZZTEST-00\d)</td>~', $html, $m));
        $this->assertSame(['ZZTEST-001', 'ZZTEST-002', 'ZZTEST-003'], $m[1]);
        $this->assertLessThan(strpos($html, 'name="r[2]"'), strpos($html, 'name="r[1]"'));
        $this->assertLessThan(strpos($html, 'name="r[3]"'), strpos($html, 'name="r[2]"'));
    }

    public function testGetTable_NumPageArgument_HasNoEffectOnOutput(): void
    {
        $items = [self::item(5, 'ZZTEST-001', 'd', 'PCS', 'ACC', 9, 'BS')];

        $this->assertSame(
            implode('', iterator_to_array((new ItemEditor($items, 0))->getTable(), false)),
            implode('', iterator_to_array((new ItemEditor($items, 42))->getTable(), false))
        );
    }

    public function testConstructor_ItemWithoutAnyMapping_RendersAnEmptyMappingInput(): void
    {
        // an Item with an empty bigseller_sku_map renders like a NULL left-join mapping (used to raise "Undefined offset")
        $html = self::render([new Item(7, 'ZZTEST-003', 'No mapping')]);

        $this->assertMatchesRegularExpression('~<input [^>]*name="bigseller_sku_map\[7\]\[new\]"[^>]*value=""~', $html);
        $this->assertMatchesRegularExpression('~<input [^>]*name="r\[7\]"[^>]*value="No mapping"~', $html);
    }

    public function testGetTable_FullOutput_documentsCurrentBehaviour(): void
    {
        // exact snapshot for refactoring; note the `<th >` produced by HtmlTable::streamHtmlText() (HtmlTable.php:56-58)
        // for attribute-less header cells
        $html = self::render([self::item(5, 'ZZTEST-001', 'Test Seat Cover', 'PCS', 'ACCESSORIES', 9, 'BS-001')]);

        $this->assertSame(
            '<table cd-editable-sheet="">'
            . '<thead><tr><th >Item Code</th><th >Big Seller SKU</th><th >Description</th><th >UOM</th><th >Group</th></tr></thead>'
            . '<tbody><tr>'
            . '<td>ZZTEST-001</td>'
            . '<td><input name="bigseller_sku_map[5][9]" value="BS-001" maxlength="255"/></td>'
            . '<td><input name="r[5]" value="Test Seat Cover" maxlength="255"/></td>'
            . '<td>PCS</td>'
            . '<td>ACCESSORIES</td>'
            . '</tr></tbody></table>',
            $html
        );
    }

    // ---------------------------------------------------------------- helpers

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

    /**
     * @param array<int, Item> $items
     */
    private static function render(array $items): string
    {
        return implode('', iterator_to_array((new ItemEditor($items, 0))->getTable(), false));
    }

    private static function item(
        int $id,
        string $code,
        string $description,
        ?string $uom,
        ?string $group,
        ?int $mappingId,
        ?string $bigsellerSku
    ): Item {
        $item = new Item($id, $code, $description, $uom, $group);
        $item->bigseller_sku_map[] = ['id' => $mappingId, 'bigseller_sku' => $bigsellerSku];

        return $item;
    }

    /**
     * Inner HTML of every <tag> cell, in document order.
     *
     * @return array<int, string>
     */
    private static function cellTexts(string $tag, string $html): array
    {
        preg_match_all('~<' . $tag . '(?:\s[^>]*)?>(.*?)</' . $tag . '>~s', $html, $matches);

        return $matches[1];
    }
}
