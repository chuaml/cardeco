<?php 
class Monthly_Sales_Record extends CSV_Table_Handler{

	private $itemList;
	public $inserted_Rows;

	public function __construct(array $FILE){
		parent::__construct($FILE);
		$this->itemList = [];
		$this->inserted_Rows = 0;
	}

	public function getOrders(){
		try{
		$this->itemList = $this->getFields_Value(
			[0, 1, 2, 3, 5, 7, 9, 10, 14, 16],
			['billno', 'order_num', 'bill_date', 'item_code', 'ship_date', 'selling_price', 'courier', 'courier_lzd', 'lzd_fee', 'voucher']);
		}catch(Exception $e){
			die('Invalid File format. ' . $e->getMessage());
		}
		return $this->itemList;
	}

	public function insertToDB(mysqli $con){
		$this->setTable(false);
		$this->getOrders();
		$this->fillEmpty_Columns(['billno', 'order_num']);
		//$this->format_Date();
		$USER_IP = $_SERVER['REMOTE_ADDR'];

		$sql = "INSERT INTO courier_record(billno, order_num, item_code, selling_price, courier, courier_lzd, lzd_fee, voucher, user_ip) 
		VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?);";
		$stmt = mysqli_prepare($con, $sql);
		foreach($this->itemList as $i => $r){
			mysqli_stmt_bind_param($stmt, 'sssddddds', 
			$r['billno'], $r['order_num'], $r['item_code'],
			$r['selling_price'], $r['courier'], $r['courier_lzd'], $r['lzd_fee'], $r['voucher'], 
			$USER_IP);

			if(!mysqli_stmt_execute($stmt)){
				throw new Exception("$i " .mysqli_error($con));
				return false;
			}
			$this->inserted_Rows += mysqli_stmt_affected_rows($stmt);
		}
		mysqli_stmt_close($stmt);
		
		return true;
	}

	private function fillEmpty_Col(string $field_name){
		$len = sizeof($this->itemList);
		$value_previous = '';
		$value_now = '';
		$value_next = '';
		$row_skipped = 0;
		for($r = 0; $r<$len; ++$r){
			$value_now = trim($this->itemList[$r][$field_name]);
			if(strlen($value_now) > 0){
				$value_previous = $value_now;
				continue;
			}

			for($b = $r; $b < $len; ++$b){
				$value_next = trim($this->itemList[$b][$field_name]);
				if(strlen($value_next) > 0){
					$row_skipped = $b;
					break;
				}
				$this->itemList[$b][$field_name] = $value_previous;
			}

			$r = $row_skipped -1;
		}
	}

	private function fillEmpty_Columns(array $fields_name){
		foreach ($fields_name as $field_name) {
			$this->fillEmpty_Col($field_name);
		}
	}

/*
	private function format_Date(){
		$billdate = date_create();
		$shipdate = date_create();
		foreach ($this->itemList as $r => $row) {
			$billdate = date_create($row['bill_date']);
			$shipdate = date_create($row['ship_date']);

			if($billdate === false){throw new Exception("invalid date format at $r : " .$row['bill_date']);}
			if($shipdate === false){throw new Exception("invalid date format at $r : " .$row['ship_date']);}

			$billdate = date_format($billdate, 'Y-m-d');
			$shipdate = date_format($shipdate, 'Y-m-d');

			if($billdate === false){throw new Exception("invalid date format at $r : " .$row['bill_date']);}
			if($shipdate === false){throw new Exception("invalid date format at $r : " .$row['ship_date']);}

			$this->itemList[$r]['bill_date'] = $billdate;
			$this->itemList[$r]['ship_date'] = $shipdate;
		}
	}
*/

}
