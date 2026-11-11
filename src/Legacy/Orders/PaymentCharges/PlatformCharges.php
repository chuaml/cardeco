<?php 
namespace Orders\PaymentCharges;

//for specifying PlateformCharges object
abstract class PlatformCharges extends PaymentCharges{
    const PAYMENT_METHOD = 'Platform';
    /**
     * Registry of concrete subclasses: key = class short name (used to instantiate), value = PLATFORM constant
     * (the string persisted in `orders.platformCharges`). Both must stay in sync with the classes below.
     */
    const TYPE_OF_CHARGES = [
        'Lazada' => 'Lazada',
        'Street11' => '11Street', 
        'Netpay' => 'Netpay', 
        'GHL' => 'GHL', 
        'Shopee' => 'Shopee',
        'Lazada_Eplus' => 'Lazada_Eplus',
        'Shopee_Eplus' => 'Shopee_Eplus',
        'TikTok_Eplus' => 'TikTok_Eplus',
        'Lazada_Paling_Best' => 'Lazada_Paling_Best'
    ]; //keep track of child class for listing
    const NAMESPACE = '\Orders\PaymentCharges\\';

    /** @var string */
    protected $platform = '';

    public final function getPlatform():string{
        return $this->platform;
    }

    /**
     * @param string $platform a PLATFORM constant as stored in `orders.platformCharges` (e.g. '11Street',
     *                         'Lazada_Eplus'); the class short name (e.g. 'Street11') is accepted as well
     */
    public static final function getPlatformCharges(string $platform, float $amount = 0.00):PlatformCharges{
        $className = \array_search($platform, self::TYPE_OF_CHARGES, true);
        if($className === false && \array_key_exists($platform, self::TYPE_OF_CHARGES)){
            $className = $platform;
        }
        if($className !== false){
            $Platform = self::NAMESPACE .$className;
            return new $Platform($amount);
        }
        throw new \InvalidArgumentException("No such PlatformCharges: {$platform}.");
    }

    protected final function setPlatform(string $platform):void{
        $this->platform = $platform;
    }
}

//constants as sub classes
//for MonthlyRecord to accept PlatformCharges or any of the subclass object
//type of platform charges constants
final class Lazada extends PlatformCharges{
    const PLATFORM = 'Lazada';

    public function __construct(float $amount){
        $this->setPlatform(self::PLATFORM);
        $this->setAmount($amount);
        $this->custId = '300-C0006';
        $this->companyName = 'CASH A/C - LAZADA (CAR DECO)';
        $this->paymentInto = '321-000';
    }
    
}

final class Street11 extends PlatformCharges{
    const PLATFORM = '11Street';

    public function __construct(float $amount){
        $this->setPlatform(self::PLATFORM);
        $this->setAmount($amount);
        $this->custId = '300-C0005';
        $this->companyName = 'CASH A/C - 11 STREET';
        $this->paymentInto = '319-000';
    }
}

final class Netpay extends PlatformCharges{
    const PLATFORM = 'Netpay';

    public function __construct(float $amount){
        $this->setPlatform(self::PLATFORM);
        $this->setAmount($amount);
        $this->custId = '300-C0001';
        $this->companyName = 'CASH A/C - LELONG';
        $this->paymentInto = '315-000';
    }
}

final class GHL extends PlatformCharges{
    const PLATFORM = 'GHL';

    public function __construct(float $amount){
        $this->setPlatform(self::PLATFORM);
        $this->setAmount($amount);
        $this->custId = '300-C0003';
        $this->companyName = 'CASH A/C - CARDECO.COM.MY';
        $this->paymentInto = '322-000';
    }
}

final class Shopee extends PlatformCharges{
    const PLATFORM = 'Shopee';

    public function __construct(float $amount){
        $this->setPlatform(self::PLATFORM);
        $this->setAmount($amount);
        $this->custId = '300-C0008';
        $this->companyName = 'CASH A/C - SHOPEE (CAR DECO)';
        $this->paymentInto = '324-000';
    }
}

final class Lazada_Eplus extends PlatformCharges{
    const PLATFORM = 'Lazada_Eplus';

    public function __construct(float $amount){
        $this->setPlatform(self::PLATFORM);
        $this->setAmount($amount);
        $this->custId = '300-C0010';
        $this->companyName = 'CASH A/C - LAZADA (E PLUS)';
        $this->paymentInto = '321-100';
    }
    
}

final class Lazada_Paling_Best extends PlatformCharges{
    const PLATFORM = 'Lazada_Paling_Best';

    public function __construct(float $amount){
        $this->setPlatform(self::PLATFORM);
        $this->setAmount($amount);
        $this->custId = '300-C0014';
        $this->companyName = 'CASH A/C - LAZADA (PALING BEST)';
        $this->paymentInto = '321-200';
    }
    
}

final class Shopee_Eplus extends PlatformCharges{
    const PLATFORM = 'Shopee_Eplus';

    public function __construct(float $amount){
        $this->setPlatform(self::PLATFORM);
        $this->setAmount($amount);
        $this->custId = '300-C0011';
        $this->companyName = 'CASH A/C - SHOPEE (E PLUS)';
        $this->paymentInto = '324-100';
    }
    
}


final class TikTok_Eplus extends PlatformCharges{
    const PLATFORM = 'TikTok_Eplus';

    public function __construct(float $amount){
        $this->setPlatform(self::PLATFORM);
        $this->setAmount($amount);
        $this->custId = '300-C0013';
        $this->companyName = 'CASH A/C - TIKTOK (E PLUS)';
        $this->paymentInto = '325-000';
    }
    
}
