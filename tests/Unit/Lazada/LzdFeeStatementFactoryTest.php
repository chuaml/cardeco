<?php

declare(strict_types=1);

namespace test\Unit\Lazada;

use Lazada\Factory\LzdFeeStatementFactory;
use Lazada\FeeStatement;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of Lazada\Factory\LzdFeeStatementFactory (src/Legacy/Lazada/Factory/LzdFeeStatementFactory.php):
 * parsed Lazada fee-statement CSV rows -> Lazada\FeeStatement[]. Columns are addressed by POSITION
 * (0,1,2,4,5,6,7,11,12,13,15) with no bounds check (LzdFeeStatementFactory.php:12-22).
 */
final class LzdFeeStatementFactoryTest extends TestCase
{
    /** one 16-column statement row with a distinct marker in every column */
    const ROW = [
        0 => '15 Mar 2024',            // transaction date
        1 => 'Fee',                    // fee type
        2 => 'Commission',             // fee name
        3 => 'IGNORED-3',
        4 => 'Commission for order',   // description
        5 => 'ZZTEST-001',             // item code (seller sku)
        6 => 'ZZLZD-0001',             // lazada sku
        7 => '-3.20',                  // amount
        8 => 'IGNORED-8',
        9 => 'IGNORED-9',
        10 => 'IGNORED-10',
        11 => '17 Mar 2024',           // statement date
        12 => 'Paid',                  // paid status
        13 => '577000000000001',       // order number
        14 => 'IGNORED-14',
        15 => 'Delivered',             // item status
    ];

    public function testGetFeeStatementList_MapsFixedColumnPositions(): void
    {
        $list = LzdFeeStatementFactory::getFeeStatementList([self::ROW]);

        $this->assertCount(1, $list);
        $stmt = $list[0];
        $this->assertInstanceOf(FeeStatement::class, $stmt);
        $this->assertSame('2024-Mar-15', $stmt->getTransactionDate());
        $this->assertSame('Fee', $stmt->getFeeType());
        $this->assertSame('Commission', $stmt->getFeeName());
        $this->assertSame('Commission for order', $stmt->getDescription());
        $this->assertSame('ZZTEST-001', $stmt->getItemCode());
        $this->assertSame('ZZLZD-0001', $stmt->getlzdSku());
        $this->assertSame(-3.2, $stmt->getAmount());
        $this->assertSame('17 Mar 2024', $stmt->getStatementDate());
        $this->assertSame('Paid', $stmt->getPaidStatus());
        $this->assertSame('577000000000001', $stmt->getOrderNum());
        $this->assertSame('Delivered', $stmt->getItemStatus());
    }

    public function testGetFeeStatementList_TrimsTextCellsAndCoercesNumericAmountString(): void
    {
        // the factory file is not strict_types, so a numeric string amount is coerced to float by PHP
        // (leading whitespace is fine on every PHP version; trailing whitespace would notice on 7.x)
        $row = self::ROW;
        $row[1] = '  Fee ';
        $row[7] = ' 12.50';
        $row[13] = "577000000000001\t";

        $stmt = LzdFeeStatementFactory::getFeeStatementList([$row])[0];

        $this->assertSame('Fee', $stmt->getFeeType());
        $this->assertSame(12.5, $stmt->getAmount());
        $this->assertSame('577000000000001', $stmt->getOrderNum());
    }

    public function testGetFeeStatementList_EmptyInput_ReturnsEmptyArray(): void
    {
        $this->assertSame([], LzdFeeStatementFactory::getFeeStatementList([]));
    }

    public function testGetFeeStatementList_PreservesInputKeysAndOrder(): void
    {
        $second = self::ROW;
        $second[13] = '577000000000002';

        $list = LzdFeeStatementFactory::getFeeStatementList([3 => self::ROW, 9 => $second]);

        $this->assertSame([3, 9], array_keys($list));
        $this->assertSame('577000000000001', $list[3]->getOrderNum());
        $this->assertSame('577000000000002', $list[9]->getOrderNum());
    }

    public function testGetFeeStatementList_ExtraTrailingColumns_AreIgnored(): void
    {
        $row = self::ROW;
        $row[16] = 'extra';
        $row[17] = 'extra';

        $stmt = LzdFeeStatementFactory::getFeeStatementList([$row])[0];

        $this->assertSame('Delivered', $stmt->getItemStatus());
    }

    public function testGetFeeStatementList_InvalidTransactionDate_PropagatesException(): void
    {
        $row = self::ROW;
        $row[0] = 'Transaction Date'; // e.g. header row passed through by mistake

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('invalid date format Transaction Date');
        LzdFeeStatementFactory::getFeeStatementList([$row]);
    }

    public function testGetFeeStatementList_NonNumericAmount_ThrowsTypeError(): void
    {
        $row = self::ROW;
        $row[7] = 'n/a';

        $this->expectException(\TypeError::class);
        LzdFeeStatementFactory::getFeeStatementList([$row]);
    }

    public function testGetFeeStatementList_RowShorterThan16Columns_RaisesUndefinedOffset(): void
    {
        // no bounds check: a short row triggers an undefined-offset notice (7.x) / warning (8.x)
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessageMatches('/Undefined (offset|array key)/');
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            LzdFeeStatementFactory::getFeeStatementList([array_slice(self::ROW, 0, 12)]);
        } finally {
            restore_error_handler();
        }
    }
}
