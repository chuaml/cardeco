<?php
namespace Orders\Factory;

require_once(__DIR__ .'/../Record.php');
require_once(__DIR__ .'/../../Product/Item.php');
require_once(__DIR__ .'/RecordFactory.php');
require_once(__DIR__ .'/../../IO/FileInputStream.php');
require_once(__DIR__ .'/../../IO/CSVInputStream.php');

use \Orders\Record;
use \UnexpectedValueException;
use \Exception;
use \Product\Item;
use \IO\CSVInputStream;

//read input csv file to generate old Shopee record post(2019-March-29);
class Shopee implements RecordFactory
{
    private $File;

    const DELIMITER = ',';

    public function __construct(string $File)
    {
        if (!file_exists($File)) {
            throw new Exception("file does not exist: $File ");
        }
        $this->File = $File;
    }

    public function generateRecords():array
    {
        $LAZADA_CSV_DELIMITER = ';';
        $in = new CSVInputStream($this->File, self::DELIMITER);
        $orders = [];
        try {
            $orders = $in->readLines();
        } finally {
            $in->close();
        }
        if (count($orders) === 0) {
            throw new Exception('No data captured. Possible invalid file.');
        }
        if (count($orders[0]) < 50) {
            throw new Exception('Too less columns. Invalid file');
        }
        array_splice($orders, 0, 1);

        $records = [];
        foreach ($orders as $r) {
            $recordWithMultiItems = $this->getRecords($r);
            foreach ($recordWithMultiItems as $o) {
                $records[] = $o;
            }
        }

        return $records;
    }

    private function getRecords(array &$row):array
    {
        //convert row to Record, split itemQuantity to individual record
        $itemQuantity = $row[16];
        $list = [];
        for ($i=0;$i<$itemQuantity;++$i) {
            $sku = trim($row[12]);
            if (strlen($sku) === 0) {
                $sku = trim($row[10]);
            }
    
            $itemName = trim($row[11]);

            $Record = new Record(
                null,
                trim($row[0]),
                new Item(null, $sku, $itemName)
            );
    
            $Record->setOrderNum(trim($row[0]));
            $Record->setStatus(trim($row[1]));
            $Record->setTrackingNum(trim($row[3]));
            $Record->setDate(trim($row[8]));
            $Record->setSellingPrice((double) $row[15]);
            $Record->setShippingWeight((double)$row[21]);
            $Record->setVoucher((double)$row[25]);
            $Record->setShippingFee((double)$row[38]);
            $Record->setShippingState(trim($row[46]));

            $list[] = $Record;
        }
        
        return $list;
    }
}
