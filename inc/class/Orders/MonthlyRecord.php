<?php
namespace Orders;

require_once(__DIR__ . '/Record.php');
require_once(__DIR__ . '/PaymentCharges/DirectCharges.php');
require_once(__DIR__ . '/PaymentCharges/PlatformCharges.php');
require_once(__DIR__ . '/../Product/Item.php');


use \Orders\PaymentCharges;
use \Orders\PaymentCharges\PlatformCharges;
use \Orders\PaymentCharges\DirectCharges;
use \Product\Item;

class MonthlyRecord extends Record
{
    private $billno = '';
    //private $grandTotal = 0.00;
    private $dateStockOut = '';
    private $transferCharges = 0.00;

    private $PlatformCharges = null;
    private $DirectCharges = [];

    public function __construct(?int $recordId = null, ?string $orderId = null, ?Item $Item = null)
    {
        parent::__construct($recordId, $orderId, $Item);
    }

    public function __get(string $property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
        return parent::__get($property);
    }

    public function getGrandTotal():float
    {
        return parent::__get('sellingPrice') + parent::__get('shippingFeeByCust') - $this->voucher;
    }

    public function setBillNo(?string $billno):void
    {
        $this->billno = $billno;
    }

    public function setDateStockOut(?string $date):void
    {
        if ($date === null) {
            $this->dateStockOut = $date;
        } else {
            $this->dateStockOut = parent::formatDate($date);
        }
    }
    public function setTransferCharges(float $amount):void
    {
        $this->transferCharges = $amount;
    }

    public function setPlatformCharges(PlatformCharges $PlatformCharges):void
    {
        $this->PlatformCharges = $PlatformCharges;
    }

    public function getPlatformCharges():?PlatformCharges
    {
        return $this->PlatformCharges;
    }

    public function setDirectCharges(DirectCharges $DirectCharges):void
    {
        $this->DirectCharges[$DirectCharges->getPaymentMethod()] = $DirectCharges;
    }

    public function removeAllDirectCharges():void
    {
        $last = count($this->DirectCharges) -1;
        for ($i=$last;$i>0;--$i) {
            unset($this->DirectCharges[$i]);
        }
    }

    public function getDirectCharges(string $paymentMethod):?DirectCharges
    {
        if (array_key_exists($paymentMethod, $this->DirectCharges) === true) {
            return $this->DirectCharges[$paymentMethod];
        }

        return null;
    }

    public function getSumDirectChargesAmount():float
    {
        $totalAmount = 0.00;
        if ($this->DirectCharges === null) {
            return $totalAmount;
        }

        foreach ($this->DirectCharges as $DirectCharge) {
            $totalAmount += $DirectCharge->getAmount();
        }

        return $totalAmount;
    }

    public function getAll():array
    {
        $PlatformCharges = null;
        $PlatformChargesAmount = null;
        if ($this->PlatformCharges !== null) {
            $PlatformCharges = $this->PlatformCharges->getPlatform();
            $PlatformChargesAmount = $this->PlatformCharges->getAmount();
        }
        $directChargesBankIn = 0.00;
        $directChargesCash = 0.00;

        if (sizeof($this->DirectCharges) > 0) {
            if (\array_key_exists(\Orders\PaymentCharges\BankIn::PAYMENT_METHOD, $this->DirectCharges)) {
                $directChargesBankIn = $this->DirectCharges['BankIn']->getAmount();
            }
            if (\array_key_exists(\Orders\PaymentCharges\Cash::PAYMENT_METHOD, $this->DirectCharges)) {
                $directChargesCash = $this->DirectCharges['Cash']->getAmount();
            }
        }
        return \array_merge(parent::getAll(), [
            'billno' => $this->billno,
            'grandTotal' => $this->getGrandTotal(),
            'dateStockOut' => $this->dateStockOut,
            'transferCharges' => $this->transferCharges,

            'platformCharges' => $PlatformCharges,
            'platformChargesAmount' => $PlatformChargesAmount,
            'bankIn' => $directChargesBankIn,
            'cash' => $directChargesCash
        ]);
    }
}
