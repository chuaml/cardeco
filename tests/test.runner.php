<?php

namespace test;

use test\tiktok\TikTok_Test;

class Test_Runner
{
    public function __construct()
    {
    }

    public function testAll()
    {
        $test = new TikTok_Test();
        $test->listOrder_OrderFile_OrderSummary();
    }
}
