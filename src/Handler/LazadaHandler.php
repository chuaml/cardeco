<?php

namespace App\Handler;

use OrderProcess\LazadaOrderProcess;

/**
 * Routes: `/` (default), `/lazada` and, via IndexHandler, `/index`.
 */
class LazadaHandler extends AbstractDailyOrderHandler
{
    protected function fileField(): string
    {
        return 'lzdOrders';
    }

    protected function viewPath(): string
    {
        return 'view/lazada.php';
    }

    protected function newOrderProcess(string $filePath)
    {
        return new LazadaOrderProcess($this->con, $filePath);
    }
}
