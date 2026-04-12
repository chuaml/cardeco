<?php 
class Shopee_Product_Info{
	
	private $product_details;
	private $TYPE_DELIMITER;
	private $INFO_DELIMITER;

	private $cell_value;


	function __construct(){
		$this->cell_value = [];
		$this->TYPE_DELIMITER = ';';
		$this->INFO_DELIMITER = ':';
		$this->product_details = [
			'product name' => '',
			'variation name' => '',
			'price' => '',
			'quantity' => '',
			'parent sku reference no.' => '',
			'sku reference no.' => ''
		];

		$this->gatherDetails();
	}

	public function setCell_Value(string $cell_value){
		$this->cell_value = explode($this->TYPE_DELIMITER, trim($cell_value));
		$this->gatherDetails();
	}

	public function resetAll(){
		$this->cell_value = [];
		$this->TYPE_DELIMITER = ';';
		$this->INFO_DELIMITER = ':';
		$this->product_details = [
			'product name' => '',
			'variation name' => '',
			'price' => '',
			'quantity' => '',
			'parent sku reference no.' => '',
			'sku reference no.' => ''
		];
	}

	public function getDetails(){
		return $this->product_details;
	}

	private function gatherDetails(){
		foreach($this->cell_value as $info){
			$this->caseInfo_Type($info);
		}

		$this->product_details['price'] = doubleval(
			preg_replace('/[a-zA-Z]/', '', $this->product_details['price'])
		);
		$this->product_details['quantity'] = intval($this->product_details['quantity']);
	}

	private function caseInfo_Type(string $info){
		$info_types = explode($this->INFO_DELIMITER, $info);
		$info_type = trim(strtolower($info_types[0]));
		$value = $info_types;
			array_splice($value, 0, 1);
			$value = implode($this->INFO_DELIMITER, $value);
		$pos = 0;
		foreach($this->product_details as $detail => $v){
			if(strlen(trim($v)) > 0){
				continue;
			}

			$pos = strpos($info_type, $detail);
			if($pos !== false){
				$this->product_details[$detail] = trim($value);
			}
		}

	}


}