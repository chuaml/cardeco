<?php

declare(strict_types=1);

namespace test\Integration\OrderProcess;

use OrderProcess\BigSellerOrderProcess;
use OrderProcess\LazadaOrderProcess;
use OrderProcess\ShopeeOrderProcess;
use OrderProcess\TikTokOrder;
use test\Support\DatabaseTestCase;
use test\Support\GoldenFile;

/**
 * Locks the COMPLETE HTML of every table the daily-order pages render (To Restock / To Collect /
 * Not Found / Orders) for the sample files, against the pristine seed database.
 *
 * The pre-existing tests only compare <th> headers or the getOrders() JSON; the table bodies that
 * the warehouse staff print were unprotected. These goldens must stay byte-identical through the
 * Phase 2 refactor (PickListBuilder extraction, handler migration).
 */
final class OrderProcessGoldenTest extends DatabaseTestCase
{
    const KEYS = ['toRestock', 'toCollect', 'notFound', 'orders'];

    public function testLazada_SampleFile_GetDataMatchesGolden(): void
    {
        $process = new LazadaOrderProcess($this->con, 'tests/lazada/data.input/lazada.input.order.sample.xlsx');

        $this->assertGoldens('lazada', $process->getData());
    }

    public function testBigSeller_SampleFile_GetDataMatchesGolden(): void
    {
        $process = new BigSellerOrderProcess($this->con, 'tests/bigseller/data.input/bigseller.input.order.sample.2025-04-02.xlsx');

        $this->assertGoldens('bigseller', $process->getData());
    }

    public function testTikTok_SampleFile_GetDataMatchesGolden(): void
    {
        $process = new TikTokOrder($this->con, 'tests/tiktok/data.input/tiktok.input.order.multiple_quantity.sample.csv');

        $this->assertGoldens('tiktok', $process->getData());
    }

    public function testShopee_SampleFile_GetDataMatchesGolden(): void
    {
        $process = new ShopeeOrderProcess($this->con, 'tests/shopee/data.input/shopee.input.order.sample.csv');

        $this->assertGoldens('shopee', $process->getData());
    }

    /**
     * @param array<string, string> $data
     */
    private function assertGoldens(string $platform, array $data): void
    {
        $this->assertSame(self::KEYS, array_keys($data), 'getData() keys');
        foreach (self::KEYS as $key) {
            $this->assertNotSame('', $data[$key], "{$platform}: {$key} table is empty");
            GoldenFile::assertHtmlMatches(__DIR__ . "/golden/{$platform}.getData.{$key}.html", $data[$key], "{$platform} {$key}");
        }
    }
}
