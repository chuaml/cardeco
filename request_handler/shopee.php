<?php
namespace Controller;










use Product\Manager\ItemManager;
use HTML\TableDisplayer;
use Orders\Factory\Shopee;
use Exception;
use mysqli;

class ShopeeOrdersController
{
    private $con;
    private $file;
    private $FILE;
    private $Data = [
        'toRestock' => '',
        'toCollect' => '',
        'notFound' => '',
        'orders' => ''
    ];
    public function __construct(mysqli $con, array $FILE)
    {
        if ($FILE['error'] !== 0) {
            throw new Exception('error on file: ' .$_FILES['lzdOrders']['name']);
        }
        $file = $FILE['tmp_name'];
        if (!file_exists($file)) {
            throw new Exception("file does not exist: {$file}.");
        }
        $this->con = $con;
        $this->file = $file;
        $this->FILE = $FILE;
    }

    public function getData():array
    {
        return $this->Data;
    }

    public function initData():void
    {
        $orders = $this->getOrders();

        $keyedSku = $this->getKeyedSku($orders);
        $keyeditemCodeStock = $this->getKeyedItemCode($keyedSku);
        $this->joinItemCodeToSku($keyedSku, $keyeditemCodeStock);
        $this->setItemsToData($keyedSku);

        $this->setOrdersToData($orders);
    }

    public function getOrders():array
    {
        $orders = (new Shopee($this->file))->generateRecords();
        return array_map(function (\Orders\Record $o) {
            return [
                'orderNum' => $o->orderNum,
                'date' => $o->date,
                'sku' => $o->getItem()->code,
                'description' => null,
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

    private function setOrdersToData(array &$orders):void
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

$Data = [
    'toRestock' => '',
    'toCollect' => '',
    'notFound' => '',
    'orders' => ''
];
$msg = '';

$jsonOrders = '';
$dailyOrderFile_Sha1Hash = '';

try {
    if (isset($_FILES['shopeeOrders'])) {
        try {
            $L = new ShopeeOrdersController($con, $_FILES['shopeeOrders']);
            $L->initData();
            $Data = $L->getData();

            $jsonOrders = json_encode($L->getOrders());
            $dailyOrderFile_Sha1Hash = sha1_file($_FILES['shopeeOrders']['tmp_name']);
        } catch (Exception $e) {
            $msg = $e->getMessage();
        }
    }
} finally {
    $con->close();
}

require('view/shopee.html');
