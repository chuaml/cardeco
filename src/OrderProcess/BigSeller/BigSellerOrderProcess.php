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
        foreach ($orders as $r) {
            $quantity = intval($r[34]);
            $sellingPrice = floatval(preg_replace('/[^0-9\.]+/', '', $r[33]));
            $shippingFee = floatval(preg_replace('/[^0-9\.]+/', '', $r[53]));
            $storeVoucher = floatval(preg_replace('/[^0-9\.]+/', '', $r[49]));
            for ($i = 0; $i < $quantity; ++$i) {
                $list[] = [
                    'orderNum' => trim($r[0]),
                    'date' => trim($r[11]),
                    'sku' => trim($r[30]),
                    'description' => trim($r[29]), //product name
                    'sellingPrice' => $sellingPrice,
                    'shippingFee' => $shippingFee,
                    'voucher' =>  $storeVoucher,
                    'trackingNum' => trim($r[44]),

                    'paidPrice' => trim($r[36]),
                    'shippingProvider' => trim($r[42]),
                    'shippingState' => trim($r[26]),

                    'marketPlace' => trim($r[4]), // necessary for computation; grouping item counts
                    'storeName_BigSeller' => trim($r[5]),

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

        $itemToCollect = $itemToRestock = array_filter($foundItems, function ($r) {
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
        }

        // output as HTML
        $tblPackList = $this->newTablePackList_HTML_Maker();
        $tblPackList->setBody($items);

        return $tblPackList->getTable();
    }

    public function getToCollectHTML(array $itemToCollect, array $keyedSku): string
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
            foreach ($itemToCollect as &$r) {
                if (array_key_exists($r['sku'], $dbItems) === true) {
                    $r[$col_platform] = count($dbItems[$r['sku']]);
                } else {
                    $r[$col_platform] = 0;
                }
            }
        }

        // out put as HTML
        $tblPackList = $this->newTablePackList_HTML_Maker();
        $tblPackList->setBody($itemToCollect);
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
