<?php

declare(strict_types=1);

namespace test\Unit\Lazada;

use Lazada\FeeStatement;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the Lazada\FeeStatement value object (src/Legacy/Lazada/FeeStatement.php),
 * the row type persisted into `lzd_fee_statements` by Lazada\Manager\Fee_StatementsManager.
 */
final class FeeStatementTest extends TestCase
{
    public function testDefaults_StringsAreNullAndAmountIsZero(): void
    {
        $stmt = new FeeStatement();

        $this->assertNull($stmt->getTransactionDate());
        $this->assertNull($stmt->getFeeType());
        $this->assertNull($stmt->getFeeName());
        $this->assertNull($stmt->getDescription());
        $this->assertNull($stmt->getItemCode());
        $this->assertNull($stmt->getlzdSku());
        $this->assertSame(0.0, $stmt->getAmount());
        $this->assertNull($stmt->getStatementDate());
        $this->assertNull($stmt->getPaidStatus());
        $this->assertNull($stmt->getOrderNum());
        $this->assertNull($stmt->getItemStatus());
    }

    /**
     * @dataProvider transactionDateProvider
     */
    public function testSetTransactionDate_FormatsAsYearTextualMonthDay(string $input, string $expected): void
    {
        // FeeStatement.php:59 uses 'Y-M-d' (textual month) - the value is stored in a char column as-is
        $stmt = new FeeStatement();

        $stmt->setTransactionDate($input);

        $this->assertSame($expected, $stmt->getTransactionDate());
    }

    public function transactionDateProvider(): array
    {
        return [
            'lazada "d M Y" (spaces become dashes)' => ['15 Mar 2024', '2024-Mar-15'],
            'iso date' => ['2024-03-15', '2024-Mar-15'],
            'iso date-time (time dropped)' => ['2024-03-15 10:20:30', '2024-Mar-15'],
            'already dashed' => ['15-Mar-2024', '2024-Mar-15'],
            'month first' => ['Mar 15 2024', '2024-Mar-15'],
            'first of january' => ['01 Jan 2023', '2023-Jan-01'],
            'year end' => ['31 Dec 2024', '2024-Dec-31'],
        ];
    }

    public function testSetTransactionDate_Unparsable_ThrowsUnexpectedValueException(): void
    {
        $stmt = new FeeStatement();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('invalid date format not a date');
        $stmt->setTransactionDate('not a date');
    }

    public function testSetTransactionDate_EmptyString_documentsCurrentBehaviour_SilentlyBecomesToday(): void
    {
        // FeeStatement.php:53-57 - date_create('') is "now", so a blank CSV cell is stored as the upload date
        $stmt = new FeeStatement();

        $stmt->setTransactionDate('');

        $this->assertMatchesRegularExpression('/^\d{4}-[A-Z][a-z]{2}-\d{2}$/', (string) $stmt->getTransactionDate());
    }

    /**
     * @group known-bug
     */
    public function testSetTransactionDate_EmptyString_IsRejected(): void
    {
        // FeeStatement.php:53-57 - an empty transaction date should be reported, not replaced by today's date
        $stmt = new FeeStatement();

        $this->expectException(\UnexpectedValueException::class);
        $stmt->setTransactionDate('');
    }

    public function testStringSetters_TrimWhitespace(): void
    {
        $stmt = new FeeStatement();

        $stmt->setFeeType("  Payment Fee \t");
        $stmt->setFeeName(' Commission ');
        $stmt->setDescription(" Order item fee\n");
        $stmt->setItemCode(' ZZTEST-001 ');
        $stmt->setlzdSku(' ZZLZD-0001 ');
        $stmt->setStatementDate(' 15 Mar 2024 ');
        $stmt->setPaidStatus(' Paid ');
        $stmt->setOrderNum(' 577000000000001 ');
        $stmt->setItemStatus(' Delivered ');

        $this->assertSame('Payment Fee', $stmt->getFeeType());
        $this->assertSame('Commission', $stmt->getFeeName());
        $this->assertSame('Order item fee', $stmt->getDescription());
        $this->assertSame('ZZTEST-001', $stmt->getItemCode());
        $this->assertSame('ZZLZD-0001', $stmt->getlzdSku());
        $this->assertSame('15 Mar 2024', $stmt->getStatementDate());
        $this->assertSame('Paid', $stmt->getPaidStatus());
        $this->assertSame('577000000000001', $stmt->getOrderNum());
        $this->assertSame('Delivered', $stmt->getItemStatus());
    }

    public function testSetAmount_StoresFloatIncludingNegativeFees(): void
    {
        $stmt = new FeeStatement();

        $stmt->setAmount(-3.2);
        $this->assertSame(-3.2, $stmt->getAmount());

        $stmt->setAmount(125.0);
        $this->assertSame(125.0, $stmt->getAmount());
    }
}
