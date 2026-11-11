<?php

declare(strict_types=1);

namespace test\Unit\Orders\Factory;

use InvalidArgumentException;
use Orders\Factory\CashsaleText;
use PHPUnit\Framework\TestCase;
use test\Unit\Support\PhpErrors;

require_once __DIR__ . '/../../Support/PhpErrors.php';

/**
 * Characterization of Orders\Factory\CashsaleText (second class in src/Legacy/Orders/Factory/Cashsale.php):
 * one cash sale (all `orders` rows of an order number) -> the ';'-delimited MASTER/DETAIL text that
 * /Cashsale writes for SQL Accounting import. Rows have the shape returned by Cashsale::setMonthlyRecordByDateStockOut().
 */
final class CashsaleTextTest extends TestCase
{
    const MASTER_LAZADA =
        'MASTER;<<NEW>>;;15-01-2024;15-01-2024;300-C0006;CASH A/C - LAZADA (CAR DECO);;;;;;;;----;----;----;C.O.D;1;;F;1;;;;'
        . 'ZZ240101ABC;;;;BILLING;;;;;;;;T;0;321-000;;0;%s;----;';

    const DETAIL_ITEM = 'DETAIL;<<NEW>>;;ZZTEST-001;----;----;Zz Boot Tray;;;2;PCS;0;41.5;15-01-2024;;;0;83;T;;T;;;0;;;;;;;;;;;;;;;;;;;;;';
    const DETAIL_SHIPPING = 'DETAIL;<<NEW>>;;SHIPPING;----;----;SHIPPING;;;1;UNIT;0;4.9;15-01-2024;;;0;4.9;T;;T;;;0;;;;;;;;;;;;;;;;;;;;;';

    // ---------------------------------------------------------------- MASTER line

    public function testConstructor_lazadaRow_buildsTheMasterLineOnly(): void
    {
        $text = new CashsaleText($this->row());

        $this->assertSame(sprintf(self::MASTER_LAZADA, '0'), $text->getData());
    }

    public function testGetData_masterLine_hasNumColColumns(): void
    {
        $this->assertSame(45, CashsaleText::NUM_COL);

        $columns = explode(';', (new CashsaleText($this->row()))->getData());

        $this->assertCount(45, $columns);
    }

    public function testGetData_masterColumns_documentedPositions(): void
    {
        $c = explode(';', (new CashsaleText($this->row()))->getData());

        $this->assertSame('MASTER', $c[0]);
        $this->assertSame('<<NEW>>', $c[1]);
        $this->assertSame('15-01-2024', $c[3], 'document date, d-m-Y');
        $this->assertSame('15-01-2024', $c[4], 'posting date');
        $this->assertSame('300-C0006', $c[5], 'customer id of the platform');
        $this->assertSame('CASH A/C - LAZADA (CAR DECO)', $c[6]);
        $this->assertSame(['----', '----', '----'], [$c[14], $c[15], $c[16]]);
        $this->assertSame('C.O.D', $c[17]);
        $this->assertSame('1', $c[18]);
        $this->assertSame('F', $c[20]);
        $this->assertSame('1', $c[21]);
        $this->assertSame('ZZ240101ABC', $c[25], 'order number');
        $this->assertSame('BILLING', $c[29]);
        $this->assertSame('T', $c[37]);
        $this->assertSame('0', $c[38]);
        $this->assertSame('321-000', $c[39], 'payment-into account of the platform');
        $this->assertSame('0', $c[41]);
        $this->assertSame('0', $c[42], 'total price before setTotalPrice()');
        $this->assertSame('----', $c[43]);
        $this->assertSame('', $c[44]);
    }

    public function testConstructor_shopeeRow_usesTheShopeeAccountCodes(): void
    {
        $c = explode(';', (new CashsaleText($this->row(['platformCharges' => 'Shopee'])))->getData());

        $this->assertSame('300-C0008', $c[5]);
        $this->assertSame('CASH A/C - SHOPEE (CAR DECO)', $c[6]);
        $this->assertSame('324-000', $c[39]);
    }

    public function testConstructor_dateWithTime_isReformattedToDayMonthYear(): void
    {
        $c = explode(';', (new CashsaleText($this->row(['date' => '2024-01-15 10:30:00'])))->getData());

        $this->assertSame('15-01-2024', $c[3]);
        $this->assertSame('15-01-2024', $c[4]);
    }

    public function testConstructor_column41_documentsCurrentBehaviour_isTheCastedPlatformNameNotTheChargesAmount(): void
    {
        // Cashsale.php setMaster(): `$col[41] = (double) $r['platformCharges']` casts the platform NAME -> always 0;
        // 'SUM(platformChargesAmount)' is never written anywhere
        $c = explode(';', (new CashsaleText($this->row(['SUM(platformChargesAmount)' => '12.34'])))->getData());

        $this->assertSame('0', $c[41]);
        $this->assertNotContains('12.34', $c);
    }

