<?php 
namespace Orders\Factory;

require_once(__DIR__ .'/RecordFactory.php');
require_once(__DIR__ .'/../PaymentCharges/PlatformCharges.php');
require_once('inc/class/Orders/Lazada/AutoFilling.php');
require_once('inc/class/Lazada/Manager/ItemManager.php');
require_once('inc/class/Product/Item.php');
require_once('inc/class/Orders/Record.php');

use \Orders\Lazada\AutoFilling;
use \Orders\Factory\RecordFactory;
use \Orders\PaymentCharges\PlatformCharges;

class Cashsale implements RecordFactory{

    private $con;
    private $result;

    private $COL;

    const FIELDS = [
        'orders.orderNum' => 's',
        'orders.date' => 's',
        'orders.sku' => 's',
        'orders.sellingPrice' => 'd',
        'SUM(orders.shippingFee)' => 'd',
        'SUM(orders.shippingFeeByCust)' => 'd',
        'SUM(voucher)' => 'd',
        'platformCharges' => 's',
        'SUM(platformChargesAmount)' => 'd',
        'SUM(bankIn)' => 'd',
        'SUM(cash)' => 'd',
        'COUNT(orderNum)AS quantity' => 'i',
        'stock_items.item_code' => 's',
        'stock_items.description' => 's',
        'stock_items.uom' => 's',

        //for setLzdShippingFeeByWeight AutoFilling
        'shippingState' => 's',
        'SUM(shippingWeight)' => 's',
        'trackingNum' => 's'
    ];


    const GROUP_BY_COL = 'orders.orderNum, orders.platformCharges, orders.trackingNum';
    const DATE_FORMAT = 'Y/m/d';

    public function __construct(\mysqli $con){
        $this->con = $con;
        $this->COL = implode(',', array_keys(self::FIELDS));
        $this->result = [];
    }

    public function generateRecords():array{
        $list = [];
        foreach($this->result as $r){
            if(!\array_key_exists($r['orderNum'], $list)){
                $list[$r['orderNum']] = new CashsaleText($r);
            }
            $list[$r['orderNum']]->setDetail($r);
        }

        foreach($list as $r){
            $r->setTotalPrice();
        }

        return $list;
    }

    public function setMonthlyRecordByDateStockOut(string $date):void{
        $date = $this->formatDate($date);
        $GROUP_BY = self::GROUP_BY_COL . ',stock_items.item_code';
        $stmt = $this->con->prepare(
            "SELECT {$this->COL} FROM orders "
            .'INNER JOIN stock_items ON stock_items.item_code = orders.sku '
            .'WHERE dateStockOut = ? '
            ."GROUP BY {$GROUP_BY} "
            .' UNION '
            ."SELECT {$this->COL} FROM orders "
            .'INNER JOIN seller_sku ON orders.sku = seller_sku.sku '
            .'INNER JOIN stock_items ON stock_items.item_code = seller_sku.itemCode '
            .'WHERE dateStockOut = ? '
            ."GROUP BY {$GROUP_BY}"
        );
        $stmt->bind_param('ss', $date, $date);
        if(!($stmt->execute())){
            throw new \Exception($stmt->error);
        }

        $this->result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $this->checkSku($date);
        $this->setLzdShippingFeeByWeight();
    }

    private function checkSku(string $date):void{
        $stmt = $this->con->prepare(
            'SELECT COUNT(*) FROM '
            .'(SELECT id FROM orders WHERE dateStockOut = ? GROUP BY orderNum, sku, platformCharges, trackingNum)AS o;'
        );
        $stmt->bind_param('s', $date);
        if(!($stmt->execute())){
            throw new \Exception($stmt->error);
        }
        $numRows = (int) $stmt->get_result()->fetch_row()[0];

        if($numRows !== count($this->result)){
            throw new \Exception('Some sku does not have alias and does not match any item code.');
        }
        $stmt->close();
    }

    private function setLzdShippingFeeByWeight():void{
        //filter rows to get lazada row only
        $lzdList = [];
        foreach($this->result as $i => $r){
            if($r['platformCharges'] === PlatformCharges::TYPE_OF_CHARGES['Lazada']){
                $lzdList[] = &$this->result[$i];
            }
        }

        //convert lzd rows to list of Record object
        $lzdRecords = array_map(function(array $r){
            $Record = new \Orders\Record(
                null, 
                $r['orderNum'], 
                new \Product\Item(null, $r['sku'], null)
            );
            $Record->setShippingFee((double) $r['SUM(orders.shippingFee)']);
            $Record->setShippingWeight($r['SUM(shippingWeight)']);
            $Record->setShippingState($r['shippingState']);
            $Record->setTrackingNum($r['trackingNum']);
            
            return $Record;
        }, $lzdList);
        
        //foreach Record, set lzd shippingFeeByWeight
        AutoFilling::setShippingFeeByWeight($lzdRecords);

        //foreach lzd rows, set shippingFeeByWeight to shippingFee from lzd Object
        foreach($lzdRecords as $i => $r){
            if(isset($lzdList[$i]) === false){
                throw new \Exception(
                    'cannot match lazada Records for setting shippingFeeByWeight to shippingFee.');
            }
            $lzdList[$i]['SUM(orders.shippingFee)'] = $r->shippingFeeByWeight;
        }
    }

