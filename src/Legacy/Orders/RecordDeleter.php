<?php 
namespace Orders;

use \Orders\MonthlyRecord;

class RecordDeleter {

    public function deleteByInsertedId(\mysqli $con, int $insertedId):void{
        $stmt = $con->prepare(
            'DELETE FROM orders WHERE insertLogId = ?; '
        );

        $stmt->bind_param('i', $insertedId);
        if(!($stmt->execute())){
            throw new \Exception($stmt->error);
        }
        $stmt->close();
        
        $stmt = $con->prepare(
            'DELETE FROM orders_insert_log WHERE id = ?; '
        );

        $stmt->bind_param('i', $insertedId);
        if(!($stmt->execute())){
            throw new \Exception($stmt->error);
        }
        $stmt->close();
    }

    public function selectAllOrdersInsertLog(\mysqli $con):array{
        $LIMIT = 50;
        $stmt = $con->prepare(
            "SELECT id, fileName FROM orders_insert_log ORDER BY id DESC LIMIT {$LIMIT};"
        );
        if(!($stmt->execute())){
            throw new \Exception($stmt->error);
        }
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
