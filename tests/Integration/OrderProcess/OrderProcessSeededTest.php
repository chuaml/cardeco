<?php

declare(strict_types=1);

namespace test\Integration\OrderProcess;

use OrderProcess\BigSellerOrderProcess;
use OrderProcess\LazadaOrderProcess;
use OrderProcess\TikTokOrder;
use test\Support\CsvFixture;
use test\Support\DatabaseTestCase;
use test\Support\HtmlTableReader;
use test\Support\XlsxFixture;

/**
 * Drives the daily-order processors with SYNTHETIC files and ZZTEST- stock rows, so the
 * pick-list rules are verified independently of the seed data:
 *
 *   ZZTEST-A  stock 5, ordered 1  -> To Collect
 *   ZZTEST-B  stock 1, ordered 2  -> To Restock
 *   ZZTEST-C  stock 2, ordered 2  -> BOTH lists by design (collected, and the stock is used up)
 *   ZZTEST-X  not in stock        -> Not Found
 *
 * The "Orders" table must show the DB description for known SKUs (the legacy by-reference back-fill).
 */
final class OrderProcessSeededTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $this->insertStockItem($this->sku('A'), 'DESC A FROM DB', 5);
        $this->insertStockItem($this->sku('B'), 'DESC B FROM DB', 1);
        $this->insertStockItem($this->sku('C'), 'DESC C FROM DB', 2);
        // weight 2 kg, no shipping fee in the file -> AutoFilling computes the default band fee (RM 4.80)
        $this->insertLzdProduct($this->sku('A'), 2.0, 10.0, 10.0, 10.0);
    }

    private function sku(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    /** @return array<int, string> one SKU per unit ordered: A x1, B x2, C x2, X x1 */
    private function orderedSkus(): array
    {
        return [$this->sku('A'), $this->sku('B'), $this->sku('B'), $this->sku('C'), $this->sku('C'), $this->sku('X')];
    }

    // ------------------------------------------------------------------ Lazada (xlsx, one row per unit)

    private function lazadaFile(): string
    {
        $rows = [array_fill(0, 60, 'H')];
        $n = 0;
        foreach ($this->orderedSkus() as $sku) {
            ++$n;
            $rows[] = CsvFixture::row(60, [
                12 => 'LZD' . $n,                  // orderNum
                9 => '2024-01-15 10:00:00',        // date
                5 => $sku,                          // sku
                51 => 'file description ' . $n,    // description (overwritten from DB when found)
                47 => '12.50',                      // sellingPrice
                49 => '0',                          // shippingFee
                46 => '12.50',                      // paidPrice
                53 => 'Courier X',                  // shippingProvider
                58 => 'TRK' . $n,                   // trackingNum
                27 => 'Selangor',                   // shippingState
            ]);
        }

        return XlsxFixture::write($rows, 60);
    }

    public function testLazada_SyntheticOrders_ClassifiesSkusAgainstStock(): void
    {
        $data = (new LazadaOrderProcess($this->con, $this->lazadaFile()))->getData();

        $this->assertPickLists($data);
    }

    public function testLazada_SyntheticOrders_OrdersTableShowsDbDescriptionAndWeightFee(): void
    {
        $data = (new LazadaOrderProcess($this->con, $this->lazadaFile()))->getData();

        $orders = (new HtmlTableReader($data['orders']))->assocRows();
        $this->assertCount(6, $orders);
        $this->assertSame(
            ['Order Number', 'Date', 'SKU', 'Description', 'Selling Price', 'Shipping Fee', 'Shipping Fee2', 'Weight', 'Paid Price', 'Shipping Provider', 'Tracking Number'],
            array_keys($orders[0])
        );
        $first = $orders[0];
        $this->assertSame('LZD1', $first['Order Number']);
        $this->assertSame('15-Jan-2024', $first['Date'], 'date normalised by Orders\Record');
        $this->assertSame('DESC A FROM DB', $first['Description'], 'description back-filled from stock_items');
        $this->assertSame('4.80', $first['Shipping Fee2'], '2 kg -> default band RM 4.80 via lzd_products weight');
        $this->assertSame('2.00', $first['Weight']);
        // unknown SKU keeps the description from the file
        $this->assertSame('file description 6', $orders[5]['Description']);
    }

    // ------------------------------------------------------------------ BigSeller (xlsx, quantity column)

    private function bigSellerFile(): string
    {
        $rows = [array_fill(0, 65, 'H')];
        $lines = [
            [$this->sku('A'), 1, 'Lazada', 'Lazada Store 1'],
            [$this->sku('B'), 2, 'Shopee', 'Shopee Store'],
            [$this->sku('C'), 1, 'Lazada', 'Lazada Store 1'],
            [$this->sku('C'), 1, 'TikTok', 'TikTok Store'],
            [$this->sku('X'), 1, 'Shopee', 'Shopee Store'],
        ];
        $n = 0;
        foreach ($lines as $line) {
            ++$n;
            $rows[] = CsvFixture::row(65, [
                0 => 'BS' . $n,
                9 => $line[2],                      // marketPlace
                10 => $line[3],                     // store name
                18 => 'Selangor',
                23 => $line[0],                     // sku
                25 => 'file description ' . $n,
                30 => (string) $line[1],            // quantity
                31 => 'RM 10.00',
                32 => 'RM 10.00',
                48 => 'Standard',
                50 => 'TRK' . $n,
                51 => 'RM 0.00',
                62 => 'RM 0.00',
                64 => '2024-01-15 10:00:00',
            ]);
        }

        return XlsxFixture::write($rows, 65);
    }

    public function testBigSeller_SyntheticOrders_ClassifiesSkusAndCountsPerMarketplace(): void
    {
        $data = (new BigSellerOrderProcess($this->con, $this->bigSellerFile()))->getData();

        $this->assertPickLists($data);

        $collect = (new HtmlTableReader($data['toCollect']))->assocRowsBy('Item Code');
        $this->assertSame(
            ['Item Code', 'Description', 'Total Q.', 'Stock', 'Lazada', 'Shopee', 'TikTok'],
            array_keys($collect[$this->sku('A')])
        );
        $this->assertSame(['1', '0', '0'], $this->platformCounts($collect[$this->sku('A')]));
        $this->assertSame(['1', '0', '1'], $this->platformCounts($collect[$this->sku('C')]), 'C ordered once on Lazada and once on TikTok');

        $restock = (new HtmlTableReader($data['toRestock']))->assocRowsBy('Item Code');
        $this->assertSame(['0', '2', '0'], $this->platformCounts($restock[$this->sku('B')]));

        $notFound = (new HtmlTableReader($data['notFound']))->assocRowsBy('Item Code');
        $this->assertSame(['0', '1', '0'], $this->platformCounts($notFound[$this->sku('X')]));
    }

    public function testBigSeller_QuantityTwo_ExpandsToTwoOrderRows(): void
    {
        $orders = (new BigSellerOrderProcess($this->con, $this->bigSellerFile()))->getOrders();

        $this->assertCount(6, $orders, '5 file lines, one with quantity 2');
        $this->assertSame(['Lazada Store 1', 'Lazada Store 1', 'Shopee Store', 'Shopee Store', 'Shopee Store', 'TikTok Store'], array_column($orders, 'storeName_BigSeller'), 'sorted by store, Lazada first');
        $this->assertSame(10.0, $orders[0]['sellingPrice'], 'RM prefix stripped and cast to float');
    }

    /** @param array<string, string> $row */
    private function platformCounts(array $row): array
    {
        return [$row['Lazada'], $row['Shopee'], $row['TikTok']];
    }

    // ------------------------------------------------------------------ TikTok (csv, quantity column)

    private function tikTokFile(): string
    {
        $rows = [array_fill(0, 43, 'H')];
        $lines = [[$this->sku('A'), 1], [$this->sku('B'), 2], [$this->sku('C'), 2], [$this->sku('X'), 1]];
        $n = 0;
        foreach ($lines as $line) {
            ++$n;
            $rows[] = CsvFixture::row(43, [
                0 => '57705287375005' . $n,     // orderNum
                6 => $line[0],                  // sku
                7 => 'file description ' . $n,  // description
                9 => (string) $line[1],         // quantity
                11 => 'MYR 20.00',              // price
                14 => 'MYR 0.00',               // voucher
                16 => 'MYR 0.00',               // shipping fee
                23 => '2024-01-15 10:00:00',    // date
                33 => '12345678900' . $n,       // tracking (digits only)
                35 => 'J&T',                    // provider
                42 => 'Selangor',               // state
            ]);
        }

        return CsvFixture::write($rows, ',', 43);
    }

    public function testTikTok_SyntheticOrders_ClassifiesSkusAgainstStock(): void
    {
        $data = (new TikTokOrder($this->con, $this->tikTokFile()))->getData();

        $this->assertPickLists($data);
        $orders = (new HtmlTableReader($data['orders']))->assocRows();
        $this->assertCount(6, $orders, 'quantities expanded to one row per unit');
        $this->assertSame('DESC B FROM DB', $orders[1]['Description']);
    }

    // ------------------------------------------------------------------ shared assertions

    /** @param array<string, string> $data */
    private function assertPickLists(array $data): void
    {
        $collect = (new HtmlTableReader($data['toCollect']))->assocRowsBy('Item Code');
        $restock = (new HtmlTableReader($data['toRestock']))->assocRowsBy('Item Code');
        $notFound = (new HtmlTableReader($data['notFound']))->assocRowsBy('Item Code');

        $this->assertArrayHasKey($this->sku('A'), $collect, 'A: stock 5 >= ordered 1');
        $this->assertSame('DESC A FROM DB', $collect[$this->sku('A')]['Description']);
        $this->assertSame('5', $collect[$this->sku('A')]['Stock']);
        $this->assertArrayNotHasKey($this->sku('A'), $restock);

        $this->assertArrayHasKey($this->sku('B'), $restock, 'B: ordered 2 > stock 1');
        $this->assertSame('1', $restock[$this->sku('B')]['Stock']);
        $this->assertArrayNotHasKey($this->sku('B'), $collect);

        // by design: quantity == stock appears in BOTH lists (collected, and the stock is exactly used up)
        $this->assertArrayHasKey($this->sku('C'), $collect);
        $this->assertArrayHasKey($this->sku('C'), $restock);

        $this->assertArrayHasKey($this->sku('X'), $notFound);
        $this->assertSame('', $notFound[$this->sku('X')]['Stock']);
        $this->assertArrayNotHasKey($this->sku('X'), $collect);
        $this->assertArrayNotHasKey($this->sku('X'), $restock);

        $quantityColumn = array_key_exists('Quantity', $collect[$this->sku('A')]) ? 'Quantity' : 'Total Q.';
        $this->assertSame('1', $collect[$this->sku('A')][$quantityColumn]);
        $this->assertSame('2', $restock[$this->sku('B')][$quantityColumn]);
        $this->assertSame('2', $collect[$this->sku('C')][$quantityColumn]);
    }
}
