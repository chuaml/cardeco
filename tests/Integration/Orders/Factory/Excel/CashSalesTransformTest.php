<?php

declare(strict_types=1);

namespace test\Integration\Orders\Factory\Excel;

use Exception;
use InvalidArgumentException;
use Orders\Factory\Excel\CashSales;
use test\Support\DatabaseTestCase;
use test\Support\UnconnectedMysqli;

/**
 * Characterization of Orders\Factory\Excel\CashSales::transformToCashSales() - monthly cash-sales workbook rows
 * (as produced by ExcelReader::read(): upper-cased header names, every cell a trimmed string) -> SQL Accounting
 * import entries. Rows are built in memory; the only DB access is the stock_items UOM lookup, which is also
 * exercised with the injected `$uomByItemCode` map and an unconnected mysqli.
 */
final class CashSalesTransformTest extends DatabaseTestCase
{
    const SQL_IMPORT_COLUMNS = [
        'DocDate', 'DocNo(20)', 'Code(10)', 'CompanyName(100)', 'Agent(10)', 'TERMS(10)', 'Description_HDR(200)',
        'SEQ', 'ItemCode(30)', 'Description_DTL(200)', 'Qty', 'UOM', 'UnitPrice', 'DISC(20)', 'Tax(10)',
        'TaxInclusive', 'TaxAmt', 'Amount', 'P_AMOUNT', 'P_PAYMENTMETHOD', 'P_BANKCHARGE', 'DOCREF1',
    ];

