<?php

namespace test\tiktok;

use OrderProcess\TikTokOrder;
use Orders\Factory\Excel\CashSales;
use Orders\Factory\Excel\ExcelReader;
use PHPUnit\Framework\TestCase;

final class TikTokTest extends TestCase
{
    public function testListOrder_OrderFile_OrderSummary(): void
    {
        $con = require 'tests/db.connection.php';
        $q = new TikTokOrder($con, 'tests/tiktok/data.input/tiktok.input.order.sample.csv');

        $data = $q->getData();
        $data['toRestock'];
        $data['toCollect'];
        $data['notFound'];

        $this->assertTrue($data !== null);
        $this->assertTrue($data['toRestock'] !== null);
        $this->assertTrue($data['toCollect'] !== null);
        $this->assertTrue($data['notFound'] !== null);
    }

    public function testListOrder_OrderFile_QuantityMatch(): void
    {
        $con = require 'tests/db.connection.php';
        $q = new TikTokOrder($con, 'tests/tiktok/data.input/tiktok.input.order.sample.2.csv');

        $list = $q->getOrders();
        $this->assertTrue(count($list) === 6);
    }

    public function testConvertToSqlImport_MonthlyCashSalesRecord_ValidConstantValue()
    {
        $con = require 'tests/db.connection.php';
        $testInputFilePath = 'tests/tiktok/data.input/tiktok.monthly.cashsales.record.sample.xlsx';
        $xlsx = new ExcelReader($testInputFilePath);
        $paymentType = 'TikTok_Eplus';
        $startRowPos = 1;
        $lastRowPos = -1;
        $rows = $xlsx->read($paymentType, $startRowPos, $lastRowPos);

        $list = CashSales::transformToCashSales($con, $paymentType, iterator_to_array($rows));

        foreach ($list as $x) {
            $this->assertTrue($x['Code(10)'] === '300-C0013');
            $this->assertTrue($x['CompanyName(100)'] === 'CASH A/C - TIKTOK (E PLUS)');
            $this->assertTrue($x['P_PAYMENTMETHOD'] === '325-100');
        }
        $this->assertTrue(count($list) > 0);
    }
}
