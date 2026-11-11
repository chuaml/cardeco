<?php

declare(strict_types=1);

namespace test\Unit\Orders\Factory;

use Exception;
use InvalidArgumentException;
use Orders\Factory\Lazada;
use Orders\Record;
use PHPUnit\Framework\TestCase;
use test\Support\CsvFixture;
use test\Support\XlsxFixture;
use test\Unit\Support\PhpErrors;

require_once __DIR__ . '/../../Support/PhpErrors.php';
use UnexpectedValueException;

/**
 * Characterization of Orders\Factory\Lazada (src/Legacy/Orders/Factory/Lazada.php): Lazada order export
 * (';'-delimited .csv) -> Orders\Record[], one Record per line, plus the "multi tracking number" shipping-fee rule.
 *
 * Columns are read by POSITION: 2 sku, 5 created date, 6 orderNum, 19 shipping state, 39 selling price,
 * 40 shipping fee paid by customer, 42 item name, 49 tracking number, 56 status.
 */
final class LazadaFactoryTest extends TestCase
{
    /** the factory addresses up to column 56 */
    const WIDTH = 57;

    /** @var array<int, string> a valid synthetic data row (positional) */
    const DEFAULTS = [
        2 => 'ZZTEST-001',
        5 => '15/01/2024 10:30',
        6 => 'ZZ123456789',
        19 => 'Johor',
        39 => '41.5',
        40 => '5.3',
        42 => 'Zz Item',
        49 => 'ZZTRK001',
        56 => 'delivered',
    ];

    // ---------------------------------------------------------------- constructor / file validation

    public function testConstructor_missingFile_throwsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('file does not exist');

