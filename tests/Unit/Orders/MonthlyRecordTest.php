<?php

declare(strict_types=1);

namespace test\Unit\Orders;

use InvalidArgumentException;
use Orders\MonthlyRecord;
use Orders\PaymentCharges\BankIn;
use Orders\PaymentCharges\Cash;
use Orders\PaymentCharges\Lazada;
use Orders\PaymentCharges\Shopee;
use Orders\Record;
use PHPUnit\Framework\TestCase;
use Product\Item;

/**
 * Characterization tests for Orders\MonthlyRecord (src/Legacy/Orders/MonthlyRecord.php): Record plus
 * bill number, stock-out date, transfer/platform/direct charges.
 */
final class MonthlyRecordTest extends TestCase
{
    public function testConstructor_defaults(): void
    {
        $m = new MonthlyRecord();

        $this->assertInstanceOf(Record::class, $m);
        $this->assertSame('', $m->billno);
        $this->assertSame('', $m->dateStockOut);
        $this->assertSame(0.00, $m->transferCharges);
        $this->assertNull($m->PlatformCharges);
        $this->assertNull($m->getPlatformCharges());
        $this->assertSame([], $m->DirectCharges);
        $this->assertSame(0.00, $m->getSumDirectChargesAmount());
        $this->assertSame(0.00, $m->getGrandTotal());
    }

    public function testMagicGet_fallsBackToParentForRecordProperties(): void
    {
        $m = new MonthlyRecord(7, 'ZZORDER1', new Item(11, 'ZZTEST-001', 'Zz Widget'));
        $m->setTrackingNum('ZZTRACK1');

        $this->assertSame(7, $m->recordId);
        $this->assertSame('ZZORDER1', $m->orderNum);
        $this->assertSame('ZZTRACK1', $m->trackingNum);
        $this->assertSame('ZZTEST-001', $m->getItem()->code);
    }

    public function testSetBillNo_storesVerbatimAndAcceptsNull(): void
    {
        $m = new MonthlyRecord();

        $m->setBillNo(' CS001 ');
        $this->assertSame(' CS001 ', $m->billno);

        $m->setBillNo(null);
        $this->assertNull($m->billno);
    }

    public function testSetDateStockOut_normalisesLikeRecordDate(): void
    {
        $m = new MonthlyRecord();

        $m->setDateStockOut('2023-02-27');
        $this->assertSame('27-Feb-2023', $m->dateStockOut);

        $m->setDateStockOut('27/02/2023');
        $this->assertSame('27-Feb-2023', $m->dateStockOut);
    }

    public function testSetDateStockOut_null_storesNull(): void
    {
        $m = new MonthlyRecord();
        $m->setDateStockOut(null);

        $this->assertNull($m->dateStockOut);
    }

    public function testSetDateStockOut_emptyString_throws(): void
    {
        $m = new MonthlyRecord();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty date value');
        $m->setDateStockOut('');
    }

    public function testSetDateStockOut_garbage_throws(): void
    {
        $m = new MonthlyRecord();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid date format garbage');
        $m->setDateStockOut('garbage');
    }

    public function testSetTransferCharges_noValidationNoRounding(): void
    {
        $m = new MonthlyRecord();
        $m->setTransferCharges(-1.555);

        $this->assertSame(-1.555, $m->transferCharges);
    }

    public function testGetGrandTotal_isSellingPricePlusShippingFeeByCustMinusVoucher(): void
    {
        $m = new MonthlyRecord();
        $m->setSellingPrice(100.0);
        $m->setShippingFeeByCust(5.5);
        $m->setVoucher(2.0);
        $m->setShippingFee(4.8); // seller-paid courier is NOT part of the grand total

        $this->assertSame(103.5, $m->getGrandTotal());
    }

    public function testSetPlatformCharges_replacesPrevious(): void
    {
        $m = new MonthlyRecord();
        $lazada = new Lazada(1.25);
        $shopee = new Shopee(2.5);

        $m->setPlatformCharges($lazada);
        $this->assertSame($lazada, $m->getPlatformCharges());

        $m->setPlatformCharges($shopee);
        $this->assertSame($shopee, $m->getPlatformCharges());
        $this->assertSame('Shopee', $m->getAll()['platformCharges']);
        $this->assertSame(2.5, $m->getAll()['platformChargesAmount']);
    }

