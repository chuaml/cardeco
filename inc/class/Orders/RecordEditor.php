<?php

namespace Orders;

use \HTML\TableDisplayer;

class RecordEditor extends TableDisplayer
{

    private $records = [];
    private $numFloorPage;

    public function setMonthlyRecords(array $MonthlyRecords, int $numPage): void
    {
        $this->records = [];
        if (sizeof($MonthlyRecords) === 0) {
            return;
        }

        $this->numFloorPage = $numPage;

        $cleanRecord = $MonthlyRecords[0]->getAll();
        foreach ($MonthlyRecords as $M) {
            $field = $M->getAll();
            foreach ($field as $fieldName => $v) {
                if (is_float($v) === true) {
                    $cleanRecord[$fieldName] = $v;
                } else {
                    $cleanRecord[$fieldName] = \htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
                }
            }
            $this->records[] = $cleanRecord;
        }
        $this->setEditorCells();
    }

    private function setEditorCells(): void
    {
        $len = sizeof($this->records);
        for ($i = 0; $i < $len; ++$i) {
            $recordId = $this->records[$i]['recordId'];
            $r = &$this->records[$i];

            $r['billno'] =
                '<input type="text" name="r[' . $recordId . '][billno]" value="'
                . $r['billno'] . '" maxlength="20" size="5" readonly/>';

            $r['trackingNum'] =
                '<input type="text" name="r[' . $recordId . '][trackingNum]" value="'
                . $r['trackingNum'] . '" maxlength="20" readonly/>';

            $r['shippingFee'] =
                '<input inputmode="numeric" pattern="[0-9,]+(?:\.[0-9]{1,2})?" name="r[' . $recordId . '][shippingFee]" value="'
                . number_format($r['shippingFee'], 2, '.', ',') . '" maxlength="16" class="money" readonly/>';

            $r['shippingFeeByCust'] =
                '<input inputmode="numeric" pattern="[0-9,]+(?:\.[0-9]{1,2})?" name="r[' . $recordId . '][shippingFeeByCust]" value="'
                . number_format($r['shippingFeeByCust'], 2, '.', ',') . '" maxlength="16" class="money" readonly/>';

            $r['voucher'] =
                '<input inputmode="numeric" pattern="[0-9,]+(?:\.[0-9]{1,2})?" name="r[' . $recordId . '][voucher]" value="'
                . number_format($r['voucher'], 2, '.', ',') . '" maxlength="16" class="money" readonly/>';

            $r['platformChargesAmount'] =
                '<input inputmode="numeric" pattern="[0-9,]+(?:\.[0-9]{1,2})?" name="r[' . $recordId . '][platformChargesAmount]" value="'
                . number_format($r['platformChargesAmount'], 2, '.', ',') . '" maxlength="16" class="money" readonly/>';

            $r['cash'] =
                '<input inputmode="numeric" pattern="[0-9,]+(?:\.[0-9]{1,2})?" name="r[' . $recordId . '][cash]" value="'
                . number_format($r['cash'], 2, '.', ',') . '" maxlength="16" class="money" readonly/>';

            $r['bankIn'] =
                '<input inputmode="numeric" pattern="[0-9,]+(?:\.[0-9]{1,2})?" name="r[' . $recordId . '][bankIn]" value="'
                . number_format($r['bankIn'], 2, '.', ',') . '" maxlength="16" class="money" readonly/>';
        }
    }

    protected function formatCell($value, $ofColumnIndex): string
    {
        if ($value !== null) {
            if (is_float($value) === true) {
                return '<td>' . number_format($value, 2, '.', ',') . '</td>';
            }

            if ($value !== "" && ($date = date_create($value)) !== false) {
                $date = date_format($date, 'Y-m-d'); // yyyy-MM-dd ISO date for custom sorting
                return "<td data-value=\"$date\">" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
            }
        }

        switch ($ofColumnIndex) {
            case 'billno':
            case 'trackingNum':
            case 'shippingFee':
            case 'shippingFeeByCust':
            case 'shippingFee':
            case 'voucher':
            case 'platformChargesAmount':
            case 'bankIn':
            case 'cash':
                return '<td>' . $value . '</td>';
            default:
                return '<td>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
    }

    protected function getReNamedHeader(): array
    {
        //key original fieldName, value as new user friendly field name
        $fieldName = [];

        // $fieldName['recordId'] = 'Record ID';
        $fieldName['billno'] = 'Bill No.';
        $fieldName['date'] = 'Date';
        $fieldName['dateStockOut'] = 'Date Stock Out';
        $fieldName['orderNum'] = 'Order Number';
        $fieldName['itemName'] = 'Description';
        $fieldName['sku'] = 'Seller SKU';
        $fieldName['trackingNum'] = 'Tracking Number';
        $fieldName['sellingPrice'] = 'Selling Price';
        $fieldName['grandTotal'] = 'Grand Total';
        $fieldName['shippingFee'] = 'Courier';
        $fieldName['shippingFeeByWeight'] = 'Courier 2';
        $fieldName['shippingFeeByCust'] = 'Courier By Customer';
        $fieldName['shippingState'] = 'State';
        $fieldName['shippingWeight'] = 'Weight';
        $fieldName['voucher'] = 'Voucher';

        $fieldName['transferCharges'] = 'Transfer Charges';
        $fieldName['platformCharges'] = 'Platform Charges';
        $fieldName['platformChargesAmount'] = 'Platform Charges Amount';
        $fieldName['bankIn'] = 'Bank In';
        $fieldName['cash'] = 'Cash';
        return $fieldName;
    }

    public function getTable(): string
    {
        $header = $this->getReNamedHeader();
        parent::setHead($header, true);
        parent::setBody($this->records);
        parent::setAttributes('id="RecordEditor" cd-editable-sheet');

        return parent::getTable();
    }

    public function getFloorPage(): string
    {
        $searchField = $_GET['searchField'] ?? '';
        $searchValue = $_GET['searchValue'] ?? '';

        $pg = '<form method="GET" id="floorPage">';
        $pg .= '<input type="hidden" name="searchField" value="' . $searchField . '">';
        $pg .= '<input type="hidden" name="searchValue" value="' . $searchValue . '">';
        if($this->numFloorPage === 0) {
            $pg .= "<input type=\"submit\" name=\"floorPage\" value=\"0\"> ";
        }
        else {
            for ($i = 0; $i < $this->numFloorPage; ++$i) {
                $pg .=
                    "<input type=\"submit\" name=\"floorPage\" value=\"{$i}\"> ";
            }
        }
        $pg .= '</form>';

        return $pg;
    }
}
