<?php 
// namespace Orders\Factory;

// require_once(__DIR__ .'/../Record.php');
// require_once(__DIR__ .'/../../Product/Item.php');
// require_once(__DIR__ .'/RecordFactory.php');

// use \Orders\Record;
// use \UnexpectedValueException;
// use \Exception;
// use \Product\Item;

// //read input csv file to generate old Shopee record (pre 2019-March-28);
// class Shopee implements RecordFactory{

//     private $File;

//     const DELIMITER = ',';

//     private $ItemDetail;

//     public function __construct(string $File){
//         if(!file_exists($File)){
//             throw new Exception("file does not exist: $File ");
//         }
//         $this->File = $File;
        
//         $this->ItemDetail = new ItemDetail(',');
//     }

//     public function generateRecords():array{
//         $in = fopen($this->File, 'rb');
//         if($in === false){throw new Exception('cannot open file: ' .$this->File);}
//         try{
//             //first line row header is skipped
//             $r = fgetcsv($in, 0, self::DELIMITER, '"', '\\');
//             if($r === null){
//                 throw new Exception('fail to parse csv file data: ' .$this->File);
//             }

//             $list = [];
//             $NUM_COL = count($r);
//             for($i=1;$i<RecordFactory::MAX_RECORD;++$i){
//                 $r = fgetcsv($in, 0, self::DELIMITER, '"', '\\');
//                 if($r === false){
//                     break;
//                 }
//                 if(count($r) !== $NUM_COL){
//                     throw new Exception(
//                         'inconsistence number of columns at line '
//                         .($i + 1) .', invalid file or cannot parse data correctly.');
//                 }
//                 $list[] = $r;
//             }
//         }finally{
//             if(fclose($in) === false){
//                 throw new Exception('fail to close file input stream:' .$this->File);
//             }
//         }

//         $Records = [];
//         foreach($list as $r){
//             \array_push($Records, ...$this->getRecords($r));
//         }

//         return $Records;
//     }

//     private function getRecords(array &$row):array{
//         $this->ItemDetail->setItemDetails($row[9]);
//         $itemLists = $this->ItemDetail->getItemLists();
//         $list = [];
//         foreach($itemLists as $item){
//             $Record = new Record(
//                 null, 
//                 trim($row[0]),
//                 new Item(null, $item['sku'], null)
//             );

//             $Record->setSellingPrice(doubleval($item['sellingPrice']));
//             $Record->setStatus(trim($row[1]));
//             $Record->setDate($this->getRecordDate($row[5]));
//             // $Record->setShippingFeeByCust(doubleval($row[7])); //not required
//             $Record->setTrackingNum(trim($row[22]));

//             $list[] = $Record;
//         }
        
//         return $list;
//     }

//     private function getRecordDate(string &$date):string{
//         $d = explode(' ', $date)[0];
//         return \preg_replace('/\//', '-', $d);
//     }

// }

// //as inner class
// class ItemDetail{
    
//     private $itemDetails;

//     private $COL = [
//         'sku' => 'SKU Reference No.:',
//         'sellingPrice' => 'Price:',
//         'quantity' => 'Quantity:'
//     ];
//     private $COL_LEN;
//     const DELIMITER = ';';

//     //example item format
//     const ITEM = [
//             'sku' => '',
//             'sellingPrice' => 0.00,
//             'quantity' => 0
//     ];
//     const ITEM_SPLIT_PATTERN = '/(\s|^)\[[0-9]+\]\sProduct\sName:/';

//     function __construct(string $data){
//         $this->setItemDetails($data);

//         $this->COL_LEN = [
//             'sku' => strlen($this->COL['sku']),
//             'sellingPrice' => strlen($this->COL['sellingPrice']),
//             'quantity' => strlen($this->COL['quantity'])
//         ];
//     }

//     public function setItemDetails(string &$data):void{
//         $detail = trim($data);
//         if(strlen($detail) === 0){
//             throw new \InvalidArgumentException('data about item detail cannot be empty.');
//         }

//         $itemDetails = explode(self::DELIMITER, $detail);
//         $len = count($itemDetails);
//         if($len === 0){
//             throw new Exception('no item detail, invalid file.');
//         }
//         $this->itemDetails = $itemDetails;
//     }

//     private function getItemListsWithQuantity(array $itemStartPosition):array{
//         //loop through an order to get N items with its data
//         $sku; $sellingPrice; $quantity; // indicate if found
//         $line;
//         $strpos;
//         $endIndex = count($this->itemDetails);
//         $num_items = count($itemStartPosition);
//         $itemLists = [];
//         for($i=0;$i<$num_items;++$i){
//             $sku = true; $sellingPrice = true; $quantity = true;
//             $startIndex = $itemStartPosition[$i];
//             $stopIndex = $itemStartPosition[$i +1] ?? $endIndex;
//             for($l = $startIndex; $l< $stopIndex; ++$l){
//                 $line = &$this->itemDetails[$l];
//                 if($sellingPrice && strpos($line, $this->COL['sellingPrice']) !== false){
//                     $strpos = strpos($line, $this->COL['sellingPrice']);
//                     $itemLists[$i]['sellingPrice'] = \substr($line, $strpos + $this->COL_LEN['sellingPrice']);
//                     $sellingPrice = false;
//                 }
//                 if($quantity && strpos($line, $this->COL['quantity']) !== false){
//                     $strpos = strpos($line, $this->COL['quantity']);
//                     $itemLists[$i]['quantity'] = \substr($line, $strpos + $this->COL_LEN['quantity']);
//                     $quantity = false;
//                 }
//                 if($sku && strpos($line, $this->COL['sku']) !== false){
//                     $strpos = strpos($line, $this->COL['sku']);
//                     $itemLists[$i]['sku'] = \substr($line, $strpos + $this->COL_LEN['sku']);
//                     $sku = false;
//                 }
//             }
//         }

//         //trim and cast data
//         return array_map(function($item){
//             $REPLACE_PATTERN_DIGIT = '/[^0-9.]/';
//             return [
//                 'sku' => trim($item['sku']),
//                 'sellingPrice' => (double)(preg_replace($REPLACE_PATTERN_DIGIT, '', $item['sellingPrice'])),
//                 'quantity' => (int)(preg_replace($REPLACE_PATTERN_DIGIT, '', $item['quantity']))
//             ];
//         }, $itemLists);
//     }

//     private function getItemStartPosition():array{
//         $itemStartPosition = [];
//         $len = count($this->itemDetails);
//         $count = 0;
//         for($i=0;$i<$len;++$i){
//             if(\preg_match(self::ITEM_SPLIT_PATTERN, $this->itemDetails[$i]) === 1){
//                 $itemStartPosition[] = $i;
//                 ++$count;
//             }
//         }
//         if($count === 0){
//             throw new \Exception('no item detail can be identified. possible invalid file');
//         }

//         return $itemStartPosition;
//     }

//     public function getItemLists():array{
//         $itemLists = $this->getItemListsWithQuantity($this->getItemStartPosition());
//         $list = [];
//         //split all item with qty as individual item
//         foreach($itemLists as $item){
//             $repeat = $item['quantity'];
//             for($i=0;$i<$repeat;++$i){
//                 $list[] = [
//                     'sku' => $item['sku'],
//                     'sellingPrice' => $item['sellingPrice']
//                 ];
//             }
//         }

//         return $list;
//     }
// }