    public function testConstructor_invalidDate_documentsCurrentBehaviour_failsInsideDateFormat(): void
    {
        // date_create() returns false and is passed straight to date_format(): TypeError on PHP 8, warning on PHP 7
        $e = PhpErrors::capture(function () {
            new CashsaleText($this->row(['date' => 'not-a-date']));
        });

        if (PHP_VERSION_ID >= 80000) {
            $this->assertInstanceOf(\TypeError::class, $e);
        } else {
            $this->assertInstanceOf(\ErrorException::class, $e);
        }
        $this->assertStringContainsString('date_format()', $e->getMessage());
    }

    // ---------------------------------------------------------------- platform registry (PlatformCharges::getPlatformCharges)


    /** @return array<string, array<int, string>> */
    public function provideEplusAndPalingBestAccountCodes(): array
    {
        return [
            'Lazada_Eplus' => ['Lazada_Eplus', '300-C0010', '321-100'],
            'Shopee_Eplus' => ['Shopee_Eplus', '300-C0011', '324-100'],
            'TikTok_Eplus' => ['TikTok_Eplus', '300-C0013', '325-000'],
            'Lazada_Paling_Best' => ['Lazada_Paling_Best', '300-C0014', '321-200'],
        ];
    }

    /**
     * @dataProvider provideEplusAndPalingBestAccountCodes
     */
    public function testConstructor_eplusAndPalingBestPlatforms_useTheirOwnAccountCodes(string $platform, string $custId, string $paymentInto): void
    {
        // PlatformCharges.php:7-15 / Cashsale.php:177: orders stored with platformCharges = Lazada_Eplus etc.
        // (accepted by RecordInserter) must export with the matching PlatformCharges subclass
        $c = explode(';', (new CashsaleText($this->row(['platformCharges' => $platform])))->getData());

        $this->assertSame($custId, $c[5]);
        $this->assertSame($paymentInto, $c[39]);
    }

    // ---------------------------------------------------------------- DETAIL lines

    public function testSetDetail_oneItem_appendsAnItemLineAndAShippingLine(): void
    {
        $text = new CashsaleText($this->row());
        $row = $this->row();

        $text->setDetail($row);

        $this->assertSame([
            sprintf(self::MASTER_LAZADA, '0'),
            self::DETAIL_ITEM,
            self::DETAIL_SHIPPING,
        ], explode("\r\n", $text->getData()));
    }

    public function testSetDetail_itemLineColumns_documentedPositions(): void
    {
        $text = new CashsaleText($this->row());
        $row = $this->row();
        $text->setDetail($row);

        $c = explode(';', explode("\r\n", $text->getData())[1]);

        $this->assertCount(45, $c);
        $this->assertSame('DETAIL', $c[0]);
        $this->assertSame('<<NEW>>', $c[1]);
        $this->assertSame('ZZTEST-001', $c[3], 'item_code');
        $this->assertSame(['----', '----'], [$c[4], $c[5]]);
        $this->assertSame('Zz Boot Tray', $c[6], 'description');
        $this->assertSame('2', $c[9], 'quantity');
        $this->assertSame('PCS', $c[10], 'uom');
        $this->assertSame('0', $c[11]);
        $this->assertSame('41.5', $c[12], 'unit price');
        $this->assertSame('15-01-2024', $c[13]);
        $this->assertSame('0', $c[16]);
        $this->assertSame('83', $c[17], 'line total = price * quantity');
        $this->assertSame('T', $c[18]);
        $this->assertSame('T', $c[20]);
        $this->assertSame('0', $c[23]);
    }

    public function testSetDetail_shippingLine_sumsSellerAndCustomerShippingFeeAsOneUnit(): void
    {
        $text = new CashsaleText($this->row());
        $row = $this->row(['SUM(orders.shippingFee)' => '4.90', 'SUM(orders.shippingFeeByCust)' => '2.10']);
        $text->setDetail($row);

        $c = explode(';', explode("\r\n", $text->getData())[2]);

        $this->assertSame('SHIPPING', $c[3]);
        $this->assertSame('SHIPPING', $c[6]);
        $this->assertSame('1', $c[9]);
        $this->assertSame('UNIT', $c[10]);
        $this->assertSame('7', $c[12]);
        $this->assertSame('7', $c[17]);
        $this->assertSame('15-01-2024', $c[13], 'the other columns are copied from the item line');
    }

