<?php
namespace Orders\Factory;

require_once(__DIR__ .'/../Record.php');
require_once(__DIR__ .'/../../Product/Item.php');
require_once(__DIR__ .'/RecordFactory.php');

use \Orders\Record;
use \UnexpectedValueException;
use \Exception;
use \Product\Item;

//read input csv file to generate lazada record;
class Lazada implements RecordFactory
{
    private $File;
    const DELIMITER = ';';
    const DATE_FORMAT = 'd-M-Y';

    public function __construct(string $File)
    {
        if (!file_exists($File)) {
            throw new Exception("file does not exist: $File ");
        }
        $this->File = $File;
    }

    /*
    private function explodeCSV():array{
        $file = fopen($this->File, 'rb');
        if(!$file){throw new Exception('cannot open file: ' .$this->File);}
        try{
            $in = fread($file, filesize($this->File));
            if(!$in){throw new Exception('cannot read file: ' .$this->File);}

            $list = [];

            $listofRow = explode("\n", $in);
            $row;
            //first line row header is skipped
            for($i=1;$i<RecordFactory::MAX_RECORD;++$i){
                $row = explode(self::DELIMITER, $listofRow[$i]);
                if(count($row) <= 1){break;}

                $list[] = $this->getRecord($row);
            }
        }finally{
            if(fclose($file) === false){
                throw new Exception('fail to close file input stream:' .$this->File);
            }
        }
        $this->computeShippingFee($list);
        return $list;
    }
    */

    public function generateRecords():array
    {
        $in = fopen($this->File, 'rb');
        if ($in === false) {
            throw new Exception('cannot open file: ' .$this->File);
        }
        
        try {
            //first line row header is skipped
            $r = fgetcsv($in, 0, self::DELIMITER, '"', '\\');
            if ($r === null) {
                throw new Exception('fail to parse csv file data: ' .$this->File);
            }

            $list = [];
            $NUM_COL = count($r);
            for ($i=1;$i<RecordFactory::MAX_RECORD;++$i) {
                $r = fgetcsv($in, 0, self::DELIMITER, '"', '\\');
                if ($r === false) {
                    break;
                }
                if (count($r) !== $NUM_COL) {
                    throw new Exception(
                        'inconsistence number of columns at line '
                        .($i + 1) .', invalid file or data cannot parse correctly.'
                    );
                }
                $list[] = $r;
            }
        } finally {
            if (fclose($in) === false) {
                throw new Exception('fail to close file input stream:' .$this->File);
            }
        }

        $list = array_map(function ($r) {
            return $this->getRecord($r);
        }, $list);
        
        $this->computeShippingFee($list);
        return $list;
    }

    private function getRecord(array &$row):Record
    {
        /*dont use orderId for Lazada, each order record is base on orderNum.
        orderNum has N ordered items each ordered item has an orderId*/
        //$orderId = $row[1];
         
        //$orderNum = $row[6];
        //$itemCode = trim($row[2]);
        //$itemName = trim($row[42]);
        $Record = new Record(
            null,
            trim($row[6]),
            new Item(null, trim($row[2]), trim($row[42]))
        );

        $Record->setDate($this->getOrderDate($row[5]));
        $Record->setTrackingNum(trim($row[49]));
        $Record->setStatus(trim($row[56]));
        $Record->setSellingPrice(doubleval($row[39]));
        $Record->setShippingFeeByCust(doubleval($row[40]));
        $Record->setShippingState($row[19]);
        
        return $Record;
    }

    private function getOrderDate(string $cell):string
    {
        $dateCell = explode(' ', trim($cell));
        $date = '';
        $num_cell = count($dateCell);
        
        //date format d/M/Y
        //assumption is, explode empty space if size 6 old format, size 2 new format from lzd
        switch ($num_cell) {
            case 6:
                $date = $dateCell[2] . '-' .$dateCell[1] . '-' .$dateCell[5];
                break;
            case 2:
                $date = \str_replace('/', '-', $dateCell[0]);
                break;
        }

        if ($date === '' || date_create($date) === false) {
            throw new UnexpectedValueException(
                'invalid date format ' . $cell .' and possible invalid file data.'
            );
        }

        return $date;
    }

    private function computeShippingFee(array &$list):void
    {
        //for same orderNum but different trackingNum, ie more than 1 trackingNum
        //copy shippingFeeByCust to shippingFee
        $OrderNum = [];
        foreach ($list as $k => $r) {
            if (!\array_key_exists($r->orderNum, $OrderNum)) {
                $OrderNum[$r->orderNum] = [];
            }
            $OrderNum[$r->orderNum][$r->trackingNum] = $k;
        }

        foreach ($OrderNum as $num_tracking) {
            if (count($num_tracking) <= 1) {
                continue;
            }
            foreach ($num_tracking as $k) {
                $list[$k]->setShippingFee($list[$k]->shippingFeeByCust);
            }
        }
    }
}
