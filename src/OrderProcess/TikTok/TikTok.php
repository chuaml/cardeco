<?php

namespace OrderProcess;

use mysqli;
use Exception;
use App\Service\PickList\PickListBuilder;
use App\Service\PickList\PickListTables;
use HTML\TableDisplayer;
use IO\CSVInputStream;
use Orders\Record;
use Product\Item;
use Product\Manager\ItemManager;

/**
 * TikTok daily order export (.csv) -> pick-list tables (To Restock / To Collect / Not Found / Orders).
 */
class TikTokOrder
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

    public function __construct(mysqli $con, string $file)
    {
        if (!file_exists($file)) {
            throw new Exception("file does not exist: {$file}.");
        }
        $this->con = $con;
        $this->file = $file;
    }

    public function getData(): array
    {
        $orders = $this->getOrders();

        $pickList = (new PickListBuilder(new ItemManager($this->con)))->build($orders);
        $this->Data = array_merge($this->Data, PickListTables::render($pickList));

        // no weight-based shipping fee for TikTok (never enabled in the legacy code)
        $this->setOrdersToData($orders);

        return $this->Data;
    }

    /**
     * One row per unit sold (the Quantity column is expanded), read by column position from the TikTok export.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(): array
    {
        $orders = (new CSVInputStream($this->file, ','))->readLines();
        array_splice($orders, 0, 1); // skip 1st line header

        $list = [];
        $line = 1; // header
        foreach ($orders as $r) {
            $line++;
            $quantity = intval($r[9]);
            if ($quantity === 0) {
                // a 0 / blank Quantity used to divide by zero below (warning on PHP 7, DivisionByZeroError on 8)
                throw new Exception("unknown quantity of item, at row: $line");
            }
            $voucher = ((float) preg_replace('/[^0-9\.]+/', '', $r[14])) / $quantity;
            $sellingPrice = ((float) preg_replace('/[^0-9\.]+/', '', $r[11])) - $voucher;
            for ($i = 0; $i < $quantity; ++$i) {
                $x = new Record(
                    null,
                    trim($r[0]),
                    new Item(null, $r[6], null)
                );

                $x->setOrderNum(trim($r[0]));
                $x->setDate(trim($r[23]));
                $x->setVoucher($voucher);
                $x->setSellingPrice($sellingPrice);
                $x->setShippingFee((float) preg_replace('/[^0-9\.]+/', '', $r[16]));
                $x->setShippingState(trim($r[42]));
                $x->setTrackingNum(trim($r[33]));

                if (preg_match('/[^0-9]+/', $x->trackingNum)) {
                    throw new Exception('invalid tracking number: ' . $x->trackingNum);
                }

                $list[] = [
                    'orderNum' => $x->orderNum,
                    'date' =>  $x->date,
                    'sku' =>  $x->getItem()->code,
                    'description' => trim($r[7]),
                    'sellingPrice' => $x->sellingPrice,
                    'sellerVoucher' => 0.00,
                    'shippingFee' => $x->shippingFee,

                    'shippingProvider' => trim($r[35]),
                    'trackingNum' => trim($x->trackingNum),
                    'shippingState' => trim($x->shippingState),
                    'stock' => null
                ];
            }
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
            'sellerVoucher' => 'Seller Voucher',
            'shippingFee' => 'Shipping Fee',
            // 'shippingFeeByWeight' => 'Shipping Fee2',
            // 'shippingWeight' => 'Weight',

            'shippingProvider' => 'Shipping Provider',
            'trackingNum' => 'Tracking Number'
        ];
        $Tbl->setHead($HEADER, true);
        $Tbl->setBody($orders);
        $Tbl->setAttributes('id="tiktokOrders"');
        $this->Data['orders'] = $Tbl->getTable();
    }
}
