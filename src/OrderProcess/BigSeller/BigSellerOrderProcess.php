<?php

namespace OrderProcess;

use mysqli;
use Exception;
use HTML\TableDisplayer;
use Orders\Lazada\AutoFilling;
use Product\Manager\ItemManager;
use Orders\Factory\Excel\ExcelReader;

class BigSellerOrderProcess
{
    private const COL_ORDER_NUM = 0;
    private const COL_MARKETPLACE = 9;
    private const COL_STORE_NAME = 10;
    private const COL_SHIPPING_STATE = 18;
    private const COL_SKU = 23;
    private const COL_PRODUCT_NAME = 25;
    private const COL_QUANTITY = 30;
    private const COL_SELLING_PRICE = 31;
    private const COL_PAID_PRICE = 32;
    private const COL_SHIPPING_PROVIDER = 48;
    private const COL_TRACKING_NUM = 50;
    private const COL_SHIPPING_FEE = 51;
    private const COL_VOUCHER = 62;
    private const COL_ORDER_TIME = 64;

    private mysqli $con;
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

    public function getData(): array
    {
        $orders = $this->getOrders();
        $keyedSku = $this->getKeyedSku($orders);
        $keyeditemCodeStock = $this->getKeyedItemCode($keyedSku);
        $this->joinItemCodeToSku($keyedSku, $keyeditemCodeStock);
        $this->setItemsToData($keyedSku);

        $this->setShippingFeeByWeight($orders);
        $this->Data['orders'] = $this->getOrdersToData($orders);

        return $this->Data;
    }

    public function getOrders(): array
    {
        $orders = (new ExcelReader($this->file))->read();

        $list = [];
        $line = 0;
        foreach ($orders as $r) {
            $line++;
            $quantity = intval($r[self::COL_QUANTITY]);
            if($quantity === 0) throw new Exception("unknown quantity of item, at row: $line");
            for ($i = 0; $i < $quantity; ++$i) { // spread out 1 line to multiple line item
                $list[] = [
                    'orderNum' => trim($r[self::COL_ORDER_NUM]),
                    'date' => trim($r[self::COL_ORDER_TIME]), // `Order Time`
                    'sku' => trim($r[self::COL_SKU]),
                    'description' => trim($r[self::COL_PRODUCT_NAME]), // `Product Name`
                    'sellingPrice' => floatval(preg_replace('/[^0-9\.]+/', '', $r[self::COL_SELLING_PRICE])), // `Price`
                    'shippingFee' => floatval(preg_replace('/[^0-9\.]+/', '', $r[self::COL_SHIPPING_FEE])), // `Shipping Fee`
                    'voucher' =>  floatval(preg_replace('/[^0-9\.]+/', '', $r[self::COL_VOUCHER])), // `Store Voucher`
                    'trackingNum' => trim($r[self::COL_TRACKING_NUM]), // `Tracking Number`

                    'paidPrice' => trim($r[self::COL_PAID_PRICE]), // `Product Subtotal`
                    'shippingProvider' => trim($r[self::COL_SHIPPING_PROVIDER]), // `Shipping Option`
                    'shippingState' => trim($r[self::COL_SHIPPING_STATE]), // `Province (State)`

                    'marketPlace' => trim($r[self::COL_MARKETPLACE]), // necessary for computation; grouping item counts
                    'storeName_BigSeller' => trim($r[self::COL_STORE_NAME]), // swapped to Marketplace Store

                    'stock' => null
                ];
            }
        }

        // sort by storeName_BigSeller
        $storeName_BigSeller = array_column($list, 'storeName_BigSeller');
        $storeName_BigSeller = array_map(function ($x) { // for custom sort, Lazada sort_asc higher first
            return str_replace('Lazada ', '0', $x);
        }, $storeName_BigSeller);
        array_multisort($storeName_BigSeller, SORT_ASC, $list);

        // exit(json_encode($list));

        return $list;
    }

    private function getKeyedSku(array &$orders): array
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

    private function getKeyedItemCode(array &$keyedSku): array
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

    private function joinItemCodeToSku(array &$keyedSku, array &$keyedItemCode): void
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

    private function setItemsToData(array &$keyedSku): void
    {
        // split items to found and notFound group
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
        $this->Data['notFound'] = $this->getNotFoundHTML($notFoundItems);


        $itemToRestock = array_filter($foundItems, function ($r) {
            return $r['quantity'] >= $r['stock'];
        });
        $this->Data['toRestock'] = $this->getToRestockHTML($itemToRestock, $keyedSku);

        $itemToCollect = array_filter($foundItems, function ($r) {
            return $r['quantity'] <= $r['stock'];
        });
        $this->Data['toCollect'] = $this->getToCollectHTML($itemToCollect, $keyedSku);
    }

