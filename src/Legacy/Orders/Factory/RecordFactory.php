<?php 
namespace Orders\Factory;

interface RecordFactory{
    const MAX_RECORD = 1000000;
    public function generateRecords():array;
}
