<?php 
require('StockManager.php');

class StockReport extends StockManager{
	private $num_rows;
	private $input_items;
	private $items_found;
	private $items_notfound;
	
	function __construct(mysqli $con){
		parent::__construct($con);
		$this->num_rows = 0;
		$this->input_items = [];
		$this->items_found = [];
		$this->items_notfound = [];
	}

	public function setQueryArg(string $items_name, string $delimiter = "\r\n"){
		$items = explode($delimiter, trim($items_name));
		$query_arg = '';
		$clean_items = [];
		$input_items = [];
		$v = '';
		$row = [];
		$keys = [
		'china_code',
		'china_name',
		'item_code'
		];
		$hash_table = [];
		
		foreach($items as $k => $r){
			$v = trim($r);
			if(strlen($v) === 0){continue;}
			
			$v = rtrim($r);
			$row = explode("\t", $v);
			
			//if(sizeof($row) < 3){}
			
			//0 china code
			//1 china name
			//2 sql accounting item code
			for($c=0;$c<3;++$c){
				$row[$keys[$c]] = isset($row[$c]) ? trim($row[$c]) : null;
				unset($row[$c]);
			}
			
			//skip duplicate rows/items
			if(isset($hash_table[$row['item_code']])){
				++$hash_table[$row['item_code']]; 
				continue;
			}

			$hash_table[$row['item_code']] = 1; 
			$v = $row['item_code'];
			
			if(strlen($v) > 0){
				$input_items[] = $row;
				$clean_items[] = mysqli_real_escape_string($this->con, $v);
			} else {
				$this->items_notfound[] = $row;
			}
		}
		
		$v = "'" .implode("','", $clean_items) . "'";
		
		$query_arg = "WHERE stock_items.item_code IN ($v) 
		ORDER BY stock.quantity";
		
		$this->input_items = $input_items;
		$this->num_rows = sizeof($clean_items);
		$this->query_arg = trim($query_arg);
	}
	
	public function execute_query(int $current_page = 1, int $limit_result = 0, bool $showImage = true){
		$result = [];
		$limit_result = $limit_result === 0 ? $this->limit_result : $limit_result;
		$query_arg = $this->query_arg; 
		
		$sql = "SELECT stock_items.id, stock_items.item_code, stock_items.description, stock_items.uom, stock.quantity FROM stock_items 
				INNER JOIN stock ON stock_items.id = stock.id 
				$query_arg 
				LIMIT $limit_result;";
		$stmt = mysqli_query($this->con,$sql);

		if($stmt === false){
			$this->result = mysqli_error($this->con);
			return false;
		}
		
		while($row = mysqli_fetch_assoc($stmt)){
			$r = [];
			foreach($row as $k => $col){
				$r[$k] = $col;
			}
			unset($col); unset($k);
			$result[] = $r;
		}

			$hash_table = [];
			$item_code = '';
			
			
			foreach($result as $k => $row){
				$item_code = $row['item_code'];
				$hash_table[$item_code] = $k;
			}
			unset($row); unset($k);
			
			$temp = [];
			$key = null;
			foreach($this->input_items as $k => $row){
				if(!isset($hash_table[$row['item_code']])){
					$this->items_notfound[] = $row;
				} else {
					$temp = $row;
					$key = $hash_table[$row['item_code']];
					$temp['description'] = $result[$key]['description'];
					$temp['quantity'] = $result[$key]['quantity'];
					$this->items_found[] = $temp;
				}
			}
			unset($row); unset($k);
		
		$this->result = $result;
	}
	
	public function getResult(){
		$items_found = $this->items_found;
		$len = sizeof($items_found);
		$qty_list = [];
		$btn = '';
		$output = '';
		
		foreach($items_found as $row){
			$qty_list[] = $row['quantity'];
		}
		unset($row);
		array_multisort($qty_list,$items_found);
		
		if($len >= 1){ 
			$btn = '<span style="float: right;"><button onclick="reportStock()">Print Orders</button></span>';
			$output .= "<p>$len</p>$btn" .'<table id="listdb">' 
			.'<thead><tr>
			<th>China Code</th>
			<th>China Name</th>
			<th>Item Code</th>
			<th>Description</th>
			<th>Quantity</th>
			<th>Order</th></tr></thead>';
			$input = '<input type="number" name="order_qty[]" min="0" max="9999" step="1" value="">'; 
			foreach($items_found as $row){
				$output .= '<tr><td>'
						.implode('</td><td>',$row)
						."</td><td>$input</td></tr>";
			}
		} else {
			$output .= '<p>No Results...</p>';
		}
		
		$output .= '</table>';
		
		return $output;
	}

	public function getItems_notfound(){
		$items_notfound = $this->items_notfound; 
		$len = sizeof($items_notfound);
		$output = $len .'<table id="listdb" class"notfound">';
		if($len >= 1){
			$output .= '<thead><tr><th>' 
				.implode('</th><th>', array_keys($items_notfound[0])) 
				.'</th></tr></thead><tbody>';
			foreach($items_notfound as $row){
				$output .= '<tr><td>'
						.implode('</td><td>',$row)
						.'</td></tr>';
			}
			$output .= '</tbody></table>';
		} else {
			$output .= '<p>Nothing</p>';
		}
		
		return $output;
	}
}
