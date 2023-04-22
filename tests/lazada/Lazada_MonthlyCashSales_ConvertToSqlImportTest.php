<?php

namespace test\lazada;

use Orders\Factory\Excel\CashSales;
use Orders\Factory\Excel\ExcelReader;
use Orders\PaymentCharges\BankIn;
use Orders\PaymentCharges\Lazada;
use PHPUnit\Framework\TestCase;

final class Lazada_MonthlyCashSales_ConvertToSqlImportTest extends TestCase
{
    private function getMonthlyCashSalesRecord_As_SqlImport($testInputFilePath, $platformName): array
    {
        $con = require 'tests/db.connection.php';
        $xlsx = new ExcelReader($testInputFilePath);
        $startRowPos = 1;
        $lastRowPos = -1;
        $rows = $xlsx->read($platformName, $startRowPos, $lastRowPos);

        return CashSales::transformToCashSales($con, $platformName, iterator_to_array($rows));
    }

    public function testPlatformCharges_Lazada_ValidConstantValue()
    {
        $chargeType = new Lazada(0.00);
        $platformName = $chargeType->getPlatform();
        $testInputFilePath = 'tests/lazada/data.input/lazada.monthly.cashsales.record.xlsx';

        $list = $this->getMonthlyCashSalesRecord_As_SqlImport($testInputFilePath, $platformName);

        $this->assertNotEquals(0, count($list));
        foreach ($list as $i => $x) {
            try {
                $this->assertEquals($chargeType->getCustId(), $x['Code(10)']);
                $this->assertEquals($chargeType->getCompanyName(), $x['CompanyName(100)']);
                $this->assertEquals($chargeType->getPaymentInto(), $x['P_PAYMENTMETHOD']);
            } catch (\Throwable $ex) {
                echo 'fail at index $i: ' . $i;
                var_dump($x);
                throw $ex;
            }
        }
    }

    public function testDirectCharges_BankIn_ValidConstantValue()
    {
        $chargeType = new BankIn(0.00);
        $platformName = (new Lazada(0.00))->getPlatform();
        $testInputFilePath = 'tests/lazada/data.input/lazada.monthly.cashsales.direct_charges.bankin.record.xlsx';

        $list = $this->getMonthlyCashSalesRecord_As_SqlImport($testInputFilePath, $platformName);

        $this->assertNotEquals(0, count($list));
        foreach ($list as $i => $x) {
            try {
                $this->assertEquals($chargeType->getCustId(), $x['Code(10)']);
                $this->assertEquals($chargeType->getCompanyName(), $x['CompanyName(100)']);
                $this->assertEquals($chargeType->getPaymentInto(), $x['P_PAYMENTMETHOD']);
            } catch (\Throwable $ex) {
                echo 'fail at index $i: ' . $i;
                var_dump($x);
                throw $ex;
            }
        }
    }
}
