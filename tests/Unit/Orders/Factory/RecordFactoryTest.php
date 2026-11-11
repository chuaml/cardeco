<?php

declare(strict_types=1);

namespace test\Unit\Orders\Factory;

use InvalidArgumentException;
use Orders\Factory\Record as RecordFactory;
use Orders\Factory\RecordFactory as RecordFactoryInterface;
use Orders\Record;
use PHPUnit\Framework\TestCase;
use test\Unit\Support\PhpErrors;
use UnexpectedValueException;

require_once __DIR__ . '/../../Support/PhpErrors.php';

/**
 * Characterization of Orders\Factory\Record (src/Legacy/Orders/Factory/Record.php): the rows posted by the
 * RecordInserter form (`$_POST['r'][n][field]`, see view/RecordInserter.html) -> Orders\Record[].
 */
final class RecordFactoryTest extends TestCase
{
    /** @var array<string, string> one row exactly as the RecordInserter form posts it */
    const ROW = [
        'orderNum' => 'ZZ240101ABC',
        'sku' => 'ZZTEST-001',
        'date' => '2024-01-15',
        'trackingNum' => 'ZZTRK001',
        'status' => 'READY_TO_SHIP',
        'sellingPrice' => '41.50',
        'shippingFee' => '4.9',
        'shippingFeeByCust' => '0',
    ];

    // ---------------------------------------------------------------- constructor

    public function testConstructor_emptyList_throwsUnexpectedValueException(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('List size is empty');

        new RecordFactory([]);
    }

    public function testMaxRecord_documentedValue(): void
    {
        $this->assertSame(1000000, RecordFactoryInterface::MAX_RECORD);
    }

    // ---------------------------------------------------------------- mapping

    public function testGenerateRecords_singleRow_mapsEveryField(): void
    {
        $records = (new RecordFactory([self::ROW]))->generateRecords();

        $this->assertCount(1, $records);
        $this->assertInstanceOf(Record::class, $records[0]);
        $this->assertSame([
            'recordId' => null,
            'orderNum' => 'ZZ240101ABC',
            'date' => '15-Jan-2024',
            'sku' => 'ZZTEST-001',
            'itemName' => '',
            'trackingNum' => 'ZZTRK001',
            'sellingPrice' => 41.5,
            'voucher' => 0.0,
            'shippingFee' => 4.9,
            'shippingFeeByCust' => 0.0,
            'shippingState' => '',
            'shippingWeight' => 0.0,
            'shippingFeeByWeight' => 0.0,
        ], $records[0]->getAll());
        $this->assertSame('READY_TO_SHIP', $records[0]->status);
    }

    public function testGenerateRecords_itemDescriptionIsAlwaysEmptyString(): void
    {
        // Record.php:40 hard-codes $description = ''; the form has no description field, so even an extra
        // 'description'/'itemName' key in the posted row is ignored
        $row = self::ROW + ['description' => 'Zz Tray', 'itemName' => 'Zz Tray'];

        $item = (new RecordFactory([$row]))->generateRecords()[0]->getItem();

        $this->assertSame('ZZTEST-001', $item->code);
        $this->assertSame('', $item->description);
        $this->assertNull($item->itemId);
    }

    public function testGenerateRecords_statusAndTrackingNumber_areTrimmedAndStatusUpperCased(): void
    {
        $row = ['status' => ' shipped ', 'trackingNum' => ' ZZTRK001 '] + self::ROW;

        $record = (new RecordFactory([$row]))->generateRecords()[0];

        $this->assertSame('SHIPPED', $record->status);
        $this->assertSame('ZZTRK001', $record->trackingNum);
    }

    public function testGenerateRecords_orderNumber_documentsCurrentBehaviour_isStoredVerbatim(): void
    {
        // the factory passes orderNum to the Record constructor instead of setOrderNum(): no trim, no
        // INVALID_ORDER_NUM check (unlike Orders\Factory\Shopee and TikTokOrder, which call setOrderNum())
        $row = ['orderNum' => ' ZZ-ORDER#1 '] + self::ROW;

        $record = (new RecordFactory([$row]))->generateRecords()[0];

        $this->assertSame(' ZZ-ORDER#1 ', $record->orderNum);
    }

    /** @return array<string, array<int, string>> */
    public function provideDates(): array
    {
        return [
            'html date input (Y-m-d)' => ['2024-01-15', '15-Jan-2024'],
            'd/m/Y is read day first' => ['15/01/2024', '15-Jan-2024'],
            'already formatted' => ['15-Jan-2024', '15-Jan-2024'],
        ];
    }

    /**
     * @dataProvider provideDates
     */
    public function testGenerateRecords_date_isNormalisedToDayMonthYear(string $posted, string $expected): void
    {
        $row = ['date' => $posted] + self::ROW;

        $this->assertSame($expected, (new RecordFactory([$row]))->generateRecords()[0]->date);
    }