    private function newTablePackList_HTML_Maker(): TableDisplayer
    {
        $tblPackList = new TableDisplayer();
        $tblPackList->setHead([
            'sku' => 'Item Code',
            'description' => 'Description',
            'quantity' => 'Total Q.',
            'stock' => 'Stock',
            'lazada' => 'Lazada',
            'shopee' => 'Shopee',
            'tiktok' => 'TikTok',
        ], true);
        return $tblPackList;
    }

    public function getToRestockHTML(array $items, array $keyedSku): string
    {
        return $this->buildPlatformPackListHTML($items, $keyedSku);
    }

    public function getToCollectHTML(array $itemToCollect, array $keyedSku): string
    {
        return $this->buildPlatformPackListHTML($itemToCollect, $keyedSku);
    }

    private function buildPlatformPackListHTML(array $items, array $keyedSku): string
    {
        $platforms = [
            'lazada' => [],
            'shopee' => [],
            'tiktok' => [],
        ];
        // group items by platform marketplace
        foreach ($platforms as $col_platform => $dbItems) {
            foreach ($keyedSku as $sku => $orders) {
                foreach ($orders as $o) {
                    if ($o['stock'] === null) continue; // skip not found sku item

                    $marketPlace = strtolower($o['marketPlace']);
                    if ($marketPlace !== $col_platform) continue;

                    if (array_key_exists($sku, $platforms[$col_platform]) === false)
                        $platforms[$col_platform][$sku] = [];

                    $platforms[$col_platform][$sku][] = $o;
                }
            }
        }

        // count by each column (platforms)
        foreach ($platforms as $col_platform => $dbItems) {
            foreach ($items as &$r) {
                if (array_key_exists($r['sku'], $dbItems) === true) {
                    $r[$col_platform] = count($dbItems[$r['sku']]);
                } else {
                    $r[$col_platform] = 0;
                }
            }
            unset($r);
        }

        // output as HTML
        $tblPackList = $this->newTablePackList_HTML_Maker();
        $tblPackList->setBody($items);

        return $tblPackList->getTable();
    }

    public function getNotFoundHTML(array $items): string
    {
        $platforms = [
            'lazada' => [],
            'shopee' => [],
            'tiktok' => [],
        ];
        // group items by platform marketplace
        // add columns counts; count by each column (marketplace platforms)
        foreach ($platforms as $col_platform => $itemList) {
            foreach ($items as &$r) {
                $marketPlace = strtolower($r['marketPlace']);
                if ($marketPlace === $col_platform) {
                    $r[$col_platform] = $r['quantity'];
                } else {
                    $r[$col_platform] = 0;
                }
            }
            unset($r);
        }

        // output as HTML
        $tblPackList = $this->newTablePackList_HTML_Maker();
        $tblPackList->setBody($items);

        return $tblPackList->getTable();
    }


    private function setShippingFeeByWeight(array &$orders): void
    {
        $Records = array_map(function (array $r) {
            $Record = new \Orders\Record(
                null,
                $r['orderNum'],
                new \Product\Item(null, $r['sku'], null)
            );
            $Record->setShippingFee((float) $r['shippingFee']);
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

    public function getOrdersToData(array &$orders): string
    {
        $Tbl = new TableDisplayer();

        $HEADER = [
            'orderNum' => 'Order Number',
            'date' => 'Date',
            'sku' => 'SKU',
            'description' => 'Description',
            'sellingPrice' => 'Selling Price',
            'shippingFee' => 'Shipping Fee',
            'voucher' => 'Voucher',
            // 'shippingFeeByWeight' => 'Shipping Fee2',
            // 'shippingWeight' => 'Weight',

            // 'paidPrice' => 'Paid Price',
            'shippingProvider' => 'Shipping Provider',
            'trackingNum' => 'Tracking Number',
            // 'marketPlace' => 'Marketplace', // for computation only; use storeName_BigSeller to display instead
            'storeName_BigSeller' => 'Store Nickname',
        ];
        $Tbl->setHead($HEADER, true);
        $Tbl->setBody($orders);
        $Tbl->setAttributes('id="orders-table"');
        return $Tbl->getTable();
    }
}
