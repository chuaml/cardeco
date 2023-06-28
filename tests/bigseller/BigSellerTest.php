<?php

namespace test\bigseller;

use OrderProcess\BigSellerOrderProcess;
use PHPUnit\Framework\TestCase;

final class BigSellerTest extends TestCase
{
    public function testListOrder_OrderFile_OrderSummary(): void
    {
        $con = require 'tests/db.connection.php';
        $q = new BigSellerOrderProcess($con, 'tests/bigseller/data.input/bigseller.input.order.sample.xlsx');
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
        $filePath = 'tests/bigseller/data.input/bigseller.input.order.sample.xlsx';
        $q = new BigSellerOrderProcess($con, $filePath);
        $expectedResult = file_get_contents('tests/bigseller/data.input/bigseller.output.order.expected.json');
        $expectedResult = json_decode($expectedResult);
        $expectedResult = json_encode($expectedResult, JSON_PRETTY_PRINT);

        $orders = $q->getOrders();
        $orders = json_encode($orders, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedResult, $orders);
    }
}
