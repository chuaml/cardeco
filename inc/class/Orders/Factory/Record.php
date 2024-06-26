<?php 
namespace Orders\Factory;

require_once(__DIR__ .'/../Record.php');
require_once(__DIR__ .'/../../Product/Item.php');
require_once(__DIR__ .'/RecordFactory.php');

use \UnexpectedValueException;
use \Exception;
use \Product\Item;

//read input array data to generate Record;
class Record implements RecordFactory{
    
    private $List;

    public function __construct(array $List){
        if(count($List) === 0){
            throw new UnexpectedValueException('List size is empty');
        }
        $this->List = $List;
    }

    public function generateRecords():array{
        $list = [];
        $len = count($this->List);
        if($len > RecordFactory::MAX_RECORD){
            $len = RecordFactory::MAX_RECORD;
        }
        for($i=0;$i<$len;++$i){
            $list[] = $this->getRecord($this->List[$i]);
        }
        
        return $list;
    }

    private function getRecord(array &$row):\Orders\Record{
        $description = '';
        $Record = new \Orders\Record(
            null, 
            $row['orderNum'],
            new Item(null, $row['sku'], $description)
        );

        $Record->setDate($row['date']);
        $Record->setTrackingNum(trim($row['trackingNum']));
        $Record->setSellingPrice(doubleval($row['sellingPrice']));
        $Record->setShippingFee(doubleval($row['shippingFee']));
        $Record->setShippingFeeByCust(doubleval($row['shippingFeeByCust']));
        
        return $Record;
    }

}
