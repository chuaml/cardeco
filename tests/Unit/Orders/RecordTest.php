<?php

declare(strict_types=1);

namespace test\Unit\Orders;

use InvalidArgumentException;
use Orders\Record;
use PHPUnit\Framework\TestCase;
use Product\Item;

/**
 * Characterization tests for Orders\Record (src/Legacy/Orders/Record.php): the order-line value object
 * used by every Record factory and by RecordInserter.
 */
final class RecordTest extends TestCase
{
    // ---------------------------------------------------------------- constructor / defaults

    public function testConstructor_noArguments_hasDocumentedDefaults(): void
    {
        $record = new Record();

        $this->assertNull($record->recordId);
        // the constructor overwrites the declared default '' with the null argument
        $this->assertNull($record->orderNum);
        $this->assertSame('', $record->date);
        $this->assertSame('', $record->trackingNum);
        $this->assertSame(0.00, $record->sellingPrice);
        $this->assertSame(0.00, $record->voucher);
        $this->assertSame(0.00, $record->shippingFee);
        $this->assertSame(0.00, $record->shippingFeeByCust);
        $this->assertSame('', $record->shippingState);
        $this->assertSame(0.00, $record->shippingWeight);
        $this->assertSame(0.00, $record->shippingFeeByWeight);
        $this->assertNull($record->Item);
        $this->assertNull($record->getItem());
    }

    public function testConstructor_withArguments_exposesThemThroughMagicGet(): void
    {
        $item = new Item(11, 'ZZTEST-001', 'Zz Widget');
        $record = new Record(7, 'ZZORDER1', $item);

        $this->assertSame(7, $record->recordId);
        $this->assertSame('ZZORDER1', $record->orderNum);
        $this->assertSame($item, $record->Item);
    }

    public function testConstructor_orderNumIsNotValidated(): void
    {
        // only setOrderNum() validates; the constructor stores anything verbatim
        $record = new Record(null, ' ZZ-ORDER#1 ');

        $this->assertSame(' ZZ-ORDER#1 ', $record->orderNum);
    }

    public function testConstants_documentedValues(): void
    {
        $this->assertSame('d-M-Y', Record::DATE_FORMAT);
        $this->assertSame(2, Record::DECIMAL_PLACES);
        $this->assertSame('/[^a-zA-Z0-9\s]/', Record::INVALID_ORDER_NUM);
    }

    // ---------------------------------------------------------------- setDate

    /** @return array<string, array<int, string>> */
    public function provideDates(): array
    {
        return [
            'iso date' => ['2023-02-26', '26-Feb-2023'],
            'slash d/m/Y is read as day first' => ['26/02/2023', '26-Feb-2023'],
            'iso date time' => ['2023-02-26 10:11:12', '26-Feb-2023'],
            'dash d-m-Y' => ['26-02-2023', '26-Feb-2023'],
            'already formatted' => ['26-Feb-2023', '26-Feb-2023'],
            'slash Y/m/d' => ['2023/02/26', '26-Feb-2023'],
            'textual month first' => ['Feb 26 2023', '26-Feb-2023'],
            'surrounding whitespace' => [' 2023-02-26 ', '26-Feb-2023'],
            // Shopee style dd-mm-yy is parsed by PHP as yy-mm-dd (see ShopeeFactoryTest known-bug)
            'two digit year dd-mm-yy is read as yy-mm-dd' => ['11-02-23 0:47', '23-Feb-2011'],
        ];
    }

    /** @dataProvider provideDates */
    public function testSetDate_variousInputs_normalisedToDayMonthYear(string $input, string $expected): void
    {
        $record = new Record();
        $record->setDate($input);

        $this->assertSame($expected, $record->date);
    }

    /** @return array<string, array<int, string>> */
    public function provideInvalidDates(): array
    {
        return [
            'empty' => ['', 'empty date value'],
            'whitespace only' => ['   ', 'empty date value'],
            'garbage' => ['garbage', 'invalid date format garbage'],
            'month 13' => ['2023-13-45', 'invalid date format 2023-13-45'],
            'US m/d/Y with day > 12' => ['02/26/2023', 'invalid date format 02/26/2023'],
        ];
    }

    /** @dataProvider provideInvalidDates */
    public function testSetDate_invalidInput_throwsInvalidArgumentException(string $input, string $message): void
    {
        $record = new Record();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $record->setDate($input);
    }

    // ---------------------------------------------------------------- setOrderNum

