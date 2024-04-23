<?php

namespace test\MonthlyCashSalesRecordTest;

use Orders\Factory\Excel\CashSales;
use Orders\Factory\Excel\ExcelReader;
use Orders\Factory\Excel\SqlImport;
use PHPUnit\Framework\TestCase;

final class MonthlyCashSalesRecordTest extends TestCase
{
    public function testConvertToSqlImportEntries_MonthlyCashSalesRecord_ExpectedHeaderName(): void
    {
        $con = require 'tests/db.connection.php';
        $testInputFilePath = 'tests/monthly_cashsales_record/data.input/monthly.cashsales.record.sample.xlsx';
        $xlsx = new ExcelReader($testInputFilePath);
        $paymentType = 'Lazada';
        $startRowPos = 1;
        $lastRowPos = -1;
        $rows = $xlsx->read($paymentType, $startRowPos, $lastRowPos);

        $list = CashSales::transformToCashSales($con, $paymentType, iterator_to_array($rows));

        $expectedHeader = [
            'DocDate',
            'DocNo(20)',
            'Code(10)',
            'CompanyName(100)',
            'Agent(10)',
            'TERMS(10)',
            'Description_HDR(200)',
            'SEQ',
            'ItemCode(30)',
            'Description_DTL(200)',
            'Qty',
            'UOM',
            'UnitPrice',
            'DISC(20)',
            'Tax(10)',
            'TaxInclusive',
            'TaxAmt',
            'Amount',
            'P_AMOUNT',
            'P_PAYMENTMETHOD',
            'P_BANKCHARGE',
            'DOCREF1',
        ];
        foreach ($expectedHeader as $h) {
            $isKeyExists = array_key_exists($h, $list[0]);
            $this->assertTrue($isKeyExists);
        }
        $this->assertTrue(count($list) > 0);

        $this->assertTrue(count($list[0]) === count($expectedHeader));
    }

    public function testConvertToSqlImportEntries_MonthlyCashSalesRecord_EntriesWithValidConstantValue(): void
    {
        $con = require 'tests/db.connection.php';
        $testInputFilePath = 'tests/monthly_cashsales_record/data.input/monthly.cashsales.record.sample.xlsx';
        $xlsx = new ExcelReader($testInputFilePath);
        $paymentType = 'Lazada';
        $startRowPos = 1;
        $lastRowPos = -1;
        $rows = $xlsx->read($paymentType, $startRowPos, $lastRowPos);

        $list = CashSales::transformToCashSales($con, $paymentType, iterator_to_array($rows));

        $count = 0;
        foreach ($list as $x) {
            $this->assertEquals('<<NEW>>', $x['DocNo(20)']);
            $this->assertEquals('----', $x['Agent(10)']);
            $this->assertEquals('C.O.D.', $x['TERMS(10)']);
            $this->assertEquals('Cash Sales', $x['Description_HDR(200)']);
            ++$count;
        }
        $this->assertTrue($count > 0);
    }

    public function testConvertToSqlImportEntries_MonthlyCashSalesRecord_ExpectedOutput(): void
    {
        $con = require 'tests/db.connection.php';
        $testInputFilePath = 'tests/monthly_cashsales_record/data.input/monthly.cashsales.record.sample.xlsx';
        $xlsx = new ExcelReader($testInputFilePath);
        $paymentType = 'Lazada';
        $startRowPos = 1;
        $lastRowPos = -1;
        $rows = $xlsx->read($paymentType, $startRowPos, $lastRowPos);

        $output = CashSales::transformToCashSales($con, $paymentType, iterator_to_array($rows));
        $output = json_encode($output, JSON_PRETTY_PRINT);

        // expected output is a Lazada payment type
        $expectedResult = file_get_contents('tests/monthly_cashsales_record/data.input/monthly.cashsales.record.sample.expected.json');
        $expectedResult = json_decode($expectedResult);
        $expectedResult = json_encode($expectedResult, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedResult, $output);
    }

    public function testMultipleSameItemSkuButDifferentPrice_MonthlyCashSalesRecord_SameItemSkuButDifferentPriceAreSeparatedUnit(): void
    {
        $con = require 'tests/db.connection.php';
        $testInputFilePath = 'tests/monthly_cashsales_record/data.input/monthly.cashsales.record.sample.xlsx';
        $xlsx = new ExcelReader($testInputFilePath);
        $paymentType = 'Lazada';
        $startRowPos = 1;
        $lastRowPos = -1;
        $rows = $xlsx->read($paymentType, $startRowPos, $lastRowPos);

        $list = CashSales::transformToCashSales($con, $paymentType, iterator_to_array($rows));
        $output = array_filter($list, function ($r) {
            return $r['DOCREF1'] === '576559840027641520' && $r['ItemCode(30)'] === 'IN-0806-17';
        });
        $output = array_values($output);

        $this->assertEquals(2, count($output));

        $r = $output[0];
        $this->assertEquals(43.5, $r['UnitPrice']);
        $this->assertEquals(2, $r['Qty']);

        $r = $output[1];
        $this->assertEquals(46.9, $r['UnitPrice']);
        $this->assertEquals(1, $r['Qty']);
    }
}
