<?php

declare(strict_types=1);

namespace test\Unit\OrderProcess;

use Exception;
use InvalidArgumentException;
use OrderProcess\TikTokOrder;
use PHPUnit\Framework\TestCase;
use test\Support\CsvFixture;
use test\Support\UnconnectedMysqli;
use test\Unit\Support\PhpErrors;

require_once __DIR__ . '/../Support/PhpErrors.php';

/**
 * Characterization of OrderProcess\TikTokOrder::getOrders() (src/OrderProcess/TikTok/TikTok.php): the file-only
 * parser of the TikTok order export. getData() (stock lookup) is never called, so an unconnected mysqli suffices.
 *
 * Columns are read by POSITION: 0 order id, 6 seller sku, 7 product name, 9 quantity, 11 unit price,
 * 14 seller discount, 16 shipping fee, 23 created time, 33 tracking id, 35 shipping provider, 42 state.
 */
final class TikTokOrderParseTest extends TestCase
{
    const SAMPLE = 'tests/tiktok/data.input/tiktok.input.order.sample.csv';
    const SAMPLE_MULTI_QTY = 'tests/tiktok/data.input/tiktok.input.order.multiple_quantity.sample.csv';

    /** width of the current TikTok export */
    const WIDTH = 53;

    /** @var array<int, string> a valid synthetic data row (positional); trailing tabs as in the real export */
    const DEFAULTS = [
        0 => "577000000000000001\t",
        6 => 'ZZTEST-001',
        7 => ' Zz Boot Tray ',
        9 => '1',
        11 => 'MYR 29.00',
        14 => 'MYR 0.00',
        16 => 'MYR 4.90',
        23 => "24/03/2023 15:53:18\t",
        33 => '685000000001',
        35 => 'J&T Express',
        42 => ' Kedah ',
    ];

    const ROW_KEYS = [
        'orderNum', 'date', 'sku', 'description', 'sellingPrice', 'sellerVoucher', 'shippingFee',
        'shippingProvider', 'trackingNum', 'shippingState', 'stock',
    ];

    // ---------------------------------------------------------------- constructor

    public function testConstructor_missingFile_throwsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('file does not exist');

