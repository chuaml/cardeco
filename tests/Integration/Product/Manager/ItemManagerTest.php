<?php

declare(strict_types=1);

namespace test\Integration\Product\Manager;

use Product\Manager\ItemManager;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Product\Manager\ItemManager (src/Legacy/Product/Manager/ItemManager.php):
 * the read-side lookup used by every daily-order processor and by Report\ShippedItemReport.
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
        $this->idB = $this->insertStockItem($this->code('B'), 'DESC B', 0, 'PCS', 'GROUP B');
    }

    private function code(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function byItemCode(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['item_code']] = $row;
        }
        ksort($indexed);

        return $indexed;
    }

    // ------------------------------------------------------------------ selectByItemCode

    public function testSelectByItemCode_EmptyList_ReturnsEmptyArrayWithoutQuerying(): void
    {
        $this->assertSame([], $this->manager->selectByItemCode([]));
    }

    public function testSelectByItemCode_KnownCodes_ReturnsDescriptionAndQuantity(): void
    {
        $rows = $this->byItemCode($this->manager->selectByItemCode([$this->code('A'), $this->code('B')]));

        $this->assertSame([$this->code('A'), $this->code('B')], array_keys($rows));
        $this->assertSame(
            ['id', 'item_code', 'description', 'uom', 'item_group', 'image', 'quantity'],
            array_keys($rows[$this->code('A')]),
            'SELECT * over stock_items JOIN stock: the two `id` columns collapse into one key'
        );
        $this->assertSame($this->idA, $rows[$this->code('A')]['id']);
        $this->assertSame('DESC A', $rows[$this->code('A')]['description']);
        $this->assertSame(5, $rows[$this->code('A')]['quantity']);
        $this->assertSame('SET', $rows[$this->code('A')]['uom']);
        $this->assertSame('GROUP A', $rows[$this->code('A')]['item_group']);
        $this->assertSame(0, $rows[$this->code('B')]['quantity']);
    }

    public function testSelectByItemCode_UnknownCode_IsSilentlyIgnored(): void
    {
        $rows = $this->manager->selectByItemCode([$this->code('A'), $this->code('NOPE')]);

        $this->assertCount(1, $rows);
        $this->assertSame($this->code('A'), $rows[0]['item_code']);
    }

    public function testSelectByItemCode_DuplicateCodesInInput_ReturnOneRowPerStockItem(): void
    {
        $rows = $this->manager->selectByItemCode([$this->code('A'), $this->code('A'), $this->code('A')]);

        $this->assertCount(1, $rows, 'IN (?,?,?) semantics: duplicates in the input list do not duplicate rows');
    }

    public function testSelectByItemCode_LowercaseCode_MatchesCaseInsensitively(): void
    {
        $rows = $this->manager->selectByItemCode([strtolower($this->code('A'))]);

        $this->assertCount(1, $rows, 'utf8mb4_0900_ai_ci collation on stock_items.item_code');
        $this->assertSame($this->code('A'), $rows[0]['item_code']);
    }

    public function testSelectByItemCode_ItemWithoutStockRow_IsNotReturned(): void
    {
        // INNER JOIN stock: an orphan stock_items row (no stock quantity) is invisible to the order processors
        $stmt = $this->con->prepare('INSERT INTO stock_items (item_code, description, uom, item_group, image) VALUES (?, \'orphan\', \'\', \'\', \'\')');
        $orphan = $this->code('ORPHAN');
        $stmt->bind_param('s', $orphan);
        $stmt->execute();
        $stmt->close();

        $this->assertSame([], $this->manager->selectByItemCode([$orphan]));
    }

    // ------------------------------------------------------------------ selectByItemCode_withBigSellerSku

    public function testSelectByItemCode_withBigSellerSku_EmptyList_ReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->manager->selectByItemCode_withBigSellerSku([]));
    }

    public function testSelectByItemCode_withBigSellerSku_MatchesByItemCodeOrByBigSellerSku(): void
    {
        $bsSku = self::FIXTURE_PREFIX . $this->suffix . '-BS-A';
        $this->insertBigSellerSkuMap($bsSku, $this->idA);

        // A is requested by its BigSeller sku, B by its item code
        $rows = $this->byItemCode($this->manager->selectByItemCode_withBigSellerSku([$bsSku, $this->code('B')]));

        $this->assertSame([$this->code('A'), $this->code('B')], array_keys($rows));
        $this->assertArrayHasKey('bigseller_sku', $rows[$this->code('A')]);
        $this->assertSame($bsSku, $rows[$this->code('A')]['bigseller_sku']);
        $this->assertSame('DESC A', $rows[$this->code('A')]['description']);
        $this->assertSame(5, $rows[$this->code('A')]['quantity']);
        $this->assertNull($rows[$this->code('B')]['bigseller_sku'], 'LEFT JOIN: unmapped item has a NULL bigseller_sku');
        $this->assertSame(0, $rows[$this->code('B')]['quantity']);
    }

    public function testSelectByItemCode_withBigSellerSku_ItemWithTwoMappings_ReturnsOneRowPerMapping(): void
    {
        $this->insertBigSellerSkuMap(self::FIXTURE_PREFIX . $this->suffix . '-BS-1', $this->idA);
        $this->insertBigSellerSkuMap(self::FIXTURE_PREFIX . $this->suffix . '-BS-2', $this->idA);

        $rows = $this->manager->selectByItemCode_withBigSellerSku([$this->code('A')]);

        $this->assertCount(2, $rows);
        $skus = array_column($rows, 'bigseller_sku');
        sort($skus);
        $this->assertSame([self::FIXTURE_PREFIX . $this->suffix . '-BS-1', self::FIXTURE_PREFIX . $this->suffix . '-BS-2'], $skus);
    }

    public function testSelectByItemCode_withBigSellerSku_UnknownSku_IsIgnored(): void
    {
        $this->assertSame([], $this->manager->selectByItemCode_withBigSellerSku([$this->code('NOPE')]));
    }

    /**
     * `SELECT *` over stock_items si JOIN stock s LEFT JOIN bigseller_sku_map b yields three `id` columns;
     * mysqli's assoc fetch keeps the LAST one, i.e. bigseller_sku_map.id - NOT the stock item id.
     * Callers must use `item_id` for the stock item id (Product/Manager/ItemManager.php:45-49).
     */
    public function testSelectByItemCode_withBigSellerSku_IdColumnIsTheMappingId_documentsCurrentBehaviour(): void
    {
        $mapId = $this->insertBigSellerSkuMap(self::FIXTURE_PREFIX . $this->suffix . '-BS-A', $this->idA);

        $rows = $this->byItemCode($this->manager->selectByItemCode_withBigSellerSku([$this->code('A'), $this->code('B')]));

        $this->assertSame(
            ['id', 'item_code', 'description', 'uom', 'item_group', 'image', 'quantity', 'bigseller_sku', 'item_id', 'date_created', 'date_modified'],
            array_keys($rows[$this->code('A')])
        );
        $this->assertSame($mapId, $rows[$this->code('A')]['id'], 'mapped item: `id` is bigseller_sku_map.id');
        $this->assertSame($this->idA, $rows[$this->code('A')]['item_id']);
        $this->assertNull($rows[$this->code('B')]['id'], 'unmapped item: `id` is the NULL from the LEFT JOIN, the stock item id is lost');
        $this->assertNull($rows[$this->code('B')]['item_id']);
    }
}
