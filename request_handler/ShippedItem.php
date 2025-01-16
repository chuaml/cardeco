<?php

namespace main;


use \Exception;
use HTML\HtmlObject;
use HTML\HtmlTable;
use HTML\HtmlTableRow;
use HTML\HtmlTableCell as Cell;
use Orders\DailyStockOutItem_Factory;
use Orders\Factory\Excel\ExcelReader;
use Product\Manager\ItemManager;

$tbl = null;

if (isset($_FILES['bigseller_all_status_orders'])) {
    if ($_FILES['bigseller_all_status_orders']['error'] !== 0)
        throw new Exception("File has error.");

    $xlsx = new ExcelReader($_FILES['bigseller_all_status_orders']['tmp_name']);
    $iterator = $xlsx->read();


    // using bigseller info from xlsx row to find its db item info
    $item_group_by_sku = [];
    foreach ($iterator as $row) {
        $x = DailyStockOutItem_Factory::map($row);
        // itemCode is bigseller sku, temporary
        if (array_key_exists($x->sku, $item_group_by_sku) === true) {
            $item = $item_group_by_sku[$x->sku];
            $item->quantity += $x->quantity;
        } else {
            $item_group_by_sku[$x->sku] = $x;
        }
    }

    //item code from stock as index
    $IM = new ItemManager($con);
    $stockItems = $IM->selectByItemCode_withBigSellerSku(array_keys($item_group_by_sku));
    foreach ($stockItems as $x) {
        $sku = $x['item_code'];
        if (isset($x['bigseller_sku']) === true) {
            $sku = $x['bigseller_sku'];
        }
        if (array_key_exists($sku, $item_group_by_sku) === false) continue;
        $r = $item_group_by_sku[$sku];

        // add item info from db
        $r->item->code = $x['item_code'];
        $r->item->description = $x['description'];
    }


    // display
    $tbl = new HtmlTable();
    $tbl->setHeader(0, new Cell('Date'));
    $tbl->setHeader(1, new Cell('Item Code'));
    $tbl->setHeader(2, new Cell('Product Name'));
    $tbl->setHeader(3, new Cell('Quantity'));

    $totalCount = 0;
    foreach ($item_group_by_sku as $x) {
        if ($x->isShipped() === false) continue;

        $r = new HtmlTableRow();
        $r->setAttribute('is-empty-item-code', strlen($x->item->code) > 0 ? 'false' : 'true');

        $r->addCell(new Cell($x->orderTime));
        $r->addCell(new Cell($x->item->code ?? $x->sku)); // replaced with item name from db
        $r->addCell(new Cell($x->item->description ?? $x->productName)); // replaced with item name from db
        $r->addCell(new Cell($x->quantity));

        $tbl->addRow($r);
        $totalCount += $x->quantity ?? 0;
    }
    $tbl->setFooter(0, new Cell(''));
    $tbl->setFooter(1, new Cell(''));
    $tbl->setFooter(2, new Cell('Total: '));
    $tbl->setFooter(3, new Cell($totalCount));
}


require('view/ShippedItem.html');
