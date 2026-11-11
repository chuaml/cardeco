<?php

declare(strict_types=1);

namespace test\Integration\Orders\Factory;

use InvalidArgumentException;
use mysqli_sql_exception;
use Orders\Factory\Cashsale;
use Orders\Factory\CashsaleText;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Orders\Factory\Cashsale (query `orders` by dateStockOut) and of the CashsaleText lines it
 * builds for the SQL Accounting cash-sale text import (legacy_pages/request_handler/Cashsale.php).
 */
final class CashsaleFactoryTest extends DatabaseTestCase
{
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

    /** @return array<string, mixed> a row shaped like the factory's SELECT (Cashsale.php:15-36) */
    private function queryRow(array $overrides = []): array
    {
        return array_merge([
            'orderNum' => $this->orderNum('O1'),
            'date' => '2024-03-01',
            'sku' => $this->sku('A'),
            'sellingPrice' => '10.00',
            'SUM(orders.shippingFee)' => '2.40',
            'SUM(orders.shippingFeeByCust)' => '0.00',
            'SUM(voucher)' => '0.00',
            'platformCharges' => 'Lazada',
            'SUM(platformChargesAmount)' => '1.20',
            'SUM(bankIn)' => '0.00',
            'SUM(cash)' => '0.00',
            'quantity' => 2,
            'item_code' => $this->sku('A'),
            'description' => 'ZZTEST desc A',
            'uom' => 'PCS',
            'shippingState' => 'Selangor-',
            'SUM(shippingWeight)' => '1.00',
            'trackingNum' => 'ZZTEST-T1',
        ], $overrides);
    }

    /** @return array<int, array<int, string>> getData() split into lines, each split into its 45 ';' columns */
    private function linesOf(CashsaleText $text): array
    {
        $lines = [];
        foreach (explode("\r\n", $text->getData()) as $line) {
            $lines[] = explode(';', $line);
        }

        return $lines;
    }

    // ------------------------------------------------------------------ Cashsale (DB)

    public function testGenerateRecords_BeforeAnyQuery_ReturnsEmptyArray(): void
    {
        $this->assertSame([], (new Cashsale($this->con))->generateRecords());
    }

