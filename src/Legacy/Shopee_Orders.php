<?php 
class Shopee_Orders extends CSV_Table_Handler{

	private $items_code;

	public function __construct(array $FILE){
		parent::__construct($FILE);
		$this->items_code = [];
		$this->setTable(false);
	}

	public function getItems_Code(){
		return $this->items_code;
	}

	public function gatherItems_Code(){
		$details = [];
		try{
			$details = $this->getField_Value(9); //product info
		}catch(Exception $e){
			die('Invalid File format. ' . $e->getMessage());
		}
		
		$this->setItems_Code($details);
	}

	public function getOrders_List(){
		$list = [];
		try{
			$list = $this->getFields_Value(
				[0, 5, 6, 7, 9, 22], 
				['order_id', 'date', 'price', 'shipping_fee', 'details', 'tracking_num']); //product info
		}catch(Exception $e){
			die('Invalid File format. ' . $e->getMessage());
		}

		array_splice($list, 0, 1); //remove header
		return $list;
	}

	private function setItems_Code(array $details){
		$DELIMITER = ';';
		$START = 3;
		$TARGET = 'sku reference no.:';
		$POS_AFTER_TARGET = strlen($TARGET);
		$v = '';
		$info_now = '';
		$pos = 0;
		$len = 0;
		$this->items_code = [];

		foreach($details as $item){
			$v = explode($DELIMITER, $item);
			$len = sizeof($v);
			for($i=$START;$i<$len;++$i){
				$info_now = trim($v[$i]);
				if(strlen($info_now) <= 0){continue;}

				$pos = strpos(strtolower($info_now), $TARGET);
				if($pos !== false){
					$pos += $POS_AFTER_TARGET;
					$this->items_code[] = trim(substr($info_now, $pos));
					
				}
				
			}
		}
	}

}
