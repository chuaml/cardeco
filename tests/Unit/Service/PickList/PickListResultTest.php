<?php

declare(strict_types=1);

namespace test\Unit\Service\PickList;

use App\Service\PickList\PickListResult;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of App\Service\PickList\PickListResult (src/Service/PickList/PickListResult.php): the ordered
 * SKUs grouped by PickListBuilder, classified against the stock on hand.
 *
 * $keyedSku is sku => list of order rows (one row per unit); each row already carries 'stock' (int, or null when
 * the SKU is unknown to stock_items).
 */
final class PickListResultTest extends TestCase
{
    // ---------------------------------------------------------------- fromKeyedSku

    public function testFromKeyedSku_splitsSkusIntoFoundAndNotFoundByStock(): void
    {
        $result = PickListResult::fromKeyedSku([
            'ZZTEST-001' => [$this->row('ZZTEST-001', 'ZZ100', 5)],
            'ZZTEST-404' => [$this->row('ZZTEST-404', 'ZZ200', null)],
        ]);

        $this->assertSame(['ZZTEST-001'], array_column($result->found, 'sku'));
        $this->assertSame(['ZZTEST-404'], array_column($result->notFound, 'sku'));
    }

    public function testFromKeyedSku_quantityIsTheNumberOfRowsOfTheSku(): void
    {
        $result = PickListResult::fromKeyedSku([
            'ZZTEST-001' => [
                $this->row('ZZTEST-001', 'ZZ100', 5),
                $this->row('ZZTEST-001', 'ZZ100', 5),
                $this->row('ZZTEST-001', 'ZZ300', 5),
            ],
            'ZZTEST-002' => [$this->row('ZZTEST-002', 'ZZ200', 1)],
            'ZZTEST-404' => [$this->row('ZZTEST-404', 'ZZ200', null), $this->row('ZZTEST-404', 'ZZ400', null)],
        ]);

        $this->assertSame([3, 1], array_column($result->found, 'quantity'));
        $this->assertSame([2], array_column($result->notFound, 'quantity'));
    }

    public function testFromKeyedSku_entryIsTheFirstRowOfTheSkuPlusQuantity(): void
    {
        $first = $this->row('ZZTEST-001', 'ZZ100', 5, ['date' => '15-Jan-2024']);
        $second = $this->row('ZZTEST-001', 'ZZ900', 5, ['date' => '16-Jan-2024']);

        $result = PickListResult::fromKeyedSku(['ZZTEST-001' => [$first, $second]]);

        $this->assertSame($first + ['quantity' => 2], $result->found[0]);
        $this->assertSame('ZZ100', $result->found[0]['orderNum']);
    }

    public function testFromKeyedSku_keepsFirstAppearanceOrder(): void
    {
        $result = PickListResult::fromKeyedSku([
            'ZZTEST-003' => [$this->row('ZZTEST-003', 'ZZ100', 1)],
            'ZZTEST-404' => [$this->row('ZZTEST-404', 'ZZ100', null)],
            'ZZTEST-001' => [$this->row('ZZTEST-001', 'ZZ200', 1)],
            'ZZTEST-002' => [$this->row('ZZTEST-002', 'ZZ300', 1)],
            'ZZTEST-405' => [$this->row('ZZTEST-405', 'ZZ300', null)],
        ]);

        $this->assertSame(['ZZTEST-003', 'ZZTEST-001', 'ZZTEST-002'], array_column($result->found, 'sku'));
        $this->assertSame(['ZZTEST-404', 'ZZTEST-405'], array_column($result->notFound, 'sku'));
        $this->assertSame([0, 1, 2], array_keys($result->found), 'lists, not sku-keyed maps');
    }

    public function testFromKeyedSku_keepsTheKeyedSkuInput(): void
    {
        $keyed = ['ZZTEST-001' => [$this->row('ZZTEST-001', 'ZZ100', 5)]];

        $result = PickListResult::fromKeyedSku($keyed);

        $this->assertSame($keyed, $result->keyedSku);
    }

    public function testFromKeyedSku_emptyInput_everythingIsEmpty(): void
    {
        $result = PickListResult::fromKeyedSku([]);

        $this->assertSame([], $result->keyedSku);
        $this->assertSame([], $result->found);
        $this->assertSame([], $result->notFound);
        $this->assertSame([], $result->toRestock());
        $this->assertSame([], $result->toCollect());
    }

    public function testFromKeyedSku_stockZero_isFoundNotNotFound(): void
    {
        $result = PickListResult::fromKeyedSku(['ZZTEST-001' => [$this->row('ZZTEST-001', 'ZZ100', 0)]]);

        $this->assertCount(1, $result->found);
        $this->assertSame([], $result->notFound);
    }

    public function testFromKeyedSku_existingQuantityKeyInTheRow_isOverwrittenByTheRowCount(): void
    {
        $result = PickListResult::fromKeyedSku([
            'ZZTEST-001' => [$this->row('ZZTEST-001', 'ZZ100', 5, ['quantity' => 99])],
        ]);

        $this->assertSame(1, $result->found[0]['quantity']);
    }

    public function testFromKeyedSku_numericSkuKeys_areSupported(): void
    {
        // PHP turns numeric-string array keys into ints; the sku inside the row stays a string
        $result = PickListResult::fromKeyedSku([
            '1001' => [$this->row('1001', 'ZZ100', 2)],
        ]);

        $this->assertSame('1001', $result->found[0]['sku']);
        $this->assertSame([1001], array_keys($result->keyedSku));
    }