    private function formatDate(string $date):string{
        $Date = \date_create($date);
        if(!$Date){
            throw new \InvalidArgumentException("invalid date {$date}.");
        }

       return \date_format($Date, self::DATE_FORMAT);
    }

}

//private class
//lines of same orderNum as a cashsaletext
class CashsaleText{
    private $data;

    const NUM_COL = 45;
    const DEFAULT_VALUE = '----';
    const SHIPPING_CODE = 'SHIPPING';
    const SHIPPING_UOM = 'UNIT';
    const DATE_FORMAT = 'd-m-Y';
    function __construct(array $r){
        $this->dataMaster = [];
        $this->dataDetail = [];
        $this->setMaster($r);
    }

    private function setMaster(array &$r):void{
        $date = date_format(\date_create($r['date']), self::DATE_FORMAT);
        $col = array_fill(0, self::NUM_COL, '');
        $PlatformCharges = PlatformCharges::getPLatformCharges($r['platformCharges']);
        $totalPrice = 0.00;

        $col[0] = 'MASTER';
        $col[1] = '<<NEW>>';
        $col[3] = $date;
        $col[4] = $date;
        $col[5] = $PlatformCharges->getCustId();
        $col[6] = $PlatformCharges->getCompanyName();
        $col[14] = self::DEFAULT_VALUE;
        $col[15] = self::DEFAULT_VALUE;
        $col[16] = self::DEFAULT_VALUE;
        $col[17] = 'C.O.D';
        $col[18] = '1';
        $col[20] = 'F';
        $col[21] = '1';
        $col[25] = $r['orderNum'];
        $col[29] = 'BILLING';
        $col[37] = 'T';
        $col[38] = '0';
        $col[39] = $PlatformCharges->getPaymentInto();
        $col[41] = (double) $r['platformCharges'];
        $col[42] = $totalPrice;
        $col[43] = self::DEFAULT_VALUE;

        $this->dataMaster = $col; 
    }

    public function setTotalPrice():void{
        $totalPrice = 0.00;
        foreach($this->dataDetail as $r){
            $totalPrice += $r[17];
        }
        $this->dataMaster[42] = $totalPrice;
    }

    public function setDetail(array &$r):void{
        $col = array_fill(0, self::NUM_COL, '');

        $date = date_format(\date_create($r['date']), self::DATE_FORMAT);
        $sellingPrice = (double) $r['sellingPrice'];
        $quantity = (int) $r['quantity'];
        $col[0] = 'DETAIL';
        $col[1] = '<<NEW>>';
        $col[3] = $r['item_code'];
        $col[4] = self::DEFAULT_VALUE;
        $col[5] = self::DEFAULT_VALUE;
        $col[6] = $r['description'];
        $col[9] = $quantity;
        $col[10] = $r['uom'];
        $col[11] = 0;
        $col[12] = $sellingPrice;
        $col[13] = $date;
        $col[16] = 0;
        $col[17] = ($sellingPrice * $quantity);
        $col[18] = 'T';
        $col[20] = 'T';
        $col[23] = 0;
        
        $this->dataDetail[] = $col;
        $this->setShippingFees($r, $col); //add again, for its shipping fee
    }

    private function setShippingFees(array $r, array $colOfDetail):void{
        //copy & modify row data for adding shipping fee
        $col = $colOfDetail;
        $totalShippingFee = ((double) $r['SUM(orders.shippingFee)']) 
            + ((double) $r['SUM(orders.shippingFeeByCust)']);
        $quantity = 1;

        $col[3] = self::SHIPPING_CODE;
        $col[6] = self::SHIPPING_CODE;
        $col[9] = $quantity;
        $col[10] = self::SHIPPING_UOM;
        $col[12] = $totalShippingFee;
        $col[17] = ($totalShippingFee * $quantity);

        $this->dataDetail[] = $col;
    }

    public function getData():string{
        $DELIMITER = ';';
        $master = [implode($DELIMITER, $this->dataMaster)];
        
        $details = array_map(function($r){
            $DELIMITER = ';';
            return implode($DELIMITER, $r);
        }, $this->dataDetail);

        $list = \array_merge($master, $details);

        $LINE_DELIMITER = "\r\n";
        return implode($LINE_DELIMITER, $list);
    }
}