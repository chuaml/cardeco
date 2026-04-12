<?php 
class Lazada_Fee_Statement extends CSV_Table_Handler{

	private $lzd_fee_stmt;
	public $inserted_Rows;

	public function __construct(array $FILE){
		parent::__construct($FILE);
		$this->lzd_fee_stmt = [];
		$this->inserted_Rows = 0;
	}

	public function getTransactions(){
		//feename 2, amount 7, ordernum 14
		try{
			$this->lzd_fee_stmt = $this->getFields_Value(
				[0, 1, 2, 4, 5, 6, 7, 11, 12, 13, 15], 
				['tdate', 'fee_type', 'fee_name', 'description', 
				'item_code', 'lzd_sku', 'amount', 'sdate', 
				'paid_status', 'order_num', 'item_status']);
		}catch(Exception $e){
			die('Invalid File format. ' . $e->getMessage());
		}

		return $this->lzd_fee_stmt;
	}

	public function insertToDB(mysqli $con){
		$this->setTable(true);
		$this->getTransactions();

		$USER_IP = trim($_SERVER['REMOTE_ADDR']);

		$sql = "INSERT INTO lzd_fee_statements
		(tdate, fee_type, fee_name, description, item_code, lzd_sku, sdate, paid_status, amount, order_num, item_status, user_ip) 
		VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);";
		$stmt = mysqli_prepare($con, $sql);
		foreach($this->lzd_fee_stmt as $r){
			if(!$stmt){
				throw new Exception(mysqli_error($con));
				return false;
			}
			mysqli_stmt_bind_param($stmt, 'ssssssssdsss', 
			$r['tdate'], $r['fee_type'], $r['fee_name'], $r['description'], 
			$r['item_code'], $r['lzd_sku'], $r['sdate'], $r['paid_status'], 
			$r['amount'], $r['order_num'], $r['item_status'], $USER_IP);
			if(!mysqli_stmt_execute($stmt)){
				throw new Exception(mysqli_error($con));
				return false;
			}


		$this->inserted_Rows += mysqli_stmt_affected_rows($stmt);
		}
		mysqli_stmt_close($stmt);

		//change comission to payment fee
		$USER_IP = mysqli_real_escape_string($con, trim($_SERVER['REMOTE_ADDR']));
		$sql = "UPDATE lzd_fee_statements SET fee_name = 'Payment Fee' WHERE fee_name = 'Commission' AND user_ip = '$USER_IP';";
		$stmt = mysqli_query($con, $sql);
		if(!$stmt){
			throw new Exception(mysqli_error($con));
		}

		return true;
	}



}