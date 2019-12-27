<?php
namespace Orders\PaymentCharges;

require_once(__DIR__ .'\\PaymentCharges.php');;

abstract class DirectCharges extends PaymentCharges
{
    private $paymentMethod = '';

    public function getPaymentMethod():string
    {
        return $this->paymentMethod;
    }

    final protected function setPaymentMethod(string $paymentMethod):void
    {
        $this->paymentMethod = $paymentMethod;
    }
}

//constants as sub classes
//for MonthlyRecord to accept any DirectCharges subclass object
//type of platform charges constants

final class BankIn extends DirectCharges
{
    const PAYMENT_METHOD = 'BankIn';
    public function __construct(float $amount)
    {
        $this->setPaymentMethod(self::PAYMENT_METHOD);
        $this->setAmount($amount);
        $this->custId = '300-C0004';
        $this->companyName = 'CASH A/C';
        $this->paymentInto = '310-000';
    }
}

final class Cash extends DirectCharges
{
    const PAYMENT_METHOD = 'Cash';
    public function __construct(float $amount)
    {
        $this->setPaymentMethod(self::PAYMENT_METHOD);
        $this->setAmount($amount);
        $this->custId = '300-C0004';
        $this->companyName = 'CASH A/C';
        $this->paymentInto = '320-000';
    }
}