    public function testDirectCharges_keyedByPaymentMethod(): void
    {
        $m = new MonthlyRecord();
        $m->setDirectCharges(new BankIn(10.0));
        $m->setDirectCharges(new Cash(5.25));

        $this->assertSame(10.0, $m->getDirectCharges('BankIn')->getAmount());
        $this->assertSame(5.25, $m->getDirectCharges('Cash')->getAmount());
        $this->assertNull($m->getDirectCharges('Platform'));
        $this->assertNull($m->getDirectCharges('bankin')); // key lookup is case sensitive
        $this->assertSame(['BankIn', 'Cash'], array_keys($m->DirectCharges));
        $this->assertSame(15.25, $m->getSumDirectChargesAmount());
    }

    public function testSetDirectCharges_sameMethodTwice_lastOneWins(): void
    {
        $m = new MonthlyRecord();
        $m->setDirectCharges(new BankIn(10.0));
        $m->setDirectCharges(new BankIn(20.0));

        $this->assertSame(20.0, $m->getDirectCharges('BankIn')->getAmount());
        $this->assertSame(20.0, $m->getSumDirectChargesAmount());
        $this->assertCount(1, $m->DirectCharges);
    }

    public function testRemoveAllDirectCharges_clearsEverything(): void
    {
        $m = new MonthlyRecord();
        $m->setDirectCharges(new BankIn(10.0));
        $m->setDirectCharges(new Cash(5.0));

        $m->removeAllDirectCharges();

        $this->assertSame([], $m->DirectCharges);
        $this->assertNull($m->getDirectCharges('BankIn'));
        $this->assertSame(0.00, $m->getSumDirectChargesAmount());
        $this->assertSame(0.00, $m->getAll()['bankIn']);
        $this->assertSame(0.00, $m->getAll()['cash']);
    }

    public function testGetAll_noCharges_appendsMonthlyKeysWithNullPlatform(): void
    {
        $m = new MonthlyRecord();
        $all = $m->getAll();

        $this->assertSame([
            'recordId', 'orderNum', 'date', 'sku', 'itemName', 'trackingNum', 'sellingPrice', 'voucher',
            'shippingFee', 'shippingFeeByCust', 'shippingState', 'shippingWeight', 'shippingFeeByWeight',
            'billno', 'grandTotal', 'dateStockOut', 'transferCharges',
            'platformCharges', 'platformChargesAmount', 'bankIn', 'cash',
        ], array_keys($all));
        $this->assertNull($all['platformCharges']);
        $this->assertNull($all['platformChargesAmount']);
        $this->assertSame(0.00, $all['bankIn']);
        $this->assertSame(0.00, $all['cash']);
        $this->assertSame(0.00, $all['grandTotal']);
    }

    public function testGetAll_fullyPopulated_exactArray(): void
    {
        $m = new MonthlyRecord(7, 'ZZORDER1', new Item(11, 'ZZTEST-001', 'Zz Widget'));
        $m->setDate('2023-02-26');
        $m->setTrackingNum('ZZTRACK1');
        $m->setSellingPrice(1234.5);
        $m->setVoucher(2.0);
        $m->setShippingFee(4.8);
        $m->setShippingFeeByCust(0.0);
        $m->setShippingState('Selangor');
        $m->setShippingWeight(1.25);
        $m->setShippingFeeByWeight(4.8);
        $m->setBillNo('CS001');
        $m->setDateStockOut('2023-02-27');
        $m->setTransferCharges(0.5);
        $m->setPlatformCharges(new Lazada(1.25));
        $m->setDirectCharges(new BankIn(10.0));
        $m->setDirectCharges(new Cash(3.0));

        $this->assertSame([
            'recordId' => 7,
            'orderNum' => 'ZZORDER1',
            'date' => '26-Feb-2023',
            'sku' => 'ZZTEST-001',
            'itemName' => 'Zz Widget',
            'trackingNum' => 'ZZTRACK1',
            'sellingPrice' => 1234.5,
            'voucher' => 2.0,
            'shippingFee' => 4.8,
            'shippingFeeByCust' => 0.0,
            'shippingState' => 'Selangor',
            'shippingWeight' => 1.25,
            'shippingFeeByWeight' => 4.8,
            'billno' => 'CS001',
            'grandTotal' => 1232.5,
            'dateStockOut' => '27-Feb-2023',
            'transferCharges' => 0.5,
            'platformCharges' => 'Lazada',
            'platformChargesAmount' => 1.25,
            'bankIn' => 10.0,
            'cash' => 3.0,
        ], $m->getAll());
    }
}
