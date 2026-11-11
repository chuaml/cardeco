<?php

declare(strict_types=1);

namespace test\Integration\Orders;

use Exception;
use Orders\SkuManager;
use test\Support\DatabaseTestCase;
use UnexpectedValueException;

/**
 * Characterization of Orders\SkuManager: the `seller_sku` alias table (sku -> stock_items.item_code).
 * insert() takes a map [sku => itemCode]; delete() takes a list of skus.
 */
final class SkuManagerTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    private function itemCode(string $tag): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $tag;
    }

    private function sku(string $tag): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-SKU-' . $tag;
    }

    /** @return array<int, array<string, string>> [itemCode, sku] rows whose sku starts with this test's prefix */
    private function mySellerSkus(): array
    {
        return $this->fetchAll(
            'SELECT itemCode, sku FROM seller_sku WHERE sku LIKE ? ORDER BY sku',
            's',
            self::FIXTURE_PREFIX . $this->suffix . '-%'
        );
    }

    // ------------------------------------------------------------------ insert()

    public function testInsert_AddsOneSellerSkuRowPerEntryForExistingItemCodes(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $this->insertStockItem($this->itemCode('B'), 'ZZTEST item B', 1);
        $data = [
            $this->sku('1') => $this->itemCode('A'),
            $this->sku('2') => $this->itemCode('B'),
        ];

        (new SkuManager())->insert($this->con, $data);

        $this->assertSame([
            ['itemCode' => $this->itemCode('A'), 'sku' => $this->sku('1')],
            ['itemCode' => $this->itemCode('B'), 'sku' => $this->sku('2')],
        ], $this->mySellerSkus());
    }

    public function testInsert_TrimsSkuAndItemCode(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $data = ['  ' . $this->sku('1') . "\t" => ' ' . $this->itemCode('A') . ' '];

        (new SkuManager())->insert($this->con, $data);

        $this->assertSame([['itemCode' => $this->itemCode('A'), 'sku' => $this->sku('1')]], $this->mySellerSkus());
    }

    public function testInsert_EmptyInput_IsANoOp(): void
    {
        $data = [];

        (new SkuManager())->insert($this->con, $data);

        $this->assertSame([], $this->mySellerSkus());
    }

    public function testInsert_UnknownItemCode_ThrowsAndInsertsNothing(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $data = [
            $this->sku('1') => $this->itemCode('A'),
            $this->sku('2') => $this->itemCode('MISSING'),
        ];

        try {
            (new SkuManager())->insert($this->con, $data);
            $this->fail('expected an exception for an unknown item code');
        } catch (Exception $e) {
            $this->assertSame("item code doesn't exists.", $e->getMessage());
        }
        $this->assertSame([], $this->mySellerSkus(), 'the whole batch is validated before any row is written');
    }

    public function testInsert_DuplicateSku_ThrowsAndInsertsNothingFromTheBatch(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $this->insertStockItem($this->itemCode('B'), 'ZZTEST item B', 1);
        $this->insertSellerSku($this->itemCode('A'), $this->sku('1'));
        $data = [
            $this->sku('1') => $this->itemCode('B'), // already taken by item A
            $this->sku('2') => $this->itemCode('B'),
        ];

        try {
            (new SkuManager())->insert($this->con, $data);
            $this->fail('expected an exception for a duplicate sku');
        } catch (Exception $e) {
            $this->assertSame('sku exists', $e->getMessage());
        }
        $this->assertSame([['itemCode' => $this->itemCode('A'), 'sku' => $this->sku('1')]], $this->mySellerSkus());
    }

    public function testInsert_BlankSku_ThrowsUnexpectedValue(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $data = ['   ' => $this->itemCode('A')];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('sku is empty.');
        (new SkuManager())->insert($this->con, $data);
    }

    public function testInsert_BlankItemCode_ThrowsUnexpectedValue(): void
    {
        $data = [$this->sku('1') => '  '];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('itemCode is empty.');
        (new SkuManager())->insert($this->con, $data);
    }

    /**
     * isItemCodeExists() compares COUNT(DISTINCT item_code) with the size of the (non-distinct) list it was given
     * (SkuManager.php:38,54 called from :114 with array_values($data)), so two aliases for the same item code in
     * one batch are rejected as "item code doesn't exists.".
     */
    public function testInsert_TwoSkusForTheSameItemCodeInOneBatch_Throws_documentsCurrentBehaviour(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $data = [
            $this->sku('1') => $this->itemCode('A'),
            $this->sku('2') => $this->itemCode('A'),
        ];

        try {
            (new SkuManager())->insert($this->con, $data);
            $this->fail('current behaviour: the batch is rejected');
        } catch (Exception $e) {
            $this->assertSame("item code doesn't exists.", $e->getMessage());
        }
        $this->assertSame([], $this->mySellerSkus());
    }

    /**
     * @group known-bug
     * SkuManager.php:38,54,114 - several aliases for one existing item code must be insertable in one call.
     */
    public function testInsert_TwoSkusForTheSameItemCodeInOneBatch_InsertsBoth(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $data = [
            $this->sku('1') => $this->itemCode('A'),
            $this->sku('2') => $this->itemCode('A'),
        ];

        (new SkuManager())->insert($this->con, $data);

        $this->assertSame([
            ['itemCode' => $this->itemCode('A'), 'sku' => $this->sku('1')],
            ['itemCode' => $this->itemCode('A'), 'sku' => $this->sku('2')],
        ], $this->mySellerSkus());
    }

    // ------------------------------------------------------------------ isSkuExists() / isItemCodeExists()

    public function testIsSkuExists_IsTrueWhenAnyOfTheGivenSkusExists(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $this->insertSellerSku($this->itemCode('A'), $this->sku('1'));
        $m = new SkuManager();

        $this->assertTrue($m->isSkuExists($this->con, [$this->sku('1')]));
        $this->assertTrue($m->isSkuExists($this->con, [$this->sku('NOPE'), $this->sku('1')]));
        $this->assertFalse($m->isSkuExists($this->con, [$this->sku('NOPE')]));
    }

    public function testIsSkuExists_EmptyInput_ThrowsUnexpectedValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new SkuManager())->isSkuExists($this->con, []);
    }

    public function testIsItemCodeExists_IsTrueOnlyWhenEveryGivenItemCodeExists(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $this->insertStockItem($this->itemCode('B'), 'ZZTEST item B', 1);
        $m = new SkuManager();

        $this->assertTrue($m->isItemCodeExists($this->con, [$this->itemCode('A')]));
        $this->assertTrue($m->isItemCodeExists($this->con, [$this->itemCode('A'), $this->itemCode('B')]));
        $this->assertFalse($m->isItemCodeExists($this->con, [$this->itemCode('A'), $this->itemCode('NOPE')]));
        $this->assertFalse($m->isItemCodeExists($this->con, [$this->itemCode('NOPE')]));
    }

    public function testIsItemCodeExists_EmptyInput_ThrowsUnexpectedValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new SkuManager())->isItemCodeExists($this->con, []);
    }

    // ------------------------------------------------------------------ delete()

    public function testDelete_RemovesOnlyTheGivenSkus(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $this->insertSellerSku($this->itemCode('A'), $this->sku('1'));
        $this->insertSellerSku($this->itemCode('A'), $this->sku('2'));
        $this->insertSellerSku($this->itemCode('A'), $this->sku('3'));
        $skus = [$this->sku('1'), $this->sku('3'), $this->sku('NOPE')];

        (new SkuManager())->delete($this->con, $skus);

        $this->assertSame([['itemCode' => $this->itemCode('A'), 'sku' => $this->sku('2')]], $this->mySellerSkus());
    }

    public function testDelete_EmptyInput_IsANoOp(): void
    {
        $this->insertStockItem($this->itemCode('A'), 'ZZTEST item A', 1);
        $this->insertSellerSku($this->itemCode('A'), $this->sku('1'));
        $skus = [];

        (new SkuManager())->delete($this->con, $skus);

        $this->assertCount(1, $this->mySellerSkus());
    }
}