        new TikTokOrder(UnconnectedMysqli::create(), '/nonexistent/zz-tiktok-orders.csv');
    }

    // ---------------------------------------------------------------- sample exports

    public function testGetOrders_sampleCsv_oneRowPerUnitOrdered(): void
    {
        $orders = $this->orders(self::SAMPLE);

        $this->assertSame(8, $this->sumOfQuantityColumn(self::SAMPLE));
        $this->assertCount(8, $orders);
    }

    public function testGetOrders_multipleQuantitySample_expandsEveryQuantityIntoUnits(): void
    {
        $orders = $this->orders(self::SAMPLE_MULTI_QTY);

        $this->assertSame(6, $this->sumOfQuantityColumn(self::SAMPLE_MULTI_QTY));
        $this->assertCount(6, $orders);
    }

    public function testGetOrders_sampleCsv_everyRowHasTheDocumentedKeys(): void
    {
        foreach ($this->orders(self::SAMPLE) as $row) {
            $this->assertSame(self::ROW_KEYS, array_keys($row));
            $this->assertNull($row['stock'], 'stock is filled later by the pick-list builder');
            $this->assertSame(0.00, $row['sellerVoucher'], 'voucher is folded into sellingPrice');
        }
    }

    public function testGetOrders_sampleCsv_firstRowIsMappedFromThePositionalColumns(): void
    {
        $raw = $this->firstDataRow(self::SAMPLE);
        $first = $this->orders(self::SAMPLE)[0];

        $this->assertSame(trim($raw[0]), $first['orderNum']);
        $this->assertSame($raw[6], $first['sku']);
        $this->assertSame(trim($raw[7]), $first['description']);
        $this->assertSame(trim($raw[35]), $first['shippingProvider']);
        $this->assertSame(trim($raw[33]), $first['trackingNum']);
        $this->assertSame(trim($raw[42]), $first['shippingState']);
        $this->assertMatchesRegularExpression('/^\d{2}-[A-Z][a-z]{2}-\d{4}$/', $first['date']);
    }

    // ---------------------------------------------------------------- field mapping (synthetic rows)

    public function testGetOrders_syntheticRow_mapsEveryField(): void
    {
        $orders = $this->orders($this->csv([$this->row([])]));

        $this->assertSame([[
            'orderNum' => '577000000000000001',
            'date' => '24-Mar-2023',
            'sku' => 'ZZTEST-001',
            'description' => 'Zz Boot Tray',
            'sellingPrice' => 29.0,
            'sellerVoucher' => 0.00,
            'shippingFee' => 4.9,
            'shippingProvider' => 'J&T Express',
            'trackingNum' => '685000000001',
            'shippingState' => 'Kedah',
            'stock' => null,
        ]], $orders);
    }

    public function testGetOrders_headerOnly_returnsEmptyList(): void
    {
        $this->assertSame([], $this->orders($this->csv([])));
    }

    public function testGetOrders_quantityTwo_producesTwoIdenticalRows(): void
    {
        $orders = $this->orders($this->csv([$this->row([9 => '2'])]));

        $this->assertCount(2, $orders);
        $this->assertSame($orders[0], $orders[1]);
    }

    public function testGetOrders_sellerDiscount_isSplitPerUnitAndDeductedFromTheUnitPrice(): void
    {
        $orders = $this->orders($this->csv([$this->row([9 => '2', 11 => 'MYR 29.00', 14 => 'MYR 8.00'])]));

        $this->assertSame([25.0, 25.0], array_column($orders, 'sellingPrice'), '29.00 - 8.00 / 2 units');
    }

    public function testGetOrders_sellerDiscount_singleUnitDeductsTheWholeDiscount(): void
    {
        $orders = $this->orders($this->csv([$this->row([9 => '1', 11 => 'MYR 29.00', 14 => 'MYR 8.00'])]));

        $this->assertSame(21.0, $orders[0]['sellingPrice']);
    }

    public function testGetOrders_discountLargerThanPrice_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid selling price');

        $this->orders($this->csv([$this->row([11 => 'MYR 29.00', 14 => 'MYR 30.00'])]));
    }

    public function testGetOrders_currencyPrefixAndThousandsSeparator_areStrippedFromAmounts(): void
    {
        $orders = $this->orders($this->csv([$this->row([11 => 'MYR 1,234.50', 14 => 'RM 0', 16 => 'MYR 1,000.00'])]));

        $this->assertSame(1234.5, $orders[0]['sellingPrice']);
        $this->assertSame(1000.0, $orders[0]['shippingFee']);
    }

    public function testGetOrders_amountsAreRoundedToTwoDecimals(): void
    {
        $orders = $this->orders($this->csv([$this->row([11 => 'MYR 10.005', 16 => 'MYR 4.999'])]));

        $this->assertSame(10.01, $orders[0]['sellingPrice']);
        $this->assertSame(5.0, $orders[0]['shippingFee']);
    }

    /** @return array<string, array<int, string>> */
    public function provideCreatedTimes(): array
    {
        return [
            'd/m/Y H:i:s' => ['24/03/2023 15:53:18', '24-Mar-2023'],
            'with trailing tab (as exported)' => ["24/03/2023 15:53:18\t", '24-Mar-2023'],
            'iso' => ['2023-03-24 15:53:18', '24-Mar-2023'],
        ];
    }

    /**
     * @dataProvider provideCreatedTimes
     */
    public function testGetOrders_createdTime_isNormalisedToDayMonthYear(string $cell, string $expected): void
    {
        $orders = $this->orders($this->csv([$this->row([23 => $cell])]));

        $this->assertSame($expected, $orders[0]['date']);
    }

    public function testGetOrders_emptyCreatedTime_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty date value');

        $this->orders($this->csv([$this->row([23 => ''])]));
    }

    public function testGetOrders_textColumns_areTrimmed(): void
    {
        $orders = $this->orders($this->csv([$this->row([
            0 => " 577000000000000002 \t",
            7 => "\tZz Mat\t",
            33 => ' 685000000002 ',
            35 => ' Pos Laju ',
            42 => " Johor\t",
        ])]));

        $this->assertSame('577000000000000002', $orders[0]['orderNum']);
        $this->assertSame('Zz Mat', $orders[0]['description']);
        $this->assertSame('685000000002', $orders[0]['trackingNum']);
        $this->assertSame('Pos Laju', $orders[0]['shippingProvider']);
        $this->assertSame('Johor', $orders[0]['shippingState']);
    }

    public function testGetOrders_sku_isNotTrimmed(): void
    {
        $orders = $this->orders($this->csv([$this->row([6 => ' ZZTEST-001 '])]));

        $this->assertSame(' ZZTEST-001 ', $orders[0]['sku']);
    }

    // ---------------------------------------------------------------- validation

    public function testGetOrders_nonNumericTrackingNumber_throwsInvalidTrackingNumber(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid tracking number: ZZ-123');

        $this->orders($this->csv([$this->row([33 => 'ZZ-123'])]));
    }

    public function testGetOrders_emptyTrackingNumber_isAccepted(): void
    {
        // orders not yet handed to the courier have no tracking id yet
        $orders = $this->orders($this->csv([$this->row([33 => '', 35 => ''])]));

        $this->assertSame('', $orders[0]['trackingNum']);
        $this->assertSame('', $orders[0]['shippingProvider']);
    }

    public function testGetOrders_orderIdWithSpecialCharacters_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid order number');

        $this->orders($this->csv([$this->row([0 => 'ZZ#1'])]));
    }

    public function testGetOrders_secondRowInvalid_abortsTheWholeFile(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid tracking number');

        $this->orders($this->csv([$this->row([]), $this->row([33 => 'ZZ-123'])]));
    }

    /** @return array<string, array<int, string>> */
    public function provideZeroQuantities(): array
    {
        return [
            'zero' => ['0'],
            'blank' => [''],
            'non numeric' => ['n/a'],
        ];
    }

    /**
     * @dataProvider provideZeroQuantities
     */
    public function testGetOrders_zeroQuantity_documentsCurrentBehaviour_dividesByZero(string $quantity): void
    {
        // TikTok.php getOrders(): `$voucher = ... / $quantity` with intval() = 0 -> DivisionByZeroError on PHP 8,
        // "Division by zero" warning on PHP 7 (where the row would then be silently skipped by the for loop)
        $path = $this->csv([$this->row([9 => $quantity])]);

        $e = PhpErrors::capture(function () use ($path) {
            $this->orders($path);
        });

        if (PHP_VERSION_ID >= 80000) {
            $this->assertInstanceOf(\DivisionByZeroError::class, $e);
        } else {
            $this->assertInstanceOf(\ErrorException::class, $e);
        }
        $this->assertSame('Division by zero', $e->getMessage());
    }

    /**
     * @group known-bug
     * @dataProvider provideZeroQuantities
     */
    public function testGetOrders_zeroQuantity_throwsAClearException(string $quantity): void
    {
        // TikTok.php getOrders(): a row whose Quantity column is 0 / blank must be rejected with a message that
        // names the problem (like BigSellerOrderProcess does), not crash with a division by zero
        $path = $this->csv([$this->row([9 => $quantity])]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/quantity/i');

        $this->orders($path);
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
        array_unshift($dataRows, CsvFixture::row(self::WIDTH, [0 => 'Order ID', 9 => 'Quantity']));

        return CsvFixture::write($dataRows);
    }

    /** @return array<int, array<string, mixed>> */
    private function orders(string $path): array
    {
        return (new TikTokOrder(UnconnectedMysqli::create(), $path))->getOrders();
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
            $sum += (int) $row[9];
        }
        fclose($fh);

        return $sum;
    }
}