    // ---------------------------------------------------------------- toRestock / toCollect

    public function testToRestock_containsSkusWhoseOrderedQuantityExceedsStock(): void
    {
        $result = $this->resultWithQuantityAndStock([
            'ZZTEST-001' => [3, 2],
            'ZZTEST-002' => [1, 10],
            'ZZTEST-003' => [5, 0],
        ]);

        $this->assertSame(['ZZTEST-001', 'ZZTEST-003'], array_column($result->toRestock(), 'sku'));
    }

    public function testToCollect_containsSkusWhoseOrderedQuantityIsBelowStock(): void
    {
        $result = $this->resultWithQuantityAndStock([
            'ZZTEST-001' => [3, 2],
            'ZZTEST-002' => [1, 10],
            'ZZTEST-003' => [5, 0],
        ]);

        $this->assertSame(['ZZTEST-002'], array_column($result->toCollect(), 'sku'));
    }

    public function testToRestockAndToCollect_quantityEqualsStock_appearsInBothByDesign(): void
    {
        // PickListResult.php toRestock() uses >= and toCollect() uses <= (legacy rule kept verbatim)
        $result = $this->resultWithQuantityAndStock(['ZZTEST-001' => [4, 4]]);

        $this->assertSame(['ZZTEST-001'], array_column($result->toRestock(), 'sku'));
        $this->assertSame(['ZZTEST-001'], array_column($result->toCollect(), 'sku'));
    }

    public function testToRestock_stockZeroAndQuantityZero_documentsCurrentBehaviour_isRestocked(): void
    {
        // not producible by fromKeyedSku() (a sku always has >= 1 row) but shows the inclusive rule
        $result = new PickListResult();
        $result->found = [['sku' => 'ZZTEST-001', 'quantity' => 0, 'stock' => 0]];

        $this->assertCount(1, $result->toRestock());
        $this->assertCount(1, $result->toCollect());
    }

    public function testToRestockAndToCollect_neverContainNotFoundSkus(): void
    {
        $result = PickListResult::fromKeyedSku([
            'ZZTEST-404' => [$this->row('ZZTEST-404', 'ZZ100', null)],
        ]);

        $this->assertSame([], $result->toRestock());
        $this->assertSame([], $result->toCollect());
    }

    public function testToRestockAndToCollect_returnZeroIndexedListsPreservingOrder(): void
    {
        $result = $this->resultWithQuantityAndStock([
            'ZZTEST-001' => [1, 10], // collect
            'ZZTEST-002' => [9, 1],  // restock
            'ZZTEST-003' => [2, 10], // collect
            'ZZTEST-004' => [9, 1],  // restock
        ]);

        $restock = $result->toRestock();
        $collect = $result->toCollect();

        $this->assertSame([0, 1], array_keys($restock));
        $this->assertSame(['ZZTEST-002', 'ZZTEST-004'], array_column($restock, 'sku'));
        $this->assertSame([0, 1], array_keys($collect));
        $this->assertSame(['ZZTEST-001', 'ZZTEST-003'], array_column($collect, 'sku'));
    }

    public function testToRestock_entriesKeepTheFullRowWithQuantityAndStock(): void
    {
        $result = $this->resultWithQuantityAndStock(['ZZTEST-001' => [3, 2]]);

        $entry = $result->toRestock()[0];

        $this->assertSame('ZZTEST-001', $entry['sku']);
        $this->assertSame(3, $entry['quantity']);
        $this->assertSame(2, $entry['stock']);
        $this->assertSame('ZZ100', $entry['orderNum']);
    }

    public function testToRestock_numericStringStock_isComparedNumerically(): void
    {
        $result = PickListResult::fromKeyedSku([
            'ZZTEST-001' => [$this->row('ZZTEST-001', 'ZZ100', '10'), $this->row('ZZTEST-001', 'ZZ100', '10')],
            'ZZTEST-002' => [$this->row('ZZTEST-002', 'ZZ100', '1'), $this->row('ZZTEST-002', 'ZZ100', '1')],
        ]);

        $this->assertSame(['ZZTEST-002'], array_column($result->toRestock(), 'sku'));
        $this->assertSame(['ZZTEST-001'], array_column($result->toCollect(), 'sku'));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * One order row (one unit) as produced by the platform parsers after the stock join.
     *
     * @param int|string|null $stock
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function row(string $sku, string $orderNum, $stock, array $extra = []): array
    {
        return $extra + [
            'orderNum' => $orderNum,
            'date' => '15-Jan-2024',
            'sku' => $sku,
            'description' => 'Zz ' . $sku,
            'sellingPrice' => 10.0,
            'stock' => $stock,
        ];
    }

    /**
     * @param array<string, array<int, int>> $quantityAndStockBySku sku => [ordered quantity, stock]
     */
    private function resultWithQuantityAndStock(array $quantityAndStockBySku): PickListResult
    {
        $keyed = [];
        foreach ($quantityAndStockBySku as $sku => $pair) {
            $keyed[$sku] = array_fill(0, $pair[0], $this->row($sku, 'ZZ100', $pair[1]));
        }

        return PickListResult::fromKeyedSku($keyed);
    }
}
