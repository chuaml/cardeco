<?php

namespace OrderProcess;

use mysqli;
use Exception;
use HTML\TableDisplayer;
use IO\CSVInputStream;
use Orders\Lazada\AutoFilling;
use Orders\Record;
use Product\Item;
use Product\Manager\ItemManager;

class TikTokOrder
{
    private $con;
    private $file;
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

    public function getData():array
    {
        $orders = $this->getOrders();

        $keyedSku = $this->getKeyedSku($orders);
        $keyeditemCodeStock = $this->getKeyedItemCode($keyedSku);
        $this->joinItemCodeToSku($keyedSku, $keyeditemCodeStock);
        $this->setItemsToData($keyedSku);

        // $this->setShippingFeeByWeight($orders);
        $this->setOrdersToData($orders);

        return $this->Data;
    }

    private function getOrders():array
    {
        $orders = (new CSVInputStream($this->file, ','))->readLines();
        
        $list = [];
        foreach($orders as $r) {
            $x = new Record(
                null,
                trim($r[0]),
                new Item(null, $r[6], null)
            );
    
            $x->setOrderNum(trim($r[0]));
            $x->setDate(trim($r[24]));
            $x->setSellingPrice((double) preg_replace('/[^0-9\.]+/', '', $r[12]));
            $x->setVoucher((double) preg_replace('/[^0-9\.]+/', '', $r[14]));
            $x->setShippingFee((double) preg_replace('/[^0-9\.]+/', '', $r[16]));
            $x->setShippingState(trim($r[42]));
            $x->setTrackingNum(trim($r[33]));

            $list[] = [
                'orderNum' => $x->orderNum,
                'date' =>  $x->date,
                'sku' =>  $x->getItem()->code,
                'description' => trim($r[7]),
                'sellingPrice' => trim($x->sellingPrice),
                'sellerVoucher' => trim($x->voucher),
                'shippingFee' => trim($x->shippingFee),

                'shippingProvider' => trim($r[35]),
                'trackingNum' => trim($x->trackingNum),
                'shippingState' => trim($x->shippingState),
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

