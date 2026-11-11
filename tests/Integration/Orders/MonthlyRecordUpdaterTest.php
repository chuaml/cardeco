<?php

declare(strict_types=1);

namespace test\Integration\Orders;

use Orders\MonthlyRecordUpdater;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Orders\MonthlyRecordUpdater: the bulk UPDATE behind the RecordEditor save
 * (request_handler/RecordEditor.php:256 -> process/RecordEditor/submit.php:9 -> update($con, $_POST['r'])).
 *
 * Input shape is the editor form: $_POST['r'][<orders.id>] = ['billno' => ..., 'trackingNum' => ..., 'shippingFee' => ...,
 * 'shippingFeeByCust' => ..., 'voucher' => ..., 'platformChargesAmount' => ..., 'bankIn' => ..., 'cash' => ...]
 * (see Orders\RecordEditor::setMonthlyRecords(), RecordEditor.php:44-74).
 */
final class MonthlyRecordUpdaterTest extends DatabaseTestCase
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

    /** @return array<string, string> a complete editor row */
    private function editorRow(array $overrides = []): array
    {
        return array_merge([
            'billno' => 'ZZTEST-B1',
            'trackingNum' => 'ZZTEST-T1',
            'shippingFee' => '1.50',
            'shippingFeeByCust' => '2.00',
            'voucher' => '0.50',
            'platformChargesAmount' => '3.25',
            'bankIn' => '0',
            'cash' => '10.00',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function orderRow(int $id): array
    {
        $rows = $this->fetchAll('SELECT * FROM orders WHERE id = ?', 'i', $id);
        $this->assertCount(1, $rows);

        return $rows[0];
    }

    public function testUpdate_WritesTheEightEditableColumnsOfEveryGivenId(): void
    {
        $id1 = $this->insertOrder(['orderNum' => $this->orderNum('O1')]);
        $id2 = $this->insertOrder(['orderNum' => $this->orderNum('O2')]);
        $post = [
            $id1 => $this->editorRow(),
            $id2 => $this->editorRow(['billno' => 'ZZTEST-B2', 'trackingNum' => 'ZZTEST-T2', 'cash' => '0', 'bankIn' => '7.75']),
        ];

        (new MonthlyRecordUpdater())->update($this->con, $post);

        $row1 = $this->orderRow($id1);
        $this->assertSame('ZZTEST-B1', $row1['billno']);
        $this->assertSame('ZZTEST-T1', $row1['trackingNum']);
        $this->assertSame('1.50', $row1['shippingFee']);
        $this->assertSame('2.00', $row1['shippingFeeByCust']);
        $this->assertSame('0.50', $row1['voucher']);
        $this->assertSame('3.25', $row1['platformChargesAmount']);
        $this->assertSame('0.00', $row1['bankIn']);
        $this->assertSame('10.00', $row1['cash']);

        $row2 = $this->orderRow($id2);
        $this->assertSame('ZZTEST-B2', $row2['billno']);
        $this->assertSame('ZZTEST-T2', $row2['trackingNum']);
        $this->assertSame('7.75', $row2['bankIn']);
        $this->assertSame('0.00', $row2['cash']);
    }

    public function testUpdate_LeavesNonEditableColumnsAndOtherRowsUntouched(): void
    {
        $id1 = $this->insertOrder([
            'orderNum' => $this->orderNum('O1'),
            'date' => '2024-01-15',
            'sku' => self::FIXTURE_PREFIX . 'SKU-A',
            'sellingPrice' => 12.5,
            'status' => 'COMPLETED',
            'platformCharges' => 'Shopee',
            'shippingState' => 'Selangor-',
            'remark' => 'ZZTEST remark',
        ]);
        $id2 = $this->insertOrder(['orderNum' => $this->orderNum('O2'), 'trackingNum' => 'ZZTEST-KEEP']);
        $before2 = $this->orderRow($id2);
        $post = [$id1 => $this->editorRow()];

        (new MonthlyRecordUpdater())->update($this->con, $post);

        $row1 = $this->orderRow($id1);
        $this->assertSame($this->orderNum('O1'), $row1['orderNum']);
        $this->assertSame('2024-01-15', $row1['date']);
        $this->assertSame(self::FIXTURE_PREFIX . 'SKU-A', $row1['sku']);
        $this->assertSame('12.50', $row1['sellingPrice']);
        $this->assertSame('COMPLETED', $row1['status']);
        $this->assertSame('Shopee', $row1['platformCharges']);
        $this->assertSame('Selangor-', $row1['shippingState']);
        $this->assertSame('ZZTEST remark', $row1['remark']);
        $this->assertNull($row1['dateStockOut']);

        $this->assertSame($before2, $this->orderRow($id2));
    }

    public function testUpdate_TrimsBillnoAndTrackingNum(): void
    {
        $id = $this->insertOrder(['orderNum' => $this->orderNum('O1')]);
        $post = [$id => $this->editorRow(['billno' => '  ZZTEST-B9  ', 'trackingNum' => "\tZZTEST-T9 \n"])];

        (new MonthlyRecordUpdater())->update($this->con, $post);

        $row = $this->orderRow($id);
        $this->assertSame('ZZTEST-B9', $row['billno']);
        $this->assertSame('ZZTEST-T9', $row['trackingNum']);
    }

    /** @return array<string, array<int, string>> */
    public function moneyInputProvider(): array
    {
        return [
            'plain decimal' => ['12.34', '12.34'],
            'integer' => ['7', '7.00'],
            'empty string becomes 0' => ['', '0.00'],
            'text becomes 0' => ['abc', '0.00'],
            'thousands separator is cut at the comma' => ['1,234.50', '1.00'],
        ];
    }

    /**
     * Money cells are cast with (double) (MonthlyRecordUpdater.php:61-66). The editor renders them with
     * number_format(..., 2, '.', ',') (RecordEditor.php:52-70) and its input pattern allows commas, so a value
     * such as "1,234.50" that is posted back unchanged is saved as 1.00.
     *
     * @dataProvider moneyInputProvider
     */
    public function testUpdate_MoneyCellsAreCastWithDouble_documentsCurrentBehaviour(string $input, string $stored): void
    {
        $id = $this->insertOrder(['orderNum' => $this->orderNum('O1')]);
        $post = [$id => $this->editorRow(['shippingFee' => $input, 'cash' => $input])];

        (new MonthlyRecordUpdater())->update($this->con, $post);

        $row = $this->orderRow($id);
        $this->assertSame($stored, $row['shippingFee']);
        $this->assertSame($stored, $row['cash']);
    }

    public function testUpdate_EmptyInput_IsANoOp(): void
    {
        $id = $this->insertOrder(['orderNum' => $this->orderNum('O1')]);
        $before = $this->orderRow($id);
        $post = [];

        (new MonthlyRecordUpdater())->update($this->con, $post);

        $this->assertSame($before, $this->orderRow($id));
    }

    public function testUpdate_UnknownId_ChangesNothingAndDoesNotThrow(): void
    {
        $id = $this->insertOrder(['orderNum' => $this->orderNum('O1')]);
        $before = $this->orderRow($id);
        $unknown = $this->fetchAll('SELECT MAX(id) + 1000 AS id FROM orders');
        $post = [(int) $unknown[0]['id'] => $this->editorRow()];

        (new MonthlyRecordUpdater())->update($this->con, $post);

        $this->assertSame($before, $this->orderRow($id));
    }
}
