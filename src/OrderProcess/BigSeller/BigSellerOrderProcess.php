<?php

namespace OrderProcess;

use mysqli;
use Exception;
use App\Service\PickList\PickListBuilder;
use App\Service\Shipping\ShippingFeeByWeight;
use HTML\TableDisplayer;
use Product\Manager\ItemManager;
use Orders\Factory\Excel\ExcelReader;

/**
 * BigSeller daily order export (.xlsx, all marketplaces) -> pick-list tables with a per-marketplace
 * unit count (Lazada / Shopee / TikTok columns) and the Orders table grouped by store.
 */
class BigSellerOrderProcess
{
    const PLATFORM_COLUMNS = ['lazada', 'shopee', 'tiktok'];

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
        $this->Data['notFound'] = $this->getNotFoundHTML($pickList->notFound);
        $this->Data['toRestock'] = $this->getToRestockHTML($pickList->toRestock(), $pickList->keyedSku);
        $this->Data['toCollect'] = $this->getToCollectHTML($pickList->toCollect(), $pickList->keyedSku);

        ShippingFeeByWeight::apply($this->con, $orders);
        $this->Data['orders'] = $this->getOrdersToData($orders);

        return $this->Data;
    }

    /**
     * One row per unit sold (the Quantity column is expanded), sorted by store name with Lazada stores first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(): array
    {
        $orders = (new ExcelReader($this->file))->read();

        $list = [];
        $line = 0;
        foreach ($orders as $r) {
            $line++;
            $quantity = intval($r[30]);
            if ($quantity === 0) throw new Exception("unknown quantity of item, at row: $line");
            for ($i = 0; $i < $quantity; ++$i) { // spread out 1 line to multiple line item
                $list[] = [
                    'orderNum' => trim($r[0]),
                    'date' => trim($r[64]), // `Order Time`
                    'sku' => trim($r[23]),
                    'description' => trim($r[25]), // `Product Name`
                    'sellingPrice' => floatval(preg_replace('/[^0-9\.]+/', '', $r[31])), // `Price`
                    'shippingFee' => floatval(preg_replace('/[^0-9\.]+/', '', $r[51])), // `Shipping Fee`
                    'voucher' =>  floatval(preg_replace('/[^0-9\.]+/', '', $r[62])), // `Store Voucher`
                    'trackingNum' => trim($r[50]), // `Tracking Number`

                    'paidPrice' => trim($r[32]), // `Product Subtotal`
                    'shippingProvider' => trim($r[48]), // `Shipping Option`
                    'shippingState' => trim($r[18]), // `Province (State)`

                    'marketPlace' => trim($r[9]), // necessary for computation; grouping item counts
                    'storeName_BigSeller' => trim($r[10]), // swapped to Marketplace Store

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

        return $list;
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

    /**
     * @param array<int, array<string, mixed>> $items one row per SKU (first order + 'quantity')
     * @param array<string|int, array<int, array<string, mixed>>> $keyedSku sku => order rows
     */
    public function getToRestockHTML(array $items, array $keyedSku): string
    {
        return $this->renderWithPlatformCounts($items, $keyedSku);
    }

    /**
     * @param array<int, array<string, mixed>> $itemToCollect one row per SKU (first order + 'quantity')
     * @param array<string|int, array<int, array<string, mixed>>> $keyedSku sku => order rows
     */
    public function getToCollectHTML(array $itemToCollect, array $keyedSku): string
    {
        return $this->renderWithPlatformCounts($itemToCollect, $keyedSku);
    }

    /**
     * Adds one column per marketplace holding the number of units of that SKU ordered there
     * (only SKUs known to stock are counted), then renders the table.
     */
    private function renderWithPlatformCounts(array $items, array $keyedSku): string
    {
        $platforms = array_fill_keys(self::PLATFORM_COLUMNS, []);
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

        $tblPackList = $this->newTablePackList_HTML_Maker();
        $tblPackList->setBody($items);

        return $tblPackList->getTable();
    }

    public function getNotFoundHTML(array $items): string
    {
        // add columns counts; count by each column (marketplace platforms)
        foreach (self::PLATFORM_COLUMNS as $col_platform) {
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

        $tblPackList = $this->newTablePackList_HTML_Maker();
        $tblPackList->setBody($items);

        return $tblPackList->getTable();
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
