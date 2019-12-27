<?php 
class Courier_Record_Viewer{
	
	private $db_result;
	private $table;
	private $TARGET_FEE_NAME;

	public function __construct(){
		$this->db_result = [];
		$this->table = [];
	}

	public function getResult(){
		$result = [];
		$row = [];
		foreach($this->db_result as $r){
			$row = [];
			foreach($r as $c => $col){
				$row[$c] = htmlspecialchars(trim($col), ENT_QUOTES, 'UTF-8');
			}
			$result[] = $row;
		}
		return $result;
	}

	public function setDB_Result(mysqli $con){
		$USER_IP = mysqli_real_escape_string($con, $_SERVER['REMOTE_ADDR']);
		$sql = "SELECT `id`, `billno`, `order_num`, `item_code`, `selling_price`, `courier`, `courier_lzd`, `lzd_fee`, `voucher` 
		FROM `courier_record` WHERE user_ip = '$USER_IP';";

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

		return true;
	}



}