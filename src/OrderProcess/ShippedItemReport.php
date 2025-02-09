<?php 

namespace Report;

use DateTime;
use \Exception;
use HTML\HtmlObject;
use HTML\HtmlTable;
use HTML\HtmlTableRow;
use HTML\HtmlTableCell as Cell;
use mysqli;
use Orders\DailyStockOutItem_Factory;
use Orders\Factory\Excel\ExcelReader;
use Orders\MonthlyRecord\OrderLine;
use Product\Manager\ItemManager;

final class ShippedItemReport
{
    private mysqli $con;
    public ?HtmlTable $tbl_for_shippedItemReport;
    public ?HtmlTable $tbl_for_MotnhlyRecord;

    public function __construct(
        mysqli $con
    ) {
        $this->con = $con;
    }

    public function handleRequest(
        array $files
    ) {
        if (isset($files['bigseller_all_status_orders'])) {
            if ($files['bigseller_all_status_orders']['error'] !== 0)
                throw new Exception("File has error.");

            $xlsx = new ExcelReader($files['bigseller_all_status_orders']['tmp_name']);
            $iterator = $xlsx->read();

            $item_group_by_sku = $this->processItems($iterator);
            $this->fetchItemDetails($item_group_by_sku);
            $this->generateShippedItemReport($item_group_by_sku);

            // Reset the iterator by calling read() again
            $iterator = $xlsx->read();
            $this->generateMonthlyRecord($iterator);
        }
    }

    private function processItems($iterator)
    {
        $item_group_by_sku = [];
        foreach ($iterator as $row) {
            $x = DailyStockOutItem_Factory::map($row);
            if ($x->isShipped() === false) continue;

            if (array_key_exists($x->sku, $item_group_by_sku) === true) {
                $item = $item_group_by_sku[$x->sku];
                $item->quantity += $x->quantity;
            } else {
                $item_group_by_sku[$x->sku] = $x;
            }
        }
        return $item_group_by_sku;
    }

    private function fetchItemDetails(&$item_group_by_sku)
    {
        $IM = new ItemManager($this->con);
        $stockItems = $IM->selectByItemCode_withBigSellerSku(array_keys($item_group_by_sku));
        foreach ($stockItems as $x) {
            $sku = $x['item_code'];
            if (isset($x['bigseller_sku']) === true) {
                $sku = $x['bigseller_sku'];
            }
            if (array_key_exists($sku, $item_group_by_sku) === false) continue;
            $r = $item_group_by_sku[$sku];

            $r->item->code = $x['item_code'];
            $r->item->description = $x['description'];
        }
    }

    private function generateShippedItemReport($item_group_by_sku)
    {
        $tbl = new HtmlTable();
        $tbl->setHeader(0, new Cell('Date'));
        $tbl->setHeader(1, new Cell('Item Code'));
        $tbl->setHeader(2, new Cell('Product Name'));
        $tbl->setHeader(3, new Cell('Quantity'));

        $totalCount = 0;
        $now = (new DateTime())->format('m/d/Y');
        foreach ($item_group_by_sku as $x) {
            $r = new HtmlTableRow();
            $r->setAttribute('is-empty-item-code', strlen($x->item->code) > 0 ? 'false' : 'true');

            $r->addCell(new Cell($now));
            $r->addCell(new Cell($x->item->code ?? $x->sku));
            $r->addCell(new Cell($x->item->description ?? $x->productName));
            $r->addCell(new Cell($x->quantity));

            $tbl->addRow($r);
            $totalCount += $x->quantity ?? 0;
        }
        $tbl->setFooter(0, new Cell(''));
        $tbl->setFooter(1, new Cell(''));
        $tbl->setFooter(2, new Cell('Total: '));
        $tbl->setFooter(3, new Cell($totalCount));
        $this->tbl_for_shippedItemReport = $tbl;
    }

    private function generateMonthlyRecord($iterator)
    {
        $orders = [];
        $now = (new DateTime())->format('m/d/Y');
        foreach ($iterator as $row) {
            $x = new OrderLine();
            $x->ShippedItem = DailyStockOutItem_Factory::map($row);
            if ($x->ShippedItem->isShipped() === false) continue;

            $x->orderStatus = $x->ShippedItem->shippingStatus;
            $x->trackingNumber = trim($row[51]);
            $x->dateOfSendOut = $now;
            $x->orderNumber = trim($row[0]);

            $orders[] = $x;
        }

        $tbl = new HtmlTable();
        $tbl->setHeader(0, new Cell('Order Status'));
        $tbl->setHeader(1, new Cell('Tracking Number'));
        $tbl->setHeader(2, (new Cell('Date of Send Out (MM/dd/yyyy)'))->setAttribute('title', 'MM/dd/yyyy'));
        $tbl->setHeader(3, new Cell('Order Number'));
        foreach ($orders as $x) {
            $r = new HtmlTableRow();
            $r->addCell(new Cell($x->orderStatus));
            $r->addCell(new Cell($x->trackingNumber));
            $r->addCell(new Cell($x->dateOfSendOut));
            $r->addCell(new Cell($x->orderNumber));
            $r->setAttribute('data-order-number', $x->orderNumber);

            $tbl->addRow($r);
        }
        $this->tbl_for_MotnhlyRecord = $tbl;
    }
}