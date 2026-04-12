<?php 
class Lzd_Fee_Checker{
	
	private $db_result;
	private $table;
	private $TARGET_FEE_NAME;

	public function __construct(){
		$this->db_result = [];
		$this->table = [];
		$this->TARGET_FEE_NAME = [
			'selling_price' => 'item price credit',
			'courier' => 'shipping fee (paid by customer)',
			'courier_lzd' => 'shipping fee (charged by lazada)',
			'lzd_fee' => 'payment fee',
			'voucher' => 'promotional charges vouchers'];
	}

	public function getAll_Conclusion(){
		$this->setupConclusionFields();
		$this->setTable();
			$this->setFieldDifferential();
			$this->setMinConlusion1();
			$this->setMinConlusion2();
		$this->setFinalConclusion();

		$table = [];
		$new_r = [];
		foreach($this->table as $row){
			array_splice($row, 6, 2, ' '); //remove fee_name, amount col
			$table[] = $row;
		}

		return $table;
	}

	private function setFieldDifferential(){
		$v = 0;
		foreach($this->table as $k => $r){
			if($r['Shipping Fee by Lzd'] === 0.00){
				continue;
			}

			if(!is_numeric($r['Shipping Fee by Lzd'])){
				$this->table[$k]['differential'] = 'None';
				continue;
			}

			$v =
			doubleval($r['courier']) + 
			doubleval($r['courier_lzd']) + 
			doubleval($r['Shipping Fee by Lzd']);
			$this->table[$k]['differential'] = round($v, 2);
		}
	}

	private function setMinConlusion1(){
		// $TARGET_FIELDS = [
		// 	'Selling Price',
		// 	'Shipping Fee',
		// 	'Lzd Fee',
		// 	'Voucher'
		// ];
		foreach($this->table as $k => $r){
			$selling_price = doubleval($r['selling_price']);
			$courier_lzd = doubleval($r['courier_lzd']);
			$lzd_fee = doubleval($r['lzd_fee']);
			$voucher = doubleval($r['voucher']);
			$conclusion = [];

			if($selling_price > 0.00){
				if($r['Selling Price'] !== 0.00){
					$conclusion[] = 'Selling Price: ' .$r['Selling Price'];
				}
			}

			if($courier_lzd > 0.00){
				if($r['Shipping Fee'] !== 0.00){
						$conclusion[] = 'Shipping Fee: ' .$r['Shipping Fee'];
				}
			}
			
			
			if($lzd_fee > 0.00){
				if($r['Lzd Fee'] !== 0.00){
					$conclusion[] = 'Lzd Fee: ' .$r['Lzd Fee'];
				}
			}
			if($voucher > 0.00){
				if($r['Voucher'] !== 0.00){
					$conclusion[] = 'Voucher: ' .$r['Voucher'];
				}
			}

			if(sizeof($conclusion) === 0){
				$conclusion[] = 'Completed';
			} else {
				$conclusion = ['No'];
			}

			$this->table[$k]['First Batch'] = implode(',', $conclusion);
		}
	}

	private function setMinConlusion2(){
		foreach($this->table as $k => $r){
			if(!is_numeric($r['Shipping Fee by Lzd'])){
				$this->table[$k]['Second Batch'] = 'None';
				continue;
			}

			if($r['Shipping Fee by Lzd'] !== 0.00){
				$this->table[$k]['Second Batch'] = 'No';
				continue;
			} 

			$this->table[$k]['Second Batch'] = 'Completed';
		}
	}

	private function setFinalConclusion(){
		foreach($this->table as $i => $r){
			$conclusion = 'No';
			$c1 = $r['First Batch'] === 'Completed' ? true : false;
			$c2 = $r['Second Batch'] === 'Completed' ? true : false;

			if($c1 && $c2){
				$conclusion = 'Completed';
			}

			if($r['Second Batch'] == 'No'){
				$conclusion = 'No';
			}

			$this->table[$i]['Conclusion'] = $conclusion;
		}
	}

	private function setTable(){
		$table = [];
		$fee_name = '';
		$new_fee_name = '';
		foreach($this->db_result as $r){
			if(!array_key_exists($r['order_num'], $table)){
				$table[$r['order_num']] = $r;
			}

			$fee_name = trim($r['fee_name']);

			$new_fee_name = $this->getFee_Type_Name($fee_name);
			if($new_fee_name === false){
				continue;
			}

			$sum = $this->compareAll_Fee_result($r);
			if($sum === false){
				continue;
			}


			$table[$r['order_num']][$new_fee_name] = $sum;

			
		}
		unset($r);

		$this->table = $table;
	}

