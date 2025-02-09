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

        $q = new ShippedItemReport($con, $_FILES);

        // shipped item report
        $tbl_for_shippedItemReport = $q->getShippedItemReport();
        $this->assertTrue($tbl_for_shippedItemReport !== null);

        $expectedResult = file_get_contents(__DIR__ . '/expected_output.shipped_item_report.html');
        $output = ($tbl_for_shippedItemReport->toHtmlText());
        $this->assert_Table_Header($expectedResult, $output);


        // for monthly record - shipped item
        $tbl_for_MotnhlyRecord = $q->getMonthlyRecord();
        $this->assertTrue($tbl_for_MotnhlyRecord !== null);

        $expectedResult = file_get_contents(__DIR__ . '/expected_output.shipped_item.monthly_record.html');
        $output = ($tbl_for_MotnhlyRecord->toHtmlText());
        $this->assert_Table_Header($expectedResult, $output);
    }

    private function assert_Table_Header(string $expected_html_text, ?string $actual_html_text)
    {
        $this->assertNotNull($actual_html_text);
        $this->assertNotEmpty($actual_html_text);

        $expected = new DOMDocument();
        $expected->loadHTML($expected_html_text);

        $actual = new DOMDocument();
        $actual->loadHTML($actual_html_text);

        // same header
        $col = $actual->getElementsByTagName('th');
        foreach ($expected->getElementsByTagName('th') as $k => $v) { // same header
            $this->assertEquals($v->nodeValue, $col[$k]->nodeValue);
        }
    }
}
