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
        $this->setOrdersToData($orders);

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
            for ($i = 0; $i < $quantity; ++$i) {
                $list[] = [
                    'orderNum' => trim($r[0]),
                    'date' => trim($r[11]),
                    'sku' => trim($r[30]),
                    'description' => trim($r[29]), //product name
                    'sellingPrice' => $sellingPrice,
                    'shippingFee' => $shippingFee,
                    'trackingNum' => trim($r[44]),

                    'paidPrice' => trim($r[36]),
                    'shippingProvider' => trim($r[42]),
                    'shippingState' => trim($r[26]),

                    'marketPlace' => trim($r[4]),

                    'stock' => null
                ];
            }
        }

        // sort by marketPlace
        $marketPlace = array_column($list, 'marketPlace');
        array_multisort($marketPlace, SORT_ASC, $list);

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

        $this->Data['toRestock'] = $this->getToRestockHTML($itemToRestock, $keyedSku);

        $this->Data['toCollect'] = $this->getToCollectHTML($itemToCollect, $keyedSku);

        $this->Data['notFound'] = $this->getNotFoundHTML($notFoundItems, $keyedSku);
    }

    public function getToRestockHTML(array $items, array $keyedSku): string
    {
        $platforms = [
            'lazada' => [],
            'shopee' => [],
            'tiktok' => [],
        ];
        // group items by platform marketplace
        foreach ($platforms as $col_platform => $itemList) {
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
        foreach ($platforms as $col_platform => $itemList) {
            foreach ($items as &$r) {
                if (array_key_exists($r['sku'], $itemList) === true) {
                    $r[$col_platform] = count($itemList[$r['sku']]);
                } else {
                    $r[$col_platform] = 0;
                }
            }
        }

        // output as HTML
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
        foreach ($platforms as $col_platform => $itemList) {
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
        foreach ($platforms as $col_platform => $itemList) {
            foreach ($itemToCollect as &$r) {
                if (array_key_exists($r['sku'], $itemList) === true) {
                    $r[$col_platform] = count($itemList[$r['sku']]);
                } else {
                    $r[$col_platform] = 0;
                }
            }
        }

        // out put as HTML
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
        $tblPackList->setBody($itemToCollect);
        return $tblPackList->getTable();
    }

    public function getNotFoundHTML(array $items, array $keyedSku): string
    {
        $platforms = [
            'lazada' => [],
            'shopee' => [],
            'tiktok' => [],
        ];
        // group items by platform marketplace
        foreach ($platforms as $col_platform => $itemList) {
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
        foreach ($platforms as $col_platform => $itemList) {
            foreach ($items as &$r) {
                if (array_key_exists($r['sku'], $itemList) === true) {
                    $r[$col_platform] = count($itemList[$r['sku']]);
                } else {
                    $r[$col_platform] = 0;
                }
            }
        }

        // output as HTML
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
            // 'shippingFeeByWeight' => 'Shipping Fee2',
            // 'shippingWeight' => 'Weight',

            // 'paidPrice' => 'Paid Price',
            'shippingProvider' => 'Shipping Provider',
            'trackingNum' => 'Tracking Number',
            'marketPlace' => 'Marketplace'
        ];
        $Tbl->setHead($HEADER, true);
        $Tbl->setBody($orders);
        $Tbl->setAttributes('id="orders-table"');
        $this->Data['orders'] = $Tbl->getTable();
    }
}
