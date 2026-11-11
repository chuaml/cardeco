<?php

namespace App\Handler;

use OrderProcess\TikTokOrder;

/**
 * Route `/tiktok` (router derives the class name with ucfirst(), hence "Tiktok").
 */
final class TiktokHandler extends AbstractDailyOrderHandler
{
    protected function fileField(): string
    {
        return 'orderFile';
    }

    protected function viewPath(): string
    {
        return 'view/tiktok.html';
    }

    protected function newOrderProcess(string $filePath)
    {
        return new TikTokOrder($this->con, $filePath);
    }
}
