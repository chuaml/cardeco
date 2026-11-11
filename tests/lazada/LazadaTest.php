<?php

namespace test\lazada;

use OrderProcess\LazadaOrderProcess;
use test\Support\DatabaseTestCase;

final class LazadaTest extends DatabaseTestCase
{
    public function testListOrder_OrderFile_OrderSummary(): void
    {
        $con = $this->con;
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
        $con = $this->con;
        $filePath = 'tests/lazada/data.input/lazada.input.order.sample.xlsx';
        $q = new LazadaOrderProcess($con, $filePath);
        $expectedResult = file_get_contents('tests/lazada/data.input/lazada.output.order.expected.json');
        $expectedResult = json_decode($expectedResult);
        $expectedResult = json_encode($expectedResult, JSON_PRETTY_PRINT);

        $orders = $q->getOrders();
        $orders = json_encode($orders, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedResult, $orders);
    }
}
