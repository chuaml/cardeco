<?php

declare(strict_types=1);

namespace test\Unit\Orders\PaymentCharges;

use InvalidArgumentException;
use Orders\PaymentCharges\BankIn;
use Orders\PaymentCharges\Cash;
use Orders\PaymentCharges\DirectCharges;
use Orders\PaymentCharges\GHL;
use Orders\PaymentCharges\Lazada;
use Orders\PaymentCharges\Lazada_Eplus;
use Orders\PaymentCharges\Lazada_Paling_Best;
use Orders\PaymentCharges\Netpay;
use Orders\PaymentCharges\PaymentCharges;
use Orders\PaymentCharges\PlatformCharges;
use Orders\PaymentCharges\Shopee;
use Orders\PaymentCharges\Shopee_Eplus;
use Orders\PaymentCharges\Street11;
use Orders\PaymentCharges\TikTok_Eplus;
use PHPUnit\Framework\TestCase;

/**
 * Characterization tests for src/Legacy/Orders/PaymentCharges/*.php. The concrete classes are
 * constants-as-classes holding SQL Accounting customer / payment account codes - the literals are locked.
 */
final class PaymentChargesTest extends TestCase
{
    /** @return array<string, array<int, string>> */
    public function providePlatformCharges(): array
    {
        // class, PLATFORM, custId, companyName, paymentInto
        return [
            'Lazada' => [Lazada::class, 'Lazada', '300-C0006', 'CASH A/C - LAZADA (CAR DECO)', '321-000'],
            'Street11' => [Street11::class, '11Street', '300-C0005', 'CASH A/C - 11 STREET', '319-000'],
            'Netpay' => [Netpay::class, 'Netpay', '300-C0001', 'CASH A/C - LELONG', '315-000'],
            'GHL' => [GHL::class, 'GHL', '300-C0003', 'CASH A/C - CARDECO.COM.MY', '322-000'],
            'Shopee' => [Shopee::class, 'Shopee', '300-C0008', 'CASH A/C - SHOPEE (CAR DECO)', '324-000'],
            'Lazada_Eplus' => [Lazada_Eplus::class, 'Lazada_Eplus', '300-C0010', 'CASH A/C - LAZADA (E PLUS)', '321-100'],
            'Lazada_Paling_Best' => [Lazada_Paling_Best::class, 'Lazada_Paling_Best', '300-C0014', 'CASH A/C - LAZADA (PALING BEST)', '321-200'],
            'Shopee_Eplus' => [Shopee_Eplus::class, 'Shopee_Eplus', '300-C0011', 'CASH A/C - SHOPEE (E PLUS)', '324-100'],
            'TikTok_Eplus' => [TikTok_Eplus::class, 'TikTok_Eplus', '300-C0013', 'CASH A/C - TIKTOK (E PLUS)', '325-000'],
        ];
    }

    /** @dataProvider providePlatformCharges */
    public function testPlatformCharges_accountCodes(string $class, string $platform, string $custId, string $companyName, string $paymentInto): void
    {
        /** @var PlatformCharges $charges */
        $charges = new $class(1.5);

        $this->assertInstanceOf(PlatformCharges::class, $charges);
        $this->assertInstanceOf(PaymentCharges::class, $charges);
        $this->assertNotInstanceOf(DirectCharges::class, $charges);
        $this->assertSame($platform, constant($class . '::PLATFORM'));
        $this->assertSame($platform, $charges->getPlatform());
        $this->assertSame($custId, $charges->getCustId());
        $this->assertSame($companyName, $charges->getCompanyName());
        $this->assertSame($paymentInto, $charges->getPaymentInto());
        $this->assertSame(1.5, $charges->getAmount());
        $this->assertSame('Platform', $class::PAYMENT_METHOD);
    }

    /** @return array<string, array<int, string>> */
    public function provideDirectCharges(): array
    {
        // class, PAYMENT_METHOD, custId, companyName, paymentInto
        return [
            'BankIn' => [BankIn::class, 'BankIn', '300-C0004', 'CASH A/C', '310-000'],
            'Cash' => [Cash::class, 'Cash', '300-C0004', 'CASH A/C', '320-000'],
        ];
    }

    /** @dataProvider provideDirectCharges */
    public function testDirectCharges_accountCodes(string $class, string $paymentMethod, string $custId, string $companyName, string $paymentInto): void
    {
        /** @var DirectCharges $charges */
        $charges = new $class(2.5);

        $this->assertInstanceOf(DirectCharges::class, $charges);
        $this->assertInstanceOf(PaymentCharges::class, $charges);
        $this->assertNotInstanceOf(PlatformCharges::class, $charges);
        $this->assertSame($paymentMethod, constant($class . '::PAYMENT_METHOD'));
        $this->assertSame($paymentMethod, $charges->getPaymentMethod());
        $this->assertSame($custId, $charges->getCustId());
        $this->assertSame($companyName, $charges->getCompanyName());
        $this->assertSame($paymentInto, $charges->getPaymentInto());
        $this->assertSame(2.5, $charges->getAmount());
    }

    // ---------------------------------------------------------------- amount

    public function testSetAmount_roundsToTwoDecimals(): void
    {
        $charges = new Lazada(0.0);

        $charges->setAmount(1.236);
        $this->assertSame(1.24, $charges->getAmount());

        $charges->setAmount(1.234);
        $this->assertSame(1.23, $charges->getAmount());
    }

    public function testSetAmount_zero_isAccepted(): void
    {
        $charges = new BankIn(5.0);
        $charges->setAmount(0.0);

        $this->assertSame(0.0, $charges->getAmount());
    }