    public function testSetDetail_zeroShippingFee_documentsCurrentBehaviour_stillAppendsAShippingLine(): void
    {
        $text = new CashsaleText($this->row());
        $row = $this->row(['SUM(orders.shippingFee)' => '0', 'SUM(orders.shippingFeeByCust)' => '0']);
        $text->setDetail($row);

        $lines = explode("\r\n", $text->getData());

        $this->assertCount(3, $lines);
        $this->assertSame('DETAIL;<<NEW>>;;SHIPPING;----;----;SHIPPING;;;1;UNIT;0;0;15-01-2024;;;0;0;T;;T;;;0;;;;;;;;;;;;;;;;;;;;;', $lines[2]);
    }

    public function testSetDetail_twoItems_appendsTwoItemAndTwoShippingLinesInOrder(): void
    {
        $text = new CashsaleText($this->row());
        $first = $this->row();
        $second = $this->row(['item_code' => 'ZZTEST-002', 'description' => 'Zz Cover', 'sellingPrice' => '10', 'quantity' => '1']);
        $text->setDetail($first);
        $text->setDetail($second);

        $lines = explode("\r\n", $text->getData());

        $this->assertCount(5, $lines);
        $this->assertSame(['MASTER', 'DETAIL', 'DETAIL', 'DETAIL', 'DETAIL'], array_map(function (string $l) {
            return explode(';', $l)[0];
        }, $lines));
        $this->assertSame(['ZZTEST-001', 'SHIPPING', 'ZZTEST-002', 'SHIPPING'], array_map(function (string $l) {
            return explode(';', $l)[3];
        }, array_slice($lines, 1)));
    }

    public function testSetDetail_quantityAndPrice_areCoercedFromStrings(): void
    {
        $text = new CashsaleText($this->row());
        $row = $this->row(['sellingPrice' => '0.1', 'quantity' => '3']);
        $text->setDetail($row);

        $c = explode(';', explode("\r\n", $text->getData())[1]);

        $this->assertSame('3', $c[9]);
        $this->assertSame('0.1', $c[12]);
        $this->assertSame('0.3', $c[17], 'float formatting follows PHP string conversion, not 2 decimals');
    }

    // ---------------------------------------------------------------- setTotalPrice

    public function testSetTotalPrice_sumsEveryDetailLineIncludingShipping(): void
    {
        $text = new CashsaleText($this->row());
        $row = $this->row();
        $text->setDetail($row);

        $text->setTotalPrice();

        $lines = explode("\r\n", $text->getData());
        $this->assertSame(sprintf(self::MASTER_LAZADA, '87.9'), $lines[0], '83 (item) + 4.9 (shipping)');
    }

    public function testSetTotalPrice_twoItems_sumsAllFourLines(): void
    {
        $text = new CashsaleText($this->row());
        $first = $this->row();
        $second = $this->row(['sellingPrice' => '10', 'quantity' => '1', 'SUM(orders.shippingFee)' => '0', 'SUM(orders.shippingFeeByCust)' => '2.5']);
        $text->setDetail($first);
        $text->setDetail($second);

        $text->setTotalPrice();

        $c = explode(';', explode("\r\n", $text->getData())[0]);
        $this->assertSame('100.4', $c[42], '83 + 4.9 + 10 + 2.5');
    }

    public function testSetTotalPrice_withoutDetails_isZero(): void
    {
        $text = new CashsaleText($this->row());

        $text->setTotalPrice();

        $this->assertSame(sprintf(self::MASTER_LAZADA, '0'), $text->getData());
    }

    public function testGetData_linesAreJoinedWithCrLfAndNoTrailingNewline(): void
    {
        $text = new CashsaleText($this->row());
        $row = $this->row();
        $text->setDetail($row);

        $data = $text->getData();

        $this->assertSame(2, substr_count($data, "\r\n"));
        $this->assertStringEndsWith(';', $data);
        $this->assertStringNotContainsString("\n\n", $data);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * One grouped `orders` row as selected by Cashsale::setMonthlyRecordByDateStockOut() (keys = Cashsale::FIELDS).
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'orderNum' => 'ZZ240101ABC',
            'date' => '2024-01-15',
            'sku' => 'ZZTEST-001',
            'sellingPrice' => '41.50',
            'SUM(orders.shippingFee)' => '4.90',
            'SUM(orders.shippingFeeByCust)' => '0.00',
            'SUM(voucher)' => '0.00',
            'platformCharges' => 'Lazada',
            'SUM(platformChargesAmount)' => '3.20',
            'SUM(bankIn)' => '0.00',
            'SUM(cash)' => '0.00',
            'quantity' => 2,
            'item_code' => 'ZZTEST-001',
            'description' => 'Zz Boot Tray',
            'uom' => 'PCS',
            'shippingState' => 'Johor',
            'SUM(shippingWeight)' => '3.00',
            'trackingNum' => 'ZZTRK001',
        ];
    }
}
