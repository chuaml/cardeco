<?php

namespace Orders;

require_once(__DIR__ . '/Factory/MonthlyRecord.php');
require_once(__DIR__ . '/../HTML/TableDisplayer.php');
require_once(__DIR__ . '/../Product/Factory/ItemFactory.php');
require_once(__DIR__ . '/PaymentCharges/PlatformCharges.php');

use Generator;
use HTML\HtmlTable;
use HTML\HtmlTableRow;
use HTML\HtmlTableCell as Cell;

class RecordEditor
{
    private $MonthlyRecords = [];
    public function __construct(array $MonthlyRecords, int $numPage)
    {
        $this->numFloorPage = $numPage;
        $this->numFloorPage = $numPage;
        $this->MonthlyRecords = $MonthlyRecords;
    }

    private function fillTableBody(HtmlTable $htmlTable): array
    {
        $x = null;
        foreach ($this->MonthlyRecords as $Records) {
            $x = $Records->getAll();
            $r = new HtmlTableRow();
            $recordId = $x['recordId'];
            $r->addCell(new Cell($x['billno']))
                ->setAttribute('contenteditable', 'plaintext-only')
                ->setAttribute('data-max-length', '20')
                ->setAttribute('data-name', "r[$recordId]");
            $r->addCell(new Cell($x['date']));
            $r->addCell(new Cell($x['dateStockOut']));
            $r->addCell(new Cell($x['orderNum']));
            $r->addCell(new Cell($x['itemName']));
            $r->addCell(new Cell($x['sku']));
            $r->addCell(new Cell($x['trackingNum']))
                ->setAttribute('contenteditable', 'plaintext-only')
                ->setAttribute('data-max-length', '20')
                ->setAttribute('data-name', "r[$recordId]");
            $r->addCell(new Cell($x['sellingPrice']));
            $r->addCell(new Cell($x['grandTotal']));
            $r->addCell(new Cell($x['shippingFee']))
                ->setAttribute('contenteditable', 'plaintext-only')
                ->setAttribute('data-max', '1000')
                ->setAttribute('data-max', '0')
                ->setAttribute('data-step', '.1')
                ->setAttribute('data-name', "r[$recordId]");
            $r->addCell(new Cell($x['shippingFeeByWeight']));
            $r->addCell(new Cell($x['shippingFeeByCust']))
                ->setAttribute('data-max', '1000')
                ->setAttribute('data-max', '0')
                ->setAttribute('data-step', '.1')
                ->setAttribute('data-name', "r[$recordId]");
            $r->addCell(new Cell($x['shippingState']));
            $r->addCell(new Cell($x['shippingWeight']));

            $r->addCell(new Cell($x['voucher']))
                ->setAttribute('data-max', '1000')
                ->setAttribute('data-max', '0')
                ->setAttribute('data-step', '.1')
                ->setAttribute('data-name', "r[$recordId]");
            $r->addCell(new Cell($x['transferCharges']));
            $r->addCell(new Cell($x['platformCharges']));
            $r->addCell(new Cell($x['platformChargesAmount']))
                ->setAttribute('data-max', '1000')
                ->setAttribute('data-max', '0')
                ->setAttribute('data-step', '.1')
                ->setAttribute('data-name', "r[$recordId]");
            $r->addCell(new Cell($x['bankIn']))
                ->setAttribute('data-max', '1000')
                ->setAttribute('data-max', '0')
                ->setAttribute('data-step', '.1')
                ->setAttribute('data-name', "r[$recordId]");
            $r->addCell(new Cell($x['cash']))
                ->setAttribute('data-max', '1000')
                ->setAttribute('data-max', '0')
                ->setAttribute('data-step', '.1')
                ->setAttribute('data-name', "r[$recordId]");

            $htmlTable->addRow($r);
        }
        return $x;
    }
    private function fillTableHeader(HtmlTable $tbl, array $row)
    {
        $tbl->addHeader('Bill No.')->setAttribute('id', 'billno');
        $tbl->addHeader('Date')->setAttribute('id', 'date');
        $tbl->addHeader('Date Stock Out')->setAttribute('id', 'dateStockOut');
        $tbl->addHeader('Order Number')->setAttribute('id', 'orderNum');
        $tbl->addHeader('Description')->setAttribute('id', 'itemName');
        $tbl->addHeader('Seller SKU')->setAttribute('id', 'sku');
        $tbl->addHeader('Tracking Number')->setAttribute('id', 'trackingNum');
        $tbl->addHeader('Selling Price')->setAttribute('id', 'sellingPrice');
        $tbl->addHeader('Grand Total')->setAttribute('id', 'grandTotal');
        $tbl->addHeader('Courier')->setAttribute('id', 'shippingFee');
        $tbl->addHeader('Courier 2')->setAttribute('id', 'shippingFeeByWeight');
        $tbl->addHeader('Courier By Customer')->setAttribute('id', 'shippingFeeByCust');
        $tbl->addHeader('State')->setAttribute('id', 'shippingState');
        $tbl->addHeader('Weight')->setAttribute('id', 'shippingWeight');
        $tbl->addHeader('Voucher')->setAttribute('id', 'voucher');

        $tbl->addHeader('Transfer Charges')->setAttribute('id', 'transferCharges');
        $tbl->addHeader('Platform Charges')->setAttribute('id', 'platformCharges');
        $tbl->addHeader('Platform Charges Amount')->setAttribute('id', 'platformChargesAmount');
        $tbl->addHeader('Bank In')->setAttribute('id', 'bankIn');
        $tbl->addHeader('Cash')->setAttribute('id', 'cash');
    }

    public function getTable(): Generator
    {
        $htmlTable = new HtmlTable();
        $htmlTable->setAttribute('id', 'RecordEditor');
        $lastRecord = $this->fillTableBody($htmlTable);
        $this->fillTableHeader($htmlTable, $lastRecord);
        return $htmlTable->streamHtmlText();
    }

    private $numFloorPage;

    public function getFloorPage(): string
    {
        $searchField = $_GET['searchField'] ?? '';
        $searchValue = $_GET['searchValue'] ?? '';

        $pg = '<form method="GET" id="floorPage">';
        $pg .= '<input type="hidden" name="searchField" value="' . $searchField . '">';
        $pg .= '<input type="hidden" name="searchValue" value="' . $searchValue . '">';
        for ($i = 0; $i < $this->numFloorPage; ++$i) {
            $pg .=
                "<input type=\"submit\" name=\"floorPage\" value=\"{$i}\"> ";
        }
        $pg .= '</form>';

        return $pg;
    }
}
