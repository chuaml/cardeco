<?php

namespace Orders\Factory;

use \UnexpectedValueException;
use \Product\Item;

//read input array data to generate Record;
class Record implements RecordFactory
{

    private $List;

    public function __construct(array $List)
    {
        if (count($List) === 0) {
            throw new UnexpectedValueException('List size is empty');
        }
        $this->List = $List;
    }

    public function generateRecords(): array
    {
        $list = [];
        $len = count($this->List);
        if ($len > RecordFactory::MAX_RECORD) {
            $len = RecordFactory::MAX_RECORD;
        }
        $count = 0;
        foreach ($this->List as $r) {
            if ($count++ > $len) break;
            $list[] = $this->getRecord($r);
        }

        return $list;
    }

    private function getRecord(array &$row): \Orders\Record
    {
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
        $Record->setStatus(trim($row['status']));

        return $Record;
    }
}
