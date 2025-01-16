<?php

namespace test\bigseller;

use Orders\DailyStockOutItem_Factory;
use Orders\Factory\Excel\ExcelReader;
use PHPUnit\Framework\TestCase;

final class DailyStockOutTest extends TestCase
{
    public function testGenerateDailyStockOutReport_BigSellerAllOrders_ExpectedHeader(): void
    {
        $testInputFilePath = 'tests/bigseller/data.input/bigseller.input.order.sample.2025-01-07.xlsx';
        $xlsx = new ExcelReader($testInputFilePath);
        $iterator = $xlsx->read();

        $x = DailyStockOutItem_Factory::map($iterator->current());

        $this->assertNotEmpty($x->orderTime);
        $this->assertNotEmpty($x->sku);
        $this->assertNotEmpty($x->productName);
        $this->assertGreaterThan(0, $x->quantity);
        $this->assertNotEmpty($x->shippingStatus);
    }

    public function testGenerateDailyStockOutReport_BigSellerAllOrders_ExpectedRow(): void
    {
        $testInputFilePath = 'tests/bigseller/data.input/bigseller.input.order.sample.2025-01-07.xlsx';
        $xlsx = new ExcelReader($testInputFilePath);
        $iterator = $xlsx->read();

        $x = DailyStockOutItem_Factory::map($iterator->current());

        $this->assertEquals('01/07/2025', $x->orderTime);
        $this->assertEquals('IN-0829-16', $x->sku);
        $this->assertEquals('Proton Saga VVT 2016 2017 2018 2019 2020 2021 2022 Custom Fit Original OEM ABS Non Slip Rear Car Boot Cargo Trunk Tray', $x->productName);
        $this->assertEquals(1, $x->quantity);
        $this->assertEquals('New', $x->shippingStatus);
        $this->assertFalse($x->isShipped());
    }
}
