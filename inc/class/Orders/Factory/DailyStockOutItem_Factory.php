<?php

namespace Orders;

use DateTime;

final class DailyStockOutItem_Factory
{
    private const COL_SHIPPING_STATUS = 7;
    private const COL_SKU = 23;
    private const COL_PRODUCT_NAME = 25;
    private const COL_QUANTITY = 30;

    public static function map($excelFileRow)
    {
        $r = $excelFileRow;
        $x = new DailyStockOutItem();

        // $x->orderTime = $r[69];
        // $dateObject = DateTime::createFromFormat("d M Y H:i", $x->orderTime);
        // $x->orderTime = $dateObject->format("m/d/Y");

        $x->sku = $r[self::COL_SKU];
        $x->productName = $r[self::COL_PRODUCT_NAME];
        $x->quantity = intval($r[self::COL_QUANTITY]);
        $x->shippingStatus = $r[self::COL_SHIPPING_STATUS];

        return $x;
    }
}