    public function testSetOrderNum_alphanumericWithSpaces_isTrimmed(): void
    {
        $record = new Record();
        $record->setOrderNum('  ZZ ORDER 1  ');

        $this->assertSame('ZZ ORDER 1', $record->orderNum);
    }

    public function testSetOrderNum_emptyString_isAccepted(): void
    {
        $record = new Record(null, 'ZZORDER1');
        $record->setOrderNum('');

        $this->assertSame('', $record->orderNum);
    }

    /** @return array<string, array<int, string>> */
    public function provideInvalidOrderNums(): array
    {
        return [
            'hyphen' => ['ZZ-1'],
            'underscore' => ['ZZ_1'],
            'hash' => ['ZZ#1'],
            'dot' => ['ZZ.1'],
            'non ascii' => ['ZZ1é'],
        ];
    }

    /** @dataProvider provideInvalidOrderNums */
    public function testSetOrderNum_nonAlphanumeric_throwsInvalidArgumentException(string $orderNum): void
    {
        $record = new Record();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("invalid order number: {$orderNum}.");
        $record->setOrderNum($orderNum);
    }

    // ---------------------------------------------------------------- money / weight setters

    public function testSetSellingPrice_roundsToTwoDecimals(): void
    {
        $record = new Record();
        $record->setSellingPrice(12.344);
        $this->assertSame(12.34, $record->sellingPrice);

        $record->setSellingPrice(12.346);
        $this->assertSame(12.35, $record->sellingPrice);
    }

    public function testSetShippingFee_roundsToTwoDecimals(): void
    {
        $record = new Record();
        $record->setShippingFee(4.806);

        $this->assertSame(4.81, $record->shippingFee);
    }

    public function testSetShippingFeeByCust_roundsToTwoDecimals(): void
    {
        $record = new Record();
        $record->setShippingFeeByCust(5.004);

        $this->assertSame(5.00, $record->shippingFeeByCust);
    }

    public function testSetShippingWeight_roundsToTwoDecimals(): void
    {
        $record = new Record();
        $record->setShippingWeight(1.236);

        $this->assertSame(1.24, $record->shippingWeight);
    }

    public function testSetShippingFeeByWeight_roundsToTwoDecimals(): void
    {
        $record = new Record();
        $record->setShippingFeeByWeight(9.504);

        $this->assertSame(9.50, $record->shippingFeeByWeight);
    }

    public function testSetVoucher_isStoredVerbatim_noValidationNoRounding(): void
    {
        $record = new Record();
        $record->setVoucher(-2.555);

        $this->assertSame(-2.555, $record->voucher);
    }

    public function testSetSellingPrice_zero_isAccepted(): void
    {
        $record = new Record();
        $record->setSellingPrice(0.0);

        $this->assertSame(0.0, $record->sellingPrice);
    }

    /** @return array<string, array<int, string>> */
    public function provideNegativeAmountSetters(): array
    {
        return [
            'sellingPrice' => ['setSellingPrice', 'invalid selling price: -0.01.'],
            'shippingFee' => ['setShippingFee', 'invalid shipping fee: -0.01.'],
            'shippingFeeByCust' => ['setShippingFeeByCust', 'invalid shipping fee by cust: -0.01.'],
            'shippingWeight' => ['setShippingWeight', 'invalid shipping weight : -0.01.'],
            'shippingFeeByWeight' => ['setShippingFeeByWeight', 'invalid shipping weight : -0.01.'],
        ];
    }

    /** @dataProvider provideNegativeAmountSetters */
    public function testAmountSetters_negative_throwInvalidArgumentException(string $setter, string $message): void
    {
        $record = new Record();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $record->$setter(-0.01);
    }

    // ---------------------------------------------------------------- string setters

    public function testSetTrackingNum_isTrimmed(): void
    {
        $record = new Record();
        $record->setTrackingNum("  ZZTRACK1 \t");

        $this->assertSame('ZZTRACK1', $record->trackingNum);
    }

    public function testSetShippingState_isTrimmed(): void
    {
        $record = new Record();
        $record->setShippingState(' Selangor ');

        $this->assertSame('Selangor', $record->shippingState);
    }

    public function testSetStatus_isUppercasedAndTrimmed(): void
    {
        $record = new Record();
        $record->setStatus('  ready_to_ship ');

        $this->assertSame('READY_TO_SHIP', $record->status);
    }

    // ---------------------------------------------------------------- getAll / getItem / getEscapedData

