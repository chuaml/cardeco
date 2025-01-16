<?php 
namespace Product;

use Exception;
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

        $stmt = $this->con->prepare(
            'insert into bigseller_sku_map(id, bigseller_sku, item_id) values(?, ?, ?) '
            . 'on duplicate key update bigseller_sku = ? , item_id = ?;'
        );
        foreach($Items as $Item){
            $list = $Item->bigseller_sku_map;
            foreach($list as $bigseller_sku_map) {
                if($bigseller_sku_map['bigseller_sku'] === null || $bigseller_sku_map['bigseller_sku'] === '') continue;
                $stmt->bind_param('isisi', 
                    $bigseller_sku_map['id'],
                    $bigseller_sku_map['bigseller_sku'],
                    $Item->itemId,
                    $bigseller_sku_map['bigseller_sku'],
                    $Item->itemId
                );
                try{
                    $stmt->execute();
                } finally {
                    $Item;
                }
            }
        }
        $stmt->close();
    }

    public function getItemLikeItemCode(string &$itemCode):array{
        $LIMIT = 200;
        $stmt = $this->con->prepare(
            'SELECT s.id, s.item_code, s.description, s.uom, s.item_group, b.id as bigseller_sku_map_id , b.bigseller_sku FROM stock_items as s '
            . 'left join bigseller_sku_map as b on b.item_id = s.id '
            . 'WHERE s.item_code LIKE ? '
            . 'order by s.id asc '
            . "LIMIT $LIMIT"
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
            $x = new Item(
                $r['id'], $r['item_code'], $r['description'], $r['uom'], $r['item_group']
            );
            $x->bigseller_sku_map[] = [
                'id' => $r['bigseller_sku_map_id'], 
                'bigseller_sku' => $r['bigseller_sku']
            ];
            $list[] = $x;
        }
        return $list;
    }

    public function getItem():array{
        $LIMIT = 200;
        $stmt = $this->con->prepare(
            'SELECT s.id, s.item_code, s.description, s.uom, s.item_group, b.id as bigseller_sku_map_id , b.bigseller_sku FROM stock_items as s '
            . 'left join bigseller_sku_map as b on b.item_id = s.id '
            . 'order by s.id asc '
            . "LIMIT $LIMIT"
        );
        if(!($stmt->execute())){
            throw new \Exception($stmt->error);
        }
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $list = [];
        foreach($result as $r){
            $x = new Item(
                $r['id'], $r['item_code'], $r['description'], $r['uom'], $r['item_group']
            );
            $x->bigseller_sku_map[] = [
                'id' => $r['bigseller_sku_map_id'], 
                'bigseller_sku' => $r['bigseller_sku']
            ];
            $list[] = $x;
        }
        return $list;
    }
}
