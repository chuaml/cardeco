<?php

namespace test\lazada;

use OrderProcess\LazadaOrderProcess;
use Orders\Factory\Excel\CashSales;
use Orders\Factory\Excel\ExcelReader;
use PhpParser\Node\Stmt\TryCatch;
use PHPUnit\Framework\TestCase;

final class LazadaTest extends TestCase
{
    public function testListOrder_OrderFile_OrderSummary(): void
    {
        $con = require 'tests/db.connection.php';
        $q = new LazadaOrderProcess($con, 'tests/lazada/data.input/lazada.input.order.sample.xlsx');
        $data = $q->getData();
        $data['toRestock'];
        $data['toCollect'];
        $data['notFound'];

        $this->assertTrue($data !== null);
        $this->assertTrue($data['toRestock'] !== null);
        $this->assertTrue($data['toCollect'] !== null);
        $this->assertTrue($data['notFound'] !== null);
    }

    public function testListOrder_OrderFile_ExpectedOutputList(): void
    {
        $con = require 'tests/db.connection.php';
        $filePath = 'tests/lazada/data.input/lazada.input.order.sample.xlsx';
        $q = new LazadaOrderProcess($con, $filePath);
        $expectedResult = file_get_contents('tests/lazada/data.input/lazada.output.order.expected.json');
        $expectedResult = json_decode($expectedResult);
        $expectedResult = json_encode($expectedResult, JSON_PRETTY_PRINT);

        $orders = $q->getOrders();
        $orders = json_encode($orders, JSON_PRETTY_PRINT);
        $this->assertEquals($orders, $expectedResult);
    }

    public function testConvertToSqlImport_MonthlyCashSalesRecord_ValidConstantValue()
    {
        $con = require 'tests/db.connection.php';
        $testInputFilePath = 'tests/lazada/data.input/lazada.monthly.cashsales.record.xlsx';
        $xlsx = new ExcelReader($testInputFilePath);
        $paymentType = 'Lazada';
        $startRowPos = 1;
        $lastRowPos = -1;
        $rows = $xlsx->read($paymentType, $startRowPos, $lastRowPos);

        $list = CashSales::transformToCashSales($con, $paymentType, iterator_to_array($rows));

        foreach ($list as $i => $x) {
            try {
                $this->assertEquals('300-C0006', $x['Code(10)']);
                $this->assertEquals('CASH A/C - LAZADA (CAR DECO)', $x['CompanyName(100)']);
                $this->assertEquals('321-000', $x['P_PAYMENTMETHOD']);
            } catch(\Throwable $ex) {
                echo 'fail at index $i: ' . $i;
                var_dump($x);
                throw $ex;
            }
        }
        $this->assertNotEquals(0, count($list));
    }
}
