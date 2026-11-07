<?php

namespace Orders\MonthlyRecord;

use Orders\DailyStockOutItem;

final class OrderLine
{
    /** @var string */
    public $orderNumber;
    /** @var string */
    public $orderStatus;
    /** @var string */
    public $trackingNumber;
    /** @var string */
    public $dateOfSendOut; // dd/MM/yyyy


    /** @var DailyStockOutItem */
    public $ShippedItem;
    
    public function __construct() {}
}
