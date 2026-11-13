<?php

declare(strict_types=1);

namespace test\Unit;

use Lzd_Fee_Matcher;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Characterization of Lzd_Fee_Matcher::getResult() (src/Legacy/Lzd_Fee_Matcher.php): per Lazada fee-statement
 * line, the difference between the statement amount and the amount recorded in courier_record.
 *
 * The rows normally come from setDB_Result(); they are injected through reflection here so that the comparison
 * itself is tested without a database. The recorded amount can be a numeric string, '' or null (no matching
 * courier record) or false (a fee type that is not compared): abs() accepted all of them on PHP 7, but '' is a
 * TypeError and null a deprecation on PHP 8, hence the (float) casts in getResult().
 */
final class LzdFeeMatcherTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides): array
    {
        return $overrides + [
            'tdate' => '2024-01-15',
            'fee_type' => 'ZZ fee type',
            'fee_name' => 'Item Price Credit',
            'description' => 'ZZTEST line',
            'sdate' => '2024-01-31',
            'paid_status' => 'Paid',
            'amount' => '25.50',
            'order_num' => 'ZZTEST-ORDER-1',
            'item_status' => 'Delivered',
            'billno' => 'ZZ-0001',
            'lzd_sku' => 'ZZTEST-LZD-SKU',
            'selling_price' => '25.50',
            'courier' => '0.00',
            'courier_lzd' => '0.00',
            'lzd_fee' => '0.00',
            'voucher' => '0.00',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function resultOf(array $rows): array
    {
        $matcher = new Lzd_Fee_Matcher();
        $dbResult = new ReflectionProperty(Lzd_Fee_Matcher::class, 'db_result');
        $dbResult->setAccessible(true);
        $dbResult->setValue($matcher, $rows);

        return $matcher->getResult();
    }

    public function testGetResult_StatementAmountEqualsRecordedAmount_LeavesDifferenceAndRemarkEmpty(): void
    {
        $result = $this->resultOf([$this->row([])]);

        $this->assertCount(1, $result);
        $this->assertSame('', $result[0]['difference']);
        $this->assertSame('', $result[0]['Remark']);
        $this->assertSame('25.50', $result[0]['amount']);
        $this->assertSame('ZZTEST-ORDER-1', $result[0]['ordernum']);
        $this->assertSame('ZZTEST-LZD-SKU', $result[0]['Lzd SKU']);
    }

    public function testGetResult_AmountsDiffer_ReportsTheAbsoluteDifferenceAndTheRecordedAmount(): void
    {
        $result = $this->resultOf([
            $this->row(['fee_name' => 'Payment Fee', 'amount' => '-1.50', 'lzd_fee' => '-1.20']),
        ]);

        $this->assertEqualsWithDelta(0.30, $result[0]['difference'], 0.00001);
        $this->assertSame('-1.20', $result[0]['Remark']);
    }

    public function testGetResult_FeeNameThatIsNotCompared_ReportsTheWholeAmountAsDifference(): void
    {
        // getRecorded_Fee() returns false for an unknown fee name; abs(false) is 0 on every PHP version
        $result = $this->resultOf([$this->row(['fee_name' => 'ZZ Unknown Fee', 'amount' => '-3.00'])]);

        $this->assertSame(3.0, $result[0]['difference']);
        $this->assertFalse($result[0]['Remark']);
    }

    /** @return array<string, array<int, mixed>> */
    public function provideMissingRecordedAmounts(): array
    {
        return [
            'empty string' => [''],
            'null (no courier record)' => [null],
        ];
    }

    /**
     * @dataProvider provideMissingRecordedAmounts
     * @param mixed $recorded
     */
    public function testGetResult_RecordedAmountMissing_CountsAsZeroOnEveryPhpVersion($recorded): void
    {
        $result = $this->resultOf([
            $this->row(['fee_name' => 'Promotional Charges Vouchers', 'amount' => '-4.25', 'voucher' => $recorded]),
        ]);

        $this->assertSame(4.25, $result[0]['difference']);
        $this->assertSame($recorded, $result[0]['Remark']);
    }
}
