<?php 
namespace Orders\Factory;

use \mysqli;
use \Product\Item;
use \Orders\PaymentCharges;
use \Exception;

class MonthlyRecord implements RecordFactory{
    private $con;
    private $searchField = 'orders.id';
    private $searchValue = '';
    private $offset = 0;
    private $recordTotal = 0;
    private $recordLimit = 100; //get lastest records
    private $COL;

    const DATE_FORMAT = 'Y/m/d';

    const FIELDS = [
        'orders.id',
        'orders.orderNum',
        'orders.billno',
        'orders.date',
        'orders.dateStockOut',
        'orders.sku',
        'orders.trackingNum',
        'orders.sellingPrice',
        'orders.status',
        'orders.transferCharges',
        'orders.shippingFee',
        'orders.shippingFeeByCust',
        'orders.shippingState',
        'orders.shippingWeight',
        'orders.voucher',
        'orders.platformCharges',
        'orders.platformChargesAmount',
        'orders.bankIn',
        'orders.cash',
        'orders.remark',
        '(stock_items.id)AS itemId',
        'stock_items.description'
    ];

    public function __construct(mysqli $con){
        $this->con = $con;
        $this->COL = implode(',', self::FIELDS);
    }

    public function setOffset(int $offset):void{
        $this->offset = $offset;
    }

    public function setRecordLimit(int $limit):void{
        $this->recordLimit = $limit;
    }

    public function getNumPage():int{
        return \ceil($this->recordTotal / $this->recordLimit);
    }

    public function setSearch(string $field, string $value):void{
        $field = trim($field);
        $value = trim($value);
        if(empty($field) || empty($value)){return;}
        switch($field){
            case 'orderNum':
            case 'date':
            case 'dateStockOut':
            case 'id':
                $this->searchField = 'orders.' .$field;
                $this->searchValue = trim($value);
                break;
            default:
                throw new \InvalidArgumentException("No such field name $field or is not allow to search.");
        }
    }

    private function setRecordTotal(\mysqli_stmt $stmt):void{
        if(!($stmt->execute())){
            throw new \Exception($this->con->error);
        }

        $total = 0;
        $result = $stmt->get_result();
        if(($r = $result->fetch_row()) !== null){
            $total = (int)$r[0];
        }
        $stmt->free_result();
        $stmt->close();

        $this->recordTotal = $total;
    }

    public function generateRecords(?string $platformCharges = null):array{
        $PAST_N_MONTH = 3; // by default show upto previos n month
        $PARAM = "{$this->searchField} LIKE ?";
        if($platformCharges !== null){
            $PARAM .= ' AND platformCharges = ?';
            $PARAM .= " AND (date BETWEEN (DATE_SUB(NOW(),INTERVAL {$PAST_N_MONTH} MONTH)) AND NOW())";
        }

        $stmt = $this->con->prepare(
            "SELECT {$this->COL} FROM orders "
            ."JOIN (SELECT id FROM orders WHERE {$PARAM} ORDER BY id DESC LIMIT ?,?)AS o "
            .'ON orders.id = o.id '
            .'LEFT JOIN seller_sku ON orders.sku = seller_sku.sku '
            .'LEFT JOIN stock_items ON orders.sku = stock_items.item_code '
            .'OR seller_sku.itemCode = stock_items.item_code '
            .'GROUP BY orders.id '
            .'ORDER BY orders.id'
        );
        if(!$stmt){
            throw new Exception($this->con->error);
        }
        $searchValue = "%{$this->searchValue}%";

        if($platformCharges === null){
            $stmt->bind_param('sii', $searchValue, $this->offset, $this->recordLimit);
        } else {
            $stmt->bind_param('ssii', $searchValue, $platformCharges, $this->offset, $this->recordLimit);
        }

        if(!($stmt->execute())){
            throw new Exception($this->con->error);
        }

        $result = $stmt->get_result();
        $list = [];
        while(($r = $result->fetch_assoc()) !== null){
            $M = new \Orders\MonthlyRecord(
                $r['id'],
                $r['orderNum'],
                new Item($r['itemId'], $r['sku'], $r['description'])
            );

            $this->setPlatformCharges($M, $r);
            $this->setDirectCharges($M, $r);            

            $this->setMonthlyRecordData($M, $r);
           
            $list[] = $M;
        }
        $stmt->close();

        //set total for pageNum
        $stmt = $this->con->prepare(
            'SELECT COUNT(id) FROM orders WHERE '
            .$PARAM
            ." AND (date BETWEEN (DATE_SUB(NOW(),INTERVAL {$PAST_N_MONTH} MONTH)) AND NOW());"
        );
        if($platformCharges === null){
            $stmt->bind_param('s', $searchValue);
        }else {
            $stmt->bind_param('ss', $searchValue, $platformCharges);
        }
        $this->setRecordTotal($stmt);

        return $list;
    }

