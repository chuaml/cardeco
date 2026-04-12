<?php 
namespace Orders;

require_once(__DIR__ .'/Factory/MonthlyRecord.php');
require_once(__DIR__ .'/../HTML/TableDisplayer.php');
require_once(__DIR__ .'/../Product/Factory/ItemFactory.php');
require_once(__DIR__ .'/PaymentCharges/PlatformCharges.php');

use \HTML\TableDisplayer;

class StockOutTable extends TableDisplayer{

    private $records = [];
    private $numFloorPage;

    public function setRecords(array $list):void{
        $this->records = [];
        if(count($list) === 0){return;}

        $cleanRecord = [];
        foreach($list as $r){
            foreach ($r as $k => $v) {
                $cleanRecord[$k] = \htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
            }
            $this->records[] = $cleanRecord;
        }
    }

    protected function getReNamedHeader():array{
        $fieldName['sku'] = 'SKU';
        $fieldName['description'] = 'Description';
        $fieldName['quantity'] = 'Quantity';
        return $fieldName;
    }

    public function getTable():string{
        $header = $this->getReNamedHeader();
        parent::setBody($this->records);
        parent::setHead($header, true);
        parent::setFoot($this->getFooter());
        parent::setAttributes('id="RecordEditor" border="1"');
        
        return parent::getTable();
    }

    private function getFooter():array{
        $total = 0;
        foreach($this->records as $r){
            $total += (int) $r['quantity'];
        }

        return ['', 'total', $total];
    }
}
