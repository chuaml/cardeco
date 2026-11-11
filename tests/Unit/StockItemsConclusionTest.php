<?php

declare(strict_types=1);

namespace test\Unit;

use PHPUnit\Framework\TestCase;
use Shopee_Product_Info;
use Stock_Items_Conclusion;

/**
 * Characterization of Stock_Items_Conclusion (src/Legacy/Stock_Items_Conclusion.php): one output row per
 * ordered UNIT (quantity is expanded) with the order's paid price / shipping fee shown on the first row only,
 * and a 'has Stock?' flag of Yes / No / Maybe (unknown item).
 */
final class StockItemsConclusionTest extends TestCase
{
    const STOCK = [
        ['item_code' => 'ZZTEST-001', 'description' => 'Test Seat Cover', 'quantity' => '5'],
        ['item_code' => 'ZZTEST-004', 'description' => 'Test Wax', 'quantity' => '0'],
    ];

    public function testSetResult_EmptyStockResult_Throws(): void
    {
        $conclusion = new Stock_Items_Conclusion(new Shopee_Product_Info());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('empty result');
        $conclusion->setResult([]);
    }

    public function testGetOutput_NoOrders_IsEmpty(): void
    {
        $this->assertSame([], self::conclude([], self::STOCK));
    }

    public function testGetOutput_QuantityTwo_ProducesTwoRowsPaidOnlyOnTheFirst(): void
    {
        $rows = self::conclude([self::order('O-1', '2024-03-15', '25.80', '4.90', 'TRK1', [['ZZTEST-001', 2]])], self::STOCK);

        $this->assertSame([
            [
                'Order ID' => 'O-1', 'Payment Date' => '2024-03-15', 'Item Code' => 'ZZTEST-001',
                'Description' => 'Test Seat Cover', 'Paid' => '25.80', 'Shipping Fee' => '4.90',
                'Tracking Number' => 'TRK1', 'has Stock?' => 'Yes',
            ],
            [
                'Order ID' => 'O-1', 'Payment Date' => '2024-03-15', 'Item Code' => 'ZZTEST-001',
                'Description' => 'Test Seat Cover', 'Paid' => '-', 'Shipping Fee' => '-',
                'Tracking Number' => 'TRK1', 'has Stock?' => 'Yes',
            ],
        ], $rows);
    }

    public function testGetOutput_UnknownItem_ShowsSkuAsDescriptionAndMaybe(): void
    {
        $rows = self::conclude([self::order('O-3', '2024-03-17', '9.00', '4.90', 'TRK3', [['ZZTEST-404', 1]])], self::STOCK);

        $this->assertCount(1, $rows);
        $this->assertSame('N/a', $rows[0]['Item Code']);
        $this->assertSame('ZZTEST-404', $rows[0]['Description']);
        $this->assertSame('Maybe', $rows[0]['has Stock?']);
    }

    public function testGetOutput_FirstOrderWithZeroStock_IsNo(): void
    {
        $rows = self::conclude([self::order('O-2', '2024-03-16', '5.00', '4.90', 'TRK2', [['ZZTEST-004', 1]])], self::STOCK);

        $this->assertSame('No', $rows[0]['has Stock?']);
    }

    public function testGetOutput_ZeroStockAfterInStockOrder_documentsCurrentBehaviour_InheritsYes(): void
    {
        // Stock_Items_Conclusion.php:45 initialises $hasStock once; :76-81 only ever set 'Yes'/'Maybe',
        // so a zero-stock item keeps whatever the previous row concluded
        $rows = self::conclude([
            self::order('O-1', '2024-03-15', '12.90', '4.90', 'TRK1', [['ZZTEST-001', 1]]),
            self::order('O-2', '2024-03-16', '5.00', '4.90', 'TRK2', [['ZZTEST-004', 1]]),
        ], self::STOCK);

        $this->assertSame(['Yes', 'Yes'], array_column($rows, 'has Stock?'));
    }

