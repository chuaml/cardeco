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

        foreach ($list as $x) {
            $this->assertTrue($x['DocNo(20)'] === '<<NEW>>');
            $this->assertTrue($x['Agent(10)'] === '----');
            $this->assertTrue($x['TERMS(10)'] === 'C.O.D.');
            $this->assertTrue($x['Description_HDR(200)'] === 'Cash Sales');
        }
        $this->assertTrue(count($list) > 0);
    }
}
