<?php

namespace App\Handler;

use OrderProcess\ShopeeOrderProcess;

/**
 * Route `/shopee`.
 */
final class ShopeeHandler extends AbstractDailyOrderHandler
{
    protected function fileField(): string
    {
        return 'shopeeOrders';
    }

    protected function viewPath(): string
    {
        return 'view/shopee.html';
    }

    protected function newOrderProcess(string $filePath)
    {
        return new ShopeeOrderProcess($this->con, $filePath);
    }
}
