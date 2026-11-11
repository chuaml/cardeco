<?php

declare(strict_types=1);

namespace test\Unit\Product;

use PHPUnit\Framework\TestCase;
use Product\Item;

/**
 * Characterization of the Product\Item DTO (src/Legacy/Product/Item.php) used by /ItemManager.
 */
final class ItemTest extends TestCase
{
    public function testConstructor_AssignsPublicFieldsAndStartsWithNoMapping(): void
    {
        $item = new Item(5, 'ZZTEST-001', 'Test Seat Cover', 'PCS', 'ACCESSORIES');

        $this->assertSame(5, $item->itemId);
        $this->assertSame('ZZTEST-001', $item->code);
        $this->assertSame('Test Seat Cover', $item->description);
        $this->assertSame('PCS', $item->uom);
        $this->assertSame('ACCESSORIES', $item->group);
        $this->assertSame([], $item->bigseller_sku_map);
    }

    public function testConstructor_UomAndGroupAreOptional_OtherArgumentsAcceptNull(): void
    {
        // request_handler/ItemManager.php:23 builds `new Item($itemId, null, $description)` from POST data
        $item = new Item(null, null, 'Only a description');

        $this->assertNull($item->itemId);
        $this->assertNull($item->code);
        $this->assertSame('Only a description', $item->description);
        $this->assertNull($item->uom);
        $this->assertNull($item->group);
    }

    public function testGetAll_ReturnsEveryFieldInDeclaredOrderIncludingMappings(): void
    {
        $item = new Item(5, 'ZZTEST-001', 'Test Seat Cover', 'PCS', 'ACCESSORIES');
        $item->bigseller_sku_map[] = ['id' => 9, 'bigseller_sku' => 'BS-ZZTEST-001'];

        $this->assertSame([
            'itemId' => 5,
            'code' => 'ZZTEST-001',
            'description' => 'Test Seat Cover',
            'uom' => 'PCS',
            'group' => 'ACCESSORIES',
            'bigseller_sku_map' => [['id' => 9, 'bigseller_sku' => 'BS-ZZTEST-001']],
        ], $item->getAll());
    }

    public function testGetEscapedData_EscapesHtmlAndQuotes(): void
    {
        $item = new Item(1, 'ZZTEST-001', 'Mat <b>"5 pcs"</b> & \'more\'');

        $this->assertSame('Mat &lt;b&gt;&quot;5 pcs&quot;&lt;/b&gt; &amp; &#039;more&#039;', $item->getEscapedData('description'));
        $this->assertSame('ZZTEST-001', $item->getEscapedData('code'));
    }

    public function testGetEscapedData_NullProperty_ReturnsEmptyString(): void
    {
        $item = new Item(1, 'ZZTEST-001', 'desc');

        $this->assertSame('', $item->getEscapedData('uom'));
    }
}
