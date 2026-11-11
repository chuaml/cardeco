<?php

declare(strict_types=1);

namespace test\Integration\Orders;

use Exception;
use InvalidArgumentException;
use Orders\PaymentCharges\PlatformCharges;
use Orders\Record;
use Orders\RecordInserter;
use Product\Item;
use test\Support\DatabaseTestCase;
use test\Support\XlsxFixture;

/**
 * Characterization of the `orders_insert_log` -> `orders_temp` -> `orders` write path (Orders\RecordInserter).
 *
 * - insertLog() is always called first: it is the only way the class obtains its insertLogId and
 *   `orders.insertLogId` is NOT NULL. Every test hashes a unique temp file because md5/sha1 are UNIQUE.
 * - Record order numbers are set through the constructor (Record::setOrderNum() rejects the '-' of the
 *   ZZTEST- prefix; the inserter itself never validates them).
 */
final class RecordInserterTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    // ------------------------------------------------------------------ helpers

    /** e.g. ZZTEST-1A2B3C-O1 (fits orders.orderNum char(30)) */
    private function orderNum(string $tag): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $tag;
    }

    private function sku(string $tag): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-SKU' . $tag;
    }

    private function newInserter(string $platform = 'Lazada'): RecordInserter
    {
        return new RecordInserter($platform);
    }

    /** Creates a unique temp file, logs it through the inserter and returns the new orders_insert_log.id. */
    private function logUniqueFile(RecordInserter $inserter, string $fileName = 'ZZTEST-orders.csv'): int
    {
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, self::FIXTURE_PREFIX . uniqid('', true) . "\n");
        $inserter->insertLog($this->con, $path, $fileName);

        $rows = $this->fetchAll('SELECT id FROM orders_insert_log WHERE md5 = ?', 's', md5_file($path));
        $this->assertCount(1, $rows, 'insertLog() must create exactly one orders_insert_log row');

        return (int) $rows[0]['id'];
    }

    /**
     * @param array<string, mixed> $o overrides for the optional setters
     */
    private function record(string $orderNum, string $sku, array $o = []): Record
    {
        $r = new Record(null, $orderNum, new Item(null, $sku, 'ZZTEST description'));
        $r->setDate(isset($o['date']) ? $o['date'] : '2024-01-15');
        $r->setTrackingNum(isset($o['trackingNum']) ? $o['trackingNum'] : '');
        $r->setStatus(isset($o['status']) ? $o['status'] : 'READY_TO_SHIP');
        $r->setSellingPrice(isset($o['sellingPrice']) ? $o['sellingPrice'] : 12.5);
        $r->setVoucher(isset($o['voucher']) ? $o['voucher'] : 0.0);
        $r->setShippingFee(isset($o['shippingFee']) ? $o['shippingFee'] : 0.0);
        $r->setShippingFeeByCust(isset($o['shippingFeeByCust']) ? $o['shippingFeeByCust'] : 0.0);
        $r->setShippingState(isset($o['shippingState']) ? $o['shippingState'] : '');
        $r->setShippingWeight(isset($o['shippingWeight']) ? $o['shippingWeight'] : 0.0);

        return $r;
    }

    /** @return array<int, array<string, mixed>> */
    private function ordersOfLog(int $insertLogId): array
    {
        return $this->fetchAll('SELECT * FROM orders WHERE insertLogId = ? ORDER BY id', 'i', $insertLogId);
    }

    /** @return array<int, array<string, mixed>> */
    private function ordersByOrderNum(string $orderNum): array
    {
        return $this->fetchAll('SELECT * FROM orders WHERE orderNum = ? ORDER BY id', 's', $orderNum);
    }

    private function countTemp(int $insertLogId): int
    {
        $rows = $this->fetchAll('SELECT COUNT(*) AS n FROM orders_temp WHERE insertLogId = ?', 'i', $insertLogId);

        return (int) $rows[0]['n'];
    }

    // ------------------------------------------------------------------ constructor

    public function testConstruct_UnknownPlatform_ThrowsInvalidArgument(): void
    {
        $platform = 'ZZTEST-NoSuchPlatform';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid platformCharges type');
        new RecordInserter($platform);
    }

    /** @return array<string, array<int, string>> */
    public function registeredPlatformValueProvider(): array
    {
        $cases = [];
        foreach (PlatformCharges::TYPE_OF_CHARGES as $key => $value) {
            $cases[$key . ' => ' . $value] = [$value];
        }

        return $cases;
    }

    /**
     * @dataProvider registeredPlatformValueProvider
     */
    public function testConstruct_AcceptsEveryValueOfTypeOfCharges(string $platform): void
    {
        $this->assertInstanceOf(RecordInserter::class, new RecordInserter($platform));
    }

    /** @return array<string, array<int, string>> */
    public function unregisteredPlatformProvider(): array
    {
        return [
            'registry KEY (Street11) is not a stored value' => ['Street11'],
            'lower-case value' => ['lazada'],
            'empty string' => [''],
        ];
    }

    /**
     * The constructor validates against the VALUES of PlatformCharges::TYPE_OF_CHARGES (RecordInserter.php:34-37),
     * i.e. the strings persisted in `orders.platformCharges`; class short names are not accepted here.
     *
     * @dataProvider unregisteredPlatformProvider
     */
    public function testConstruct_StringThatIsNotARegistryValue_IsRejected(string $platform): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RecordInserter($platform);
    }

    // ------------------------------------------------------------------ insertLog()

    public function testInsertLog_WritesFileHashesTrimmedNameAndToday(): void
    {
        $inserter = $this->newInserter();
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, self::FIXTURE_PREFIX . uniqid('', true) . "\n");

        $inserter->insertLog($this->con, $path, '  ZZTEST-batch.csv  ');

        $rows = $this->fetchAll(
            'SELECT md5, sha1, fileName, (date = CURDATE()) AS isToday FROM orders_insert_log WHERE md5 = ?',
            's',
            md5_file($path)
        );
        $this->assertCount(1, $rows);
        $this->assertSame(sha1_file($path), $rows[0]['sha1']);
        $this->assertSame('ZZTEST-batch.csv', $rows[0]['fileName']);
        $this->assertSame(1, (int) $rows[0]['isToday']);
    }

    public function testInsertLog_MissingFile_Throws(): void
    {
        $inserter = $this->newInserter();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("file doesn't exist");
        $inserter->insertLog($this->con, '/nonexistent/ZZTEST-' . uniqid('', true) . '.csv', 'ZZTEST-x.csv');
    }

    public function testInsertLog_BlankFileName_ThrowsAndWritesNothing(): void
    {
        $inserter = $this->newInserter();
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, self::FIXTURE_PREFIX . uniqid('', true) . "\n");

        try {
            $inserter->insertLog($this->con, $path, '   ');
            $this->fail('expected an exception for a blank file name');
        } catch (Exception $e) {
            $this->assertSame('file name is empty.', $e->getMessage());
        }
        $this->assertCount(0, $this->fetchAll('SELECT id FROM orders_insert_log WHERE md5 = ?', 's', md5_file($path)));
    }

    public function testInsertLog_SameFileContentTwice_IsRejectedByUniqueHash(): void
    {
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, self::FIXTURE_PREFIX . uniqid('', true) . "\n");
        $this->newInserter()->insertLog($this->con, $path, 'ZZTEST-first.csv');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Duplicate entry/');
        $this->newInserter()->insertLog($this->con, $path, 'ZZTEST-second.csv');
    }

    // ------------------------------------------------------------------ insert()

    public function testInsert_WritesEveryRecordToOrdersWithTheLogIdAndPlatformAndClearsTemp(): void
    {
        $inserter = $this->newInserter('Shopee');
        $logId = $this->logUniqueFile($inserter);
        $records = [
            $this->record($this->orderNum('O1'), $this->sku('A'), [
                'date' => '2024-01-15',
                'trackingNum' => 'ZZTEST-T1',
                'status' => 'ready_to_ship',
                'sellingPrice' => 12.5,
                'voucher' => 1.25,
                'shippingFee' => 4.8,
                'shippingFeeByCust' => 5.3,
                'shippingState' => 'Selangor',
                'shippingWeight' => 1.5,
            ]),
            $this->record($this->orderNum('O2'), $this->sku('B')),
        ];

        $inserter->insert($this->con, $records);

        $rows = $this->ordersOfLog($logId);
        $this->assertCount(2, $rows);

        $first = $rows[0];
        $this->assertSame($logId, (int) $first['insertLogId']);
        $this->assertSame($this->orderNum('O1'), $first['orderNum']);
        $this->assertSame('2024-01-15', $first['date']);
        $this->assertSame($this->sku('A'), $first['sku']);
        $this->assertSame('ZZTEST-T1', $first['trackingNum']);
        $this->assertSame('12.50', $first['sellingPrice']);
        $this->assertSame('READY_TO_SHIP', $first['status'], 'Record::setStatus() upper-cases');
        $this->assertSame('1.25', $first['voucher']);
        $this->assertSame('4.80', $first['shippingFee']);
        $this->assertSame('5.30', $first['shippingFeeByCust']);
        $this->assertSame('Selangor-', $first['shippingState']);
        $this->assertSame('1.50', $first['shippingWeight']);
        $this->assertSame('Shopee', $first['platformCharges']);
        // columns the inserter never fills come through orders_temp as NULL / column default
        $this->assertNull($first['billno']);
        $this->assertNull($first['dateStockOut']);
        $this->assertNull($first['transferCharges']);
        $this->assertNull($first['platformChargesAmount']);
        $this->assertSame('0.00', $first['bankIn']);
        $this->assertSame('0.00', $first['cash']);
        $this->assertNull($first['remark']);

        $this->assertSame($this->orderNum('O2'), $rows[1]['orderNum']);
        $this->assertSame($this->sku('B'), $rows[1]['sku']);

        $this->assertSame(0, $this->countTemp($logId), 'orders_temp must be cleared after commit');
    }

    /** @return array<string, array<int, string>> */
    public function recordDateProvider(): array
    {
        return [
            'Y/m/d' => ['2024/01/15', '2024-01-15'],
            'd/m/Y' => ['15/01/2024', '2024-01-15'],
            'Y-m-d H:i:s' => ['2024-01-15 10:30:00', '2024-01-15'],
            'd-M-Y (Record::DATE_FORMAT)' => ['15-Jan-2024', '2024-01-15'],
        ];
    }

    /**
     * Record::setDate() normalises to 'd-M-Y', insertToTemp() re-formats to 'Y/m/d' (RecordInserter.php:88),
     * MySQL stores a DATE.
     *
     * @dataProvider recordDateProvider
     */
    public function testInsert_DateIsNormalisedToTheDateColumn(string $recordDate, string $expectedDate): void
    {
        $inserter = $this->newInserter();
        $logId = $this->logUniqueFile($inserter);
        $records = [$this->record($this->orderNum('O1'), $this->sku('A'), ['date' => $recordDate])];

        $inserter->insert($this->con, $records);

        $rows = $this->ordersOfLog($logId);
        $this->assertCount(1, $rows);
        $this->assertSame($expectedDate, $rows[0]['date']);
    }

    /**
     * RecordInserter.php:96 appends '-' to shippingState before it reaches orders_temp.
     */
    public function testInsert_ShippingStateIsStoredWithATrailingDash_documentsCurrentBehaviour(): void
    {
        $inserter = $this->newInserter();
        $logId = $this->logUniqueFile($inserter);
        $records = [
            $this->record($this->orderNum('O1'), $this->sku('A'), ['shippingState' => 'Selangor']),
            $this->record($this->orderNum('O2'), $this->sku('B'), ['shippingState' => '']),
        ];

        $inserter->insert($this->con, $records);

        $rows = $this->ordersOfLog($logId);
        $this->assertCount(2, $rows);
        $this->assertSame('Selangor-', $rows[0]['shippingState']);
        $this->assertSame('-', $rows[1]['shippingState']);
    }

    /**
     * A Record without setDate() keeps date '' and date_create('') is "now" (RecordInserter.php:88),
     * although Record::setDate('') itself would throw.
     */
    public function testInsert_RecordWithoutDate_StoresTodaysDate_documentsCurrentBehaviour(): void
    {
        $inserter = $this->newInserter();
        $logId = $this->logUniqueFile($inserter);
        $r = new Record(null, $this->orderNum('O1'), new Item(null, $this->sku('A'), 'ZZTEST description'));
        $r->setStatus('READY_TO_SHIP');
        $records = [$r];

        $inserter->insert($this->con, $records);

        $rows = $this->ordersOfLog($logId);
        $this->assertCount(1, $rows);
        $this->assertSame(date('Y-m-d'), $rows[0]['date']);
    }

    public function testInsert_RecordWithEmptySku_IsNotInserted(): void
    {
        $inserter = $this->newInserter();
        $logId = $this->logUniqueFile($inserter);
        $records = [
            $this->record($this->orderNum('O1'), ''),
            $this->record($this->orderNum('O2'), $this->sku('B')),
        ];

        $inserter->insert($this->con, $records);

        $rows = $this->ordersOfLog($logId);
        $this->assertCount(1, $rows);
        $this->assertSame($this->orderNum('O2'), $rows[0]['orderNum']);
        $this->assertSame(0, $this->countTemp($logId), 'the empty-sku row must not linger in orders_temp');
    }

    public function testInsert_SameOrderNumWithDifferentSkusInOneBatch_InsertsEveryLine(): void
    {
        $inserter = $this->newInserter();
        $logId = $this->logUniqueFile($inserter);
        $records = [
            $this->record($this->orderNum('O1'), $this->sku('A')),
            $this->record($this->orderNum('O1'), $this->sku('B')),
        ];

        $inserter->insert($this->con, $records);

        $rows = $this->ordersOfLog($logId);
        $this->assertCount(2, $rows);
        $this->assertSame([$this->sku('A'), $this->sku('B')], array_column($rows, 'sku'));
    }

    /**
     * commit() filters `orderNum NOT IN (SELECT orderNum FROM orders)` (RecordInserter.php:149-154): an order
     * number that already exists is dropped without any error, the pre-existing row is left as it was.
     */
    public function testInsert_OrderNumAlreadyInOrders_IsSilentlySkipped_documentsCurrentBehaviour(): void
    {
        $existingId = $this->insertOrder([
            'insertLogId' => 0,
            'orderNum' => $this->orderNum('O1'),
            'sku' => $this->sku('OLD'),
            'status' => 'COMPLETED',
        ]);
        $inserter = $this->newInserter();
        $logId = $this->logUniqueFile($inserter);
        $records = [
            $this->record($this->orderNum('O1'), $this->sku('A')),
            $this->record($this->orderNum('O2'), $this->sku('B')),
        ];

        $inserter->insert($this->con, $records);

        $rows = $this->ordersOfLog($logId);
        $this->assertCount(1, $rows);
        $this->assertSame($this->orderNum('O2'), $rows[0]['orderNum']);

        $existing = $this->ordersByOrderNum($this->orderNum('O1'));
        $this->assertCount(1, $existing);
        $this->assertSame($existingId, (int) $existing[0]['id']);
        $this->assertSame($this->sku('OLD'), $existing[0]['sku']);
        $this->assertSame(0, (int) $existing[0]['insertLogId']);
        $this->assertSame(0, $this->countTemp($logId));
    }

    public function testInsert_WithoutInsertLog_ThrowsAndLeavesNoRows(): void
    {
        $inserter = $this->newInserter();
        $records = [$this->record($this->orderNum('O1'), $this->sku('A'))];

        try {
            $inserter->insert($this->con, $records);
            $this->fail('insert() without insertLog() must fail: orders_temp.insertLogId is NOT NULL');
        } catch (Exception $e) {
            $this->assertStringContainsString('insertLogId', $e->getMessage());
        }

        $this->assertCount(0, $this->ordersByOrderNum($this->orderNum('O1')));
        $this->assertCount(0, $this->fetchAll('SELECT orderNum FROM orders_temp WHERE orderNum = ?', 's', $this->orderNum('O1')));
    }

    /**
     * `orders_temp.platformCharges` / `orders.platformCharges` are char(16) while 'Lazada_Paling_Best' is 18 characters:
     * the registry accepts the platform (PlatformCharges.php:11-21) but STRICT mode rejects the row at insertToTemp()
     * (RecordInserter.php:98). The temp batch is still cleared by the finally block.
     */
    public function testInsert_LazadaPalingBest_IsRejectedByTheChar16PlatformColumn_documentsCurrentBehaviour(): void
    {
        $inserter = $this->newInserter('Lazada_Paling_Best');
        $logId = $this->logUniqueFile($inserter);
        $records = [$this->record($this->orderNum('O1'), $this->sku('A'))];

        try {
            $inserter->insert($this->con, $records);
            $this->fail('expected the insert to be rejected by the char(16) column');
        } catch (Exception $e) {
            $this->assertStringContainsString('Data too long', $e->getMessage());
        }

        $this->assertCount(0, $this->ordersOfLog($logId));
        $this->assertSame(0, $this->countTemp($logId));
    }

    /**
     * @group known-bug
     * orders.platformCharges / orders_temp.platformCharges char(16) (db/cardeco.sql.gz) cannot hold 'Lazada_Paling_Best'
     * (PlatformCharges.php:129-140); the column needs widening in db/upgrade.sql.
     */
    public function testInsert_LazadaPalingBest_IsStoredInFull(): void
    {
        $inserter = $this->newInserter('Lazada_Paling_Best');
        $logId = $this->logUniqueFile($inserter);
        $records = [$this->record($this->orderNum('O1'), $this->sku('A'))];

        $inserter->insert($this->con, $records);

        $rows = $this->ordersOfLog($logId);
        $this->assertCount(1, $rows);
        $this->assertSame('Lazada_Paling_Best', $rows[0]['platformCharges']);
    }

    // ------------------------------------------------------------------ update()

    /** @return array<string, array<int, string>> */
    public function existingStatusProvider(): array
    {
        return [
            'PENDING is updated' => ['PENDING', 'ZZTEST-NEW', 'SHIPPED'],
            'READY_TO_SHIP is updated' => ['READY_TO_SHIP', 'ZZTEST-NEW', 'SHIPPED'],
            'COMPLETED is untouched' => ['COMPLETED', 'ZZTEST-OLD', 'COMPLETED'],
            'CANCELLED is untouched' => ['CANCELLED', 'ZZTEST-OLD', 'CANCELLED'],
            'DELIVERED is untouched' => ['DELIVERED', 'ZZTEST-OLD', 'DELIVERED'],
        ];
    }

    /**
     * @dataProvider existingStatusProvider
     */
    public function testUpdate_OnlyPendingAndReadyToShipRowsGetNewTrackingNumAndStatus(string $existingStatus, string $expectedTrackingNum, string $expectedStatus): void
    {
        $this->insertOrder([
            'orderNum' => $this->orderNum('O1'),
            'sku' => $this->sku('A'),
            'trackingNum' => 'ZZTEST-OLD',
            'status' => $existingStatus,
        ]);
        $records = [
            $this->record($this->orderNum('O1'), $this->sku('A'), ['trackingNum' => 'ZZTEST-NEW', 'status' => 'shipped']),
        ];

        $this->newInserter()->update($this->con, $records);

        $rows = $this->ordersByOrderNum($this->orderNum('O1'));
        $this->assertCount(1, $rows);
        $this->assertSame($expectedTrackingNum, $rows[0]['trackingNum']);
        $this->assertSame($expectedStatus, $rows[0]['status']);
    }

    public function testUpdate_RecordWithEmptyTrackingNum_IsSkipped(): void
    {
        $this->insertOrder([
            'orderNum' => $this->orderNum('O1'),
            'sku' => $this->sku('A'),
            'trackingNum' => 'ZZTEST-OLD',
            'status' => 'PENDING',
        ]);
        $records = [
            $this->record($this->orderNum('O1'), $this->sku('A'), ['trackingNum' => '', 'status' => 'SHIPPED']),
        ];

        $this->newInserter()->update($this->con, $records);

        $rows = $this->ordersByOrderNum($this->orderNum('O1'));
        $this->assertSame('ZZTEST-OLD', $rows[0]['trackingNum']);
        $this->assertSame('PENDING', $rows[0]['status']);
    }

    public function testUpdate_RecordWhoseOwnStatusIsPending_IsSkipped(): void
    {
        $this->insertOrder([
            'orderNum' => $this->orderNum('O1'),
            'sku' => $this->sku('A'),
            'trackingNum' => 'ZZTEST-OLD',
            'status' => 'READY_TO_SHIP',
        ]);
        $records = [
            $this->record($this->orderNum('O1'), $this->sku('A'), ['trackingNum' => 'ZZTEST-NEW', 'status' => 'pending']),
        ];

        $this->newInserter()->update($this->con, $records);

        $rows = $this->ordersByOrderNum($this->orderNum('O1'));
        $this->assertSame('ZZTEST-OLD', $rows[0]['trackingNum']);
        $this->assertSame('READY_TO_SHIP', $rows[0]['status']);
    }

    public function testUpdate_UpdatesEveryLineOfTheOrderNum(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('O1'), 'sku' => $this->sku('A'), 'status' => 'READY_TO_SHIP']);
        $this->insertOrder(['orderNum' => $this->orderNum('O1'), 'sku' => $this->sku('B'), 'status' => 'READY_TO_SHIP']);
        $this->insertOrder(['orderNum' => $this->orderNum('O2'), 'sku' => $this->sku('A'), 'status' => 'READY_TO_SHIP']);
        $records = [
            $this->record($this->orderNum('O1'), $this->sku('A'), ['trackingNum' => 'ZZTEST-NEW', 'status' => 'SHIPPED']),
        ];

        $this->newInserter()->update($this->con, $records);

        $o1 = $this->ordersByOrderNum($this->orderNum('O1'));
        $this->assertSame(['ZZTEST-NEW', 'ZZTEST-NEW'], array_column($o1, 'trackingNum'));
        $this->assertSame(['SHIPPED', 'SHIPPED'], array_column($o1, 'status'));

        $o2 = $this->ordersByOrderNum($this->orderNum('O2'));
        $this->assertSame('', $o2[0]['trackingNum']);
        $this->assertSame('READY_TO_SHIP', $o2[0]['status']);
    }

    public function testUpdate_UnknownOrderNum_ChangesNothingAndDoesNotThrow(): void
    {
        $records = [
            $this->record($this->orderNum('NOPE'), $this->sku('A'), ['trackingNum' => 'ZZTEST-NEW', 'status' => 'SHIPPED']),
        ];

        $this->newInserter()->update($this->con, $records);

        $this->assertCount(0, $this->ordersByOrderNum($this->orderNum('NOPE')));
    }
}
