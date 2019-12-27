<?php 
namespace Product\Manager;

use mysqli;
use Exception;

class ItemManager{
    
    private $con;

    public function __construct(mysqli $con){
        $this->con = $con;
    }

    public function selectByItemCode(array $itemCodes):array{
        $len = count($itemCodes);
        if($len === 0){return [];}

        $param = implode(',', \array_fill(0, $len, '?'));
        $dataType = implode('', \array_fill(0, $len, 's'));
        $stmt = $this->con->prepare(
            'SELECT * FROM stock_items INNER JOIN stock '
            .'ON stock_items.id = stock.id '
            ."WHERE stock_items.item_code IN ({$param})"
        );

        $stmt->bind_param($dataType, ...$itemCodes);
        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
 