<?php 
class Stock_Items_Getter{
	private $items_code;

	public function __construct(){
		$this->items_code = [];
	}

	public function setItems_Code(array $items_code){
		$this->items_code = $items_code;
	}

	public function getStock_Items(mysqli $con){
		$items_code = $this->escape_items_code($con);
		$sql = "SELECT i.item_code, i.description, s.quantity FROM stock_items i 
				INNER JOIN stock s ON i.id = s.id 
				WHERE item_code IN ($items_code);";
		$stmt = mysqli_query($con, $sql);
		if(!$stmt){
			throw new Exception(mysqli_error($con));
			return false;
		}

		$result = [];
		while($r = mysqli_fetch_assoc($stmt)){
			$result[] = $r;
		}
		mysqli_free_result($stmt);
		return $result; 
	}

	private function escape_items_code(mysqli $con){
		$v = '';
		$array = [];
		foreach($this->items_code as $item){
			$v = trim($item);
			if(strlen($v) > 0){
				$array[] = mysqli_real_escape_string($con, $item);
			}
		}
		return ("'" .implode("','", $array) ."'");

	}


}