    public function testSetMonthlyRecordByDateStockOut_InvalidDate_ThrowsInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Cashsale($this->con))->setMonthlyRecordByDateStockOut('not a date');
    }

    /**
     * Cashsale.php setMonthlyRecordByDateStockOut()/checkSku(): valid under MySQL 8 ONLY_FULL_GROUP_BY
     * (non-grouped columns wrapped in ANY_VALUE()) - the /Cashsale export works on the Docker stack.
     */
    public function testSetMonthlyRecordByDateStockOut_BuildsOneCashsaleTextPerOrderNum(): void
    {
        $this->insertStockItem($this->sku('A'), 'ZZTEST desc A', 5, 'PCS');
        $common = [
            'orderNum' => $this->orderNum('O1'),
            'date' => '2024-03-01',
            'dateStockOut' => '2024-03-03',
            'sku' => $this->sku('A'),
            'trackingNum' => 'ZZTEST-T1',
            'sellingPrice' => 10.0,
            'shippingFee' => 2.4,
            'platformCharges' => 'Shopee',
        ];
        $this->insertOrder($common);
        $this->insertOrder($common);
        $this->insertOrder(array_merge($common, ['orderNum' => $this->orderNum('O2'), 'dateStockOut' => '2024-03-04']));
        $f = new Cashsale($this->con);

        $f->setMonthlyRecordByDateStockOut('2024-03-03');
        $list = $f->generateRecords();

        $this->assertArrayHasKey($this->orderNum('O1'), $list);
        $this->assertArrayNotHasKey($this->orderNum('O2'), $list);
        $lines = $this->linesOf($list[$this->orderNum('O1')]);
        $this->assertCount(3, $lines, 'MASTER + item DETAIL + SHIPPING DETAIL');
        $this->assertSame(['MASTER', '<<NEW>>', '', '01-03-2024', '01-03-2024', '300-C0008', 'CASH A/C - SHOPEE (CAR DECO)'], array_slice($lines[0], 0, 7));
        $this->assertSame($this->orderNum('O1'), $lines[0][25]);
        $this->assertSame('24.8', $lines[0][42], '2 x 10.00 + 2 x 2.40 shipping');
        $this->assertSame('DETAIL', $lines[1][0]);
        $this->assertSame($this->sku('A'), $lines[1][3]);
        $this->assertSame('2', $lines[1][9]);
        $this->assertSame('PCS', $lines[1][10]);
        $this->assertSame('20', $lines[1][17]);
        $this->assertSame('SHIPPING', $lines[2][3]);
        $this->assertSame('4.8', $lines[2][17]);
    }

    // ------------------------------------------------------------------ CashsaleText (pure)

    public function testCashsaleText_MasterLine_CarriesPlatformCustomerCodesDatesAndOrderNum(): void
    {
        $text = new CashsaleText($this->queryRow());

        $lines = $this->linesOf($text);
        $this->assertCount(1, $lines);
        $master = $lines[0];
        $this->assertCount(CashsaleText::NUM_COL, $master);
        $this->assertSame('MASTER', $master[0]);
        $this->assertSame('<<NEW>>', $master[1]);
        $this->assertSame('01-03-2024', $master[3], 'orders.date as d-m-Y');
        $this->assertSame('01-03-2024', $master[4]);
        $this->assertSame('300-C0006', $master[5], 'Lazada customer code');
        $this->assertSame('CASH A/C - LAZADA (CAR DECO)', $master[6]);
        $this->assertSame(['----', '----', '----', 'C.O.D', '1'], array_slice($master, 14, 5));
        $this->assertSame(['F', '1'], array_slice($master, 20, 2));
        $this->assertSame($this->orderNum('O1'), $master[25]);
        $this->assertSame('BILLING', $master[29]);
        $this->assertSame(['T', '0', '321-000'], array_slice($master, 37, 3), 'Lazada payment account');
        $this->assertSame('0', $master[42], 'total price is 0 until setTotalPrice()');
        $this->assertSame('----', $master[43]);
    }

    /**
     * Cashsale.php:198 writes `(double) $r['platformCharges']` - the platform NAME cast to a number - into
     * column 41, so the platform charge amount never reaches the import file.
     */
    public function testCashsaleText_MasterColumn41_IsAlwaysZero_documentsCurrentBehaviour(): void
    {
        $text = new CashsaleText($this->queryRow(['SUM(platformChargesAmount)' => '3.45']));

        $this->assertSame('0', $this->linesOf($text)[0][41]);
    }

    public function testCashsaleText_SetDetail_AppendsAnItemLineAndAShippingLine(): void
    {
        $text = new CashsaleText($this->queryRow());

        $row = $this->queryRow();
        $text->setDetail($row);

        $lines = $this->linesOf($text);
        $this->assertCount(3, $lines);
        $item = $lines[1];
        $this->assertCount(CashsaleText::NUM_COL, $item);
        $this->assertSame(['DETAIL', '<<NEW>>', '', $this->sku('A'), '----', '----', 'ZZTEST desc A'], array_slice($item, 0, 7));
        $this->assertSame(['2', 'PCS', '0', '10', '01-03-2024'], array_slice($item, 9, 5), 'qty, uom, 0, unit price, date');
        $this->assertSame(['0', '20', 'T'], array_slice($item, 16, 3), '0, qty x price, T');
        $this->assertSame('T', $item[20]);
        $this->assertSame('0', $item[23]);

        $shipping = $lines[2];
        $this->assertSame(['SHIPPING', '----', '----', 'SHIPPING'], array_slice($shipping, 3, 4));
        $this->assertSame(['1', 'UNIT', '0', '2.4', '01-03-2024'], array_slice($shipping, 9, 5), 'shippingFee + shippingFeeByCust');
        $this->assertSame('2.4', $shipping[17]);
    }

    public function testCashsaleText_SetTotalPrice_SumsEveryDetailAmountIntoMasterColumn42(): void
    {
        $text = new CashsaleText($this->queryRow());
        $rowA = $this->queryRow();
        $rowB = $this->queryRow(['item_code' => $this->sku('B'), 'sellingPrice' => '5.50', 'quantity' => 1, 'SUM(orders.shippingFee)' => '0.00', 'SUM(orders.shippingFeeByCust)' => '1.00']);
        $text->setDetail($rowA);
        $text->setDetail($rowB);

        $text->setTotalPrice();

        $lines = $this->linesOf($text);
        $this->assertCount(5, $lines);
        $this->assertSame('28.9', $lines[0][42], '20 + 2.4 + 5.5 + 1.0');
    }

    /**
     * CashsaleText::setMaster() resolves the customer codes through PlatformCharges::getPlatformCharges()
     * (Cashsale.php:177); a value no subclass claims stops the export.
     */
    public function testCashsaleText_UnknownPlatform_ThrowsInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No such PlatformCharges: ZZTEST-Bogus');
        new CashsaleText($this->queryRow(['platformCharges' => 'ZZTEST-Bogus']));
    }

    public function testCashsaleText_EplusPlatform_UsesTheEplusCustomerCodes(): void
    {
        $text = new CashsaleText($this->queryRow(['platformCharges' => 'Lazada_Eplus']));

        $master = $this->linesOf($text)[0];
        $this->assertSame('300-C0010', $master[5]);
        $this->assertSame('CASH A/C - LAZADA (E PLUS)', $master[6]);
        $this->assertSame('321-100', $master[39]);
    }
}
