<?php

declare(strict_types=1);

namespace test\Integration;

use Exception\HttpException;
use mysqli_sql_exception;
use Stock_Item;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Stock_Item (src/Legacy/Stock_Item.php), the model behind /itemdetail.
 * handleRequest() is driven with $_POST-shaped arrays using the field names of view/itemdetail.html.php.
 */
final class StockItemTest extends DatabaseTestCase
{
    /** stock_items.id is a signed INT; its maximum is never allocated by the seed */
    const MISSING_ID = 2147483647;

    /** @var string */
    private $suffix;
    /** @var Stock_Item */
    private $handler;
    /** @var int */
    private $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $this->handler = new Stock_Item($this->con);
        $this->itemId = $this->insertStockItem($this->code('A'), 'DESC A', 3, 'SET', 'GROUP A');
    }

    private function code(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    private function bsSku(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-BS-' . $letter;
    }

    /** @return array<int, string> bigseller skus of the item ordered by mapping id */
    private function skusOf(int $itemId): array
    {
        return array_column(
            $this->fetchAll('SELECT bigseller_sku FROM bigseller_sku_map WHERE item_id = ? ORDER BY id', 'i', $itemId),
            'bigseller_sku'
        );
    }

    // ------------------------------------------------------------------ getItem / getBigSellerSku

    public function testGetItem_ExistingId_ReturnsTheStockItemsRow(): void
    {
        $this->assertSame(
            [
                'id' => $this->itemId,
                'item_code' => $this->code('A'),
                'description' => 'DESC A',
                'uom' => 'SET',
                'item_group' => 'GROUP A',
                'image' => '',
            ],
            $this->handler->getItem($this->itemId)
        );
    }

    public function testGetBigSellerSku_ReturnsMappingRowsOfTheItemOnly(): void
    {
        $other = $this->insertStockItem($this->code('B'), 'DESC B', 1);
        $map1 = $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);
        $map2 = $this->insertBigSellerSkuMap($this->bsSku('2'), $this->itemId);
        $this->insertBigSellerSkuMap($this->bsSku('OTHER'), $other);

        $rows = $this->handler->getBigSellerSku($this->itemId);
        usort($rows, function (array $a, array $b): int {
            return $a['id'] <=> $b['id'];
        });

        $this->assertSame(
            [
                ['id' => $map1, 'bigseller_sku' => $this->bsSku('1'), 'item_id' => $this->itemId],
                ['id' => $map2, 'bigseller_sku' => $this->bsSku('2'), 'item_id' => $this->itemId],
            ],
            $rows
        );
        $this->assertSame([], $this->handler->getBigSellerSku(self::MISSING_ID));
    }

    /**
     * @group known-bug
     * Stock_Item.php:26 returns `$result[0]` of an empty result set -> "Undefined offset" notice, then a TypeError
     * because null does not satisfy the `: array` return type. Intended: a clean 404 for an unknown item id
     * (view/itemdetail.html.php:2 feeds $_GET['id'] straight into getItem()).
     */
    public function testGetItem_MissingId_ThrowsHttpNotFound(): void
    {
        $this->expectException(HttpException::class);

        $this->handler->getItem(self::MISSING_ID);
    }

    public function testGetItem_MissingId_FailsWithTypeError_documentsCurrentBehaviour(): void
    {
        $this->expectException(\TypeError::class);

        @$this->handler->getItem(self::MISSING_ID); // `@` keeps PHPUnit from converting the notice first
    }

    // ------------------------------------------------------------------ handleRequest: dispatch

    public function testHandleRequest_EmptyPost_DoesNothing(): void
    {
        $this->handler->handleRequest([]);

        $this->assertSame('DESC A', $this->handler->getItem($this->itemId)['description']);
    }

    public function testHandleRequest_UnknownAction_DoesNothing(): void
    {
        $this->handler->handleRequest(['request' => 'stock_item.delete', 'id' => $this->itemId]);

        $this->assertSame('DESC A', $this->handler->getItem($this->itemId)['description']);
    }

    // ------------------------------------------------------------------ handleRequest: stock_item.update

    public function testHandleRequest_StockItemUpdate_RewritesAllFourEditableColumns(): void
    {
        $this->handler->handleRequest([
            'request' => 'stock_item.update',
            'id' => (string) $this->itemId,
            'item_code' => $this->code('A2'),
            'description' => 'DESC A <edited> & "quoted"',
            'uom' => 'PCS',
            'item_group' => 'GROUP Z',
        ]);

        $this->assertSame(
            [
                'id' => $this->itemId,
                'item_code' => $this->code('A2'),
                'description' => 'DESC A <edited> & "quoted"',
                'uom' => 'PCS',
                'item_group' => 'GROUP Z',
                'image' => '',
            ],
            $this->handler->getItem($this->itemId),
            'stored raw - escaping is the view\'s job (text())'
        );
    }

    // ------------------------------------------------------------------ handleRequest: bigseller_sku_map.update

    public function testHandleRequest_BigSellerUpdate_InsertsOnlyCheckedAddedRows(): void
    {
        $this->handler->handleRequest([
            'request' => 'bigseller_sku_map.update',
            'item_id' => (string) $this->itemId,
            'added' => [
                0 => ['_enable' => 'on', 'bigseller_sku' => $this->bsSku('NEW1')],
                1 => ['bigseller_sku' => $this->bsSku('UNCHECKED')],      // checkbox not ticked
                2 => ['_enable' => 'off', 'bigseller_sku' => $this->bsSku('OFF')],
                3 => ['_enable' => 'on', 'bigseller_sku' => $this->bsSku('NEW2')],
            ],
        ]);

        $this->assertSame([$this->bsSku('NEW1'), $this->bsSku('NEW2')], $this->skusOf($this->itemId));
    }

    public function testHandleRequest_BigSellerUpdate_RenamesOnlyCheckedExistingRows(): void
    {
        $map1 = $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);
        $map2 = $this->insertBigSellerSkuMap($this->bsSku('2'), $this->itemId);

        $this->handler->handleRequest([
            'request' => 'bigseller_sku_map.update',
            'item_id' => (string) $this->itemId,
            'x' => [
                $map1 => ['_enable' => 'on', 'bigseller_sku' => $this->bsSku('1-RENAMED')],
                $map2 => ['bigseller_sku' => $this->bsSku('2-IGNORED')],
            ],
        ]);

        $this->assertSame([$this->bsSku('1-RENAMED'), $this->bsSku('2')], $this->skusOf($this->itemId));
    }

    public function testHandleRequest_BigSellerUpdate_WithoutAddedOrX_IsANoOp(): void
    {
        $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);

        $this->handler->handleRequest(['request' => 'bigseller_sku_map.update', 'item_id' => (string) $this->itemId]);

        $this->assertSame([$this->bsSku('1')], $this->skusOf($this->itemId));
    }

    public function testUpdateBigSellerSku_DuplicateSkuForSameItem_ThrowsHttp400(): void
    {
        $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);
        $map2 = $this->insertBigSellerSkuMap($this->bsSku('2'), $this->itemId);

        try {
            $this->handler->updateBigSellerSku([$map2 => ['bigseller_sku' => $this->bsSku('1')]]);
            $this->fail('expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('Duplicate entry for "' . $this->bsSku('1') . '"', $e->getMessage());
        }
        $this->assertSame([$this->bsSku('1'), $this->bsSku('2')], $this->skusOf($this->itemId), 'nothing changed');
    }

    public function testUpdateBigSellerSku_SameSkuOnAnotherItem_IsAllowed(): void
    {
        // UNIQUE is (bigseller_sku, item_id): the same BigSeller sku may map to two different stock items
        $other = $this->insertStockItem($this->code('B'), 'DESC B', 1);
        $this->insertBigSellerSkuMap($this->bsSku('SHARED'), $other);
        $map = $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);

        $this->handler->updateBigSellerSku([$map => ['bigseller_sku' => $this->bsSku('SHARED')]]);

        $this->assertSame([$this->bsSku('SHARED')], $this->skusOf($this->itemId));
    }

    /**
     * Stock_Item.php:140-146 catches every mysqli_sql_exception and re-throws ONLY error 1062; any other
     * failure (here 1406 "Data too long", bigseller_sku is varchar(32) under STRICT mode) is swallowed and the
     * request "succeeds" without saving.
     */
    public function testUpdateBigSellerSku_SkuLongerThanColumn_IsSilentlyDropped_documentsCurrentBehaviour(): void
    {
        $map = $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);

        $this->handler->updateBigSellerSku([$map => ['bigseller_sku' => str_repeat('Z', 40)]]);

        $this->assertSame([$this->bsSku('1')], $this->skusOf($this->itemId), 'row unchanged, no error surfaced');
    }

    /**
     * @group known-bug
     * Intended: a failed UPDATE that is not a duplicate must still surface (Stock_Item.php:140-146 swallows it).
     */
    public function testUpdateBigSellerSku_SkuLongerThanColumn_SurfacesTheDatabaseError(): void
    {
        $map = $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);

        $this->expectException(\Throwable::class);

        $this->handler->updateBigSellerSku([$map => ['bigseller_sku' => str_repeat('Z', 40)]]);
    }

    public function testAddBigSellerSku_DuplicateSku_LeaksRawMysqliException_documentsCurrentBehaviour(): void
    {
        // unlike updateBigSellerSku(), addBigSellerSku() (Stock_Item.php:102-123) has no 1062 -> 400 translation
        $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);

        $this->expectException(mysqli_sql_exception::class);
        $this->expectExceptionCode(1062);

        $this->handler->addBigSellerSku([['bigseller_sku' => $this->bsSku('1')]], $this->itemId);
    }

    /**
     * @group known-bug
     * Intended: adding a duplicate BigSeller sku is a user error -> HttpException(400) like the update path.
     */
    public function testAddBigSellerSku_DuplicateSku_ThrowsHttp400(): void
    {
        $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);

        $this->expectException(HttpException::class);

        $this->handler->addBigSellerSku([['bigseller_sku' => $this->bsSku('1')]], $this->itemId);
    }

    // ------------------------------------------------------------------ handleRequest: bigseller_sku_map.remove

    public function testHandleRequest_BigSellerRemove_DeletesOnlyCheckedRows(): void
    {
        $map1 = $this->insertBigSellerSkuMap($this->bsSku('1'), $this->itemId);
        $map2 = $this->insertBigSellerSkuMap($this->bsSku('2'), $this->itemId);
        $map3 = $this->insertBigSellerSkuMap($this->bsSku('3'), $this->itemId);

        $this->handler->handleRequest([
            'request' => 'bigseller_sku_map.remove',
            'item_id' => (string) $this->itemId,
            'x' => [
                $map1 => ['_enable' => 'on', 'bigseller_sku' => $this->bsSku('1')],
                $map2 => ['bigseller_sku' => $this->bsSku('2')],
                $map3 => ['_enable' => 'on', 'bigseller_sku' => $this->bsSku('3')],
            ],
        ]);

        $this->assertSame([$this->bsSku('2')], $this->skusOf($this->itemId));
    }

    public function testFilterForSelectedOnly_KeepsKeysOfCheckedRowsOnly(): void
    {
        $this->assertSame(
            [7 => ['_enable' => 'on'], 9 => ['_enable' => 'on', 'bigseller_sku' => 'x']],
            $this->handler->filterForSelectedOnly([
                7 => ['_enable' => 'on'],
                8 => ['_enable' => 'ON'],
                9 => ['_enable' => 'on', 'bigseller_sku' => 'x'],
                10 => ['bigseller_sku' => 'y'],
            ])
        );
    }
}
