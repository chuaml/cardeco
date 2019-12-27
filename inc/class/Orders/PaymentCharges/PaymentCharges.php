<?php 
namespace Orders\PaymentCharges;

use \Exception;
use \InvalidArgumentException;

//the top level abstract PaymentCharges class
//DirectCharges and PlatformCharges extends from it
//Cash, BankIn extend DirectCharges
//Lazada, 11Street, Shopee, ... extend PlatformCharges
abstract class PaymentCharges{
    private $amount = 0.00;
    protected $custId = '';
	protected $companyName = '';
	protected $paymentInto = '';

    public final function setAmount(float $amount):void{
        if($amount < 0.00){
            throw new InvalidArgumentException(
                "invalid charges amount: $amount");
        }

        $this->amount = round($amount,2);
    }

    public final function getAmount():float{
        return $this->amount;
    }

    public final function getCustId():string{
        return $this->custId;
    }

    public final function getCompanyName():string{
        return $this->companyName;
    }

    public final function getPaymentInto():string{
        return $this->paymentInto;
    }
    
}
