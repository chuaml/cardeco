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

    public function selectByItemCode_withBigSellerSku(array $itemCodes): array
    {
        $len = count($itemCodes);
        if ($len === 0) {
            return [];
        }

        $param = implode(',', \array_fill(0, $len, '?'));
        $dataType = implode('', \array_fill(0, $len, 's'));
        $stmt = $this->con->prepare(
            'SELECT * FROM stock_items si ' 
            . 'INNER JOIN stock s ON si.id = s.id '
            . 'left join bigseller_sku_map b ON b.item_id = si.id '
            . "WHERE si.item_code IN ({$param}) "
            . "OR b.bigseller_sku IN ($param)"
        );

        $stmt->bind_param($dataType . $dataType, ...$itemCodes, ...$itemCodes); // x2 parameters
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
 