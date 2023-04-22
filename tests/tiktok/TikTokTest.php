<?php

namespace test\tiktok;

use IO\CSVInputStream;
use OrderProcess\TikTokOrder;
use Orders\Factory\Excel\CashSales;
use Orders\Factory\Excel\ExcelReader;
use Orders\PaymentCharges\TikTok_Eplus;
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

    public function testListOrder_OrderFile_ExpandQuantityToMultipleItems(): void
    {
        $con = require 'tests/db.connection.php';
        $filePath = 'tests/tiktok/data.input/tiktok.input.order.multiple_quantity.sample.csv';
        $q = new TikTokOrder($con, $filePath);
        $rawRecords = (new CSVInputStream($filePath, ','))->readLines();

        $list = $q->getOrders();

        $this->assertLessThan(count($list), count($rawRecords));
    }

    public function testListOrder_OrderFile_ExpectedOutputList(): void
    {
        $con = require 'tests/db.connection.php';
        $filePath = 'tests/tiktok/data.input/tiktok.input.order.multiple_quantity.sample.csv';
        $q = new TikTokOrder($con, $filePath);
        $expectedResult = file_get_contents('tests/tiktok/data.input/tiktok.output.order.multiple_quantity.expected.json');
        $expectedResult = json_decode($expectedResult);
        $expectedResult = json_encode($expectedResult, JSON_PRETTY_PRINT);

        $orders = $q->getOrders();
        $orders = json_encode($orders, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedResult, $orders);
    }

    private function getMonthlyCashSalesRecord_As_SqlImport($testInputFilePath, $platformName): array
    {
        $con = require 'tests/db.connection.php';
        $xlsx = new ExcelReader($testInputFilePath);
        $startRowPos = 1;
        $lastRowPos = -1;
        $rows = $xlsx->read($platformName, $startRowPos, $lastRowPos);

        return CashSales::transformToCashSales($con, $platformName, iterator_to_array($rows));
    }

    public function testConvertToSqlImport_MonthlyCashSalesRecord_ValidConstantValue()
    {
        $chargeType = new TikTok_Eplus(0.00);
        $platformName = $chargeType->getPlatform();
        $testInputFilePath = 'tests/tiktok/data.input/tiktok.monthly.cashsales.record.sample.xlsx';

        $list = $this->getMonthlyCashSalesRecord_As_SqlImport($testInputFilePath, $platformName);

        $this->assertGreaterThan(0, count($list));
        foreach ($list as $x) {
            $this->assertTrue($x['Code(10)'] === '300-C0013');
            $this->assertTrue($x['CompanyName(100)'] === 'CASH A/C - TIKTOK (E PLUS)');
            $this->assertTrue($x['P_PAYMENTMETHOD'] === '325-100');
        }
    }
}
