<?php
namespace Orders\Factory;

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
            $Record->setDate(self::normaliseDate(trim($row[8])));
            $Record->setSellingPrice((double) $row[15]);
            $Record->setShippingWeight((double)$row[21]);
            $Record->setVoucher((double)$row[25]);
            $Record->setShippingFee((double)$row[38]);
            $Record->setShippingState(trim($row[46]));

            $list[] = $Record;
        }
        
        return $list;
    }

    /** Date layouts of the Shopee order export, tried in order. */
    const DATE_FORMATS = ['d-m-y H:i', 'd-m-y H:i:s', 'd-m-y'];

    /**
     * Shopee writes dates as dd-mm-yy (e.g. "11-02-23 0:47" = 11 Feb 2023). PHP's generic parser used by
     * Record::setDate() reads a dashed two-digit-year string as yy-mm-dd (-> 23 Feb 2011), so the Shopee layout is
     * parsed explicitly here; any other layout is handed through unchanged for the generic parser.
     */
    public static function normaliseDate(string $date): string
    {
        foreach (self::DATE_FORMATS as $format) {
            $parsed = \DateTime::createFromFormat('!' . $format, $date);
            $errors = \DateTime::getLastErrors();
            $clean = $errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0);
            if ($parsed !== false && $clean) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }

        return $date;
    }
}
