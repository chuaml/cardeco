<?php

namespace App\Service\PickList;

use Product\Manager\ItemManager;

/**
 * The "daily orders -> pick list" pipeline that used to be copy-pasted into LazadaOrderProcess,
 * BigSellerOrderProcess, TikTokOrder and request_handler/shopee.php (getKeyedSku / getKeyedItemCode /
 * joinItemCodeToSku / the found-vs-not-found split of setItemsToData).
 *
 * Input rows are the platform-specific order lines, ONE ARRAY PER UNIT SOLD, and must carry the keys
 * 'sku', 'description' and 'stock' (null = not looked up yet).
 */
final class PickListBuilder
{
    /** @var ItemManager */
    private $itemManager;

    public function __construct(ItemManager $itemManager)
    {
        $this->itemManager = $itemManager;
    }

    /**
     * Groups $orders by SKU, looks the SKUs up in stock_items/stock and writes the DB description and
     * stock quantity back into EVERY matching row of $orders (by reference) - this is what makes the
     * "Orders" table show the catalogue description instead of the marketplace one.
     *
     * @param array<int, array<string, mixed>> $orders modified in place
     */
    public function build(array &$orders): PickListResult
    {
        $keyedSku = [];
        foreach ($orders as $i => $r) {
            if (!array_key_exists($r['sku'], $keyedSku)) {
                $keyedSku[$r['sku']] = [];
            }
            $keyedSku[$r['sku']][] = &$orders[$i];
        }

        $stockByItemCode = [];
        foreach ($this->itemManager->selectByItemCode(array_keys($keyedSku)) as $r) {
            $stockByItemCode[$r['item_code']] = [
                'description' => $r['description'],
                'stock' => (int) $r['quantity'],
            ];
        }

        foreach ($stockByItemCode as $itemCode => $stock) {
            if (!array_key_exists($itemCode, $keyedSku)) {
                continue;
            }
            foreach ($keyedSku[$itemCode] as $i => $unused) {
                $keyedSku[$itemCode][$i]['description'] = $stock['description'];
                $keyedSku[$itemCode][$i]['stock'] = $stock['stock'];
            }
        }

        return PickListResult::fromKeyedSku($keyedSku);
    }
}
