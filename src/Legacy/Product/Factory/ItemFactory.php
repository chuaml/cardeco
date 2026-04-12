<?php 
namespace Product\Factory;

require_once(__DIR__ .'/../Item.php');

use Product\Item;

class ItemFactory{
    
    public function getData(\mysqli $con, array &$itemCodes):array{
        $len = count($itemCodes);
        if($len === 0){
            throw new \InvalidArgumentException('empty list of itemCodes.');
        }
        $dataType = implode('', array_fill(0, $len, 's'));
        $param = implode(',', array_fill(0, $len, '?'));
        $stmt = $con->prepare(
            'SELECT id, item_code, description, uom, item_group FROM stock_items '
            ."WHERE item_code IN({$param})"
        );
        $stmt->bind_param($dataType, ...$itemCodes);
        if(!($stmt->execute())){
            throw new \Exception($stmt->error);
        }
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close();
        
        return $result;
    }
}
 