    private function setPlatformCharges(\Orders\MonthlyRecord $MonthlyRecord, array &$r):void{
        $platformCharges = &$r['platformCharges'];
        if(strlen($platformCharges) === 0){return;}
        $amount = (double)$r['platformChargesAmount'];
        switch($platformCharges){
            case 'Lazada':
                $PlatformCharges = new PaymentCharges\Lazada($amount);
                break;
            case '11Street':
                $PlatformCharges = new PaymentCharges\Street11($amount);
                break;
            case 'Netpay':
                $PlatformCharges = new PaymentCharges\Netpay($amount);
                break;
            case 'GHL':
                $PlatformCharges = new PaymentCharges\GHL($amount);
                break;
            case 'Shopee':
                $PlatformCharges = new PaymentCharges\Shopee($amount);
                break;
            default:
                throw new \Exception('undefined PlatformCharges type: ' .$platformCharges);
            
        }
        $MonthlyRecord->setPlatformCharges($PlatformCharges);
    }

    private function setDirectCharges(\Orders\MonthlyRecord $MonthlyRecord, array &$r):void{
        $MonthlyRecord->setDirectCharges(new PaymentCharges\BankIn((double)$r['bankIn']));
        $MonthlyRecord->setDirectCharges(new PaymentCharges\Cash((double)$r['cash']));
    }

    private function setMonthlyRecordData(\Orders\MonthlyRecord $M, array &$r):void{
        $M->setBillNo($r['billno']);
        $M->setDate($r['date']);
        $M->setDateStockOut($r['dateStockOut']);
        $M->setOrderNum($r['orderNum']);
        $M->setSellingPrice((double)$r['sellingPrice']);
        $M->setShippingFee((double)$r['shippingFee']);
        $M->setShippingFeeByCust((double)$r['shippingFeeByCust']);
        $M->setShippingState($r['shippingState']);
        $M->setShippingWeight((double)$r['shippingWeight']);
        $M->setTrackingNum($r['trackingNum']);
        $M->setTransferCharges((double)$r['transferCharges']);
        $M->setVoucher((double)$r['voucher']);
    }

    public function getMonthlyRecordsByDate(string $date, ?string $platformCharges = null):array{
        $Date = \date_create($date);
        if(!$Date){
            throw new \InvalidArgumentException("invalid date {$date}.");
        }

        $date = \date_format($Date, self::DATE_FORMAT);

        $PARAM = 'YEAR(date) = YEAR(?) AND MONTH(date) = MONTH(?)';
        if($platformCharges !== null){
            $PARAM .= ' AND platformCharges = ?';
        }
        $stmt = $this->con->prepare(
            "SELECT {$this->COL} FROM orders "
            ."JOIN (SELECT id FROM orders WHERE {$PARAM} ORDER BY id)AS o ON o.id = orders.id "
            .'LEFT JOIN seller_sku ON orders.sku = seller_sku.sku '
            .'LEFT JOIN stock_items ON orders.sku = stock_items.item_code '
            .'OR seller_sku.itemCode = stock_items.item_code '
            .'GROUP BY orders.id '
            .'ORDER BY orders.id'
        );
        
        

        if($platformCharges === null){
            $stmt->bind_param('ss',
             $date,
             $date);
        } else {
            $stmt->bind_param('sss', 
             $date,
             $date,
             $platformCharges);
        }

        if(!($stmt->execute())){
            throw new \Exception($this->con->error);
        }

        $list = [];
        $result = $stmt->get_result();
        while(($r = $result->fetch_assoc()) !== null){
            $M = new \Orders\MonthlyRecord(
                $r['id'],
                $r['orderNum'],
                new Item($r['itemId'], $r['sku'], $r['description'])
            );

            $this->setPlatformCharges($M, $r);
            $this->setDirectCharges($M, $r);            

            $this->setMonthlyRecordData($M, $r);
           
            $list[] = $M;
        }
        $stmt->free_result();

        //set total for pageNum
        $stmt_count = $this->con->prepare("SELECT COUNT(id) FROM orders WHERE {$PARAM}");
        if($platformCharges === null){
            $stmt_count->bind_param('ss',
             $date,
             $date);
        } else {
            $stmt_count->bind_param('sss', 
             $date,
             $date,
             $platformCharges);
        }
        $this->setRecordTotal($stmt_count);

        return $list;
    }

    public function getStockOutByDate(string $date):array{
        $Date = \date_create($date);
        if(!$Date){
            throw new \Exception("invalid date {$date}.");
        }

        $date = \date_format($Date, self::DATE_FORMAT);
        $stmt = $this->con->prepare(
            'SELECT orders.sku, stock_items.description, COUNT(orders.id)AS quantity FROM orders ' 
            .'LEFT JOIN stock_items ON orders.sku = stock_items.item_code '
            .'WHERE orders.dateStockOut = ? '
            .'GROUP BY orders.id'
        );
        $stmt->bind_param('s', $date);
        if(!($stmt->execute())){
            throw new \Exception($this->con->error);
        }

        $result = $stmt->get_result();
        
        $list = $result->fetch_all(MYSQLI_ASSOC);
        $result->free_result();

        return $list;
    }
}
