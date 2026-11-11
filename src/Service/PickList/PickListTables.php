<?php

namespace App\Service\PickList;

use HTML\TableDisplayer;

/**
 * Renders the three pick-list tables (To Restock / To Collect / Not Found) in the 4-column layout
 * shared by the Lazada, TikTok and Shopee pages. BigSeller has its own per-marketplace layout.
 */
final class PickListTables
{
    const HEADER = [
        'sku' => 'Item Code',
        'description' => 'Description',
        'quantity' => 'Quantity',
        'stock' => 'Stock',
    ];

    /**
     * @return array{toRestock: string, toCollect: string, notFound: string}
     */
    public static function render(PickListResult $result): array
    {
        $table = new TableDisplayer();
        $table->setHead(self::HEADER, true);

        $table->setBody($result->toRestock());
        $toRestock = $table->getTable();

        $table->setBody($result->toCollect());
        $toCollect = $table->getTable();

        $table->setBody($result->notFound);
        $notFound = $table->getTable();

        return [
            'toRestock' => $toRestock,
            'toCollect' => $toCollect,
            'notFound' => $notFound,
        ];
    }
}