	private function setupConclusionFields(){
		$null = 'No';
		foreach($this->db_result as $r => $row){
			$this->db_result[$r]['Selling Price'] = $null;
			$this->db_result[$r]['Shipping Fee'] = $null;
			$this->db_result[$r]['Shipping Fee by Lzd'] = $null;
				$this->db_result[$r]['differential'] = $null;
			$this->db_result[$r]['Lzd Fee'] = $null;
			$this->db_result[$r]['Voucher'] = $null;

				$this->db_result[$r]['First Batch'] = $null;
				$this->db_result[$r]['Second Batch'] = $null;
		}
	}

	private function getFee_Type_Name(string $FEE_NAME){
		$fee_name = strtolower(trim($FEE_NAME));
		$name = ''; 
		switch($fee_name){
				case $this->TARGET_FEE_NAME['selling_price']:
					$name = 'Selling Price';
					break;
				case $this->TARGET_FEE_NAME['courier']: 
					$name = 'Shipping Fee';
					break;
				case $this->TARGET_FEE_NAME['courier_lzd']:
					$name = 'Shipping Fee by Lzd';
					break;
				case $this->TARGET_FEE_NAME['lzd_fee']:
					$name = 'Lzd Fee';
					break;
				case $this->TARGET_FEE_NAME['voucher']:
					$name = 'Voucher';
					break;
				default:
					$name = false;
		}
		return $name;
	}

	private function compareAll_Fee_result(array $row){
		$name = strtolower(trim($row['fee_name']));
		$result = 0; 
		switch($name){
				case $this->TARGET_FEE_NAME['selling_price']:
					$result = $this->calcSelling_Price($row);
					break;
				case $this->TARGET_FEE_NAME['courier']: 
					$result = $this->calcCourier($row);
					break;
				case $this->TARGET_FEE_NAME['courier_lzd']:
					$result = $this->calcCourier_Lzd($row);
					break;
				case $this->TARGET_FEE_NAME['lzd_fee']:
					$result = $this->calcLzd_Fee($row);
					break;
				case $this->TARGET_FEE_NAME['voucher']:
					$result = $this->calcVoucher($row);
					break;
				default:
					$result = false;
		}
		return $result;
	}

	private function calcSelling_Price(array $row){
		$amount = doubleval($row['amount']);
		$sum = doubleval($row['selling_price']) - $amount;

		if($sum !== 0.00){return $amount;}

		return $sum;
	}

	private function calcCourier(array $row){
		$amount = doubleval($row['amount']);
		$sum = doubleval($row['courier_lzd']) - $amount;

		if($sum !== 0.00){return $amount;}

		return $sum;
	}

	private function calcCourier_Lzd(array $row){
		$amount = doubleval($row['amount']);
		$sum = 
		doubleval($row['courier']) + doubleval($row['courier_lzd']) + $amount;

		if($sum !== 0.00){return $amount;}

		return $sum;
	}

	private function calcLzd_Fee(array $row){
		$amount = doubleval($row['amount']);
		$sum = doubleval($row['lzd_fee']) + $amount;

		if($sum !== 0.00){return $amount;}

		return $sum;

	}

	private function calcVoucher(array $row){
		$amount = doubleval($row['amount']);
		$sum = doubleval($row['voucher']) + $amount;
		if($sum !== 0.00){return $amount;}

		return $sum;
	}

	public function setDB_Result(mysqli $con){
		$USER_IP = mysqli_real_escape_string($con, $_SERVER['REMOTE_ADDR']);
		$sql = "SELECT T1.order_num, T1.selling_price, T1.courier, T1.courier_lzd, T1.lzd_fee, T1.voucher, T2.fee_name, T2.amount FROM 
		(SELECT id, order_num, SUM(selling_price)AS selling_price, SUM(courier)AS courier, SUM(courier_lzd)AS courier_lzd, SUM(lzd_fee)AS lzd_fee, SUM(voucher)AS voucher, user_ip FROM courier_record 
		WHERE user_ip = '$USER_IP' 
		GROUP BY order_num)T1
		INNER JOIN 
		(SELECT order_num, fee_name, SUM(amount)AS amount, user_ip FROM lzd_fee_statements 
		WHERE user_ip = '$USER_IP' 
		GROUP BY order_num, fee_name)T2 
		ON T1.order_num = T2.order_num 
		ORDER BY T1.id;";

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