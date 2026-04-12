<?php 
class Lzd_Fee_Matcher{
	
	private $db_result;
	private $TARGET_FEE_NAME;

	public function __construct(){
		$this->db_result = [];
		$this->TARGET_FEE_NAME = [
			'selling_price' => 'item price credit',
			'courier' => 'shipping fee (paid by customer)',
			'courier_lzd' => 'shipping fee (charged by lazada)',
			'lzd_fee' => 'payment fee',
			'voucher' => 'promotional charges vouchers'];
	}

	public function getResult(){
		$result = [];
		$sum = 0;
		$recorded_amount = 0.00;
		$diff = 0.00;
		foreach($this->db_result as $r){
			$sum = $this->compareAll_Fee_result($r);
			$recorded_amount = $this->getRecorded_Fee($r);
			// if($sum === false){continue;} //skip not in range fee type
			// if($sum !== 0.00){
			// 	$r['billno'] = $sum;
			// }

			$diff = doubleval(abs($r['amount'])) - doubleval(abs($recorded_amount));
			$recorded_amount = $diff === 0.00 ? '' : $recorded_amount; 
			$diff = $diff === 0.00 ? '' :abs($diff); 

			$result[] = [
				'transaction date'	=> $r['tdate'], 
				'fee type' 			=> $r['fee_type'],
				'fee name'			=> $r['fee_name'],
				'detail' 			=> $r['description'],

				'statement' 		=> $r['sdate'],
				'paid status' 		=> $r['paid_status'],
				'amount' 			=> $r['amount'],
				'ordernum' 			=> $r['order_num'],
				'item status' 		=> $r['item_status'],

				'billno' 			=> $r['billno'],
				'difference' 		=> $diff, 
				'Remark'			=> $recorded_amount, 
				'Lzd SKU'			=> $r['lzd_sku']
			];
		}

		return $result;
	}

	private function getRecorded_Fee(array $row){
		$name = strtolower(trim($row['fee_name']));
		$result = ''; 
		switch($name){
				case $this->TARGET_FEE_NAME['selling_price']:
					$result = $row['selling_price'];
					break;
				case $this->TARGET_FEE_NAME['courier']: 
					$result = $row['courier_lzd'];
					break;
				case $this->TARGET_FEE_NAME['courier_lzd']:
					$result = (doubleval($row['courier_lzd']) + 
					doubleval($row['courier'])) * -1;
					break;
				case $this->TARGET_FEE_NAME['lzd_fee']:
					$result = $row['lzd_fee'];
					break;
				case $this->TARGET_FEE_NAME['voucher']:
					$result = $row['voucher'];
					break;
				default:
					$result = false;
		}
		return $result;
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

	public function setDB_Result($con){
		$USER_IP = mysqli_real_escape_string($con, $_SERVER['REMOTE_ADDR']);
		$sql = "SELECT T2.tdate, T2.fee_type, T2.fee_name, T2.description, T2.lzd_sku, T2.sdate, T2.paid_status, T2.amount, T2.order_num, T2.item_status,  T1.selling_price, T1.courier, T1.courier_lzd, T1.lzd_fee, T1.voucher, T1.billno FROM 
		(SELECT billno, order_num, SUM(selling_price)AS selling_price, SUM(courier)AS courier, SUM(courier_lzd)AS courier_lzd, SUM(lzd_fee)AS lzd_fee, SUM(voucher)AS voucher FROM courier_record 
		WHERE user_ip = '$USER_IP' 
		GROUP BY order_num)T1
		RIGHT JOIN 
		(SELECT id, tdate, fee_type, fee_name, description, lzd_sku, sdate, paid_status, SUM(amount)AS amount, order_num, item_status FROM lzd_fee_statements 
		WHERE user_ip = '$USER_IP' 
		GROUP BY order_num, fee_name)T2 
		ON T2.order_num = T1.order_num 
		ORDER BY T2.id;";

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