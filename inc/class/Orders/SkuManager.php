<?php 
namespace Orders;

require_once(__DIR__ .'/../Database/Insertable.php');
require_once(__DIR__ .'/../Database/Deletable.php');

use \mysqli;
use \Exception;
use \InvalidArgumentException;
use \Database\Insertable;
use \Database\Deletable;

class SkuManager implements Insertable, Deletable{

    private $COL;
    private $fields;
    private $param;
    private $dataType;

    public function __construct(){
        $this->COL = [
            'sku' => 's',
            'itemCode' => 's'
        ];
        $this->fields = implode(',', \array_keys($this->COL));
        $this->param = implode(',', \array_fill(0, count($this->COL), '?'));
        $this->dataType = implode('', $this->COL);
    }

    public function isItemCodeExists(mysqli $con, array $itemCode):bool{
        $len = count($itemCode);
        if($len === 0){
            throw new \UnexpectedValueException('empty itemCode array size.');
        }

        $param = implode(',', \array_fill(0, $len, '?'));
        $dataType = implode('', \array_fill(0, $len, 's'));

        $stmt = $con->prepare(
            'SELECT COUNT(*) FROM '
            ."(SELECT DISTINCT(item_code) FROM stock_items WHERE item_code IN ({$param}))AS s;"
        );

        $stmt->bind_param($dataType, ...$itemCode);
        if(!($stmt->execute())){
            throw new Exception($stmt->error);
        }

        $result = $stmt->get_result();
        $count = 0;
        if(($r = $result->fetch_row()) !== null){
            $count = $r[0];
        }
        $stmt->free_result();
        $stmt->close();

        return $count === $len;
    }

    public function isSkuExists(mysqli $con, array $sku):bool{
        $len = count($sku);
        if($len === 0){
            throw new \UnexpectedValueException('empty sku array size.');
        }

        $param = implode(',', \array_fill(0, $len, '?'));
        $dataType = implode('', \array_fill(0, $len, 's'));

        $stmt = $con->prepare(
            "SELECT COUNT(id) FROM seller_sku WHERE sku IN ({$param})"
        );
        
        $stmt->bind_param($dataType, ...$sku);
        if(!($stmt->execute())){
            throw new Exception($stmt->error);
        }

        $result = $stmt->get_result();
        $count = 0;
        if(($r = $result->fetch_row()) !== null){
            $count = (int)$r[0];
        }
        $stmt->free_result();
        $stmt->close();

        return $count > 0;
    }

    private function trimData(array &$kSku_vItemCode):array{
        $data = [];
        foreach($kSku_vItemCode as $sku => $itemCode){
            $data[trim($sku)] = trim($itemCode);
        }
        return $data;
    }

    private function validateData(mysqli $con, array &$kSku_vItemCode):void{
        foreach($kSku_vItemCode as $sku => $itemCode){
            if(strlen($sku) === 0){
                throw new \UnexpectedValueException('sku is empty.');
            }
            if(strlen($itemCode) === 0){
                throw new \UnexpectedValueException('itemCode is empty.');
            }
        }
        if($this->isSkuExists($con, array_keys($kSku_vItemCode))){
            throw new Exception('sku exists');
        }
    }

    public function insert(mysqli $con, array &$kSku_vItemCode):void{
        if(count($kSku_vItemCode) === 0){
            return;
        }
        $data = $this->trimData($kSku_vItemCode);
        $this->validateData($con, $data);
        if(!($this->isItemCodeExists($con, array_values($data)))){
            throw new \Exception("item code doesn't exists.");
        }

        $stmt = $con->prepare(
            "INSERT INTO seller_sku({$this->fields}) VALUES({$this->param});"
        );

        $sku; $itemCode;
        $stmt->bind_param($this->dataType, $sku, $itemCode);
        foreach($data as $SKU => $ITEMCODE){
            $sku = $SKU;
            $itemCode = $ITEMCODE;
            if(!($stmt->execute())){
                throw new Exception($stmt->error);
            }
        }
        $stmt->close();
    }

    public function delete(mysqli $con, array &$sku):void{
        $len = count($sku);
        if($len === 0){
            return;
        }

        $dataType = implode('',array_fill(0, $len, 's'));
        $param = implode(',', array_fill(0, $len, '?'));
        $stmt = $con->prepare(
            "DELETE FROM seller_sku WHERE sku IN ({$param});"
        );

        $stmt->bind_param($dataType, ...$sku);
        if(!($stmt->execute())){
            throw new Exception($stmt->error);
        }
        $stmt->close();
    }
}