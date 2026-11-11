<?php

declare(strict_types=1);

namespace test\Integration\Lazada\Manager;

use Lazada\FeeStatement;
use Lazada\Manager\Fee_StatementsManager;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Lazada\Manager\Fee_StatementsManager (src/Legacy/Lazada/Manager/Fee_StatementsManager.php).
 * Rows are keyed by `user_ip` (the uploader's REMOTE_ADDR acts as the session key), so every test uses its own
 * ZZTEST- ip and only ever reads its own rows.
 */
final class FeeStatementsManagerTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;
    /** @var string */
    private $ip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $this->ip = self::FIXTURE_PREFIX . 'IP-' . $this->suffix;
    }

    private function orderNum(string $n): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $n;
    }

    private function statement(string $orderNum, string $feeName, float $amount, string $feeType = 'Orders-Item Charges'): FeeStatement
    {
        $s = new FeeStatement();
        $s->setTransactionDate('06 Aug 2018');
        $s->setFeeType($feeType);
        $s->setFeeName($feeName);
        $s->setDescription('  Chrome Door Handle  ');
        $s->setItemCode('EX-0647-2');
        $s->setlzdSku('OE702OTAA9NU9ZANMY-20599700');
        $s->setAmount($amount);
        $s->setStatementDate('06 Aug 2018 - 12 Aug 2018');
        $s->setPaidStatus('Paid');
        $s->setOrderNum($orderNum);
        $s->setItemStatus('Delivered');

        return $s;
    }

    /** @return array<int, array<string, mixed>> */
    private function rowsOfIp(string $ip): array
    {
        return $this->fetchAll(
            'SELECT tdate, fee_type, fee_name, description, item_code, lzd_sku, amount, sdate, paid_status, order_num, item_status, user_ip '
            . 'FROM lzd_fee_statements WHERE user_ip = ? ORDER BY id',
            's',
            $ip
        );
    }

    // ------------------------------------------------------------------ insertRecords

    public function testInsertRecords_StoresEveryStatementColumnForTheGivenIp(): void
    {
        Fee_StatementsManager::insertRecords($this->con, [
            $this->statement($this->orderNum('1'), Fee_StatementsManager::FEE_NAME_ITEM_PRICE, 82.0),
            $this->statement($this->orderNum('1'), Fee_StatementsManager::FEE_NAME_PLATFORM_CHARGES, -1.64, 'Orders-Lazada Fees'),
        ], $this->ip);

        $this->assertSame(
            [
                [
                    'tdate' => '2018-Aug-06',
                    'fee_type' => 'Orders-Item Charges',
                    'fee_name' => 'Item Price Credit',
                    'description' => 'Chrome Door Handle',
                    'item_code' => 'EX-0647-2',
                    'lzd_sku' => 'OE702OTAA9NU9ZANMY-20599700',
                    'amount' => '82.00',
                    'sdate' => '06 Aug 2018 - 12 Aug 2018',
                    'paid_status' => 'Paid',
                    'order_num' => $this->orderNum('1'),
                    'item_status' => 'Delivered',
                    'user_ip' => $this->ip,
                ],
                [
                    'tdate' => '2018-Aug-06',
                    'fee_type' => 'Orders-Lazada Fees',
                    'fee_name' => 'Payment Fee',
                    'description' => 'Chrome Door Handle',
                    'item_code' => 'EX-0647-2',
                    'lzd_sku' => 'OE702OTAA9NU9ZANMY-20599700',
                    'amount' => '-1.64',
                    'sdate' => '06 Aug 2018 - 12 Aug 2018',
                    'paid_status' => 'Paid',
                    'order_num' => $this->orderNum('1'),
                    'item_status' => 'Delivered',
                    'user_ip' => $this->ip,
                ],
            ],
            $this->rowsOfIp($this->ip),
            'setters trim(); the transaction date is stored as Y-M-d with a TEXTUAL month (FeeStatement.php:59)'
        );
    }

    public function testInsertRecords_EmptyList_InsertsNothing(): void
    {
        Fee_StatementsManager::insertRecords($this->con, [], $this->ip);

        $this->assertSame([], $this->rowsOfIp($this->ip));
    }

    // ------------------------------------------------------------------ aggregates

    public function testGetOrderNumPaymentAmount_SumsItemPriceCreditAndPaymentFeePerOrder(): void
    {
        Fee_StatementsManager::insertRecords($this->con, [
            $this->statement($this->orderNum('1'), 'Item Price Credit', 82.0),
            $this->statement($this->orderNum('1'), 'Payment Fee', -1.64),
            $this->statement($this->orderNum('1'), 'Shipping Fee (Paid By Customer)', 4.75),   // excluded
            $this->statement($this->orderNum('1'), 'Shipping Fee (Charged by Lazada)', -5.30),  // excluded
            $this->statement($this->orderNum('2'), 'Item Price Credit', 65.0),
            $this->statement($this->orderNum('2'), 'Item Price Credit', 65.0),                  // 2 units, 2 lines
            $this->statement($this->orderNum('3'), 'Shipping Fee (Paid By Customer)', 4.75),   // order with no payment lines
        ], $this->ip);

        $map = Fee_StatementsManager::getOrderNumPaymentAmount($this->con, $this->ip);
        ksort($map);

        $this->assertSame([$this->orderNum('1') => 80.36, $this->orderNum('2') => 130.0], $map, 'order_num => float sum; order 3 absent');
    }

    public function testGetOrderNumShippingFeeByCust_SumsOnlyTheCustomerPaidShippingLines(): void
    {
        Fee_StatementsManager::insertRecords($this->con, [
            $this->statement($this->orderNum('1'), 'Item Price Credit', 82.0),
            $this->statement($this->orderNum('1'), 'Shipping Fee (Paid By Customer)', 4.75),
            $this->statement($this->orderNum('1'), 'Shipping Fee (Paid By Customer)', 0.25),
            $this->statement($this->orderNum('1'), 'Shipping Fee (Charged by Lazada)', -5.30),
            $this->statement($this->orderNum('2'), 'Item Price Credit', 10.0),
        ], $this->ip);

        $this->assertSame([$this->orderNum('1') => 5.0], Fee_StatementsManager::getOrderNumShippingFeeByCust($this->con, $this->ip));
    }

    public function testAggregates_AreScopedToTheUserIp(): void
    {
        $otherIp = $this->ip . '-OTHER';
        Fee_StatementsManager::insertRecords($this->con, [$this->statement($this->orderNum('1'), 'Item Price Credit', 82.0)], $this->ip);
        Fee_StatementsManager::insertRecords($this->con, [
            $this->statement($this->orderNum('1'), 'Item Price Credit', 999.0),
            $this->statement($this->orderNum('1'), 'Shipping Fee (Paid By Customer)', 9.0),
        ], $otherIp);

        $this->assertSame([$this->orderNum('1') => 82.0], Fee_StatementsManager::getOrderNumPaymentAmount($this->con, $this->ip));
        $this->assertSame([], Fee_StatementsManager::getOrderNumShippingFeeByCust($this->con, $this->ip));
        $this->assertSame([$this->orderNum('1') => 9.0], Fee_StatementsManager::getOrderNumShippingFeeByCust($this->con, $otherIp));
    }

    public function testAggregates_UnknownIp_ReturnEmptyMaps(): void
    {
        $this->assertSame([], Fee_StatementsManager::getOrderNumPaymentAmount($this->con, $this->ip . '-NONE'));
        $this->assertSame([], Fee_StatementsManager::getOrderNumShippingFeeByCust($this->con, $this->ip . '-NONE'));
    }

    public function testFeeNameConstants_MatchTheLiteralsUsedInTheAggregateQueries(): void
    {
        // the aggregate methods hard-code the strings instead of using the constants (Fee_StatementsManager.php:50-51,74)
        $this->assertSame('Payment Fee', Fee_StatementsManager::FEE_NAME_PLATFORM_CHARGES);
        $this->assertSame('Item Price Credit', Fee_StatementsManager::FEE_NAME_ITEM_PRICE);
        $this->assertSame('Shipping Fee (Paid By Customer)', Fee_StatementsManager::FEE_NAME_SHIPPING_FEE);
        $this->assertSame('Shipping Fee (Charged by Lazada)', Fee_StatementsManager::FEE_NAME_SHIPPING_FEE_CUST);
    }
}
