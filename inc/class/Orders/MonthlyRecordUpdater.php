<?php 
namespace Orders;

require_once(__DIR__ .'/../Database/Updatable.php');

use \Database\Updatable;

class MonthlyRecordUpdater implements Updatable{

    private $COL = [
        'billno' => 's',
        'trackingNum' => 's',
        'shippingFee' => 'd',
        'shippingFeeByCust' => 'd',
        'voucher' => 'd',
        'platformChargesAmount' => 'd',
        'bankIn' => 'd',
        'cash'  => 'd'
    ];

    private $whereCOL = [
        'id' => 'i'
    ];

    private $whereOp = 'AND';

    public function update(\mysqli $con, array &$MonthlyRecords):void{
        if(sizeof($MonthlyRecords) === 0){return;}

        $parameter = implode(' =?,', array_keys($this->COL)) . ' =?';
        $whereParam = implode(" =? $this->whereOp ", array_keys($this->whereCOL)) . ' =?';
        $dataType = implode('',$this->COL) . implode('',$this->whereCOL);
        $stmt = $con->prepare( 
          "UPDATE orders SET $parameter WHERE $whereParam;"
        );
        
        $billno;
        $trackingNum;
        $shippingFee;
        $shippingFeeByCust;
        $voucher;
        $platformChargesAmount;
        $bankIn;
        $cash;
        $id;

        $stmt->bind_param($dataType,
            $billno,
            $trackingNum,
            $shippingFee,
            $shippingFeeByCust,
            $voucher,
            $platformChargesAmount,
            $bankIn,
            $cash,
            $id
        );

        try{
            foreach($MonthlyRecords as $recordId => $r){
                $billno = trim($r['billno']);
                $trackingNum = trim($r['trackingNum']);
                $shippingFee = (double)$r['shippingFee'];
                $shippingFeeByCust = (double)$r['shippingFeeByCust'];
                $voucher = (double)$r['voucher'];
                $platformChargesAmount = (double)$r['platformChargesAmount'];
                $bankIn = (double)$r['bankIn'];
                $cash = (double)$r['cash'];
                $id = $recordId;
        
                if(!($stmt->execute())){
                    throw new \Exception(mysqli_stmt_error($stmt));
                }
            }
        }finally{
            $stmt->close();
        }
    }

}
