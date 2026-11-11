<?php

declare(strict_types=1);

namespace test\Unit\Orders\Factory;

use Exception;
use InvalidArgumentException;
use Orders\Factory\Shopee;
use Orders\Record;
use PHPUnit\Framework\TestCase;
use test\Support\CsvFixture;
use test\Support\XlsxFixture;
use test\Unit\Support\PhpErrors;

require_once __DIR__ . '/../../Support/PhpErrors.php';

/**
 * Characterization of Orders\Factory\Shopee (src/Legacy/Orders/Factory/Shopee.php): Shopee order
 * export (.csv) -> Orders\Record[] with one Record per unit ordered (Quantity column expanded).
 *
 * Columns are read by POSITION: 0 orderNum, 1 status, 3 trackingNum, 8 date, 10 parent sku,
 * 11 item name, 12 sku, 15 selling price, 16 quantity, 21 weight, 25 voucher, 38 shipping fee, 46 state.
 */
final class ShopeeFactoryTest extends TestCase
{
    const SAMPLE = 'tests/shopee/data.input/shopee.input.order.sample.csv';

    /** width of the current Shopee export; the factory requires at least 50 columns */
    const WIDTH = 55;

    /** @var array<int, string> a valid synthetic data row (positional) */
    const DEFAULTS = [
        0 => 'ZZ240101ABC',
        1 => 'Completed',
        3 => 'SPXMY000000000001',
        8 => '2024-01-15 10:47',
        10 => 'ZZTEST-PARENT',
        11 => 'Zz Boot Tray',
        12 => 'ZZTEST-001',
        15 => '41.5',
        16 => '1',
        21 => '3',
        25 => '1.25',
        38 => '1.54',
        46 => 'Zz Town',
    ];

    // ---------------------------------------------------------------- constructor

    public function testConstructor_missingFile_throwsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('file does not exist');

