<?php

namespace App\Service\PickList;

/**
 * Result of PickListBuilder::build(): the ordered SKUs grouped and classified against stock.
 *
 * Every entry of $found / $notFound is the FIRST order row of that SKU plus a 'quantity' key holding the
 * number of units ordered (one order row == one unit). Row order follows the first appearance of each SKU
 * in the orders, exactly like the legacy setItemsToData() implementations.
 */
final class PickListResult
{
    /**
     * sku => list of order rows (references into the caller's $orders array).
     *
     * @var array<string|int, array<int, array<string, mixed>>>
     */
    public $keyedSku = [];

    /**
     * SKUs that exist in stock_items: first order row + 'quantity'.
     *
     * @var array<int, array<string, mixed>>
     */
    public $found = [];

    /**
     * SKUs unknown to stock_items ('stock' is null): first order row + 'quantity'.
     *
     * @var array<int, array<string, mixed>>
     */
    public $notFound = [];

    /**
     * @param array<string|int, array<int, array<string, mixed>>> $keyedSku
     */
    public static function fromKeyedSku(array $keyedSku): self
    {
        $result = new self();
        $result->keyedSku = $keyedSku;
        foreach ($keyedSku as $rows) {
            $item = $rows[0];
            $item['quantity'] = count($rows);
            if ($item['stock'] === null) {
                $result->notFound[] = $item;
            } else {
                $result->found[] = $item;
            }
        }

        return $result;
    }

    /**
     * Items whose ordered quantity is >= the stock on hand.
     *
     * NOTE: the legacy rule is inclusive on both sides, so quantity == stock appears in BOTH
     * toRestock() and toCollect(). Kept verbatim (see implementation plan, Open Question 2a).
     *
     * @return array<int, array<string, mixed>>
     */
    public function toRestock(): array
    {
        return array_values(array_filter($this->found, function (array $r) {
            return $r['quantity'] >= $r['stock'];
        }));
    }

    /**
     * Items whose ordered quantity is <= the stock on hand (see note on toRestock()).
     *
     * @return array<int, array<string, mixed>>
     */
    public function toCollect(): array
    {
        return array_values(array_filter($this->found, function (array $r) {
            return $r['quantity'] <= $r['stock'];
        }));
    }
}
