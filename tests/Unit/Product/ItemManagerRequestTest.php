<?php

declare(strict_types=1);

namespace test\Unit\Product;

use PHPUnit\Framework\TestCase;
use Product\Item;
use Product\ItemManager;

/**
 * Product\ItemManager::itemsFromRequest(): turns the /ItemManager form post
 *   r[<item id>] = description,  bigseller_sku_map[<item id>][<mapping id>|new] = sku
 * into Item objects. Mappings are matched to items by ITEM ID. The former controller paired them by position,
 * which turned PHP's auto-index of new rows (0, 1, 2, ...) into the primary keys of other items' mapping rows.
 */
final class ItemManagerRequestTest extends TestCase
{
    public function testItemsFromRequest_DescriptionsOnly_BuildsOneItemPerPostedId(): void
    {
        $items = ItemManager::itemsFromRequest(['5' => 'Seat cover', '9' => 'Mat'], []);

        $this->assertCount(2, $items);
        $this->assertContainsOnlyInstancesOf(Item::class, $items);
        $this->assertSame([5, 9], [$items[0]->itemId, $items[1]->itemId]);
        $this->assertSame(['Seat cover', 'Mat'], [$items[0]->description, $items[1]->description]);
        $this->assertSame([], $items[0]->bigseller_sku_map);
    }

    public function testItemsFromRequest_ExistingMapping_KeepsItsIdOnItsOwnItem(): void
    {
        $items = ItemManager::itemsFromRequest(
            ['5' => 'Seat cover', '9' => 'Mat'],
            ['9' => ['42' => 'BS-MAT']]
        );

        $this->assertSame([], $items[0]->bigseller_sku_map, 'item 5 posted no mapping');
        $this->assertSame([['id' => 42, 'bigseller_sku' => 'BS-MAT']], $items[1]->bigseller_sku_map);
    }

    public function testItemsFromRequest_NewMapping_HasNullIdInsteadOfAnAutoIndex(): void
    {
        $items = ItemManager::itemsFromRequest(
            ['5' => 'Seat cover', '9' => 'Mat'],
            ['5' => ['new' => 'BS-NEW-5'], '9' => ['new' => 'BS-NEW-9']]
        );

        $this->assertSame([['id' => null, 'bigseller_sku' => 'BS-NEW-5']], $items[0]->bigseller_sku_map);
        $this->assertSame([['id' => null, 'bigseller_sku' => 'BS-NEW-9']], $items[1]->bigseller_sku_map);
    }

    public function testItemsFromRequest_EmptyMappingKey_IsTreatedAsNew(): void
    {
        $items = ItemManager::itemsFromRequest(['5' => 'x'], ['5' => ['' => 'BS-1']]);

        $this->assertSame([['id' => null, 'bigseller_sku' => 'BS-1']], $items[0]->bigseller_sku_map);
    }

    public function testItemsFromRequest_MappingsAreMatchedByItemIdNotByPosition(): void
    {
        // the mapping of the SECOND item is posted first: it must still land on item 9
        $items = ItemManager::itemsFromRequest(
            ['5' => 'a', '9' => 'b'],
            ['9' => ['7' => 'BS-9'], '5' => ['3' => 'BS-5']]
        );

        $this->assertSame([['id' => 3, 'bigseller_sku' => 'BS-5']], $items[0]->bigseller_sku_map);
        $this->assertSame([['id' => 7, 'bigseller_sku' => 'BS-9']], $items[1]->bigseller_sku_map);
    }

    public function testItemsFromRequest_MappingForUnknownItem_IsIgnored(): void
    {
        $items = ItemManager::itemsFromRequest(['5' => 'a'], ['777' => ['new' => 'BS-X']]);

        $this->assertCount(1, $items);
        $this->assertSame([], $items[0]->bigseller_sku_map);
    }

    public function testItemsFromRequest_MalformedMappingValues_AreIgnoredOrBlanked(): void
    {
        $items = ItemManager::itemsFromRequest(
            ['5' => 'a', '9' => 'b'],
            ['5' => 'not-an-array', '9' => ['new' => ['nested' => 'x']]]
        );

        $this->assertSame([], $items[0]->bigseller_sku_map);
        $this->assertSame([['id' => null, 'bigseller_sku' => '']], $items[1]->bigseller_sku_map, 'blank sku is skipped by update()');
    }

    public function testItemsFromRequest_NoDescriptions_ReturnsEmptyList(): void
    {
        $this->assertSame([], ItemManager::itemsFromRequest([], ['5' => ['new' => 'BS']]));
    }
}
