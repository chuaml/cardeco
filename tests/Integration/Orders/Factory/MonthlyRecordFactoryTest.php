<?php

declare(strict_types=1);

namespace test\Integration\Orders\Factory;

use Exception;
use InvalidArgumentException;
use mysqli_sql_exception;
use Orders\Factory\MonthlyRecord as MonthlyRecordFactory;
use Orders\MonthlyRecord;
use Orders\PaymentCharges;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Orders\Factory\MonthlyRecord (the RecordEditor / RecordUpdater read model).
 *
 * - Every query is scoped with setSearch('orderNum', <prefix>) so only this test's rows are returned.
 * - Order numbers are 'ZZTEST' + hex suffix WITHOUT the '-' of the fixture prefix: the factory feeds them through
 *   Record::setOrderNum(), which rejects anything but [a-zA-Z0-9\s]. SKUs / tracking numbers keep the full prefix.
 * - "recent" rows are dated inside the factory's 3-month window, "old" rows 6 months back.
 */
final class MonthlyRecordFactoryTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    // ------------------------------------------------------------------ helpers

    private function prefix(): string
    {
        return 'ZZTEST' . $this->suffix;
    }

    private function orderNum(string $tag): string
    {
        return $this->prefix() . $tag;
    }

    private function sku(string $tag): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $tag;
    }

    private function recentDate(int $daysAgo = 10): string
    {
        return date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
    }

    private function oldDate(): string
    {
        return date('Y-m-d', strtotime('-6 months'));
    }

    private function factory(int $limit = 50, int $offset = 0): MonthlyRecordFactory
    {
        $f = new MonthlyRecordFactory($this->con);
        $f->setRecordLimit($limit);
        $f->setOffset($offset);
        $f->setSearch('orderNum', $this->prefix());

        return $f;
    }

    /**
     * @param array<int, MonthlyRecord> $records
     * @return array<int, MonthlyRecord> only the records created by this test, re-indexed
     */
    private function mine(array $records): array
    {
        $prefix = $this->prefix();
        $mine = array_filter($records, function (MonthlyRecord $m) use ($prefix): bool {
            return strncmp((string) $m->orderNum, $prefix, strlen($prefix)) === 0;
        });

        return array_values($mine);
    }

    // ------------------------------------------------------------------ generateRecords(): population

    public function testGenerateRecords_PopulatesMonthlyRecordFromOrdersRowAndMatchingStockItem(): void
    {
        $itemId = $this->insertStockItem($this->sku('A'), 'ZZTEST desc A', 3);
        $date = $this->recentDate(10);
        $dateStockOut = $this->recentDate(8);
        $orderId = $this->insertOrder([
            'orderNum' => $this->orderNum('O1'),
            'billno' => 'ZZTEST-B1',
            'date' => $date,
            'dateStockOut' => $dateStockOut,
            'sku' => $this->sku('A'),
            'trackingNum' => 'ZZTEST-T1',
            'sellingPrice' => 12.5,
            'status' => 'READY_TO_SHIP',
            'transferCharges' => 0.3,
            'shippingFee' => 4.8,
            'shippingFeeByCust' => 5.3,
            'shippingState' => 'Selangor-',
            'shippingWeight' => 1.5,
            'voucher' => 1.25,
            'platformCharges' => 'Lazada',
            'platformChargesAmount' => 2.5,
            'bankIn' => 0,
            'cash' => 0,
            'remark' => 'ZZTEST remark',
        ]);

        $list = $this->mine($this->factory()->generateRecords(null));

        $this->assertCount(1, $list);
        $m = $list[0];
        $this->assertInstanceOf(MonthlyRecord::class, $m);
        $this->assertSame($orderId, $m->recordId);
        $this->assertSame($this->orderNum('O1'), $m->orderNum);
        $this->assertSame('ZZTEST-B1', $m->billno);
        $this->assertSame(date('d-M-Y', strtotime($date)), $m->date, 'dates are re-formatted to Record::DATE_FORMAT');
        $this->assertSame(date('d-M-Y', strtotime($dateStockOut)), $m->dateStockOut);
        $this->assertSame('ZZTEST-T1', $m->trackingNum);
        $this->assertSame(12.5, $m->sellingPrice);
        $this->assertSame(0.3, $m->transferCharges);
        $this->assertSame(4.8, $m->shippingFee);
        $this->assertSame(5.3, $m->shippingFeeByCust);
        $this->assertSame('Selangor-', $m->shippingState);
        $this->assertSame(1.5, $m->shippingWeight);
        $this->assertSame(1.25, $m->voucher);
        $this->assertEqualsWithDelta(16.55, $m->getGrandTotal(), 0.0001, 'sellingPrice + shippingFeeByCust - voucher');

        $item = $m->getItem();
        $this->assertSame($itemId, $item->itemId);
        $this->assertSame($this->sku('A'), $item->code);
        $this->assertSame('ZZTEST desc A', $item->description);

        $all = $m->getAll();
        $this->assertSame($this->sku('A'), $all['sku']);
        $this->assertSame('ZZTEST desc A', $all['itemName']);
        $this->assertSame('Lazada', $all['platformCharges']);
        $this->assertSame(2.5, $all['platformChargesAmount']);
        $this->assertSame(0.0, $all['bankIn']);
        $this->assertSame(0.0, $all['cash']);
        $this->assertArrayNotHasKey('remark', $all, 'orders.remark is selected but never mapped');
    }

    public function testGenerateRecords_SkuKnownOnlyAsSellerSkuAlias_ResolvesTheStockItemThroughSellerSku(): void
    {
        $itemId = $this->insertStockItem($this->sku('A'), 'ZZTEST desc A', 3);
        $this->insertSellerSku($this->sku('A'), $this->sku('ALIAS'));
        $this->insertOrder(['orderNum' => $this->orderNum('O1'), 'date' => $this->recentDate(), 'sku' => $this->sku('ALIAS')]);

        $list = $this->mine($this->factory()->generateRecords(null));

        $this->assertCount(1, $list);
        $item = $list[0]->getItem();
        $this->assertSame($itemId, $item->itemId);
        $this->assertSame($this->sku('ALIAS'), $item->code, 'Item.code is orders.sku, not the resolved item_code');
        $this->assertSame('ZZTEST desc A', $item->description);
    }

    public function testGenerateRecords_UnknownSku_ItemHasNullIdAndNullDescription(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('O1'), 'date' => $this->recentDate(), 'sku' => $this->sku('UNKNOWN')]);

        $list = $this->mine($this->factory()->generateRecords(null));

        $this->assertCount(1, $list);
        $item = $list[0]->getItem();
        $this->assertNull($item->itemId);
        $this->assertSame($this->sku('UNKNOWN'), $item->code);
        $this->assertNull($item->description);
    }

    /** @return array<string, array<int, string>> */
    public function registeredPlatformProvider(): array
    {
        // Lazada_Paling_Best is registered too but cannot be stored: orders.platformCharges is char(16)
        return [
            'Lazada' => ['Lazada', PaymentCharges\Lazada::class],
            'Shopee' => ['Shopee', PaymentCharges\Shopee::class],
            'Netpay' => ['Netpay', PaymentCharges\Netpay::class],
            'GHL' => ['GHL', PaymentCharges\GHL::class],
            '11Street' => ['11Street', PaymentCharges\Street11::class],
            'Lazada_Eplus' => ['Lazada_Eplus', PaymentCharges\Lazada_Eplus::class],
            'Shopee_Eplus' => ['Shopee_Eplus', PaymentCharges\Shopee_Eplus::class],
            'TikTok_Eplus' => ['TikTok_Eplus', PaymentCharges\TikTok_Eplus::class],
        ];
    }

    /**
     * setPlatformCharges() resolves orders.platformCharges through PlatformCharges::getPlatformCharges()
     * (MonthlyRecord.php:161-173), so every registered subclass - including the *_Eplus ones - reads back.
     *
     * @dataProvider registeredPlatformProvider
     */
    public function testGenerateRecords_MapsPlatformChargesColumnToItsPaymentChargesClass(string $platform, string $expectedClass): void
    {
        $this->insertOrder([
            'orderNum' => $this->orderNum('O1'),
            'date' => $this->recentDate(),
            'platformCharges' => $platform,
            'platformChargesAmount' => 1.75,
        ]);

        $list = $this->mine($this->factory()->generateRecords($platform));

        $this->assertCount(1, $list);
        $charges = $list[0]->getPlatformCharges();
        $this->assertInstanceOf($expectedClass, $charges);
        $this->assertSame($platform, $charges->getPlatform());
        $this->assertSame(1.75, $charges->getAmount());
    }

    public function testGenerateRecords_EmptyPlatformCharges_LeavesPlatformChargesNull(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('O1'), 'date' => $this->recentDate(), 'platformCharges' => '']);

        $list = $this->mine($this->factory()->generateRecords(null));

        $this->assertCount(1, $list);
        $this->assertNull($list[0]->getPlatformCharges());
        $all = $list[0]->getAll();
        $this->assertNull($all['platformCharges']);
        $this->assertNull($all['platformChargesAmount']);
    }

    /**
     * A value in orders.platformCharges that no PlatformCharges subclass claims aborts the whole listing
     * (MonthlyRecord.php:166-170 re-throws the registry's InvalidArgumentException as a plain Exception).
     */
    public function testGenerateRecords_UnknownPlatformStringInOrdersRow_ThrowsAndAbortsTheListing(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('O1'), 'date' => $this->recentDate(), 'platformCharges' => 'ZZTEST-Bogus']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('undefined PlatformCharges type: ZZTEST-Bogus');
        $this->factory()->generateRecords(null);
    }

    public function testGenerateRecords_SetsBankInAndCashDirectChargesFromTheRow(): void
    {
        $this->insertOrder([
            'orderNum' => $this->orderNum('O1'),
            'date' => $this->recentDate(),
            'bankIn' => 7.5,
            'cash' => 2.25,
        ]);

        $list = $this->mine($this->factory()->generateRecords(null));

        $this->assertCount(1, $list);
        $m = $list[0];
        $this->assertSame(7.5, $m->getDirectCharges(PaymentCharges\BankIn::PAYMENT_METHOD)->getAmount());
        $this->assertSame(2.25, $m->getDirectCharges(PaymentCharges\Cash::PAYMENT_METHOD)->getAmount());
        $this->assertSame(9.75, $m->getSumDirectChargesAmount());
        $all = $m->getAll();
        $this->assertSame(7.5, $all['bankIn']);
        $this->assertSame(2.25, $all['cash']);
    }

    // ------------------------------------------------------------------ generateRecords(): search, paging, counting

    public function testGenerateRecords_PagesFromTheNewestIdAndReturnsEachPageInAscendingIdOrder(): void
    {
        $a = $this->insertOrder(['orderNum' => $this->orderNum('A'), 'date' => $this->recentDate()]);
        $b = $this->insertOrder(['orderNum' => $this->orderNum('B'), 'date' => $this->recentDate()]);
        $c = $this->insertOrder(['orderNum' => $this->orderNum('C'), 'date' => $this->recentDate()]);

        $page0 = $this->mine($this->factory(2, 0)->generateRecords(null));
        $page1 = $this->mine($this->factory(2, 2)->generateRecords(null));

        $this->assertSame([$b, $c], [$page0[0]->recordId, $page0[1]->recordId]);
        $this->assertSame([$a], [$page1[0]->recordId]);
        $this->assertCount(1, $page1);
    }

    public function testGetNumPage_IsTheCeilingOfRecentMatchingRowsOverTheLimit(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('A'), 'date' => $this->recentDate()]);
        $this->insertOrder(['orderNum' => $this->orderNum('B'), 'date' => $this->recentDate()]);
        $this->insertOrder(['orderNum' => $this->orderNum('C'), 'date' => $this->recentDate()]);
        $f = $this->factory(2);

        $this->assertSame(0, $f->getNumPage(), 'no count before generateRecords()');
        $f->generateRecords(null);

        $this->assertSame(2, $f->getNumPage());
    }

    public function testGenerateRecords_WithPlatformFilter_ExcludesOtherPlatformsAndOrdersOlderThanThreeMonths(): void
    {
        $lazadaRecent = $this->insertOrder(['orderNum' => $this->orderNum('A'), 'date' => $this->recentDate(), 'platformCharges' => 'Lazada']);
        $this->insertOrder(['orderNum' => $this->orderNum('B'), 'date' => $this->recentDate(), 'platformCharges' => 'Shopee']);
        $this->insertOrder(['orderNum' => $this->orderNum('C'), 'date' => $this->oldDate(), 'platformCharges' => 'Lazada']);
        $f = $this->factory(50);

        $list = $this->mine($f->generateRecords('Lazada'));

        $this->assertCount(1, $list);
        $this->assertSame($lazadaRecent, $list[0]->recordId);
        $this->assertSame(1, $f->getNumPage());
    }

    /**
     * Without a platform filter the LIST query has no date restriction (MonthlyRecord.php:98-101) while the COUNT
     * query always restricts to the last 3 months (:146-149): old orders are listed but the page count is 0.
     */
    public function testGenerateRecords_WithoutPlatformFilter_ListsOldOrdersButCountsNone_documentsCurrentBehaviour(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('A'), 'date' => $this->oldDate()]);
        $this->insertOrder(['orderNum' => $this->orderNum('B'), 'date' => $this->oldDate()]);
        $this->insertOrder(['orderNum' => $this->orderNum('C'), 'date' => $this->oldDate()]);
        $f = $this->factory(2);

        $list = $this->mine($f->generateRecords(null));

        $this->assertCount(2, $list);
        $this->assertSame(0, $f->getNumPage());
    }

    /**
     * @group known-bug
     * MonthlyRecord.php:98-101 vs :146-149 - the page count must be computed over the same rows the list returns.
     */
    public function testGenerateRecords_WithoutPlatformFilter_PageCountCoversTheListedRows(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('A'), 'date' => $this->oldDate()]);
        $this->insertOrder(['orderNum' => $this->orderNum('B'), 'date' => $this->oldDate()]);
        $this->insertOrder(['orderNum' => $this->orderNum('C'), 'date' => $this->oldDate()]);
        $f = $this->factory(2);

        $list = $this->mine($f->generateRecords(null));

        $this->assertCount(2, $list);
        $this->assertSame(2, $f->getNumPage());
    }

    public function testSetSearch_UnknownField_ThrowsInvalidArgument(): void
    {
        $f = new MonthlyRecordFactory($this->con);

        $this->expectException(InvalidArgumentException::class);
        $f->setSearch('sku', $this->sku('A'));
    }

    public function testSetSearch_BlankValue_IsIgnoredAndKeepsThePreviousSearch(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('A'), 'date' => $this->recentDate()]);
        $f = $this->factory(50);
        $f->setSearch('orderNum', '   ');
        $f->setSearch('no-such-field', ''); // the field is not even validated when the value is blank

        $list = $f->generateRecords(null);

        $this->assertCount(1, $list, 'still scoped to this test prefix (a reset search would list up to 50 seed rows)');
        $this->assertSame($this->orderNum('A'), $list[0]->orderNum);
    }

    // ------------------------------------------------------------------ getMonthlyRecordsByDate()

    public function testGetMonthlyRecordsByDate_InvalidDate_ThrowsInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new MonthlyRecordFactory($this->con))->getMonthlyRecordsByDate('not a date');
    }

    /**
     * MonthlyRecord.php getMonthlyRecordsByDate(): valid under MySQL 8 ONLY_FULL_GROUP_BY (stock_items columns are
     * wrapped in ANY_VALUE()) - this is the RecordEditor month listing and both CSV exports.
     */
    public function testGetMonthlyRecordsByDate_ReturnsTheOrdersOfThatMonth(): void
    {
        $itemId = $this->insertStockItem($this->sku('A'), 'ZZTEST desc A', 3);
        $jan1 = $this->insertOrder(['orderNum' => $this->orderNum('A'), 'date' => '2024-01-05', 'sku' => $this->sku('A'), 'platformCharges' => 'Lazada']);
        $jan2 = $this->insertOrder(['orderNum' => $this->orderNum('B'), 'date' => '2024-01-20', 'sku' => $this->sku('A'), 'platformCharges' => 'Shopee']);
        $this->insertOrder(['orderNum' => $this->orderNum('C'), 'date' => '2024-02-02', 'sku' => $this->sku('A'), 'platformCharges' => 'Lazada']);
        $f = new MonthlyRecordFactory($this->con);

        $all = $this->mine($f->getMonthlyRecordsByDate('2024-01'));
        $shopeeOnly = $this->mine($f->getMonthlyRecordsByDate('2024-01', 'Shopee'));

        $this->assertSame([$jan1, $jan2], [$all[0]->recordId, $all[1]->recordId]);
        $this->assertCount(2, $all);
        $this->assertSame($itemId, $all[0]->getItem()->itemId);
        $this->assertSame('05-Jan-2024', $all[0]->date);
        $this->assertSame([$jan2], [$shopeeOnly[0]->recordId]);
        $this->assertCount(1, $shopeeOnly);
    }

    // ------------------------------------------------------------------ getStockOutByDate()

    public function testGetStockOutByDate_InvalidDate_Throws(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid date');
        (new MonthlyRecordFactory($this->con))->getStockOutByDate('not a date');
    }

    /**
     * MonthlyRecord.php:275-280 selects stock_items.description with `GROUP BY orders.id`; under ONLY_FULL_GROUP_BY
     * the statement fails with error 1055 at prepare() (the audit's "quantity is always 1" never gets a chance).
     * This is the RecordUpdater summary table (request_handler/RecordUpdater.php:15-21,83,89).
     */
    public function testGetStockOutByDate_documentsCurrentBehaviour_OneRowPerOrderLineWithQuantityOne(): void
    {
        // GROUP BY orders.id is kept from the legacy query: every order line is its own row with quantity 1
        $this->insertStockItem($this->sku('A'), 'ZZTEST desc A', 3);
        $this->insertOrder(['orderNum' => $this->orderNum('A'), 'sku' => $this->sku('A'), 'dateStockOut' => '2024-03-03']);
        $this->insertOrder(['orderNum' => $this->orderNum('B'), 'sku' => $this->sku('A'), 'dateStockOut' => '2024-03-03']);

        $rows = (new MonthlyRecordFactory($this->con))->getStockOutByDate('2024-03-03');

        $prefix = $this->sku('');
        $mine = array_values(array_filter($rows, function (array $r) use ($prefix): bool {
            return strncmp((string) $r['sku'], $prefix, strlen($prefix)) === 0;
        }));
        $this->assertCount(2, $mine);
        $this->assertSame('ZZTEST desc A', $mine[0]['description']);
        $this->assertEquals(1, $mine[0]['quantity']);
        $this->assertEquals(1, $mine[1]['quantity']);
    }

    /**
     * @group known-bug
     * MonthlyRecord.php getStockOutByDate(): `GROUP BY orders.id` makes COUNT(orders.id) always 1, so the
     * RecordUpdater stock-out summary lists every order line instead of one row per SKU with the line count.
     * Intended (pending product decision): one row per SKU.
     */
    public function testGetStockOutByDate_ReturnsOneRowPerSkuWithTheLineCountAsQuantity(): void
    {
        $this->insertStockItem($this->sku('A'), 'ZZTEST desc A', 3);
        $this->insertStockItem($this->sku('B'), 'ZZTEST desc B', 3);
        $this->insertOrder(['orderNum' => $this->orderNum('A'), 'sku' => $this->sku('A'), 'dateStockOut' => '2024-03-03']);
        $this->insertOrder(['orderNum' => $this->orderNum('B'), 'sku' => $this->sku('A'), 'dateStockOut' => '2024-03-03']);
        $this->insertOrder(['orderNum' => $this->orderNum('C'), 'sku' => $this->sku('B'), 'dateStockOut' => '2024-03-03']);
        $this->insertOrder(['orderNum' => $this->orderNum('D'), 'sku' => $this->sku('A'), 'dateStockOut' => '2024-03-04']);

        $rows = (new MonthlyRecordFactory($this->con))->getStockOutByDate('2024-03-03');

        $prefix = $this->sku('');
        $mine = array_values(array_filter($rows, function (array $r) use ($prefix): bool {
            return strncmp((string) $r['sku'], $prefix, strlen($prefix)) === 0;
        }));
        usort($mine, function (array $x, array $y): int {
            return strcmp((string) $x['sku'], (string) $y['sku']);
        });
        $this->assertEquals([
            ['sku' => $this->sku('A'), 'description' => 'ZZTEST desc A', 'quantity' => 2],
            ['sku' => $this->sku('B'), 'description' => 'ZZTEST desc B', 'quantity' => 1],
        ], $mine);
    }
}
