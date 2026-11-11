<?php

declare(strict_types=1);

namespace test\Integration\Lazada\Manager;

use Lazada\Manager\OrdersManager;
use mysqli_sql_exception;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Lazada\Manager\OrdersManager (src/Legacy/Lazada/Manager/OrdersManager.php),
 * the `orders` lookup behind the LzdFeeChecker reconciliation table.
 */
final class OrdersManagerTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    private function orderNum(string $n): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $n; // <= 30 chars (orders.orderNum is char(30))
    }

    public function testGetForLzdFeeStmtByOrderNums_ReturnsReconciliationColumnsForTheRequestedOrders(): void
    {
        $this->insertOrder([
            'orderNum' => $this->orderNum('1'),
            'billno' => 'ZZTEST-CS1',
            'sellingPrice' => 82.00,
            'shippingFeeByCust' => 4.75,
            'voucher' => 2.00,
            'platformChargesAmount' => 1.64,
        ]);
        $this->insertOrder(['orderNum' => $this->orderNum('2'), 'sellingPrice' => 10.00]);
        $this->insertOrder(['orderNum' => $this->orderNum('3'), 'sellingPrice' => 99.00]); // not requested

        $rows = OrdersManager::getForLzdFeeStmtByOrderNums($this->con, [$this->orderNum('1'), $this->orderNum('2'), $this->orderNum('MISSING')]);
        usort($rows, function (array $a, array $b): int {
            return strcmp($a['orderNum'], $b['orderNum']);
        });

        $this->assertSame(
            [
                [
                    'billno' => 'ZZTEST-CS1',
                    'orderNum' => $this->orderNum('1'),
                    'sellingPrice' => '82.00',
                    'shippingFeeByCust' => '4.75',
                    'voucher' => '2.00',
                    'platformChargesAmount' => '1.64',
                    'stmtPaymentAmount' => '0.00',
                    'stmtShippingFeeByCust' => '0.00',
                ],
                [
                    'billno' => null,
                    'orderNum' => $this->orderNum('2'),
                    'sellingPrice' => '10.00',
                    'shippingFeeByCust' => '0.00',
                    'voucher' => '0.00',
                    'platformChargesAmount' => null,
                    'stmtPaymentAmount' => '0.00',
                    'stmtShippingFeeByCust' => '0.00',
                ],
            ],
            $rows,
            'decimals as strings, the two stmt* columns are constant 0.00 placeholders filled in by the controller'
        );
    }

    public function testGetForLzdFeeStmtByOrderNums_OrderWithTwoLines_ReturnsBothLines(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('1'), 'sku' => 'ZZTEST-SKU-A', 'sellingPrice' => 10.00]);
        $this->insertOrder(['orderNum' => $this->orderNum('1'), 'sku' => 'ZZTEST-SKU-B', 'sellingPrice' => 20.00]);

        $rows = OrdersManager::getForLzdFeeStmtByOrderNums($this->con, [$this->orderNum('1')]);

        $this->assertCount(2, $rows, 'no aggregation per order number - the caller sums the lines');
        $prices = array_column($rows, 'sellingPrice');
        sort($prices);
        $this->assertSame(['10.00', '20.00'], $prices);
    }

    public function testGetForLzdFeeStmtByOrderNums_OnlyUnknownOrderNums_ReturnsEmptyArray(): void
    {
        $this->assertSame([], OrdersManager::getForLzdFeeStmtByOrderNums($this->con, [$this->orderNum('MISSING')]));
    }

    /**
     * OrdersManager.php - used to build `IN()` with zero placeholders when the list is empty - a syntax error.
     * request_handler/LzdFeeChecker.php:47 passes array_keys() of the statement aggregate, which is empty whenever
     * no statement has been uploaded for this IP. Intended: an empty input simply yields no rows.
     */
    public function testGetForLzdFeeStmtByOrderNums_EmptyList_ReturnsEmptyArray(): void
    {
        $this->assertSame([], OrdersManager::getForLzdFeeStmtByOrderNums($this->con, []));
    }
}
