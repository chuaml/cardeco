<?php
namespace main;

require 'vendor/autoload.php';

class a
{
    private static function ColumnAlphabetToNumber(string $columnAlphabet):int
    {
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
        for ($i=0;$i<$count;++$i, --$digitPos) {
            $number += (26 ** $digitPos) * $map[$col[$i]];
        }
        return $number;
    }
    private static function readData(string $fileName, ?string $sheetName, int $seek):array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($fileName);

        if ($sheetName != null) {
            $spreadsheet->setActiveSheetIndexByName($sheetName);
        }

        $worksheet = $spreadsheet->getActiveSheet();
        
        $list = [];
 
        $it = $worksheet->getRowIterator();
        $it = $it->seek($seek);
        var_dump($it);
        foreach ($it as $row) {
            $cellIt = $row->getCellIterator();
            $r = [];
            foreach ($cellIt as $cell) {
                try {
                    $r[] = $cell->getFormattedValue();
                } catch (\PhpOffice\PhpSpreadsheet\Calculation\Exception $e) {
                    $r[] = $cell->getValue();
                }
            }
            $list[] = $r;
        }

        return $list;
    }

    public static function fetch(string $fileName, ?string $fileTab = null, int $seek = 1)
    {
        //assume row 1 is header
        $header = self::readData($fileName, $fileTab, 1, 1)[0];
        foreach ($header as $i => $name) {
            $header[$i] = strtoupper($name);
        }
 

        $list = self::readData($fileName, $fileTab, $seek);
        $listCount = count($list);
        if ($listCount === 0) {
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

// $reader = new a();
// $list = $reader->fetch('C:/Users/mlcmi/Desktop/sample.xlsx', 'Lazada', 19);

// var_dump($list);


// $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
// $spreadsheet = $reader->load('sample.xlsx');
// $worksheet = $spreadsheet->getActiveSheet();

// $list = [];
// $it = $worksheet->getRowIterator()->seek(200);
// //it still start at row 1
// foreach ($it as $row) {
//     $cellIt = $row->getCellIterator();
//     $r = [];
//     foreach ($cellIt as $cell) {
//         $r[] = $cell->getFormattedValue();
//     }
//     $list[] = $r;
// }
// //in the end the list contains all the row values, row 1 to row n
// var_dump($list);



// $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
// $reader->setReadDataOnly(true);
// $spreadsheet = $reader->load("C:/Users/mlcmi/Desktop/sample.xlsx");


// $worksheet = $spreadsheet->getActiveSheet();
// // Get the highest row number and column letter referenced in the worksheet
// $highestRow = $worksheet->getHighestRow(); // e.g. 10
// $highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
// // Increment the highest column letter
// $highestColumn++;


// echo '<table border="1">' . "\n";
// for ($row = 1; $row <= $highestRow; ++$row) {
//     echo '<tr>' . PHP_EOL;
//     for ($col = 'A'; $col != $highestColumn; ++$col) {
//         echo '<td>' .
//              $worksheet->getCell($col . $row)
//                  ->getValue() .
//              '</td>' . PHP_EOL;
//     }
//     echo '</tr>' . PHP_EOL;
// }
// echo '</table>' . PHP_EOL;

class Stopwatch{
    private $recordedTime = 0.0;

    public static  final function now():float
    {
        list($usec, $sec) = explode(' ', microtime());
        return ((float)$usec + (float)$sec);
    }

    public function set():void{
        $this->recordedTime = self::now();
    }

    public function lap():void{
        echo (self::now() - $this->recordedTime) .' seconds<br><br>';
    }
}

$t = new Stopwatch();


$data = 0;
$t->set();
$a = 26**5;
for ($i=0;$i<$a;++$i) {
    $data = $i;
}
 
for ($i=0;$i<$a;++$i) {
    $data += $i;
}

for ($i=0;$i<$a;++$i) {
    $data *= $i;
}

for ($i=0;$i<$a;++$i) {
    $data = $i *3;
}
$t->lap();



// $data = 0;
// $t->set();
// $a = 26**5;
// for ($i=0;$i<$a;++$i) {
//     $data = $i;
//     $data += $i;
//     $data *= $i;
//     $date = $i * 3;
// }
// $t->lap();


// $data = '';
// $t->set();
// $a = 'Z';
// for ($i='A';$i!=$a;++$i) {
//     $data = $i;
// }
// $t->lap();