    /** @var string */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    private function orderNum(string $tag): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $tag;
    }

    private function sku(string $tag): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $tag;
    }

    /** @return array<string, string> one workbook row with every header the transformation reads */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'DATE' => '2024-01-15',
            'SQL NO' => '',
            'ORDER NUMBER' => $this->orderNum('O1'),
            'SELLER SKU' => $this->sku('A'),
            'DESCRIPTION' => 'ZZTEST item A',
            'SELLING PRICE' => '10.00',
            'COURIER PAY BY CUSTOMER' => '0',
            'SELLER VOUCHER' => '0',
            'PLATFORM FEE CHARGES' => '0',
            'TRACKING NUMBER' => 'ZZTEST-T1',
        ], $overrides);
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function transform(array $rows, string $platform = 'Lazada', ?array $uomByItemCode = null): array
    {
        return CashSales::transformToCashSales($this->con, $platform, $rows, $uomByItemCode);
    }

    // ------------------------------------------------------------------ shape

    public function testTransform_EmptyList_ReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->transform([]));
    }

    public function testTransform_EntryKeysFollowTheSqlImportColumnOrder(): void
    {
        $entries = $this->transform([$this->row()]);

        $this->assertCount(1, $entries);
        $this->assertSame(self::SQL_IMPORT_COLUMNS, array_keys($entries[0]));
    }

    public function testTransform_SingleRow_ProducesOneFullyPopulatedEntry(): void
    {
        $this->insertStockItem($this->sku('A'), 'ZZTEST item A', 5, 'PCS');
        $rows = [$this->row(['SQL NO' => 'ZZTEST-CS1', 'SELLER VOUCHER' => '1.50', 'PLATFORM FEE CHARGES' => '0.75'])];

        $entries = $this->transform($rows);

        $this->assertSame([
            'DocDate' => '15-01-24',
            'DocNo(20)' => 'ZZTEST-CS1',
            'Code(10)' => '300-C0006',
            'CompanyName(100)' => 'CASH A/C - LAZADA (CAR DECO)',
            'Agent(10)' => '----',
            'TERMS(10)' => 'C.O.D.',
            'Description_HDR(200)' => 'Cash Sales',
            'SEQ' => 1,
            'ItemCode(30)' => $this->sku('A'),
            'Description_DTL(200)' => 'ZZTEST item A',
            'Qty' => 1,
            'UOM' => 'PCS',
            'UnitPrice' => 10.0,
            'DISC(20)' => 1.5,
            'Tax(10)' => '',
            'TaxInclusive' => 0.0,
            'TaxAmt' => 0.0,
            'Amount' => 8.5,
            'P_AMOUNT' => 8.5,
            'P_PAYMENTMETHOD' => '321-000',
            'P_BANKCHARGE' => 0.75,
            'DOCREF1' => $this->orderNum('O1'),
        ], $entries[0]);
    }

    // ------------------------------------------------------------------ fill-down, grouping, shipping

    public function testTransform_BlankDateSqlNoOrderNumberAndTrackingCells_AreFilledDownFromTheRowAbove(): void
    {
        $rows = [
            $this->row(['SQL NO' => 'ZZTEST-CS1', 'SELLER SKU' => $this->sku('A')]),
            $this->row(['DATE' => '', 'SQL NO' => '', 'ORDER NUMBER' => '', 'TRACKING NUMBER' => '', 'SELLER SKU' => $this->sku('B')]),
            $this->row(['DATE' => '', 'SQL NO' => '', 'ORDER NUMBER' => '', 'TRACKING NUMBER' => '', 'SELLER SKU' => $this->sku('C')]),
        ];

        $entries = $this->transform($rows);

        $this->assertCount(3, $entries);
        $this->assertSame([1, 2, 3], array_column($entries, 'SEQ'), 'one order, three lines');
        $this->assertSame(['ZZTEST-CS1', 'ZZTEST-CS1', 'ZZTEST-CS1'], array_column($entries, 'DocNo(20)'));
        $this->assertSame(['15-01-24', '15-01-24', '15-01-24'], array_column($entries, 'DocDate'));
        $this->assertSame([$this->orderNum('O1'), $this->orderNum('O1'), $this->orderNum('O1')], array_column($entries, 'DOCREF1'));
    }

    public function testTransform_BlankSellerSkuCell_IsFilledDownToo(): void
    {
        $rows = [
            $this->row(['SELLER SKU' => $this->sku('A'), 'SELLING PRICE' => '10.00']),
            $this->row(['SELLER SKU' => '', 'SELLING PRICE' => '10.00']),
        ];

        $entries = $this->transform($rows);

        $this->assertCount(1, $entries, 'the blank sku inherits sku A and is grouped with it');
        $this->assertSame(2, $entries[0]['Qty']);
    }

    public function testTransform_SameSkuAndPriceInOneOrder_IsSummedIntoQtyVoucherAndPlatformFee(): void
    {
        $rows = [
            $this->row(['SELLER VOUCHER' => '1', 'PLATFORM FEE CHARGES' => '0.5']),
            $this->row(['SELLER VOUCHER' => '2', 'PLATFORM FEE CHARGES' => '0.5']),
            $this->row(['SELLER VOUCHER' => '0', 'PLATFORM FEE CHARGES' => '0.5']),
        ];

        $entries = $this->transform($rows);

        $this->assertCount(1, $entries);
        $e = $entries[0];
        $this->assertSame(3, $e['Qty']);
        $this->assertSame(10.0, $e['UnitPrice']);
        $this->assertSame(3.0, $e['DISC(20)']);
        $this->assertSame(27.0, $e['Amount'], '3 x 10.00 - 3.00 voucher');
        $this->assertSame(27.0, $e['P_AMOUNT']);
        $this->assertSame(1.5, $e['P_BANKCHARGE']);
    }

    public function testTransform_SameSkuWithDifferentPrices_StaysOnSeparateLines(): void
    {
        $rows = [
            $this->row(['SELLING PRICE' => '10.00']),
            $this->row(['SELLING PRICE' => '12.00']),
            $this->row(['SELLING PRICE' => '10.00']),
        ];

        $entries = $this->transform($rows);

        $this->assertCount(2, $entries);
        $this->assertSame([1, 2], array_column($entries, 'SEQ'));
        $this->assertSame([10.0, 12.0], array_column($entries, 'UnitPrice'));
        $this->assertSame([2, 1], array_column($entries, 'Qty'));
    }

    public function testTransform_CourierPayByCustomer_AppendsAShippingFeeLineAfterTheItemLines(): void
    {
        $rows = [
            $this->row(['SELLER SKU' => $this->sku('A'), 'COURIER PAY BY CUSTOMER' => '5.90']),
            $this->row(['SELLER SKU' => $this->sku('B'), 'SELLING PRICE' => '20.00', 'COURIER PAY BY CUSTOMER' => '0']),
        ];

        $entries = $this->transform($rows);

        $this->assertCount(3, $entries);
        $this->assertSame([$this->sku('A'), $this->sku('B'), 'SHIPPING'], array_column($entries, 'ItemCode(30)'));
        $shipping = $entries[2];
        $this->assertSame('SHIPPING FEE', $shipping['Description_DTL(200)']);
        $this->assertSame(3, $shipping['SEQ']);
        $this->assertSame(1, $shipping['Qty']);
        $this->assertSame('UNIT', $shipping['UOM']);
        $this->assertSame(5.9, $shipping['UnitPrice']);
        $this->assertSame(0.0, $shipping['DISC(20)']);
        $this->assertSame(5.9, $shipping['Amount']);
        $this->assertNull($shipping['P_AMOUNT']);
        $this->assertNull($shipping['P_BANKCHARGE']);
        $this->assertSame('15-01-24', $shipping['DocDate']);
        $this->assertSame($this->orderNum('O1'), $shipping['DOCREF1']);
        $this->assertEqualsWithDelta(35.9, $entries[0]['P_AMOUNT'], 0.0001, 'order total includes the shipping line');
    }

    /**
     * The courier fee is one of the summed columns (CashSales.php:113), so a fee repeated on every unit line of a
     * grouped item is added up before the SHIPPING line is created (:177).
     */
    public function testTransform_CourierFeeRepeatedOnGroupedLines_IsSummedIntoTheShippingLine_documentsCurrentBehaviour(): void
    {
        $rows = [
            $this->row(['COURIER PAY BY CUSTOMER' => '5']),
            $this->row(['COURIER PAY BY CUSTOMER' => '5']),
        ];

        $entries = $this->transform($rows);

        $this->assertCount(2, $entries);
        $this->assertSame('SHIPPING', $entries[1]['ItemCode(30)']);
        $this->assertSame(10.0, $entries[1]['UnitPrice']);
    }

    public function testTransform_OrderTotalsAreWrittenOnTheFirstLineOfEachOrderOnly(): void
    {
        $rows = [
            $this->row(['ORDER NUMBER' => $this->orderNum('O1'), 'SELLER SKU' => $this->sku('A'), 'SELLING PRICE' => '10.00', 'PLATFORM FEE CHARGES' => '1.00']),
            $this->row(['ORDER NUMBER' => $this->orderNum('O1'), 'SELLER SKU' => $this->sku('B'), 'SELLING PRICE' => '20.00', 'PLATFORM FEE CHARGES' => '0.50']),
            $this->row(['ORDER NUMBER' => $this->orderNum('O2'), 'SELLER SKU' => $this->sku('C'), 'SELLING PRICE' => '30.00', 'PLATFORM FEE CHARGES' => '0']),
        ];

        $entries = $this->transform($rows);

        $this->assertCount(3, $entries);
        $this->assertSame([1, 2, 1], array_column($entries, 'SEQ'), 'SEQ restarts for every order');
        $this->assertSame(30.0, $entries[0]['P_AMOUNT']);
        $this->assertSame(1.5, $entries[0]['P_BANKCHARGE']);
        $this->assertNull($entries[1]['P_AMOUNT']);
        $this->assertNull($entries[1]['P_BANKCHARGE']);
        $this->assertSame(30.0, $entries[2]['P_AMOUNT']);
        $this->assertSame(0.0, $entries[2]['P_BANKCHARGE']);
    }

    public function testTransform_RowsOfOneOrderSplitByAnotherOrder_AreStillGroupedTogether(): void
    {
        $rows = [
            $this->row(['ORDER NUMBER' => $this->orderNum('O1'), 'SELLER SKU' => $this->sku('A')]),
            $this->row(['ORDER NUMBER' => $this->orderNum('O2'), 'SELLER SKU' => $this->sku('B')]),
            $this->row(['ORDER NUMBER' => $this->orderNum('O1'), 'SELLER SKU' => $this->sku('C')]),
        ];

        $entries = $this->transform($rows);

        $this->assertSame([$this->orderNum('O1'), $this->orderNum('O1'), $this->orderNum('O2')], array_column($entries, 'DOCREF1'));
        $this->assertSame([1, 2, 1], array_column($entries, 'SEQ'));
    }

    // ------------------------------------------------------------------ header cells

    /** @return array<string, array<int, string>> */
    public function docDateProvider(): array
    {
        return [
            'ISO date' => ['2024-01-15', '15-01-24'],
            'ISO date with zero-padded day' => ['2024-12-05', '05-12-24'],
            'Record::DATE_FORMAT' => ['15-Jan-2024', '15-01-24'],
        ];
    }

    /**
     * @dataProvider docDateProvider
     */
    public function testTransform_DocDate_IsFormattedAsDayMonthTwoDigitYear(string $date, string $expected): void
    {
        $entries = $this->transform([$this->row(['DATE' => $date])]);

        $this->assertSame($expected, $entries[0]['DocDate']);
    }

    public function testTransform_UnparsableDate_Throws(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid date: not a date');
        $this->transform([$this->row(['DATE' => 'not a date'])]);
    }

    public function testTransform_BlankSqlNo_BecomesTheNewDocumentPlaceholder(): void
    {
        $rows = [
            $this->row(['ORDER NUMBER' => $this->orderNum('O1'), 'SQL NO' => '']),
            $this->row(['ORDER NUMBER' => $this->orderNum('O2'), 'SQL NO' => 'ZZTEST-CS2']),
        ];

        $entries = $this->transform($rows);

        $this->assertSame(['<<NEW>>', 'ZZTEST-CS2'], array_column($entries, 'DocNo(20)'));
    }

    // ------------------------------------------------------------------ UOM lookup

    public function testTransform_UomComesFromStockItemsAndIsNullForUnknownItemCodes(): void
    {
        $this->insertStockItem($this->sku('A'), 'ZZTEST item A', 5, 'PCS');
        $rows = [
            $this->row(['SELLER SKU' => $this->sku('A')]),
            $this->row(['SELLER SKU' => $this->sku('UNKNOWN')]),
        ];

        $entries = $this->transform($rows);

        $this->assertSame(['PCS', null], array_column($entries, 'UOM'));
    }

    public function testTransform_InjectedUomMap_IsUsedWithoutTouchingTheDatabase(): void
    {
        $rows = [
            $this->row(['SELLER SKU' => $this->sku('A')]),
            $this->row(['SELLER SKU' => $this->sku('B')]),
        ];
        $uomByItemCode = [$this->sku('A') => 'BOX'];

        $entries = CashSales::transformToCashSales(UnconnectedMysqli::create(), 'Lazada', $rows, $uomByItemCode);

        $this->assertSame(['BOX', null], array_column($entries, 'UOM'));
    }

    // ------------------------------------------------------------------ payment type

    /** @return array<string, array<int, string>> */
    public function platformHeaderProvider(): array
    {
        return [
            'Lazada' => ['Lazada', '300-C0006', 'CASH A/C - LAZADA (CAR DECO)', '321-000'],
            'Shopee' => ['Shopee', '300-C0008', 'CASH A/C - SHOPEE (CAR DECO)', '324-000'],
            'TikTok_Eplus' => ['TikTok_Eplus', '300-C0013', 'CASH A/C - TIKTOK (E PLUS)', '325-000'],
            'Cash Bil' => ['Cash Bil', '300-C0004', 'CASH A/C', '320-000'],
        ];
    }

    /**
     * @dataProvider platformHeaderProvider
     */
    public function testTransform_HeaderConstantsFollowThePaymentChargesName(string $platform, string $code, string $company, string $paymentInto): void
    {
        $entries = $this->transform([$this->row()], $platform);

        $this->assertSame($code, $entries[0]['Code(10)']);
        $this->assertSame($company, $entries[0]['CompanyName(100)']);
        $this->assertSame($paymentInto, $entries[0]['P_PAYMENTMETHOD']);
    }

    public function testTransform_UnknownPaymentChargesName_ThrowsInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no PaymentCharges class for: ZZTEST-Nope');
        $this->transform([$this->row()], 'ZZTEST-Nope');
    }

    public function testTransform_BankTransferCell_SwitchesToBankInForThatLineAndTheFollowingOnesUntilACashCell(): void
    {
        $rows = [
            $this->row(['SELLER SKU' => $this->sku('A'), 'CASH' => '', 'BANK TRANSFER' => '']),
            $this->row(['SELLER SKU' => $this->sku('B'), 'CASH' => '', 'BANK TRANSFER' => 'RM 50.00']),
            $this->row(['SELLER SKU' => $this->sku('C'), 'CASH' => '', 'BANK TRANSFER' => '']),
            $this->row(['SELLER SKU' => $this->sku('D'), 'CASH' => '20', 'BANK TRANSFER' => '']),
        ];

        $entries = $this->transform($rows, 'Lazada');

        $this->assertSame(['300-C0006', '300-C0004', '300-C0004', '300-C0006'], array_column($entries, 'Code(10)'));
        $this->assertSame(['CASH A/C - LAZADA (CAR DECO)', 'CASH A/C', 'CASH A/C', 'CASH A/C - LAZADA (CAR DECO)'], array_column($entries, 'CompanyName(100)'));
        $this->assertSame(['321-000', '310-000', '310-000', '321-000'], array_column($entries, 'P_PAYMENTMETHOD'));
    }

    public function testTransform_RowWithoutASummedColumn_ThrowsInvalidArgument(): void
    {
        $row = $this->row();
        unset($row['PLATFORM FEE CHARGES']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('undifined index key: PLATFORM FEE CHARGES');
        $this->transform([$row]);
    }
}
