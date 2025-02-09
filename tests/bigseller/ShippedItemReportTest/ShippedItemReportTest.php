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

        // shipped item report
        $expectedResult = file_get_contents(__DIR__ . '/expected_output.shipped_item_report.html');
        $output = ($q->tbl_for_shippedItemReport->toHtmlText());
        $this->assert_Table_Header($expectedResult, $output);

        // for monthly record - shipped item
        $expectedResult = file_get_contents(__DIR__ . '/expected_output.shipped_item.monthly_record.html');
        $output = ($q->tbl_for_MotnhlyRecord->toHtmlText());
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
