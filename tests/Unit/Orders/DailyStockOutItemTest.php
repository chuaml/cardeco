<?php

declare(strict_types=1);

namespace test\Unit\Orders;

use Orders\DailyStockOutItem;
use Orders\DailyStockOutItem_Factory;
use PHPUnit\Framework\TestCase;
use Product\Item;

/**
 * Characterization of Orders\DailyStockOutItem (src/Legacy/Orders/DailyStockOutItem.php) and
 * Orders\DailyStockOutItem_Factory::map() (src/Legacy/Orders/Factory/DailyStockOutItem_Factory.php): one row of the
 * BigSeller "all orders" export (positional array, as produced by ExcelReader) -> DTO used by Report\ShippedItemReport.
 *
 * map() reads column 23 (seller sku), 25 (product name), 30 (quantity) and '7' (shipping status).
 */
final class DailyStockOutItemTest extends TestCase
{
    const WIDTH = 40;

    // ---------------------------------------------------------------- DailyStockOutItem

    public function testConstructor_defaults(): void
    {
        $x = new DailyStockOutItem();

        $this->assertNull($x->sku);
        $this->assertNull($x->productName);
        $this->assertSame(0, $x->quantity);
        $this->assertNull($x->shippingStatus);
        $this->assertInstanceOf(Item::class, $x->item);
        $this->assertNull($x->item->code);
        $this->assertNull($x->item->itemId);
        $this->assertFalse($x->isShipped());
    }

    public function testConstructor_everyInstanceGetsItsOwnEmptyItem(): void
    {
        $a = new DailyStockOutItem();
        $b = new DailyStockOutItem();

        $this->assertNotSame($a->item, $b->item);
    }

    /** @return array<string, array<int, mixed>> */
    public function provideShippingStatuses(): array
    {
        // only the exact string 'Shipped' counts; every other BigSeller status (and any casing) is "not shipped"
        return [
            'Shipped' => ['Shipped', true],
            'New' => ['New', false],
            'Processing' => ['Processing', false],
            'Delivered' => ['Delivered', false],
            'Cancelled' => ['Cancelled', false],
            'Returned' => ['Returned', false],
            'lower-case shipped' => ['shipped', false],
            'upper-case SHIPPED' => ['SHIPPED', false],
            'shipped with surrounding spaces' => [' Shipped ', false],
            'empty string' => ['', false],
            'null' => [null, false],
        ];
    }

    /**
     * @dataProvider provideShippingStatuses
     */
    public function testIsShipped_recognisesOnlyTheExactShippedStatus($status, bool $expected): void
    {
        $x = new DailyStockOutItem();
        $x->shippingStatus = $status;

        $this->assertSame($expected, $x->isShipped());
    }

    // ---------------------------------------------------------------- DailyStockOutItem_Factory::map

    public function testMap_positionalRow_mapsSkuNameQuantityAndStatus(): void
    {
        $x = DailyStockOutItem_Factory::map($this->row([23 => 'ZZTEST-001', 25 => 'Zz Boot Tray', 30 => '3', 7 => 'Shipped']));

        $this->assertInstanceOf(DailyStockOutItem::class, $x);
        $this->assertSame('ZZTEST-001', $x->sku);
        $this->assertSame('Zz Boot Tray', $x->productName);
        $this->assertSame(3, $x->quantity);
        $this->assertSame('Shipped', $x->shippingStatus);
        $this->assertTrue($x->isShipped());
    }

    public function testMap_doesNotTouchTheDbItemReference(): void
    {
        $x = DailyStockOutItem_Factory::map($this->row([23 => 'ZZTEST-001', 25 => 'Zz', 30 => '1', 7 => 'New']));

        $this->assertNull($x->item->code, 'item is filled later from stock_items, not from the export row');
        $this->assertNull($x->item->description);
    }

    public function testMap_skuAndNameAreNotTrimmed(): void
    {
        $x = DailyStockOutItem_Factory::map($this->row([23 => ' ZZTEST-001 ', 25 => ' Zz ', 30 => '1', 7 => 'New']));

        $this->assertSame(' ZZTEST-001 ', $x->sku);
        $this->assertSame(' Zz ', $x->productName);
    }

    /** @return array<string, array<int, mixed>> */
    public function provideQuantities(): array
    {
        return [
            'int' => [2, 2],
            'numeric string' => ['3', 3],
            'numeric string with trailing space' => ['4 ', 4],
            'float string is truncated' => ['2.9', 2],
            'float' => [2.9, 2],
            'empty string' => ['', 0],
            'null' => [null, 0],
            'non numeric' => ['n/a', 0],
        ];
    }

    /**
     * @dataProvider provideQuantities
     */
    public function testMap_quantityIsCoercedWithIntval($cell, int $expected): void
    {
        $x = DailyStockOutItem_Factory::map($this->row([23 => 'ZZTEST-001', 25 => 'Zz', 30 => $cell, 7 => 'New']));

        $this->assertSame($expected, $x->quantity);
    }

    public function testMap_statusColumnIsReadThroughTheStringKeySeven(): void
    {
        // DailyStockOutItem_Factory.php uses $r['7']; PHP normalises '7' to int 7, so positional rows work
        $row = $this->row([23 => 'ZZTEST-001', 25 => 'Zz', 30 => '1']);
        $row[7] = 'Delivered';

        $this->assertSame('Delivered', DailyStockOutItem_Factory::map($row)->shippingStatus);
    }

    public function testMap_eachCallReturnsANewInstance(): void
    {
        $row = $this->row([23 => 'ZZTEST-001', 25 => 'Zz', 30 => '1', 7 => 'New']);

        $this->assertNotSame(DailyStockOutItem_Factory::map($row), DailyStockOutItem_Factory::map($row));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param array<int, mixed> $positional
     * @return array<int, mixed>
     */
    private function row(array $positional): array
    {
        $row = array_fill(0, self::WIDTH, '');
        foreach ($positional as $i => $v) {
            $row[$i] = $v;
        }

        return $row;
    }
}
