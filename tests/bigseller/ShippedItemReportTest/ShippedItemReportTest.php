<?php

namespace test\bigseller\ShippedItemTest;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Report\ShippedItemReport;
use test\Mock;

final class ShippedItemReportTest extends TestCase
{
    public function testShippedItem_AllOrderFile_ShippedItemReport(): void
    {
        $_FILES = Mock::newFile(
            'bigseller_all_status_orders',
            __DIR__ . '/input.bigseller.all_order.sample.2025-02-01.xlsx'
        );

        $con = require 'tests/db.connection.php';

        $q = new ShippedItemReport($con);
        $q->handleRequest($_FILES);
        // assert is set
        $this->assertTrue($q->tbl_for_shippedItemReport !== null);
        $this->assertTrue($q->tbl_for_MotnhlyRecord !== null);

        // load to domcontent
        $tbl = new DOMDocument();
        $tbl->loadHTML($q->tbl_for_shippedItemReport->toHtmlText());

        $expectedResult = file_get_contents(__DIR__ . '/expected_output.shipped_item_report.html');
        $expectedTbl = new DOMDocument();
        $expectedTbl->loadHTML($expectedResult);
        // compare 2 DOMDocument object structure
        $this->assertEquals($expectedTbl->getElementsByTagName('tr')->length, $tbl->getElementsByTagName('tr')->length);
        foreach ($tbl->getElementsByTagName('th') as $k => $v) {
            $this->assertEquals($expectedTbl->getElementsByTagName('th')[$k]->nodeValue, $v->nodeValue);
        }


        $tbl = new DOMDocument();
        $tbl->loadHTML($q->tbl_for_MotnhlyRecord->toHtmlText());

        $expectedResult = file_get_contents(__DIR__ . '/expected_output.shipped_item.monthly_record.html');
        $expectedTbl = new DOMDocument();
        $expectedTbl->loadHTML($expectedResult);
        // compare 2 DOMDocument object structure
        $this->assertEquals($expectedTbl->getElementsByTagName('tr')->length, $tbl->getElementsByTagName('tr')->length);
        foreach ($tbl->getElementsByTagName('th') as $k => $v) {
            $this->assertEquals($expectedTbl->getElementsByTagName('th')[$k]->nodeValue, $v->nodeValue);
        }
    }
}
