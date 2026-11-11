<?php 
namespace Product;

use Exception;
use \Product\Item;

class ItemManager{

    /** Mapping key posted by ItemEditor for an item that has no bigseller_sku_map row yet. */
    const NEW_MAPPING_KEY = 'new';

    /** @var \mysqli */
    private $con;

    public function __construct(\mysqli $con){
        $this->con = $con;
    }

    /**
     * Builds the Item list for update() from the /ItemManager form:
     *   $descriptions = $_POST['r']                 : item id => description
     *   $mappings     = $_POST['bigseller_sku_map'] : item id => [ mapping id | 'new' => bigseller sku ]
     * Mappings are matched to items by item id (never by position); mappings for unknown items are ignored.
     *
     * @param array<int|string, string> $descriptions
     * @param array<int|string, array<int|string, string>> $mappings
     * @return Item[]
     */
    public static function itemsFromRequest(array $descriptions, array $mappings): array{
        $items = [];
        foreach($descriptions as $itemId => $description){
            $items[(int) $itemId] = new Item((int) $itemId, null, (string) $description);
        }
        foreach($mappings as $itemId => $itemMappings){
            if(!is_array($itemMappings) || !array_key_exists((int) $itemId, $items)){
                continue;
            }
            foreach($itemMappings as $mappingId => $sku){
                $items[(int) $itemId]->bigseller_sku_map[] = [
                    'id' => ($mappingId === self::NEW_MAPPING_KEY || $mappingId === '') ? null : (int) $mappingId,
                    'bigseller_sku' => is_string($sku) ? $sku : ''
                ];
            }
        }

        return array_values($items);
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

        $owner = $this->con->prepare('SELECT item_id FROM bigseller_sku_map WHERE id = ?');
        $stmt = $this->con->prepare(
            'insert into bigseller_sku_map(id, bigseller_sku, item_id) values(?, ?, ?) '
            . 'on duplicate key update bigseller_sku = ? , item_id = ?;'
        );
        foreach($Items as $Item){
            $list = $Item->bigseller_sku_map;
            foreach($list as $bigseller_sku_map) {
                if($bigseller_sku_map['bigseller_sku'] === null || $bigseller_sku_map['bigseller_sku'] === '') continue;

                $mappingId = $bigseller_sku_map['id'];
                if($mappingId !== null){
                    // the upsert is keyed on the primary key: refuse to touch a mapping row that belongs to another item
                    $owner->bind_param('i', $mappingId);
                    $owner->execute();
                    $row = $owner->get_result()->fetch_assoc();
                    if($row === null || (int) $row['item_id'] !== (int) $Item->itemId){
                        throw new \Exception("bigseller_sku_map {$mappingId} does not belong to item {$Item->itemId}");
                    }
                }

                $stmt->bind_param('isisi', 
                    $mappingId,
                    $bigseller_sku_map['bigseller_sku'],
                    $Item->itemId,
                    $bigseller_sku_map['bigseller_sku'],
                    $Item->itemId
                );
                $stmt->execute();
            }
        }
        $stmt->close();
        $owner->close();
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