        new Shopee('/nonexistent/zz-shopee-orders.csv');
    }

    // ---------------------------------------------------------------- sample export

    public function testGenerateRecords_sampleCsv_producesOneRecordPerUnitOrdered(): void
    {
        $records = (new Shopee(self::SAMPLE))->generateRecords();

        $this->assertSame(63, $this->sumOfQuantityColumn(self::SAMPLE));
        $this->assertCount(63, $records);
        $this->assertContainsOnlyInstancesOf(Record::class, $records);
    }

    public function testGenerateRecords_sampleCsv_firstRecordIsMappedFromThePositionalColumns(): void
    {
        $row = $this->firstDataRow(self::SAMPLE);
        $first = (new Shopee(self::SAMPLE))->generateRecords()[0];

        $this->assertSame(trim($row[0]), $first->orderNum);
        $this->assertSame(strtoupper(trim($row[1])), $first->status);
        $this->assertSame(trim($row[3]), $first->trackingNum);
        $this->assertSame(trim($row[12]), $first->getItem()->code, 'sku comes from column 12 when it is not blank');
        $this->assertSame(trim($row[11]), $first->getItem()->description);
        $this->assertSame(round((float) $row[15], 2), $first->sellingPrice);
        $this->assertSame(round((float) $row[21], 2), $first->shippingWeight);
        $this->assertSame((float) $row[25], $first->voucher);
        $this->assertSame(round((float) $row[38], 2), $first->shippingFee);
        $this->assertSame(trim($row[46]), $first->shippingState);
        $this->assertMatchesRegularExpression('/^\d{2}-[A-Z][a-z]{2}-\d{4}$/', $first->date, 'date is normalised to d-M-Y');
        $this->assertNull($first->recordId);
        $this->assertSame(0.00, $first->shippingFeeByCust);
    }

    public function testGenerateRecords_sampleCsv_headerOfTheColumnsRead_documentsCurrentBehaviour(): void
    {
        // The export layout has drifted: the factory reads column 38 as the shipping fee and column 46 as the
        // shipping state, but in the current Shopee export those columns are 'Commission Fee' and 'Town'
        // ('State' is column 49, 'Buyer Paid Shipping Fee' column 34). Locked here so a mapping fix is deliberate.
        $header = $this->headerRow(self::SAMPLE);

        $this->assertSame('Order ID', $header[0]);
        $this->assertSame('Order Status', $header[1]);
        $this->assertSame('Tracking Number*', $header[3]);
        $this->assertSame('Order Creation Date', $header[8]);
        $this->assertSame('Parent SKU Reference No.', $header[10]);
        $this->assertSame('Product Name', $header[11]);
        $this->assertSame('SKU Reference No.', $header[12]);
        $this->assertSame('Deal Price', $header[15]);
        $this->assertSame('Quantity', $header[16]);
        $this->assertSame('SKU Total Weight', $header[21]);
        $this->assertSame('Seller Voucher', $header[25]);
        $this->assertSame('Commission Fee', $header[38]);
        $this->assertSame('Town', $header[46]);
        $this->assertSame('State', $header[49]);
    }

    // ---------------------------------------------------------------- field mapping (synthetic rows)

    public function testGenerateRecords_syntheticRow_mapsEveryField(): void
    {
        $path = $this->csv([$this->row([
            0 => ' ZZ240101ABC ',
            1 => ' completed ',
            3 => ' SPXMY000000000001 ',
            8 => '2024-01-15 10:47',
            11 => ' Zz Boot Tray ',
            12 => ' ZZTEST-001 ',
            46 => ' Zz Town ',
        ])]);

        $records = (new Shopee($path))->generateRecords();

        $this->assertCount(1, $records);
        $this->assertSame([
            'recordId' => null,
            'orderNum' => 'ZZ240101ABC',
            'date' => '15-Jan-2024',
            'sku' => 'ZZTEST-001',
            'itemName' => 'Zz Boot Tray',
            'trackingNum' => 'SPXMY000000000001',
            'sellingPrice' => 41.5,
            'voucher' => 1.25,
            'shippingFee' => 1.54,
            'shippingFeeByCust' => 0.0,
            'shippingState' => 'Zz Town',
            'shippingWeight' => 3.0,
            'shippingFeeByWeight' => 0.0,
        ], $records[0]->getAll());
        $this->assertSame('COMPLETED', $records[0]->status, 'status is trimmed and upper-cased');
    }

    public function testGenerateRecords_blankSku_fallsBackToParentSku(): void
    {
        $path = $this->csv([$this->row([10 => 'ZZTEST-PARENT', 12 => ''])]);

        $records = (new Shopee($path))->generateRecords();

        $this->assertSame('ZZTEST-PARENT', $records[0]->getItem()->code);
    }

    public function testGenerateRecords_whitespaceOnlySku_fallsBackToParentSku(): void
    {
        $path = $this->csv([$this->row([10 => ' ZZTEST-PARENT ', 12 => '   '])]);

        $records = (new Shopee($path))->generateRecords();

        $this->assertSame('ZZTEST-PARENT', $records[0]->getItem()->code);
    }

    public function testGenerateRecords_skuPresent_parentSkuIsIgnored(): void
    {
        $path = $this->csv([$this->row([10 => 'ZZTEST-PARENT', 12 => 'ZZTEST-001'])]);

        $records = (new Shopee($path))->generateRecords();

        $this->assertSame('ZZTEST-001', $records[0]->getItem()->code);
    }

    // ---------------------------------------------------------------- quantity expansion

    public function testGenerateRecords_quantityThree_producesThreeEqualRecords(): void
    {
        $path = $this->csv([$this->row([16 => '3'])]);

        $records = (new Shopee($path))->generateRecords();

        $this->assertCount(3, $records);
        $this->assertSame($records[0]->getAll(), $records[1]->getAll());
        $this->assertSame($records[0]->getAll(), $records[2]->getAll());
        $this->assertNotSame($records[0], $records[1], 'each unit is a distinct Record instance');
        $this->assertNotSame($records[0]->getItem(), $records[1]->getItem());
    }

    public function testGenerateRecords_quantityZero_documentsCurrentBehaviour_rowIsSilentlyDropped(): void
    {
        $path = $this->csv([$this->row([16 => '0']), $this->row([0 => 'ZZ240101DEF'])]);

        $records = (new Shopee($path))->generateRecords();

        $this->assertCount(1, $records);
        $this->assertSame('ZZ240101DEF', $records[0]->orderNum);
    }

    public function testGenerateRecords_multipleRows_recordsKeepFileOrder(): void
    {
        $path = $this->csv([
            $this->row([0 => 'ZZ240101AAA', 16 => '1']),
            $this->row([0 => 'ZZ240101BBB', 16 => '2']),
            $this->row([0 => 'ZZ240101CCC', 16 => '1']),
        ]);

        $orderNums = array_map(function (Record $r) {
            return $r->orderNum;
        }, (new Shopee($path))->generateRecords());

        $this->assertSame(['ZZ240101AAA', 'ZZ240101BBB', 'ZZ240101BBB', 'ZZ240101CCC'], $orderNums);
    }

    // ---------------------------------------------------------------- file validation

    public function testGenerateRecords_headerWithFewerThan50Columns_throwsTooLessColumns(): void
    {
        $path = CsvFixture::write([
            CsvFixture::row(49, [0 => 'Order ID']),
            CsvFixture::row(49, self::DEFAULTS),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Too less columns');

        (new Shopee($path))->generateRecords();
    }

    public function testGenerateRecords_headerWithExactly50Columns_isAccepted(): void
    {
        $path = CsvFixture::write([
            CsvFixture::row(50, [0 => 'Order ID']),
            CsvFixture::row(50, self::DEFAULTS),
        ]);

        $records = (new Shopee($path))->generateRecords();

        $this->assertCount(1, $records);
        $this->assertSame('ZZ240101ABC', $records[0]->orderNum);
    }

    public function testGenerateRecords_headerOnly_returnsEmptyList(): void
    {
        $path = $this->csv([]);

        $this->assertSame([], (new Shopee($path))->generateRecords());
    }

    public function testGenerateRecords_blankFirstLine_throwsTooLessColumns(): void
    {
        // fgetcsv() turns a blank line into [null] -> a 1-column "header"
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, "\n");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Too less columns');

        (new Shopee($path))->generateRecords();
    }

    public function testGenerateRecords_emptyFile_documentsCurrentBehaviour_failsOnCountOfFalse(): void
    {
        // CSVInputStream::readLines() returns [false] for an empty file, so Shopee.php:41 (`count($orders) === 0`)
        // never fires and Shopee.php:44 calls count(false): TypeError on PHP 8, Warning on PHP 7.
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, '');

        $e = PhpErrors::capture(function () use ($path) {
            (new Shopee($path))->generateRecords();
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
    public function testGenerateRecords_emptyFile_throwsNoDataCaptured(): void
    {
        // Shopee.php:41-43 ('No data captured. Possible invalid file.') is unreachable: IO\CSVInputStream::readLine()
        // checks `=== null` but fgetcsv() returns false at EOF, so an empty upload ends in count(false).
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, '');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No data captured');

        (new Shopee($path))->generateRecords();
    }

    // ---------------------------------------------------------------- date handling

    public function testGenerateRecords_dateDdMmYyWithTime_isReadAsDayMonthYear(): void
    {
        // Shopee::normaliseDate(): the export's 'dd-mm-yy H:mm' is parsed explicitly (PHP's generic parser used to
        // read it as yy-mm-dd -> 23-Feb-2011, which RecordInserterCSV then persisted).
        $path = $this->csv([$this->row([8 => '11-02-23 0:47'])]);

        $records = (new Shopee($path))->generateRecords();

        $this->assertSame('11-Feb-2023', $records[0]->date);
    }

    public function testGenerateRecords_slashDate_isReadDayFirst(): void
    {
        $path = $this->csv([$this->row([8 => '15/01/2024 10:47'])]);

        $records = (new Shopee($path))->generateRecords();

        $this->assertSame('15-Jan-2024', $records[0]->date);
    }

    public function testGenerateRecords_emptyDate_throwsInvalidArgumentException(): void
    {
        $path = $this->csv([$this->row([8 => ''])]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty date value');

        (new Shopee($path))->generateRecords();
    }

    // ---------------------------------------------------------------- Record validation is applied

    public function testGenerateRecords_orderNumberWithSpecialCharacters_throwsInvalidArgumentException(): void
    {
        $path = $this->csv([$this->row([0 => 'ZZ#240101'])]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid order number');

        (new Shopee($path))->generateRecords();
    }

    public function testGenerateRecords_negativeSellingPrice_throwsInvalidArgumentException(): void
    {
        $path = $this->csv([$this->row([15 => '-0.01'])]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid selling price');

        (new Shopee($path))->generateRecords();
    }

    public function testGenerateRecords_nonNumericAmounts_areReadAsZero(): void
    {
        $path = $this->csv([$this->row([15 => 'n/a', 21 => '', 25 => '-', 38 => 'free'])]);

        $record = (new Shopee($path))->generateRecords()[0];

        $this->assertSame(0.0, $record->sellingPrice);
        $this->assertSame(0.0, $record->shippingWeight);
        $this->assertSame(0.0, $record->voucher);
        $this->assertSame(0.0, $record->shippingFee);
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

    /**
     * @param array<int, array<int, string>> $dataRows
     */
    private function csv(array $dataRows): string
    {
        array_unshift($dataRows, CsvFixture::row(self::WIDTH, [0 => 'Order ID', 16 => 'Quantity']));

        return CsvFixture::write($dataRows);
    }

    /** @return array<int, string> */
    private function headerRow(string $path): array
    {
        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        fclose($fh);

        return $header;
    }

    /** @return array<int, string> */
    private function firstDataRow(string $path): array
    {
        $fh = fopen($path, 'r');
        fgetcsv($fh);
        $row = fgetcsv($fh);
        fclose($fh);

        return $row;
    }

    private function sumOfQuantityColumn(string $path): int
    {
        $fh = fopen($path, 'r');
        fgetcsv($fh);
        $sum = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $sum += (int) $row[16];
        }
        fclose($fh);

        return $sum;
    }
}