    /**
     * @group known-bug
     */
    public function testGetOutput_ZeroStockAfterInStockOrder_IsNo(): void
    {
        // Stock_Items_Conclusion.php:45,76-81 - $hasStock must be recomputed for every item
        $rows = self::conclude([
            self::order('O-1', '2024-03-15', '12.90', '4.90', 'TRK1', [['ZZTEST-001', 1]]),
            self::order('O-2', '2024-03-16', '5.00', '4.90', 'TRK2', [['ZZTEST-004', 1]]),
        ], self::STOCK);

        $this->assertSame(['Yes', 'No'], array_column($rows, 'has Stock?'));
    }

    public function testGetOutput_MultiItemOrder_documentsCurrentBehaviour_ItemsComeOutRotatedByOne(): void
    {
        // Stock_Items_Conclusion.php:58 slices the cell from the NEXT item's "[n] Product Name:" offset,
        // so for items [1],[2],[3] the rows are emitted as [2],[3],[1] and the paid price lands on item [2]
        $rows = self::conclude([
            self::order('O-4', '2024-03-18', '17.90', '4.90', 'TRK4', [['ZZTEST-001', 1], ['ZZTEST-004', 1], ['ZZTEST-005', 1]]),
        ], [
            ['item_code' => 'ZZTEST-001', 'description' => 'Test Seat Cover', 'quantity' => '5'],
            ['item_code' => 'ZZTEST-004', 'description' => 'Test Wax', 'quantity' => '5'],
            ['item_code' => 'ZZTEST-005', 'description' => 'Test Cloth', 'quantity' => '5'],
        ]);

        $this->assertSame(['ZZTEST-004', 'ZZTEST-005', 'ZZTEST-001'], array_column($rows, 'Item Code'));
        $this->assertSame(['17.90', '-', '-'], array_column($rows, 'Paid'));
    }

    /**
     * @group known-bug
     */
    public function testGetOutput_MultiItemOrder_ItemsComeOutInCellOrder(): void
    {
        // Stock_Items_Conclusion.php:58 - should slice from $listDetail[$l][1], keeping [1],[2] order
        $rows = self::conclude([
            self::order('O-4', '2024-03-18', '17.90', '4.90', 'TRK4', [['ZZTEST-001', 1], ['ZZTEST-004', 1]]),
        ], [
            ['item_code' => 'ZZTEST-001', 'description' => 'Test Seat Cover', 'quantity' => '5'],
            ['item_code' => 'ZZTEST-004', 'description' => 'Test Wax', 'quantity' => '5'],
        ]);

        $this->assertSame(['ZZTEST-001', 'ZZTEST-004'], array_column($rows, 'Item Code'));
    }

    public function testGetOutput_EscapesEveryCell(): void
    {
        $rows = self::conclude(
            [self::order('O-<1>', '2024-03-15', '1.00', '0.00', 'TRK"1"', [['ZZTEST-001', 1]])],
            [['item_code' => 'ZZTEST-001', 'description' => 'Seat & Cover', 'quantity' => '5']]
        );

        $this->assertSame('O-&lt;1&gt;', $rows[0]['Order ID']);
        $this->assertSame('TRK&quot;1&quot;', $rows[0]['Tracking Number']);
        $this->assertSame('Seat &amp; Cover', $rows[0]['Description']);
    }

    // ---------------------------------------------------------------- helpers

    private static function conclude(array $orders, array $stock): array
    {
        $conclusion = new Stock_Items_Conclusion(new Shopee_Product_Info());
        $conclusion->setOrders_List($orders);
        $conclusion->setResult($stock);

        return $conclusion->getOutput();
    }

    /**
     * @param array<int, array{0: string, 1: int}> $items [sku, quantity] per "[n] Product Name" block
     */
    private static function order(string $id, string $date, string $price, string $shippingFee, string $tracking, array $items): array
    {
        $cell = '';
        foreach ($items as $n => $item) {
            $cell .= '[' . ($n + 1) . '] Product Name:Test item ' . ($n + 1) . '; Variation Name:; Price:RM 1.00; Quantity:' . $item[1] . '; '
                . 'Parent SKU Reference No.:; SKU Reference No.:' . $item[0] . '; ';
        }

        return [
            'order_id' => $id,
            'date' => $date,
            'price' => $price,
            'shipping_fee' => $shippingFee,
            'details' => trim($cell),
            'tracking_num' => $tracking,
        ];
    }
}
