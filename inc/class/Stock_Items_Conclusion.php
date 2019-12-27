<?php 
class Stock_Items_Conclusion{
	private $result;
	private $orders_list;
	private $output;
	private $Info;

	public function __construct(Shopee_Product_Info $Info){
		$this->result = [];
		$this->orders_list = [];
		$this->output = [];
		$this->Info = $Info;
	}

	public function setOrders_List(array $order_list){
		$this->orders_list = $order_list;
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
		foreach($this->output as $r){
			foreach($r as $c => $col){
				$r[$c] = htmlspecialchars($col, ENT_QUOTES, 'UTF-8');
			}
			unset($col); unset($c);
			$output[] = $r;
		}
		return $output;
	}

	private function setOutput(){
		$hasStock = 'No';
		$detail = [];
		$listDetail = [];
		
		foreach($this->orders_list as $order){
			//find num items
			preg_match_all('/\[\d+\] Product Name:/', $order['details'], $tempMatches, PREG_OFFSET_CAPTURE);
			$listDetail = $tempMatches[0];
			$numItem = count($listDetail);
			
			$strlen = strlen($order['details']);

			for($l=0;$l<$numItem;++$l){

				$strpos = (int)(isset($listDetail[$l + 1]) ? $listDetail[$l + 1][1] : 0);
				$this->Info->resetAll();
				$this->Info->setCell_Value(
					substr($order['details'], $strpos, ($strlen - $strpos))
				);
				
				$details = $this->Info->getDetails();
				$item_code = $details['parent sku reference no.'];


				if(!empty($details['sku reference no.'])){
					$item_code = $details['sku reference no.'];
				}

				if(array_key_exists($item_code, $this->result)){
					$stock = intval($this->result[$item_code]['quantity']);
					$quantity = 0;
					if($stock > 0){
						$hasStock = 'Yes';
					}
				}else{
					$hasStock = 'Maybe';
				}

				$description = $this->result[$item_code]['description'] ?? $item_code;
				$item_code = $this->result[$item_code]['item_code'] ?? 'N/a';

				$paidPrice = $order['price'];
				$shippingFee = $order['shipping_fee'];
				if($l > 0){
					$paidPrice = "-";
					$shippingFee = "-";
				}
				$this->output[] = [
					'Order ID'		=>	$order['order_id'],
					'Payment Date'	=>	$order['date'],
					'Item Code'		=>	$item_code,
					'Description'	=>	$description,
					'Paid'			=>	$paidPrice,
					'Shipping Fee'	=>	$shippingFee,
					'Tracking Number' =>$order['tracking_num'],
					'has Stock?'	=>	$hasStock
				];
				for($i = 1;$i< $details['quantity'];++$i){
					$this->output[] = [
						'Order ID'		=>	$order['order_id'],
						'Payment Date'	=>	$order['date'],
						'Item Code'		=>	$item_code,
						'Description'	=>	$description,
						'Paid'			=>	"-",
						'Shipping Fee'	=>	"-",
						'Tracking Number' =>$order['tracking_num'],
						'has Stock?'	=>	$hasStock
					];
				}
			}

			
		}
	}

}