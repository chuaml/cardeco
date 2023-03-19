<?php

namespace test\tiktok;

use OrderProcess\TikTokOrder;
use PHPUnit\Framework\TestCase;

final class TikTokTest extends TestCase
{
    // @test
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
}

