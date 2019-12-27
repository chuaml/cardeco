<?php 
namespace IO;

use Exception;

class CSVInputStream extends FileInputStream{

    private $delimiter = ',';
    private $enclosure = '"';
    private $escape = '\\';
    private $maxFieldLength = 0;

    public function __construct(string $file, 
        string $delimiter = ',', string $enclosure = '"', string $escape = '\\', int $maxFieldLength = 0){
        parent::__construct($file);
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = $escape;
    }

    public function setMaxFieldLength(int $maxFieldLength):void{
        if($maxFieldLength >= 0){
            $this->maxFieldLength = $maxFieldLength;
        }
    }

    public function readLines(int $maxNumLine = 2147483647):array{
        $list = [];
        $r = fgetcsv($this->IO, $this->maxFieldLength, $this->delimiter, $this->enclosure, $this->escape);
        if($r === null){
            throw new Exception('Fail to parse CSV file: ' .$this->file);
        }
        $list[] = $r;

        for($i=1;$i<$maxNumLine;++$i){
            $r = fgetcsv($this->IO, $this->maxFieldLength, $this->delimiter, $this->enclosure, $this->escape);
        
            if($r === false){
                break;
            }

            $list[] = $r;
        }

        return $list;
    }

}