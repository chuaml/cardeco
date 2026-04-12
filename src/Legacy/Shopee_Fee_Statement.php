<?php 
class Shopee_Fee_Statement extends CSV_Table_Handler{

	private $shopee_fee_stmt;
	private $db_result;
	private $table;
	private $output;

	public function __construct(array $FILE){
		parent::__construct($FILE);
		$this->setTable(false);
		$this->db_result = [];
	}

	public function getTransactions(){
		try{
			$this->shopee_fee_stmt = $this->getFields_Value(
				[0, 1], 
				['payment', 'order_num']);
		}catch(Exception $e){
			die('Invalid File format. ' . $e->getMessage());
		}

		$this->filterOrder_Number();

		return $this->shopee_fee_stmt;
	}

	private function filterOrder_Number(){
		$temp = [];
		$order_num = '';
		foreach($this->shopee_fee_stmt as $k => $r){
			$temp = explode('#', $r['order_num']);
			$order_num = $temp[1] ?? $temp[0];
			
			$r['payment'] = doubleval($r['payment']);
			$r['order_num'] = trim($order_num);

			$this->shopee_fee_stmt[$k] = $r;
		}
	}

	public function compareDifferences(){
		$payment = 0.00;
		$recorded = 0.00;
		$diff = 0.00;
		$row = [];

		foreach($this->shopee_fee_stmt as $r){
			$row = [];
			if(array_key_exists($r['order_num'], $this->table)){
				$payment = doubleval($r['payment']);
				$recorded = doubleval($this->table[$r['order_num']]['selling_price']);
				$diff = $recorded - $payment;
				
				$row['order_num'] = $r['order_num'];
				$row['payment'] = $payment;
				$row['MSales'] = $recorded;
				$row['difference'] = $diff;
			} else {
				$row['order_num'] = $r['order_num'];
				$row['payment'] = doubleval($r['payment']);
				$row['MSales'] = 'N/a';
				$row['difference'] = 'N/a';
			}

			$this->output[] = $row; 
		}
	}

	public function getOutput(){
		return $this->output;
	}

	private function indexDb_Result(){
		$this->table = [];
		foreach($this->db_result as $r){
			$this->table[$r['order_num']] = $r; 
		}
	}

	public function setDB_Result($con){
		$USER_IP = mysqli_real_escape_string($con, $_SERVER['REMOTE_ADDR']);
		$sql = "SELECT order_num, selling_price FROM courier_record WHERE user_ip = '$USER_IP';";

		$stmt = mysqli_query($con, $sql);
		if(!$stmt){
			throw new Exception(mysqli_error($con));
			return false;
		}

		$this->db_result = [];
		while($row = mysqli_fetch_assoc($stmt)){
			$this->db_result[] = $row;
		}
		mysqli_free_result($stmt);

		$this->indexDb_Result();

		return true;
	}

}