        new Lazada('/nonexistent/zz-lazada-orders.csv');
    }

    public function testGenerateRecords_headerOnly_returnsEmptyList(): void
    {
        $this->assertSame([], (new Lazada($this->csv([])))->generateRecords());
    }

    public function testGenerateRecords_headerRowIsSkipped_oneRecordPerDataLine(): void
    {
        $path = $this->csv([$this->row([6 => 'ZZ100']), $this->row([6 => 'ZZ200'])]);

        $records = (new Lazada($path))->generateRecords();

        $this->assertCount(2, $records);
        $this->assertContainsOnlyInstancesOf(Record::class, $records);
        $this->assertSame(['ZZ100', 'ZZ200'], [$records[0]->orderNum, $records[1]->orderNum]);
    }

    public function testGenerateRecords_lineWithDifferentColumnCount_throwsWithLineNumber(): void
    {
        $path = CsvFixture::write([
            $this->header(),
            array_slice($this->row([]), 0, 50),
        ], ';');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('inconsistence number of columns at line 2');

        (new Lazada($path))->generateRecords();
    }

    public function testGenerateRecords_thirdLineWithExtraColumn_reportsLineThree(): void
    {
        $wide = $this->row([]);
        $wide[] = 'extra';
        $path = CsvFixture::write([$this->header(), $this->row([]), $wide], ';');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('inconsistence number of columns at line 3');

        (new Lazada($path))->generateRecords();
    }

    public function testGenerateRecords_emptyFile_documentsCurrentBehaviour_failsOnCountOfFalse(): void
    {
        // Lazada.php:67 checks `fgetcsv() === null` but EOF yields false, so count(false) is reached:
        // TypeError on PHP 8, Warning on PHP 7.
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, '');

        $e = PhpErrors::capture(function () use ($path) {
            (new Lazada($path))->generateRecords();
        });

        if (PHP_VERSION_ID >= 80000) {
            $this->assertInstanceOf(\TypeError::class, $e);
        } else {
            $this->assertInstanceOf(\ErrorException::class, $e);
        }
        $this->assertStringContainsString('count()', $e->getMessage());
    }

    /**
     * @group known-bug
     */
    public function testGenerateRecords_emptyFile_throwsFailToParse(): void
    {
        // Lazada.php:66-69: the 'fail to parse csv file data' guard never fires because fgetcsv() returns
        // false (not null) for an empty file.
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, '');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('fail to parse csv file data');

        (new Lazada($path))->generateRecords();
    }

    // ---------------------------------------------------------------- field mapping

    public function testGenerateRecords_singleRow_mapsPositionalColumns(): void
    {
        $path = $this->csv([$this->row([
            2 => ' ZZTEST-001 ',
            6 => ' ZZ123456789 ',
            19 => ' Johor ',
            42 => ' Zz Item ',
            49 => ' ZZTRK001 ',
            56 => ' delivered ',
        ])]);

        $records = (new Lazada($path))->generateRecords();

        $this->assertCount(1, $records);
        $this->assertSame([
            'recordId' => null,
            'orderNum' => 'ZZ123456789',
            'date' => '15-Jan-2024',
            'sku' => 'ZZTEST-001',
            'itemName' => 'Zz Item',
            'trackingNum' => 'ZZTRK001',
            'sellingPrice' => 41.5,
            'voucher' => 0.0,
            'shippingFee' => 0.0,
            'shippingFeeByCust' => 5.3,
            'shippingState' => 'Johor',
            'shippingWeight' => 0.0,
            'shippingFeeByWeight' => 0.0,
        ], $records[0]->getAll());
        $this->assertSame('DELIVERED', $records[0]->status);
    }

    public function testGenerateRecords_quotedFieldContainingDelimiter_isOneColumn(): void
    {
        $path = $this->csv([$this->row([42 => 'Zz Item; with semicolon'])]);

        $records = (new Lazada($path))->generateRecords();

        $this->assertSame('Zz Item; with semicolon', $records[0]->getItem()->description);
    }

    public function testGenerateRecords_orderNumber_documentsCurrentBehaviour_isNotValidated(): void
    {
        // the factory passes column 6 to the Record constructor instead of setOrderNum(), so characters that
        // setOrderNum() rejects (and that the Shopee factory rejects) are accepted here
        $path = $this->csv([$this->row([6 => 'ZZ-ORDER#1'])]);

        $records = (new Lazada($path))->generateRecords();

        $this->assertSame('ZZ-ORDER#1', $records[0]->orderNum);
    }

    public function testGenerateRecords_negativeSellingPrice_throwsInvalidArgumentException(): void
    {
        $path = $this->csv([$this->row([39 => '-1'])]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid selling price');

        (new Lazada($path))->generateRecords();
    }

    public function testGenerateRecords_nonNumericAmounts_areReadAsZero(): void
    {
        $path = $this->csv([$this->row([39 => 'n/a', 40 => ''])]);

        $record = (new Lazada($path))->generateRecords()[0];

        $this->assertSame(0.0, $record->sellingPrice);
        $this->assertSame(0.0, $record->shippingFeeByCust);
    }

    public function testGenerateRecords_amountsAreRoundedToTwoDecimals(): void
    {
        $path = $this->csv([$this->row([39 => '41.555', 40 => '5.344'])]);

        $record = (new Lazada($path))->generateRecords()[0];

        $this->assertSame(41.56, $record->sellingPrice);
        $this->assertSame(5.34, $record->shippingFeeByCust);
    }

    // ---------------------------------------------------------------- date heuristics (Lazada.php getOrderDate)

    /** @return array<string, array<int, string>> */
    public function provideAcceptedOrderDates(): array
    {
        return [
            'new export: d/m/Y H:i (2 tokens)' => ['15/01/2024 10:30', '15-Jan-2024'],
            'new export with seconds' => ['15/01/2024 10:30:45', '15-Jan-2024'],
            'new export single-digit day and month' => ['5/1/2024 10:30', '05-Jan-2024'],
            'old export: EEE MMM dd HH:mm:ss zzz yyyy (6 tokens)' => ['Mon Jan 15 10:30:00 MYT 2024', '15-Jan-2024'],
            'surrounding whitespace is trimmed' => ['  15/01/2024 10:30  ', '15-Jan-2024'],
        ];
    }

    /**
     * @dataProvider provideAcceptedOrderDates
     */
    public function testGenerateRecords_orderDate_isNormalisedToDayMonthYear(string $cell, string $expected): void
    {
        $path = $this->csv([$this->row([5 => $cell])]);

        $this->assertSame($expected, (new Lazada($path))->generateRecords()[0]->date);
    }

    /** @return array<string, array<int, string>> */
    public function provideRejectedOrderDates(): array
    {
        return [
            'iso date without time (1 token)' => ['2024-01-15'],
            'd/m/Y without time (1 token)' => ['15/01/2024'],
            'textual month with time (4 tokens)' => ['15 Jan 2024 10:30'],
            'impossible day and month' => ['99/99/2024 10:30'],
            'empty cell' => [''],
        ];
    }

    /**
     * @dataProvider provideRejectedOrderDates
     */
    public function testGenerateRecords_unrecognisedOrderDate_throwsUnexpectedValueException(string $cell): void
    {
        $path = $this->csv([$this->row([5 => $cell])]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('invalid date format');

        (new Lazada($path))->generateRecords();
    }

    // ---------------------------------------------------------------- computeShippingFee (multi tracking number)

    public function testGenerateRecords_orderWithSingleTrackingNumber_shippingFeeStaysZero(): void
    {
        $path = $this->csv([
            $this->row([6 => 'ZZ100', 49 => 'TRK-A', 40 => '5.3']),
            $this->row([6 => 'ZZ100', 49 => 'TRK-A', 40 => '2.2']),
        ]);

        $fees = $this->shippingFees((new Lazada($path))->generateRecords());

        $this->assertSame([0.0, 0.0], $fees);
    }

    public function testGenerateRecords_orderWithTwoTrackingNumbers_copiesShippingFeeByCustToShippingFee(): void
    {
        $path = $this->csv([
            $this->row([6 => 'ZZ100', 49 => 'TRK-A', 40 => '5.3']),
            $this->row([6 => 'ZZ100', 49 => 'TRK-B', 40 => '1.1']),
            $this->row([6 => 'ZZ200', 49 => 'TRK-C', 40 => '9.9']),
        ]);

        $records = (new Lazada($path))->generateRecords();

        $this->assertSame([5.3, 1.1, 0.0], $this->shippingFees($records), 'single-tracking order ZZ200 is untouched');
        $this->assertSame([5.3, 1.1, 9.9], array_map(function (Record $r) {
            return $r->shippingFeeByCust;
        }, $records), 'shippingFeeByCust itself is never changed');
    }

    public function testGenerateRecords_twoLinesShareATrackingNumber_documentsCurrentBehaviour_onlyLastLineGetsFee(): void
    {
        // Lazada.php computeShippingFee(): `$OrderNum[$orderNum][$trackingNum] = $k` keeps only the LAST line index
        // per tracking number, so the first TRK-A line keeps shippingFee 0.00 although the order has 2 tracking numbers.
        $path = $this->csv([
            $this->row([6 => 'ZZ100', 49 => 'TRK-A', 40 => '5.3']),
            $this->row([6 => 'ZZ100', 49 => 'TRK-A', 40 => '2.2']),
            $this->row([6 => 'ZZ100', 49 => 'TRK-B', 40 => '1.1']),
        ]);

        $fees = $this->shippingFees((new Lazada($path))->generateRecords());

        $this->assertSame([0.0, 2.2, 1.1], $fees);
    }

    /**
     * @group known-bug
     */
    public function testGenerateRecords_twoLinesShareATrackingNumber_everyLineOfTheOrderGetsShippingFee(): void
    {
        // Lazada.php computeShippingFee() comment: "for same orderNum but different trackingNum ... copy
        // shippingFeeByCust to shippingFee" - intended for every line of such an order, not only the last per tracking.
        $path = $this->csv([
            $this->row([6 => 'ZZ100', 49 => 'TRK-A', 40 => '5.3']),
            $this->row([6 => 'ZZ100', 49 => 'TRK-A', 40 => '2.2']),
            $this->row([6 => 'ZZ100', 49 => 'TRK-B', 40 => '1.1']),
        ]);

        $fees = $this->shippingFees((new Lazada($path))->generateRecords());

        $this->assertSame([5.3, 2.2, 1.1], $fees);
    }

    public function testGenerateRecords_sameTrackingNumberOnDifferentOrders_doesNotCountAsMultiTracking(): void
    {
        $path = $this->csv([
            $this->row([6 => 'ZZ100', 49 => 'TRK-A', 40 => '5.3']),
            $this->row([6 => 'ZZ200', 49 => 'TRK-A', 40 => '1.1']),
        ]);

        $fees = $this->shippingFees((new Lazada($path))->generateRecords());

        $this->assertSame([0.0, 0.0], $fees);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param array<int, string> $positional overrides for self::DEFAULTS
     * @return array<int, string>
     */
    private function row(array $positional): array
    {
        return CsvFixture::row(self::WIDTH, $positional + self::DEFAULTS);
    }

    /** @return array<int, string> a header row as wide as the data rows */
    private function header(): array
    {
        $header = [];
        for ($i = 0; $i < self::WIDTH; ++$i) {
            $header[] = 'col' . $i;
        }

        return $header;
    }

    /**
     * @param array<int, array<int, string>> $dataRows
     */
    private function csv(array $dataRows): string
    {
        array_unshift($dataRows, $this->header());

        return CsvFixture::write($dataRows, ';');
    }

    /**
     * @param array<int, Record> $records
     * @return array<int, float>
     */
    private function shippingFees(array $records): array
    {
        return array_map(function (Record $r) {
            return $r->shippingFee;
        }, $records);
    }
}
