<?php 
namespace Orders;

require_once(__DIR__ .'/PaymentCharges/PlatformCharges.php');
require_once(__DIR__ .'/../Database/Insertable.php');
require_once(__DIR__ .'/../Database/Updatable.php');

use \Orders\MonthlyRecord;
use \Orders\PaymentCharges\PlatformCharges;
use \Database\Insertable;
use \Database\Updatable;

//to insert new Records data into db
class RecordInserter implements Insertable, Updatable{

    private $insertLogId; //id from order_insertLog, to identify these batch Records
    private $platformCharges = '';

    private $COL = [
            'orderNum' => 's',
            'date' => 's',
            'sku' => 's',
            'trackingNum' => 's',
            'sellingPrice' => 'd',
            'status' => 's',
            'voucher' => 'd',
            'shippingFee' => 'd',
            'shippingFeeByCust' => 'd',
            'shippingState' => 's',
            'shippingWeight' => 'd'
        ];

    const DATE_FORMAT = 'Y/m/d';

    public function __construct(string &$platformCharges){
        $Chargetypes = PlatformCharges::TYPE_OF_CHARGES;
        if(\array_search($platformCharges, $Chargetypes) === false){
            throw new \InvalidArgumentException("invalid platformCharges type: $platformCharges ");
        }
        $this->platformCharges = $platformCharges;
    }

    public function insert(\mysqli $con, array &$Records):void{
        try{
            $this->insertToTemp($con, $Records);
            $this->commit($con);
        }finally{
            $this->clearTemp($con);
        }
    }

    private function insertToTemp(\mysqli $con, array &$Records):void{
        $col = \implode(',', \array_keys($this->COL));
        $placeholder = \implode(',', \array_fill(0, count($this->COL), '?'));
        $dataType = \implode('', $this->COL);

        $stmt = $con->prepare(
            "INSERT INTO orders_temp(insertLogId, {$col}, platformCharges) VALUES"
            ."(?, {$placeholder}, ?);"
        );
        if(!$stmt){
            throw new \Exception($con->errno . ' ' . $con->error);
        }

        $insertLogId = $this->insertLogId;
        $orderNum;
        $date;
        $sku;
        $trackingNum;
        $sellingPrice;
        $status;
        $voucher;
        $shippingFee;
        $shippingFeeByCust;
        $shippingState;
        $shippingWeight;
        $platformCharges = $this->platformCharges;
        
        $stmt->bind_param("i{$dataType}s", 
            $insertLogId,
            $orderNum,
            $date,
            $sku,
            $trackingNum,
            $sellingPrice,
            $status,
            $voucher,
            $shippingFee,
            $shippingFeeByCust,
            $shippingState,
            $shippingWeight,
            $platformCharges
        );

        foreach($Records as $r){
            $orderNum = $r->orderNum;
            $date = \date_format(\date_create($r->date), self::DATE_FORMAT);
            $sku = $r->Item->code;
            $trackingNum = $r->trackingNum;
            $sellingPrice = $r->sellingPrice;
            $status = $r->status;
            $voucher = $r->voucher;
            $shippingFee = $r->shippingFee;
            $shippingFeeByCust = $r->shippingFeeByCust;
            $shippingState = $r->shippingState;
            $shippingWeight = $r->shippingWeight;
            if(!($stmt->execute())){
                throw new \Exception($stmt->errno . ' ' . $stmt->error);
            }
        }
        $stmt->close();
    }

    public function insertLog(\mysqli $con, string $file, string $fileNameNoPath):void{
        if(!\file_exists($file)){
            throw new \Exception("file doesn't exist: $file");
        }
        $md5 = \md5_file($file);
        $sha1 = \sha1_file($file);
        $fileName = trim($fileNameNoPath);
        if(!$md5 || !$sha1){
            throw new \Exception("fail to generate md5 hash.");
        }
        if(!$sha1){
            throw new \Exception("fail to generate sha1 hash.");
        }
        if(strlen($fileName) === 0){
            throw new \Exception("file name is empty.");
        }
        
        $COL = [
            'md5' => 's',
            'sha1' => 's',
            'fileName' => 's'
        ];
        $fields = implode(',', array_keys($COL));
        $param = implode(',', array_fill(0, count($COL), '?'));
        $dataType = implode('', $COL);

        $stmt = $con->prepare(
            "INSERT INTO orders_insert_log({$fields}, date) VALUES({$param}, NOW());"
        );
        $stmt->bind_param($dataType, $md5, $sha1, $fileName);

        if(!($stmt->execute())){
            throw new \Exception($con->error);
        }
        $stmt->close();

        $this->insertLogId = (int) $con->insert_id;
    }

    private function commit(\mysqli $con):void{
        //confirm insert new Record to orders from temp
        //duplicate orderNum is skipped for update to override
        $stmt = $con->prepare(
            'INSERT orders SELECT * FROM orders_temp WHERE insertLogId = ? '
            ."AND sku > '' "
            .'AND orderNum NOT IN (SELECT orderNum FROM orders);');
        $stmt->bind_param('i', $this->insertLogId);
        if(!($stmt->execute())){
            throw new \Exception($stmt->errno . ' '. $stmt->error);
        }
        $stmt->close();
    }

    private function clearTemp(\mysqli $con):void{
        $stmt = $con->prepare('DELETE FROM orders_temp WHERE insertLogId = ?');
        $stmt->bind_param('i', $this->insertLogId);
        if(!($stmt->execute())){
            throw new \Exception($con->errno . ' ' . $con->error);
        }
        $stmt->close();
    }

    public function update(\mysqli $con, array &$Records):void{
        //update existing Record trackingNum & status
        $col = [
            'trackingNum' => 's',
            'status' => 's'
        ];
        $whereCol = [
            'orderNum' => 's'
        ];
        $whereOp = 'AND';
        $statusToUpdate = [
            'PENDING',
            'READY_TO_SHIP'
        ];

        $parameter = implode(' =?,', array_keys($col)) . ' =?';
        $whereParam = implode(" =? $whereOp ", array_keys($whereCol)) . ' =?';
        $targetStatus = "'" .implode("','", $statusToUpdate) ."'";
        $dataType = implode('', $col) . implode('', $whereCol);
        $stmt = $con->prepare(
            "UPDATE orders SET $parameter WHERE $whereParam "
            ."AND status IN ($targetStatus);"
        );
        if(!$stmt){
            throw new \Exception($con->errno . ' ' . $con->error);
        }

        $trackingNum;
        $status;
        $orderNum;

        $stmt->bind_param($dataType, 
            $trackingNum,
            $status,
            $orderNum
        );

        $SKIP_STATUS = 'PENDING';

        foreach($Records as $r){
            $trackingNum = $r->trackingNum;
                if(strlen($trackingNum) === 0){continue;}
            $status = $r->status;
                if(strtoupper($status) === $SKIP_STATUS){continue;}
            $orderNum = $r->orderNum;

            if(!($stmt->execute())){
                throw new \Exception($stmt->errno . ' ' . $stmt->error);
            }
        }
        $stmt->close();
    }

}