    public function testGenerateRecords_emptyDate_throwsInvalidArgumentException(): void
    {
        $row = ['date' => ''] + self::ROW;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty date value');

        (new RecordFactory([$row]))->generateRecords();
    }

    public function testGenerateRecords_amountsAreCoercedWithDoubleval(): void
    {
        $row = ['sellingPrice' => '41.555', 'shippingFee' => 'free', 'shippingFeeByCust' => '2.5abc'] + self::ROW;

        $record = (new RecordFactory([$row]))->generateRecords()[0];

        $this->assertSame(41.56, $record->sellingPrice, 'rounded to 2 decimals by Record::setSellingPrice');
        $this->assertSame(0.0, $record->shippingFee, 'non numeric -> 0');
        $this->assertSame(2.5, $record->shippingFeeByCust, 'leading numeric prefix is used');
    }

    public function testGenerateRecords_thousandsSeparator_documentsCurrentBehaviour_truncatesTheAmount(): void
    {
        // doubleval('1,234.50') stops at the comma
        $row = ['sellingPrice' => '1,234.50'] + self::ROW;

        $this->assertSame(1.0, (new RecordFactory([$row]))->generateRecords()[0]->sellingPrice);
    }

    public function testGenerateRecords_negativeShippingFee_throwsInvalidArgumentException(): void
    {
        $row = ['shippingFee' => '-1'] + self::ROW;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid shipping fee');

        (new RecordFactory([$row]))->generateRecords();
    }

    public function testGenerateRecords_rowMissingAField_documentsCurrentBehaviour_raisesUndefinedIndex(): void
    {
        $row = self::ROW;
        unset($row['trackingNum']);

        // Notice on PHP 7 ("Undefined index"), Warning on PHP 8 ("Undefined array key"); no validation of the posted keys
        $e = PhpErrors::capture(function () use ($row) {
            (new RecordFactory([$row]))->generateRecords();
        });

        $this->assertInstanceOf(\ErrorException::class, $e);
        $this->assertMatchesRegularExpression('/Undefined (index|array key)/', $e->getMessage());
    }

    // ---------------------------------------------------------------- list handling

    public function testGenerateRecords_threeRows_returnsThreeRecordsInOrder(): void
    {
        $rows = [
            ['orderNum' => 'ZZ100'] + self::ROW,
            ['orderNum' => 'ZZ200'] + self::ROW,
            ['orderNum' => 'ZZ300'] + self::ROW,
        ];

        $orderNums = array_map(function (Record $r) {
            return $r->orderNum;
        }, (new RecordFactory($rows))->generateRecords());

        $this->assertSame(['ZZ100', 'ZZ200', 'ZZ300'], $orderNums);
    }

    public function testGenerateRecords_nonSequentialKeys_resultIsAZeroIndexedList(): void
    {
        // request_handler/RecordInserter.php array_filter()s blank form rows, leaving gaps in the keys
        $rows = [
            0 => ['orderNum' => 'ZZ100'] + self::ROW,
            3 => ['orderNum' => 'ZZ300'] + self::ROW,
            'x' => ['orderNum' => 'ZZ400'] + self::ROW,
        ];

        $records = (new RecordFactory($rows))->generateRecords();

        $this->assertSame([0, 1, 2], array_keys($records));
        $this->assertSame(['ZZ100', 'ZZ300', 'ZZ400'], array_map(function (Record $r) {
            return $r->orderNum;
        }, $records));
    }

    public function testGenerateRecords_everyRecordIsADistinctInstance(): void
    {
        $records = (new RecordFactory([self::ROW, self::ROW]))->generateRecords();

        $this->assertNotSame($records[0], $records[1]);
        $this->assertNotSame($records[0]->getItem(), $records[1]->getItem());
        $this->assertSame($records[0]->getAll(), $records[1]->getAll());
    }

    /**
     * @group known-bug
     */
    public function testGenerateRecords_moreThanMaxRecordRows_isCappedAtMaxRecord(): void
    {
        // Record.php:31 `if ($count++ > $len) break;` is off by one: with MAX_RECORD + 2 rows it yields
        // MAX_RECORD + 1 records instead of MAX_RECORD. Below the cap the condition can never trigger, so this
        // is only observable with > 1,000,000 rows (needs ~1 GB) - run on purpose with CARDECO_HEAVY_TESTS=1.
        if (getenv('CARDECO_HEAVY_TESTS') !== '1') {
            $this->markTestSkipped('needs > 1,000,000 rows; set CARDECO_HEAVY_TESTS=1 to run');
        }
        $rows = array_fill(0, RecordFactoryInterface::MAX_RECORD + 2, self::ROW);

        $records = (new RecordFactory($rows))->generateRecords();

        $this->assertCount(RecordFactoryInterface::MAX_RECORD, $records);
    }
}
