<?php

namespace OrderProcess;

use mysqli;
use Exception;
use App\Service\PickList\PickListBuilder;
use App\Service\PickList\PickListTables;
use HTML\TableDisplayer;
use Orders\Factory\Shopee;
use Orders\Record;
use Product\Manager\ItemManager;

/**
 * Shopee daily order export (.csv) -> pick-list tables (To Restock / To Collect / Not Found / Orders).
 *
 * Replaces the class `Controller\ShopeeOrdersController` that used to live inside request_handler/shopee.php.
 */
class ShopeeOrderProcess
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
        if (!file_exists($filePath)) {
            throw new Exception("file does not exist: {$filePath}.");
        }
        $this->con = $con;
        $this->file = $filePath;
    }

    public function getData(): array
    {
        $orders = $this->getOrders();

        $pickList = (new PickListBuilder(new ItemManager($this->con)))->build($orders);
        $this->Data = array_merge($this->Data, PickListTables::render($pickList));

        $this->setOrdersToData($orders);

        return $this->Data;
    }

    /**
     * One row per unit sold (Orders\Factory\Shopee expands the Quantity column).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(): array
    {
        $orders = (new Shopee($this->file))->generateRecords();
        return array_map(function (Record $o) {
            $item = $o->getItem();
            return [
                'orderNum' => $o->orderNum,
                'date' => $o->date,
                'sku' => $item->code,
                'description' => $item->description,
                'sellingPrice' => $o->sellingPrice,
                'shippingFee' => $o->shippingFee,
                'voucher' => $o->voucher,

                'trackingNum' => $o->trackingNum,
                'shippingWeight' => $o->shippingWeight,
                'shippingState' => $o->shippingState,
                'stock' => null
            ];
        }, $orders);
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
            'voucher' => 'Seller Voucher',
            'shippingFee' => 'Shipping Fee',
            'shippingWeight' => 'Weight',

            'trackingNum' => 'Tracking Number'
        ];
        $Tbl->setHead($HEADER, true);
        $Tbl->setBody($orders);
        $Tbl->setAttributes('id="shopeeOrders"');
        $this->Data['orders'] = $Tbl->getTable();
    }
}
