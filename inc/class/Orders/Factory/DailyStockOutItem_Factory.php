<?php

namespace Orders;

use DateTime;

final class DailyStockOutItem_Factory
{
    public static function map($excelFileRow)
    {
        $r = $excelFileRow;
        $x = new DailyStockOutItem();

        $x->orderTime = $r[65];
        $dateObject = DateTime::createFromFormat("d M Y H:i", $x->orderTime);
        $x->orderTime = $dateObject->format("m/d/Y");

        $x->sku = $r[23];
        $x->productName = $r[25];
        $x->quantity = intval($r[30]);
        $x->shippingStatus = $r['7'];

        return $x;
    }
}
