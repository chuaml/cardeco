<?php 

namespace OrderProcess;

use mysqli;
use Exception;
use HTML\TableDisplayer;
use Orders\Lazada\AutoFilling;
use Product\Manager\ItemManager;
use Orders\Factory\Excel\ExcelReader;

class LazadaOrderProcess
{
    private const COL_SKU = 5;
    private const COL_DATE = 9;
    private const COL_ORDER_NUM = 12;
    private const COL_SHIPPING_STATE = 27;
    private const COL_PAID_PRICE = 46;
    private const COL_SELLING_PRICE = 47;
    private const COL_SHIPPING_FEE = 49;
    private const COL_DESCRIPTION = 51;
    private const COL_SHIPPING_PROVIDER = 53;
    private const COL_TRACKING_NUM = 58;

    private $con;
    private $file;
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

    public function getData():array
    {
        $orders = $this->getOrders();
        $keyedSku = $this->getKeyedSku($orders);
        $keyeditemCodeStock = $this->getKeyedItemCode($keyedSku);
        $this->joinItemCodeToSku($keyedSku, $keyeditemCodeStock);
        $this->setItemsToData($keyedSku);

        $this->setShippingFeeByWeight($orders);
        $this->setOrdersToData($orders);
        
        return $this->Data;
    }

    public function getOrders():array
    {
        $orders = (new ExcelReader($this->file))->read();
        
        $list = [];
        foreach($orders as $r){
            $list[] = [
                'orderNum' => trim($r[self::COL_ORDER_NUM]),
                'date' => trim($r[self::COL_DATE]),
                'sku' => trim($r[self::COL_SKU]),
                'description' => trim($r[self::COL_DESCRIPTION]),
                'sellingPrice' => trim($r[self::COL_SELLING_PRICE]),
                'shippingFee' => trim($r[self::COL_SHIPPING_FEE]),

                'paidPrice' => trim($r[self::COL_PAID_PRICE]),
                'shippingProvider' => trim($r[self::COL_SHIPPING_PROVIDER]),
                'trackingNum' => trim($r[self::COL_TRACKING_NUM]),
                'shippingState' => trim($r[self::COL_SHIPPING_STATE]),
                'stock' => null
            ];
        }

        return $list;
    }

    private function getKeyedSku(array &$orders):array
    {
        //sku from all orders as index
        $keyedSku = [];
        foreach ($orders as $i => $r) {
            if (!array_key_exists($r['sku'], $keyedSku)) {
                $keyedSku[$r['sku']] = [];
            }
            $keyedSku[$r['sku']][] = &$orders[$i];
        }
        return $keyedSku;
    }

    private function getKeyedItemCode(array &$keyedSku):array
    {
        //item code from stock as index
        $IM = new ItemManager($this->con);
        $stockItems = $IM->selectByItemCode(array_keys($keyedSku));
        $keyItemCode = [];
        foreach ($stockItems as $r) {
            $keyItemCode[$r['item_code']] = [
                'description' => $r['description'],
                'stock' => (int)$r['quantity']
            ];
        }
        return $keyItemCode;
    }

    private function joinItemCodeToSku(array &$keyedSku, array &$keyedItemCode):void
    {
        foreach ($keyedItemCode as $itemCode => $r) {
            if (\array_key_exists($itemCode, $keyedSku)) {
                foreach ($keyedSku[$itemCode] as $i => $order) {
                    $keyedSku[$itemCode][$i]['description'] = $r['description'];
                    $keyedSku[$itemCode][$i]['stock'] = $r['stock'];
                }
            }
        }
    }

    private function setItemsToData(array &$keyedSku):void
    {
        $HEADER = [
            'sku' => 'Item Code',
            'description' => 'Description',
            'quantity' => 'Quantity',
            'stock' => 'Stock'
        ];
        $foundItems = [];
        $notFoundItems = [];
        foreach ($keyedSku as $r) {
            $order = $r[0];
            $order['quantity'] = count($r);
            if ($order['stock'] === null) {
                $notFoundItems[] = $order;
            } else {
                $foundItems[] = $order;
            }
        }

        $itemToRestock = [];
        $itemToCollect = [];
        foreach ($foundItems as $r) {
            if ($r['quantity'] >= $r['stock']) {
                $itemToRestock[] = $r;
            }
        }
        foreach ($foundItems as $r) {
            if ($r['quantity'] <= $r['stock']) {
                $itemToCollect[] = $r;
            }
        }

        $Tbl = new TableDisplayer();
        $Tbl->setHead($HEADER, true);

        $Tbl->setBody($itemToRestock);
        $this->Data['toRestock'] = $Tbl->getTable();

        $Tbl->setBody($itemToCollect);
        $this->Data['toCollect'] = $Tbl->getTable();

        $Tbl->setBody($notFoundItems);
        $this->Data['notFound'] = $Tbl->getTable();
    }

    private function setShippingFeeByWeight(array &$orders):void
    {
        $Records = array_map(function (array $r) {
            $Record = new \Orders\Record(
                null,
                $r['orderNum'],
                new \Product\Item(null, $r['sku'], null)
            );
            $Record->setShippingFee((double) $r['shippingFee']);
            $Record->setShippingState($r['shippingState']);
            $Record->setTrackingNum($r['trackingNum']);

            $Record->setDate($r['date']);
            return $Record;
        }, $orders);
        AutoFilling::setShippingWeightByLzdProduct($this->con, $Records);
        AutoFilling::setShippingFeeByWeight($Records);

        foreach ($Records as $i => $r) {
            if ($orders[$i]['orderNum'] !== $r->orderNum) {
                throw new Exception("cannot map shippingFeeByWeight to orders. iteration: {$i}");
            }
            $orders[$i]['shippingFeeByWeight'] = $r->shippingFeeByWeight;
            $orders[$i]['shippingWeight'] = $r->shippingWeight;
            $orders[$i]['date'] = $r->date;
        }
    }

    private function setOrdersToData(array &$orders):void
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
