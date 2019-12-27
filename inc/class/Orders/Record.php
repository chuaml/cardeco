<?php 
namespace Orders;

require_once(__DIR__ .'/../HTML/EscapableData.php');
require_once(__DIR__ . '/../Product/Item.php');

use \HTML\EscapableData;
use \Exception;
use \InvalidArgumentException;
use \Product\Item;

class Record{
    private $recordId;
    private $orderNum = '';
    private $date = '';
    private $trackingNum = '';
    private $status = '';
    private $sellingPrice = 0.00;
    private $voucher = 0.00;
    private $shippingFee = 0.00; //paid by seller
    private $shippingFeeByCust = 0.00;
    private $shippingState = '';
    private $shippingWeight = 0.00;

    private $shippingFeeByWeight = 0.00; //1 orderNum has 1 trackingNum.shippingFeeByWeight, require refactor
    
    private $Item;

    const DATE_FORMAT = 'd-M-Y';
    const DECIMAL_PLACES = 2;
    const INVALID_ORDER_NUM = '/[^a-zA-Z0-9]/';
    public function __construct(?int $recordId = null, ?string $orderNum = null, ?Item $Item = null){
        $this->recordId = $recordId;
        $this->orderNum = $orderNum;
        $this->Item = $Item;
    }

    public function __get(string $property){
       return $this->$property;
    }

    public function setDate(string $date):void{
        $this->date = $this->formatDate($date);
    }

    protected static final function formatDate(string $date):string{
        //use - instead of / 
        //php treat date with / as M/d/Y where - is always Y-m-d or d-m-Y
        $d = date_create(str_replace('/', '-', $date));
        if(!$d){
            throw new InvalidArgumentException("invalid date format $date ");
        }
        return date_format($d, self::DATE_FORMAT);
    }

    public function setOrderNum(string $orderNum):void{
        if(\preg_match(self::INVALID_ORDER_NUM, $orderNum) === 1){
            throw new \InvalidArgumentException("invalid order number: {$orderNum}.");
        }
        $this->orderNum = trim($orderNum);
    }

    public function setTrackingNum(string $trackingNum):void{
        $this->trackingNum = trim($trackingNum);
    }

    public function setStatus(string $status):void{
        $this->status = strtoupper(trim($status));
    }

    public function setSellingPrice(float $price):void{
        if($price < 0.00){
            throw new InvalidArgumentException(
                "invalid selling price: {$price}.");
        }

        $this->sellingPrice = round($price, self::DECIMAL_PLACES);
    }

    public function setVoucher(float $amount):void{
        $this->voucher = $amount;
    }
    
    public function setShippingFee(float $amount):void{
        if($amount < 0.00){
            throw new InvalidArgumentException(
                "invalid shipping fee: {$amount}.");
        }
        $this->shippingFee = round($amount, self::DECIMAL_PLACES);
    }

    public function setShippingFeeByCust(float $amount):void{
        if($amount < 0.00){
            throw new InvalidArgumentException(
                "invalid shipping fee by cust: {$amount}.");
        }
        $this->shippingFeeByCust = round($amount, self::DECIMAL_PLACES);
    }

    public function setShippingState(string $name):void{
        $this->shippingState = trim($name);
    }

    public function setShippingWeight(float $amount):void{
        if($amount < 0.00){
            throw new InvalidArgumentException(
                "invalid shipping weight : {$amount}.");
        }
        $this->shippingWeight = round($amount, self::DECIMAL_PLACES);
    }

    public function setShippingFeeByWeight(float $amount):void{
        if($amount < 0.00){
            throw new InvalidArgumentException(
                "invalid shipping weight : {$amount}.");
        }
        $this->shippingFeeByWeight = round($amount, self::DECIMAL_PLACES);
    }

    public function getEscapedData(string $property):string{
        return htmlspecialchars($this->__get($property), ENT_QUOTES, 'UTF-8');
    }

    public function getItem():?Item{
        $Item;
        if($this->Item === null){
            $Item = $this->Item;
        } else {
            $Item = clone $this->Item;
        }
        return $Item;
    }

    public function getAll():array{
        $itemCode;
        $itemName;
        if($this->Item === null){
            $itemCode = $this->Item;
            $itemName = $this->Item;
        }else{
            $itemCode = $this->Item->code;
            $itemName = $this->Item->description;
        }
        return [
            'recordId' => $this->recordId,
            'orderNum' => $this->orderNum,
            'date' => $this->date,
            'sku' => $itemCode,
            'itemName' => $itemName,
            'trackingNum' => $this->trackingNum,
            'status' => $this->status,
            'sellingPrice' => $this->sellingPrice,
            'voucher' => $this->voucher,
            'shippingFee' => $this->shippingFee,
            'shippingFeeByCust' => $this->shippingFeeByCust,
            'shippingState' => $this->shippingState,
            'shippingWeight' => $this->shippingWeight,
            'shippingFeeByWeight' => $this->shippingFeeByWeight
        ];
    }
}
