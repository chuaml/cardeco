<?php

namespace Orders;

use Product\Item;

final class DailyStockOutItem
{
    public string $orderTime; // y-M-d
    public string $sku;
    public string $productName;
    public int $quantity = 0;
    public string $shippingStatus;

    public Item $item; // reference to db item

    public function __construct()
    {
        $this->item = new Item(null, null, null);
    }

    public function isShipped(): bool
    {
        return $this->shippingStatus === 'Shipped';
    }
}