    public function testSetAmount_negative_throwsInvalidArgumentException(): void
    {
        $charges = new Shopee(1.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid charges amount: -0.01');
        $charges->setAmount(-0.01);
    }

    public function testConstructor_negativeAmount_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid charges amount: -1');
        new Cash(-1.0);
    }

    // ---------------------------------------------------------------- registry

    public function testTypeOfCharges_lazadaEntry_usedByCallersAsPlatformName(): void
    {
        // AutoFilling::getLzdRecords, Cashsale and the request handlers compare against this entry
        $this->assertSame('Lazada', PlatformCharges::TYPE_OF_CHARGES['Lazada']);
        $this->assertSame(Lazada::PLATFORM, PlatformCharges::TYPE_OF_CHARGES['Lazada']);
    }

    /** PlatformCharges.php:7-15 - the registry as it is today (typo keys, two subclasses missing). */
    public function testTypeOfCharges_documentsCurrentBehaviour(): void
    {
        $this->assertSame([
            'Lazada' => 'Lazada',
            'Street11' => '11Street',
            'Netpay' => 'Netpay',
            'GHL' => 'GHL',
            'Shopee' => 'Shopee',
            'Lazada_Eplush' => 'Lazada_Eplus',
            'Shopee_Eplush' => 'Shopee_Eplus',
        ], PlatformCharges::TYPE_OF_CHARGES);
        $this->assertSame('\Orders\PaymentCharges\\', PlatformCharges::NAMESPACE);
    }

    public function testGetPlatformCharges_registryKey_buildsSubclassWithAmount(): void
    {
        $charges = PlatformCharges::getPlatformCharges('Lazada', 1.5);
        $this->assertInstanceOf(Lazada::class, $charges);
        $this->assertSame(1.5, $charges->getAmount());

        $street = PlatformCharges::getPlatformCharges('Street11');
        $this->assertInstanceOf(Street11::class, $street);
        $this->assertSame(0.0, $street->getAmount());
    }

    public function testGetPlatformCharges_unknownPlatform_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No such PlatformCharges: Zalora.');
        PlatformCharges::getPlatformCharges('Zalora');
    }

    /**
     * PlatformCharges.php:25-31 - the lookup is by registry KEY, so the PLATFORM value stored in
     * `orders.platformCharges` for 11Street ('11Street') is rejected although the class is registered.
     */
    public function testGetPlatformCharges_street11PlatformValue_documentsCurrentBehaviour(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No such PlatformCharges: 11Street.');
        PlatformCharges::getPlatformCharges(Street11::PLATFORM);
    }

    /** PlatformCharges.php:13 - the typo key resolves to a class that does not exist -> PHP Error. */
    public function testGetPlatformCharges_typoKeyLazadaEplush_documentsCurrentBehaviour(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Lazada_Eplush');
        PlatformCharges::getPlatformCharges('Lazada_Eplush');
    }

    /** PlatformCharges.php:7-15 - TikTok_Eplus / Lazada_Paling_Best are not registered at all. */
    public function testGetPlatformCharges_unregisteredSubclass_documentsCurrentBehaviour(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No such PlatformCharges: TikTok_Eplus.');
        PlatformCharges::getPlatformCharges(TikTok_Eplus::PLATFORM);
    }

    // ---------------------------------------------------------------- known bugs

    /**
     * @group known-bug
     * PlatformCharges.php:7-15 - every TYPE_OF_CHARGES key must be the short name of an existing subclass
     * (getPlatformCharges() builds the class name from the key). 'Lazada_Eplush' / 'Shopee_Eplush' are typos.
     */
    public function testTypeOfCharges_keysAreExistingClassShortNames(): void
    {
        foreach (PlatformCharges::TYPE_OF_CHARGES as $key => $platform) {
            $class = 'Orders\\PaymentCharges\\' . $key;
            $this->assertTrue(class_exists($class), "TYPE_OF_CHARGES key '{$key}' is not a class");
            $this->assertSame(constant($class . '::PLATFORM'), $platform, "value for key '{$key}' must equal {$class}::PLATFORM");
        }
    }

    /**
     * @group known-bug
     * PlatformCharges.php:7-15 - every concrete PlatformCharges subclass must be listed (TikTok_Eplus and
     * Lazada_Paling_Best are missing, so RecordEditor / Cashsale cannot map rows stored with those platforms).
     * @dataProvider providePlatformCharges
     */
    public function testTypeOfCharges_listsEveryConcreteSubclass(string $class, string $platform): void
    {
        $shortName = substr($class, strrpos($class, '\\') + 1);

        $this->assertArrayHasKey($shortName, PlatformCharges::TYPE_OF_CHARGES);
        $this->assertContains($platform, PlatformCharges::TYPE_OF_CHARGES);
    }

    /**
     * @group known-bug
     * PlatformCharges.php:25-31 - getPlatformCharges() must resolve the PLATFORM constant of every concrete
     * subclass, because that is the value persisted in `orders.platformCharges` and read back by
     * CashsaleText (Cashsale.php:177). Today only Lazada / Netpay / GHL / Shopee (key == value) work.
     * @dataProvider providePlatformCharges
     */
    public function testGetPlatformCharges_everyPlatformConstant_isResolvable(string $class, string $platform): void
    {
        $charges = PlatformCharges::getPlatformCharges($platform, 2.0);

        $this->assertInstanceOf($class, $charges);
        $this->assertSame(2.0, $charges->getAmount());
    }
}
