<?php 
namespace Orders\Factory;

require_once(__DIR__ .'/../MonthlyRecord.php');
require_once(__DIR__ .'/RecordFactory.php');

use \Orders\MonthlyRecord;
use \UnexpectedValueException;
use \Exception;


//read input csv file to generate date stock out with trackingNum;
class DateStockOut implements RecordFactory{

    private $File;
    const DELIMITER = ',';
    const PRINTABLE_CHAR_PATTERN = '/[[:^print:]]/';

    public function __construct(string $File){
        if(!file_exists($File)){
            throw new Exception("file does not exist: $File ");
        }
        $this->File = $File;
    }

    public function generateRecords():array{
        $in = fopen($this->File, 'rb');
        if(!$in){throw new Exception('cannot open file: ' .$this->File);}
        $list = [];
        $r;
        try{
            //first row, throw exception if fail
            $r = fgetcsv($in, 0, self::DELIMITER, '"', '\\');
            if($r === null){throw new Exception('fail to parse csv file data: ' .$this->File);}
            if($r !== false){
                $list[] = $r;
            }

            //the rest rows
            for($i=1;$i<RecordFactory::MAX_RECORD;++$i){
                $r = fgetcsv($in, 0, self::DELIMITER, '"', '\\');
                if($r === false){
                    break;
                }

                $list[] = $r;
            }
        }finally{
            if(fclose($in) === false){
                throw new Exception('I/O Exception, cannot close file: ' .$this->File);
            }
        }
        
        return array_map(function($r){
                    return $this->getRecord($r);
                }, $list);
    }

    private function getRecord(array &$row):MonthlyRecord{
        //remove special char, ex utf8-bom char, invisible char
        $r = array_map(function($v) {
            return \preg_replace(self::PRINTABLE_CHAR_PATTERN, '', $v);
        },$row);
        
        $M = new MonthlyRecord();
        $M->setTrackingNum($r[0]);
        $M->setDateStockOut($r[1]);

        return $M;
    }
 
}
