<?php
namespace Orders\Factory\Excel;

use Exception;

class ExcelReader
{
    private static function ColumnAlphabetToNumber(string $columnAlphabet):int{
        $col = str_split(strtoupper($columnAlphabet));
        
        $map = [
            'A' => 1,
            'B' => 2,
            'C' => 3,
            'D' => 4,
            'E' => 5,
            'F' => 6,
            'G' => 7,
            'H' => 8,
            'I' => 9,
            'J' => 10,
            'K' => 11,
            'L' => 12,
            'M' => 13,
            'N' => 14,
            'O' => 15,
            'P' => 16,
            'Q' => 17,
            'R' => 18,
            'S' => 19,
            'T' => 20,
            'U' => 21,
            'V' => 22,
            'W' => 23,
            'X' => 24,
            'Y' => 25,
            'Z' => 26
        ];
        
        //calc the column alphabets as actual number
        //alphabet is number base 26 
        $number = 0;
        $count = count($col);
        $digitPos = count($col) -1;
        for($i=0;$i<$count;++$i, --$digitPos){
            $number += (26 ** $digitPos) * $map[$col[$i]];
        }
        return $number;
    } 
    private static function readData(string $fileName, ?string $sheetName, int $startRowPos , int $lastRowPos):array
    {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($fileName);
        
        if($sheetName != null){
            $spreadsheet->setActiveSheetIndexByName($sheetName);
        }

        $worksheet = $spreadsheet->getActiveSheet();
        
        $list = [];
        
        $count = intval($worksheet->getHighestRow());
        if($lastRowPos <= -1 || $lastRowPos > $count){
            $lastRowPos = $count;
        }
        
        $lastCol = self::ColumnAlphabetToNumber($worksheet->getHighestColumn());

        for($i=$startRowPos; $i<=$lastRowPos ; ++$i){
            $r = [];
            for($c=1;$c<=$lastCol;++$c){
                $cell = $worksheet->getCellByColumnAndRow($c,$i);

                try {
                    $r[] = trim($cell->getFormattedValue());
                } catch (\PhpOffice\PhpSpreadsheet\Calculation\Exception $e) {
                    $r[] = trim($cell->getValue());
                }
            }

            $list[] = $r;
        }

        return $list;
    }

    public static function fetch(string $fileName, ?string $fileTab = null, int $startRowPos = 1 , int $lastRowPos = -1)
    {
        //assume row 1 is header
        $header = self::readData($fileName, $fileTab,1,1)[0];
        foreach ($header as $i => $name) {
            $header[$i] = strtoupper($name);
        }

        if($startRowPos <= 1){
            //move after first line header
            $startRowPos = 2;
        }

        $list = self::readData($fileName, $fileTab,$startRowPos,$lastRowPos);
        $listCount = count($list);
        if($listCount === 0){
            return $list;
        } 
        
        $colCount = count($header);
        //add reference named index
        for ($i=0;$i<$listCount;++$i) {
            for ($c=0;$c<$colCount;++$c) {
                $list[$i][$header[$c]] =& $list[$i][$c];
            }
        }

        return $list;
    }
}