    public function testGetAll_withoutItem_exactShapeAndKeyOrder(): void
    {
        $record = new Record();

        $this->assertSame([
            'recordId' => null,
            'orderNum' => null,
            'date' => '',
            'sku' => null,
            'itemName' => null,
            'trackingNum' => '',
            'sellingPrice' => 0.00,
            'voucher' => 0.00,
            'shippingFee' => 0.00,
            'shippingFeeByCust' => 0.00,
            'shippingState' => '',
            'shippingWeight' => 0.00,
            'shippingFeeByWeight' => 0.00,
        ], $record->getAll());
    }

    public function testGetAll_withItem_skuAndItemNameComeFromItem(): void
    {
        $record = new Record(7, 'ZZORDER1', new Item(11, 'ZZTEST-001', 'Zz Widget', 'PCS', 'ZZGROUP'));
        $record->setDate('2023-02-26');
        $record->setTrackingNum('ZZTRACK1');
        $record->setSellingPrice(41.5);
        $record->setVoucher(1.5);
        $record->setShippingFee(1.54);
        $record->setShippingFeeByCust(4.8);
        $record->setShippingState('Selangor');
        $record->setShippingWeight(3.0);
        $record->setShippingFeeByWeight(4.8);

        $this->assertSame([
            'recordId' => 7,
            'orderNum' => 'ZZORDER1',
            'date' => '26-Feb-2023',
            'sku' => 'ZZTEST-001',
            'itemName' => 'Zz Widget',
            'trackingNum' => 'ZZTRACK1',
            'sellingPrice' => 41.5,
            'voucher' => 1.5,
            'shippingFee' => 1.54,
            'shippingFeeByCust' => 4.8,
            'shippingState' => 'Selangor',
            'shippingWeight' => 3.0,
            'shippingFeeByWeight' => 4.8,
        ], $record->getAll());
    }

    public function testGetItem_returnsClone_mutatingItDoesNotAffectRecord(): void
    {
        $record = new Record(null, 'ZZORDER1', new Item(11, 'ZZTEST-001', 'Zz Widget'));

        $clone = $record->getItem();
        $this->assertNotSame($record->Item, $clone);
        $this->assertEquals($record->Item, $clone);
        $this->assertNotSame($record->getItem(), $record->getItem());

        $clone->code = 'CHANGED';
        $clone->description = 'changed';

        $this->assertSame('ZZTEST-001', $record->getAll()['sku']);
        $this->assertSame('Zz Widget', $record->getAll()['itemName']);
    }

    public function testGetItem_noItem_returnsNull(): void
    {
        $record = new Record(null, 'ZZORDER1');

        $this->assertNull($record->getItem());
    }

    public function testGetEscapedData_escapesHtmlSpecialCharsWithQuotes(): void
    {
        $record = new Record();
        $record->setShippingState('<b>Kuala & "Lumpur\'s"</b>');

        $this->assertSame(
            '&lt;b&gt;Kuala &amp; &quot;Lumpur&#039;s&quot;&lt;/b&gt;',
            $record->getEscapedData('shippingState')
        );
    }

    public function testGetEscapedData_floatProperty_isStringified(): void
    {
        $record = new Record();
        $record->setSellingPrice(12.5);

        $this->assertSame('12.5', $record->getEscapedData('sellingPrice'));
    }

    // ---------------------------------------------------------------- known bugs

    /**
     * @group known-bug
     * Record.php:68-70 - setStatus() writes an UNDECLARED property, so a record that never had setStatus()
     * called emits "Undefined property" on read (and a dynamic-property deprecation on PHP 8.2+).
     * Intended: `status` is a declared property defaulting to '' like the other string fields.
     */
    public function testStatus_freshRecord_isDeclaredAndDefaultsToEmptyString(): void
    {
        $record = new Record();

        $this->assertTrue(property_exists(Record::class, 'status'), 'status must be a declared property');
        $this->assertSame('', $record->status);
    }

    /**
     * @group known-bug
     * Record.php:135-160 - getAll() omits `status` although setStatus() exists and RecordInserter reads it.
     * Intended: getAll() exposes the (uppercased, trimmed) status like every other field.
     */
    public function testGetAll_includesStatus(): void
    {
        $record = new Record(null, 'ZZORDER1');
        $record->setStatus(' completed ');

        $all = $record->getAll();
        $this->assertArrayHasKey('status', $all);
        $this->assertSame('COMPLETED', $all['status']);
    }
}
