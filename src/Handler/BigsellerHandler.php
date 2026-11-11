<?php

namespace App\Handler;

use OrderProcess\BigSellerOrderProcess;

/**
 * Route `/bigseller` (router derives the class name with ucfirst(), hence "Bigseller").
 */
final class BigsellerHandler extends AbstractDailyOrderHandler
{
    protected function fileField(): string
    {
        return 'dailyOrders';
    }

    protected function viewPath(): string
    {
        return 'view/bigseller.html';
    }

    protected function newOrderProcess(string $filePath)
    {
        return new BigSellerOrderProcess($this->con, $filePath);
    }
}
