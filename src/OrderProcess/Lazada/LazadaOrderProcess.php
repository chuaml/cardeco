<?php

namespace OrderProcess;

use mysqli;
use App\Service\PickList\PickListBuilder;
use App\Service\PickList\PickListTables;
use App\Service\Shipping\ShippingFeeByWeight;
use HTML\TableDisplayer;
use Product\Manager\ItemManager;
use Orders\Factory\Excel\ExcelReader;

/**
 * Lazada daily order export (.xlsx) -> pick-list tables (To Restock / To Collect / Not Found / Orders).
 */
class LazadaOrderProcess
{
    /** @var mysqli */
    private $con;
    /** @var string */
    private $file;
    /** @var array<string, string> */
    private $Data = [
        'toRestock' => '',
        'toCollect' => '',
        'notFound' => '',
        'orders' => ''
    ];

    public function __construct(mysqli $con, string $filePath)
    {
        $this->con = $con;
        $this->file = $filePath;
    }

    public function getData(): array
    {
        $orders = $this->getOrders();

        $pickList = (new PickListBuilder(new ItemManager($this->con)))->build($orders);
        $this->Data = array_merge($this->Data, PickListTables::render($pickList));

        ShippingFeeByWeight::apply($this->con, $orders);
        $this->setOrdersToData($orders);

        return $this->Data;
    }

    /**
     * One row per unit sold, read by column position from the Lazada export.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(): array
    {
        $orders = (new ExcelReader($this->file))->read();

        $list = [];
        foreach ($orders as $r) {
            $list[] = [
                'orderNum' => trim($r[12]),
                'date' => trim($r[9]),
                'sku' => trim($r[5]),
                'description' => trim($r[51]),
                'sellingPrice' => trim($r[47]),
                'shippingFee' => trim($r[49]),

                'paidPrice' => trim($r[46]),
                'shippingProvider' => trim($r[53]),
                'trackingNum' => trim($r[58]),
                'shippingState' => trim($r[27]),
                'stock' => null
            ];
        }

        return $list;
    }

    private function setOrdersToData(array &$orders): void
    {
        $Tbl = new TableDisplayer();

        $HEADER = [
            'orderNum' => 'Order Number',
            'date' => 'Date',
            'sku' => 'SKU',
            'description' => 'Description',
            'sellingPrice' => 'Selling Price',
            'shippingFee' => 'Shipping Fee',
            'shippingFeeByWeight' => 'Shipping Fee2',
            'shippingWeight' => 'Weight',

            'paidPrice' => 'Paid Price',
            'shippingProvider' => 'Shipping Provider',
            'trackingNum' => 'Tracking Number'
        ];
        $Tbl->setHead($HEADER, true);
        $Tbl->setBody($orders);
        $Tbl->setAttributes('id="lazadaOrders"');
        $this->Data['orders'] = $Tbl->getTable();
    }
}
