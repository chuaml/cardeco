<?php

namespace Orders;

use Product\Item;

final class DailyStockOutItem
{
    // public string $orderTime; // y-M-d
    /** @var string */
    public $sku;
    /** @var string */
    public $productName;
    /** @var int */
    public $quantity = 0;
    /** @var string */
    public $shippingStatus;

    /** @var Item */
    public $item; // reference to db item

    public function __construct()
    {
        $this->item = new Item(null, null, null);
    }

    public function isShipped(): bool
    {
        return $this->shippingStatus === 'Shipped';
    }
}
