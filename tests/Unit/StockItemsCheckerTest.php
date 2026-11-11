<?php

declare(strict_types=1);

namespace test\Unit;

use PHPUnit\Framework\TestCase;
use Shopee_Product_Info;
use Stock_Items_Checker;

/**
 * Characterization of Stock_Items_Checker (src/Legacy/Stock_Items_Checker.php): aggregates the ordered
 * quantity per SKU from legacy Shopee "Product Info" cells, joins it with stock rows
 * (item_code/description/quantity) and splits the result into outofstock / itemList / notfound tables.
 */
final class StockItemsCheckerTest extends TestCase
{
    const STOCK = [
        ['item_code' => 'ZZTEST-001', 'description' => 'Test Seat Cover', 'quantity' => '5'],
        ['item_code' => 'ZZTEST-004', 'description' => 'Test Wax', 'quantity' => '0'],
    ];

    public function testGetOutput_BeforeAnyInput_HasThreeEmptySections(): void
    {
        $checker = new Stock_Items_Checker(new Shopee_Product_Info());

        $this->assertSame(['outofstock' => [], 'itemList' => [], 'notfound' => []], $checker->getOutput());
    }

    public function testSetResult_EmptyStockResult_Throws(): void
    {
        $checker = new Stock_Items_Checker(new Shopee_Product_Info());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('empty result');
        $checker->setResult([]);
    }

    public function testGetOutput_ClassifiesItemsIntoOutOfStockItemListAndNotFound(): void
    {
        $output = self::check([
            self::order('ZZTEST-001', 2),
            self::order('ZZTEST-004', 1),
            self::order('ZZTEST-404', 1),
        ], self::STOCK);

        $this->assertSame([['Item Code' => 'ZZTEST-004', 'Description' => 'Test Wax', 'Quantity' => '1', 'Stock' => '0']], $output['outofstock']);
        $this->assertSame([['Item Code' => 'ZZTEST-001', 'Description' => 'Test Seat Cover', 'Quantity' => '2', 'Stock' => '5']], $output['itemList']);
        $this->assertSame([['Item Code' => 'N/A', 'Description' => 'ZZTEST-404', 'Quantity' => '1', 'Stock' => 'N/A']], $output['notfound']);
    }

    public function testSetItemsCode_SumsQuantitiesOfTheSameSkuAcrossOrders(): void
    {
        // 2 + 4 = 6 ordered against a stock of 5 -> one aggregated out-of-stock row
        $output = self::check([self::order('ZZTEST-001', 2), self::order('ZZTEST-001', 4)], self::STOCK);

        $this->assertCount(1, $output['outofstock']);
        $this->assertSame('6', $output['outofstock'][0]['Quantity']);
        $this->assertSame([], $output['itemList']);
    }

    /**
     * @dataProvider stockBoundaryProvider
     */
    public function testGetOutput_StockBoundary(string $stock, int $ordered, string $expectedSection): void
    {
        // Stock_Items_Checker.php:84 - out of stock when stock < ordered OR stock <= 0
        $output = self::check(
            [self::order('ZZTEST-001', $ordered)],
            [['item_code' => 'ZZTEST-001', 'description' => 'd', 'quantity' => $stock]]
        );

        $this->assertCount(1, $output[$expectedSection]);
    }

    public function stockBoundaryProvider(): array
    {
        return [
            'stock below ordered' => ['1', 2, 'outofstock'],
            'stock equal to ordered' => ['2', 2, 'itemList'],
            'stock above ordered' => ['3', 2, 'itemList'],
            'zero stock' => ['0', 1, 'outofstock'],
            'negative stock' => ['-2', 1, 'outofstock'],
        ];
    }

    public function testGetOutput_EscapesValuesAndReturnsEveryCellAsString(): void
    {
        $output = self::check(
            [self::order('ZZTEST-001', 2)],
            [['item_code' => 'ZZTEST-001', 'description' => 'Seat Cover "Deluxe" <5 pcs> & more', 'quantity' => '7']]
        );

        $this->assertSame([
            'Item Code' => 'ZZTEST-001',
            'Description' => 'Seat Cover &quot;Deluxe&quot; &lt;5 pcs&gt; &amp; more',
            'Quantity' => '2',
            'Stock' => '7',
        ], $output['itemList'][0]);
    }

    public function testSetItemsCode_BlankDetailsAreSkipped(): void
    {
        $output = self::check([['details' => '   '], self::order('ZZTEST-001', 1)], self::STOCK);

        $this->assertCount(1, $output['itemList']);
        $this->assertSame([], $output['notfound']);
    }

    public function testSetItemsCode_ReplacesPreviousCodesOnEachCall(): void
    {
        $checker = new Stock_Items_Checker(new Shopee_Product_Info());
        $checker->setItems_Code([self::order('ZZTEST-001', 1)]);
        $checker->setItems_Code([self::order('ZZTEST-004', 1)]);

        $checker->setResult(self::STOCK);

        $output = $checker->getOutput();
        $this->assertSame([], $output['itemList']);
        $this->assertSame('ZZTEST-004', $output['outofstock'][0]['Item Code']);
    }

    public function testSetItemsCode_ChildSkuPresent_documentsCurrentBehaviour_ParentSkuIsUsed(): void
    {
        // Shopee_Product_Info.php:73 fills 'sku reference no.' with the parent SKU, so the preference
        // for the child SKU at Stock_Items_Checker.php:31-33 never sees 'ZZTEST-001-BLK'
        $cell = '[1] Product Name:A; Variation Name:Black; Price:RM 1; Quantity:1; '
            . 'Parent SKU Reference No.:ZZTEST-001; SKU Reference No.:ZZTEST-001-BLK;';

        $output = self::check([['details' => $cell]], [
            ['item_code' => 'ZZTEST-001-BLK', 'description' => 'child', 'quantity' => '9'],
            ['item_code' => 'ZZTEST-001', 'description' => 'parent', 'quantity' => '9'],
        ]);

        $this->assertSame('ZZTEST-001', $output['itemList'][0]['Item Code']);
        $this->assertSame('parent', $output['itemList'][0]['Description']);
    }

    public function testSetItemsCode_OnlyParentSku_ParentIsUsed(): void
    {
        $cell = '[1] Product Name:A; Variation Name:; Price:RM 1; Quantity:1; Parent SKU Reference No.:ZZTEST-001; SKU Reference No.:;';

        $output = self::check([['details' => $cell]], self::STOCK);

        $this->assertSame('ZZTEST-001', $output['itemList'][0]['Item Code']);
    }

    // ---------------------------------------------------------------- helpers

    private static function check(array $orders, array $stock): array
    {
        $checker = new Stock_Items_Checker(new Shopee_Product_Info());
        $checker->setItems_Code($orders);
        $checker->setResult($stock);

        return $checker->getOutput();
    }

    /** an order row whose Product Info cell names only the (child) SKU */
    private static function order(string $sku, int $quantity): array
    {
        return [
            'details' => '[1] Product Name:Test item; Variation Name:; Price:RM 10.00; Quantity:' . $quantity . '; '
                . 'Parent SKU Reference No.:; SKU Reference No.:' . $sku . ';',
        ];
    }
}
