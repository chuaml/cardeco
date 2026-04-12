<?php 
namespace Lazada\Factory;

use \Exception;
use \Lazada\Item;

//read input csv file to generate lazada record;
class LzdItemFactory{

    private $File;
    const DELIMITER = ';';
    const EXPECTED_FIELD_COUNT = 167;
    const TARGET_FIELD = [
        'Shop SKU',
        'SellerSku',
        'name',
        'special_price',
        'color_family',
        'MainImage',
        'Image2',
        'Image3',
        'Image4',
        'Image5',
        'package_weight',
        'package_length',
        'package_width',
        'package_height',
    ];

    private $fieldIndex = null;

    public function __construct(string $File){
        if(!file_exists($File)){
            throw new Exception("file does not exist: $File ");
        }
        $this->File = $File;
    }

    public function generateRecords():array{
        $in = fopen($this->File, 'rb');
        if($in === false){throw new Exception('cannot open file: ' .$this->File);}
        try{
            //first line row header for determining field index position
            $r = fgetcsv($in, 0, self::DELIMITER, '"', '\\');
            if($r === null){
                throw new Exception('fail to parse csv file data: ' .$this->File);
            }

            $NUM_COL = count($r);
            if($NUM_COL < self::EXPECTED_FIELD_COUNT){
                throw new \Exception('invalid file. must lazada products info .csv');
            }

            $this->fieldIndex = $this->getFieldIndex($r);

            $list = [];
            for($i=1;$i<2147483647;++$i){
                $r = fgetcsv($in, 0, self::DELIMITER, '"', '\\');
                if($r === false){
                    break;
                }
                if(count($r) !== $NUM_COL){
                    throw new Exception(
                        'inconsistence number of columns at line '
                        .($i + 1) .', invalid file or data cannot parse correctly.');
                }
                $list[] = $r;
            }
        }finally{
            if(fclose($in) === false){
                throw new Exception('fail to close file input stream:' .$this->File);
            }
        }

        $list = array_map(function($r){
            return $this->getRecord($r);
        }, $list);

        return $list;
    }

    private function getFieldIndex(array $fields){
        $map = \array_fill_keys(self::TARGET_FIELD, null);
        $SIZE = count($fields);
        for($i=0;$i<$SIZE;++$i){
            $fieldName = trim($fields[$i]);
            if(\array_key_exists(trim($fieldName),$map) === false){
                continue;
            }
            if($map[$fieldName] !== null){
                throw new Exception("column field name must be unique. duplicate field: '{$fieldName}'.");
            }

            $map[$fieldName] = $i;
        }

        //for first field name not found, bug in UTF-8 BOM invisible char
        if($map[self::TARGET_FIELD[0]] === null){
            $suspectedFieldName = trim(\substr($fields[0],strpos($fields[0], self::TARGET_FIELD[0])));
            if($suspectedFieldName === self::TARGET_FIELD[0]){
                $map[self::TARGET_FIELD[0]] = 0;
            }
        }

        foreach($map as $keyName => $index){
            if($index === null){
                throw new Exception("field: '{$keyName}' does not exist.");
            }
        }

        return $map;
    }

    private function getRecord(array &$row):Item{
        $Item = new Item();
        
        $Item->setLzdSku(trim($row[$this->fieldIndex[self::TARGET_FIELD[0]]]));
        $Item->setSellerSku(trim($row[$this->fieldIndex[self::TARGET_FIELD[1]]]));
        $Item->setName(trim($row[$this->fieldIndex[self::TARGET_FIELD[2]]]));
        $Item->setPrice((double)$row[$this->fieldIndex[self::TARGET_FIELD[3]]]);
        $Item->setColor(trim($row[$this->fieldIndex[self::TARGET_FIELD[4]]]));
        $Item->setImages(
            [
                trim($row[$this->fieldIndex[self::TARGET_FIELD[5]]]), 
                trim($row[$this->fieldIndex[self::TARGET_FIELD[6]]]), 
                trim($row[$this->fieldIndex[self::TARGET_FIELD[7]]]), 
                trim($row[$this->fieldIndex[self::TARGET_FIELD[8]]]), 
                trim($row[$this->fieldIndex[self::TARGET_FIELD[9]]])
            ]
        );
        $Item->setWeight((double)$row[$this->fieldIndex[self::TARGET_FIELD[10]]]);
        $Item->setLength((double)$row[$this->fieldIndex[self::TARGET_FIELD[11]]]);
        $Item->setWidth((double)$row[$this->fieldIndex[self::TARGET_FIELD[12]]]);
        $Item->setHeight((double)$row[$this->fieldIndex[self::TARGET_FIELD[13]]]);
      
        return $Item;
    }

}
