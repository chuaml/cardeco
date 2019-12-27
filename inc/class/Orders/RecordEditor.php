<?php 
namespace Orders;

require_once(__DIR__ .'/Factory/MonthlyRecord.php');
require_once(__DIR__ .'/../HTML/TableDisplayer.php');
require_once(__DIR__ .'/../Product/Factory/ItemFactory.php');
require_once(__DIR__ .'/PaymentCharges/PlatformCharges.php');

use \Orders\Factory\MonthlyRecord;
use \Product\Factory\ItemFactory;
use \Orders\PaymentCharges\PlatformCharges;
use \HTML\TableDisplayer;

class RecordEditor extends TableDisplayer{

    private $records = [];
    private $numFloorPage;

    public function setMonthlyRecords(array $MonthlyRecords, int $numPage):void{
        $this->records = [];
        if(sizeof($MonthlyRecords) === 0){return;}

        $this->numFloorPage = $numPage;

        $field;
        $cleanRecord = $MonthlyRecords[0]->getAll();
        foreach($MonthlyRecords as $M){
            $field = $M->getAll();
            foreach ($field as $fieldName => $v) {
                $cleanRecord[$fieldName] = \htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
            }
            $this->records[] = $cleanRecord;
        }
        $this->setEditorCells();
    }

    private function setEditorCells():void{
        $len = sizeof($this->records);
        //recordId as row array index
        $recordId; $r;
        for($i=0;$i<$len;++$i){
            $recordId = $this->records[$i]['recordId'];
            $r = &$this->records[$i];

            $r['billno'] = 
            '<input type="text" name="r['.$recordId.'][billno]" value="'
            .$r['billno'].'" maxlength="20" size="5" readonly/>';
            
            $r['trackingNum'] = 
            '<input type="text" name="r['.$recordId.'][trackingNum]" value="'
            .$r['trackingNum'].'" maxlength="20" readonly/>';

            $r['shippingFee'] = 
            '<input type="number" name="r['.$recordId.'][shippingFee]" value="'
            .$r['shippingFee'].'" min="0" max="1000" step="0.01" readonly/>';
            
            $r['shippingFeeByCust'] = 
            '<input type="number" name="r['.$recordId.'][shippingFeeByCust]" value="'
            .$r['shippingFeeByCust'].'" min="0" max="1000" step="0.01" readonly/>';

            $r['voucher'] = 
            '<input type="number" name="r['.$recordId.'][voucher]" value="'
            .$r['voucher'].'" min="0" max="1000" step="0.01" readonly/>';
            
            $r['platformChargesAmount'] = 
            '<input type="number" name="r['.$recordId.'][platformChargesAmount]" value="'
            .$r['platformChargesAmount'].'" min="0" max="1000" step="0.01" readonly/>';
            
            $r['cash'] = 
            '<input type="number" name="r['.$recordId.'][cash]" value="'
            .$r['cash'].'" min="0" max="1000" step="0.01" readonly/>';
            
            $r['bankIn'] = 
            '<input type="number" name="r['.$recordId.'][bankIn]" value="'
            .$r['bankIn'].'" min="0" max="1000" step="0.01" readonly/>';
        }
    }

    protected function getReNamedHeader():array{
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
        $fieldName['status'] = 'Status';
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

    public function getTable():string{
        $header = $this->getReNamedHeader();
        parent::setHead($header, true);
        parent::setBody($this->records);
        parent::setAttributes('id="RecordEditor" border="1"');
        
        return parent::getTable();
    }

    public function getFloorPage():string{
        $searchField = $_GET['searchField'] ?? '';
        $searchValue = $_GET['searchValue'] ?? '';

        $pg = '<form method="GET" id="floorPage">';
        $pg .= '<input type="hidden" name="searchField" value="' .$searchField .'">';
        $pg .= '<input type="hidden" name="searchValue" value="' .$searchValue .'">';
        for($i=0;$i<$this->numFloorPage;++$i){
            $pg .= 
            "<input type=\"submit\" name=\"floorPage\" value=\"{$i}\"> ";
        }
        $pg .= '</form>';

        return $pg;
    }
}
