<?php

namespace Orders\MonthlyRecord;

use Orders\DailyStockOutItem;

final class OrderLine
{
    public string $orderNumber;
    public string $orderStatus;
    public string $trackingNumber;
    public string $dateOfSendOut; // dd/MM/yyyy


    public DailyStockOutItem $ShippedItem;
    
    public function __construct() {}
}
