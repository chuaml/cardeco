<?php 
namespace Lazada;

class FeeStatement{
    private $transactionDate = null;
    private $feeType = null;
    private $feeName = null;
    private $description = null;
    private $itemCode = null;
    private $lzdSku = null;
    private $amount = 0.00;
    private $statementDate = null;
    private $paidStatus = null;
    private $orderNum = null;
    private $itemStatus = null;

    public function getTransactionDate():?string{
        return $this->transactionDate;
    }

    public function getFeeType():?string{
        return $this->feeType;
    }
    public function getFeeName():?string{
        return $this->feeName;
    }
    public function getDescription():?string{
        return $this->description;
    }
    public function getItemCode():?string{
        return $this->itemCode;
    }
    public function getlzdSku():?string{
        return $this->lzdSku;
    }
    public function getAmount():float{
        return $this->amount;
    }
    public function getStatementDate():?string{
        return $this->statementDate;
    }
    public function getPaidStatus():?string{
        return $this->paidStatus;
    }
    public function getOrderNum():?string{
        return $this->orderNum;
    }
    public function getItemStatus():?string{
        return $this->itemStatus;
    }
    
    public function setTransactionDate(string $date):void{
        $Date = date_create(str_replace(' ', '-', $date));
        if($date === null || $Date === null || $Date === false){
            throw new UnexpectedValueException(
                'invalid date format ' .$date);
        }

        $this->transactionDate = \date_format($Date, 'Y-M-d');
    }
    public function setFeeType(string $val):void{
        $this->feeType = trim($val);
    }
    public function setFeeName(string $val):void{
        $this->feeName = trim($val);
    }
    public function setDescription(string $val):void{
        $this->description = trim($val);
    }
    public function setItemCode(string $val):void{
        $this->itemCode = trim($val);
    }
    public function setlzdSku(string $val):void{
        $this->lzdSku = trim($val);
    }
    public function setAmount(float $val):void{
        $this->amount = \doubleval($val);
    }
    public function setStatementDate(string $val):void{
        $this->statementDate = trim($val);
    }
    public function setPaidStatus(string $val):void{
        $this->paidStatus = trim($val);
    }
    public function setOrderNum(string $val):void{
        $this->orderNum = trim($val);
    }
    public function setItemStatus(string $val):void{
        $this->itemStatus = trim($val);
    }
    
}