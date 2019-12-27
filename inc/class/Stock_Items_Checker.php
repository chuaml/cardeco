<?php 
class Stock_Items_Checker{
	private $result;
	private $items_code;
	private $output;
	private $Info;


	public function __construct(Shopee_Product_Info $Info){
		$this->result = [];
		$this->items_code = [];
		$this->output = ['outofstock' => [], 'itemList' => [], 'notfound' => []];
		$this->Info = $Info;
	}

	public function setItems_Code(array $order_list){
		$this->items_code = [];
		$details = [];
		$v = '';
		foreach($order_list as $order){
			$v = trim($order['details']);
			
			if(strlen($v) <= 0){
				continue;
			}

			$this->Info->resetAll();
			$this->Info->setCell_Value($v);
			$details = $this->Info->getDetails();
			$item_code = $details['parent sku reference no.'];
			if(strlen($details['sku reference no.']) > 0){
				$item_code = $details['sku reference no.'];
			}


			if(!array_key_exists($item_code, $this->items_code)){
				$this->items_code[$item_code] = $details['quantity'];
			} else {
				$this->items_code[$item_code] += $details['quantity'];
			}
			
		}
	}

	public function setResult(array $result){
		if(sizeof($result) <= 0){
			throw new Exception('empty result');
			return false;
		}
		$this->result = [];
		foreach($result as $r){
			$this->result[$r['item_code']] = $r;
		}

		$this->setOutput();
	}

	public function getOutput(){
		$output = [];
		$paper = [];
		foreach($this->output as $t => $type){
			$paper = [];
			foreach($type as $r){
				foreach($r as $c => $col){
					$r[$c] = htmlspecialchars($col, ENT_QUOTES, 'UTF-8');
				}
				unset($col); unset($c);
				$paper[] = $r;
			}
			unset($r);

			$output[$t] = $paper;
		}
		return $output;
	}

	private function setOutput(){
		$this->output = ['outofstock' => [], 'itemList' => [], 'notfound' => []];
		$row = [];
		foreach($this->items_code as $item_code => $quantity){
			$row = $this->getTableForm($item_code, $quantity);

			if(array_key_exists($item_code, $this->result)){
				if($row['Stock'] < $quantity || $row['Stock'] <= 0){
					$this->output['outofstock'][] = $row;
				}else{
					$this->output['itemList'][] = $row;
				}
			}else{
				$this->output['notfound'][] = $row;
			}
		}
	}

	private function getTableForm(string $item_code, int $quantity){
		$ROW = [
			'Item Code' => 'N/A',
			'Description' => $item_code,
			'Quantity' => $quantity,
			'Stock' => 'N/A'
		];
		if(array_key_exists($item_code, $this->result)){
			$ROW['Item Code'] = $item_code;
			$ROW['Description'] = $this->result[$item_code]['description'];
			$ROW['Stock'] = intval($this->result[$item_code]['quantity']);
		}

		return $ROW;
	}


}