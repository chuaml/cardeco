<?php 
namespace Product;

use \Product\Item;

class ItemManager{

    private $con;

    public function __construct(\mysqli $con){
        $this->con = $con;
    }

    public function update(array $Items):void{
        $stmt = $this->con->prepare(
            'UPDATE stock_items SET description = ? WHERE id = ?;'
        );
        $stmt->bind_param('si', $description, $id);
        foreach($Items as $Item){
            $description = $Item->description;
            $id = $Item->itemId;
            if(!($stmt->execute())){
                throw new \Exception($stmt->error);
            }
        }
        $stmt->close();
    }

    public function getItemLikeItemCode(string &$itemCode):array{
        $LIMIT = 25;
        $stmt = $this->con->prepare(
            'SELECT id, item_code, description, uom, item_group FROM stock_items '
            ."WHERE item_code LIKE ? LIMIT {$LIMIT}"
        );
        $item_code = '%' . trim($itemCode) . '%';
        $stmt->bind_param('s', $item_code);
        if(!($stmt->execute())){
            throw new \Exception($stmt->error);
        }
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $list = [];
        foreach($result as $r){
            $list[] = new Item(
                $r['id'], $r['item_code'], $r['description'], $r['uom'], $r['item_group']
            );
        }
        return $list;
    }
}
