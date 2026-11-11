<?php

declare(strict_types=1);

namespace test\Integration\Product;

use Product\Item;
use Product\ItemManager;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Product\ItemManager (src/Legacy/Product/ItemManager.php): the write side of the
 * /ItemManager page (description edits + bigseller_sku_map upsert) and its LIKE search.
 */
final class ItemManagerTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;
    /** @var ItemManager */
    private $manager;
    /** @var int */
    private $idA;
    /** @var int */
    private $idB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $this->manager = new ItemManager($this->con);
        $this->idA = $this->insertStockItem($this->code('A'), 'DESC A', 5, 'SET', 'GROUP A');
        $this->idB = $this->insertStockItem($this->code('B'), 'DESC B', 1, 'PCS', 'GROUP B');
    }

    private function code(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    private function bsSku(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-BS-' . $letter;
    }

    /** @param array<int, array<string, mixed>> $mappings */
    private function item(int $id, string $description, array $mappings = []): Item
    {
        $item = new Item($id, null, $description);
        $item->bigseller_sku_map = $mappings;

        return $item;
    }

    /** @return array<int, array<string, mixed>> bigseller_sku_map rows of an item ordered by id */
    private function mappingsOf(int $itemId): array
    {
        return $this->fetchAll('SELECT id, bigseller_sku, item_id FROM bigseller_sku_map WHERE item_id = ? ORDER BY id', 'i', $itemId);
    }

    private function descriptionOf(int $itemId): string
    {
        $rows = $this->fetchAll('SELECT description FROM stock_items WHERE id = ?', 'i', $itemId);

        return $rows[0]['description'];
    }

    // ------------------------------------------------------------------ update(): description

    public function testUpdate_ChangesDescriptionOfEachItem_LeavesOtherColumnsAlone(): void
    {
        $this->manager->update([
            $this->item($this->idA, 'DESC A EDITED'),
            $this->item($this->idB, 'DESC B EDITED'),
        ]);

        $this->assertSame('DESC A EDITED', $this->descriptionOf($this->idA));
        $this->assertSame('DESC B EDITED', $this->descriptionOf($this->idB));
        $row = $this->fetchAll('SELECT item_code, uom, item_group FROM stock_items WHERE id = ?', 'i', $this->idA);
        $this->assertSame(['item_code' => $this->code('A'), 'uom' => 'SET', 'item_group' => 'GROUP A'], $row[0]);
        $this->assertSame([], $this->mappingsOf($this->idA), 'no mapping rows created when the list is empty');
    }

    public function testUpdate_EmptyList_IsANoOp(): void
    {
        $this->manager->update([]);

        $this->assertSame('DESC A', $this->descriptionOf($this->idA));
    }

    // ------------------------------------------------------------------ update(): bigseller_sku_map upsert

    public function testUpdate_NewMappingWithNullId_InsertsBigSellerSkuRow(): void
    {
        $this->manager->update([$this->item($this->idA, 'DESC A', [['id' => null, 'bigseller_sku' => $this->bsSku('A')]])]);

        $rows = $this->mappingsOf($this->idA);
        $this->assertCount(1, $rows);
        $this->assertSame($this->bsSku('A'), $rows[0]['bigseller_sku']);
        $this->assertSame($this->idA, $rows[0]['item_id']);
        $this->assertGreaterThan(0, $rows[0]['id'], 'NULL id -> AUTO_INCREMENT assigns the key');
    }

    public function testUpdate_SameSkuTwice_DoesNotDuplicateTheMapping(): void
    {
        $item = $this->item($this->idA, 'DESC A', [['id' => null, 'bigseller_sku' => $this->bsSku('A')]]);
        $this->manager->update([$item]);
        $first = $this->mappingsOf($this->idA);

        $this->manager->update([$item]); // the form is re-submitted unchanged (id still unknown to the client)

        $this->assertSame($first, $this->mappingsOf($this->idA), 'UNIQUE(bigseller_sku, item_id) routes the 2nd insert to ON DUPLICATE KEY UPDATE');
    }

    public function testUpdate_ExistingMappingId_ChangesTheSkuInPlace(): void
    {
        $mapId = $this->insertBigSellerSkuMap($this->bsSku('OLD'), $this->idA);

        $this->manager->update([$this->item($this->idA, 'DESC A', [['id' => $mapId, 'bigseller_sku' => $this->bsSku('NEW')]])]);

        $rows = $this->mappingsOf($this->idA);
        $this->assertCount(1, $rows);
        $this->assertSame($mapId, $rows[0]['id']);
        $this->assertSame($this->bsSku('NEW'), $rows[0]['bigseller_sku']);
    }

    /** @return array<string, array{0: mixed}> */
    public function blankSkuProvider(): array
    {
        return ['empty string' => [''], 'null' => [null]];
    }

    /**
     * @dataProvider blankSkuProvider
     * @param mixed $blank
     */
    public function testUpdate_BlankSku_IsSkipped($blank): void
    {
        $this->manager->update([$this->item($this->idA, 'DESC A', [['id' => null, 'bigseller_sku' => $blank]])]);

        $this->assertSame([], $this->mappingsOf($this->idA));
    }

    public function testUpdate_WhitespaceSku_IsStoredVerbatim_documentsCurrentBehaviour(): void
    {
        // only '' and null are filtered (Product/ItemManager.php:37); ' ' passes through untrimmed
        $this->manager->update([$this->item($this->idA, 'DESC A', [['id' => null, 'bigseller_sku' => ' ']])]);

        $rows = $this->mappingsOf($this->idA);
        $this->assertCount(1, $rows);
        $this->assertSame(' ', $rows[0]['bigseller_sku'], 'VARCHAR keeps the space: a useless mapping row is created');
    }

    /**
     * The upsert keys on the PRIMARY KEY first: a posted `id` that belongs to ANOTHER item's mapping re-points that
     * row (ON DUPLICATE KEY UPDATE ... item_id = ?). request_handler/ItemManager.php:28-33 posts PHP's auto-index
     * (0, 1, 2, ...) as the id of every NEW mapping, so saving the page can hijack mappings with id 1, 2, ...
     */
    public function testUpdate_IdOfAnotherItemsMapping_HijacksThatMapping_documentsCurrentBehaviour(): void
    {
        $mapOfA = $this->insertBigSellerSkuMap($this->bsSku('A'), $this->idA);

        $this->manager->update([$this->item($this->idB, 'DESC B', [['id' => $mapOfA, 'bigseller_sku' => $this->bsSku('B')]])]);

        $this->assertSame([], $this->mappingsOf($this->idA), 'A lost its mapping');
        $rows = $this->mappingsOf($this->idB);
        $this->assertCount(1, $rows);
        $this->assertSame($mapOfA, $rows[0]['id']);
        $this->assertSame($this->bsSku('B'), $rows[0]['bigseller_sku']);
    }

    // ------------------------------------------------------------------ getItemLikeItemCode()

    public function testGetItemLikeItemCode_ContainsMatch_ReturnsItemsWithMappingPlaceholder(): void
    {
        $term = ' ' . $this->suffix . ' '; // trimmed, matched with LIKE '%term%' (contains, not prefix)
        $items = $this->manager->getItemLikeItemCode($term);

        $this->assertCount(2, $items);
        $this->assertContainsOnlyInstancesOf(Item::class, $items);
        $this->assertSame([$this->idA, $this->idB], [$items[0]->itemId, $items[1]->itemId], 'ordered by stock_items.id');
        $this->assertSame($this->code('A'), $items[0]->code);
        $this->assertSame('DESC A', $items[0]->description);
        $this->assertSame('SET', $items[0]->uom);
        $this->assertSame('GROUP A', $items[0]->group);
        $this->assertSame(
            [['id' => null, 'bigseller_sku' => null]],
            $items[0]->bigseller_sku_map,
            'unmapped item still carries one placeholder mapping (LEFT JOIN row) - ItemEditor relies on [0]'
        );
    }

    public function testGetItemLikeItemCode_ItemWithTwoMappings_IsReturnedTwice_documentsCurrentBehaviour(): void
    {
        $this->insertBigSellerSkuMap($this->bsSku('1'), $this->idA);
        $this->insertBigSellerSkuMap($this->bsSku('2'), $this->idA);

        $term = $this->code('A');
        $items = $this->manager->getItemLikeItemCode($term);

        $this->assertCount(2, $items, 'one Item object per join row, not one per stock item');
        $this->assertSame($this->idA, $items[0]->itemId);
        $this->assertSame($this->idA, $items[1]->itemId);
        $skus = [$items[0]->bigseller_sku_map[0]['bigseller_sku'], $items[1]->bigseller_sku_map[0]['bigseller_sku']];
        sort($skus);
        $this->assertSame([$this->bsSku('1'), $this->bsSku('2')], $skus);
    }

    public function testGetItemLikeItemCode_NoMatch_ReturnsEmptyList(): void
    {
        $term = $this->code('NOPE');

        $this->assertSame([], $this->manager->getItemLikeItemCode($term));
    }

    public function testGetItemLikeItemCode_MoreThan200Matches_IsCappedAt200(): void
    {
        $stmt = $this->con->prepare('INSERT INTO stock_items (item_code, description, uom, item_group, image) VALUES (?, \'bulk\', \'\', \'\', \'\')');
        $code = '';
        $stmt->bind_param('s', $code);
        for ($i = 0; $i < 199; ++$i) { // + A + B inserted in setUp = 201 matches
            $code = self::FIXTURE_PREFIX . $this->suffix . '-L' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $stmt->execute();
        }
        $stmt->close();

        $term = $this->suffix;
        $this->assertCount(200, $this->manager->getItemLikeItemCode($term), 'LIMIT 200 (Product/ItemManager.php:56)');
    }
}
