<?php 
require('StockManager.php');

class StockItem_detail extends StockManager{
	
	function __construct(mysqli $con){
		parent::__construct($con);
	}

	public function setQueryArg(string $id){
		if(strlen(trim($id)) === 0){
			$this->query_arg = '';
			return null;
		}
		
		$item_id = mysqli_real_escape_string($this->con, intval(trim($id)));
		$this->query_arg  = "WHERE stock_items.id = $id";
	}
	
	public function execute_query(int $current_page = 1, int $limit_result = 2, $showImage = true){
		$result = [];
		$query_arg = $this->query_arg; 
		$delimiter = $this->delimiter;

		$sql = "SELECT stock_items.id, stock_items.item_code, stock_items.description, GROUP_CONCAT(CONCAT(stock_images.directory, stock_images.image)SEPARATOR '$delimiter')AS image FROM stock_items 
				LEFT JOIN stock_images ON stock_items.id IN (stock_images.item) 
				$query_arg 
				GROUP BY stock_items.id, stock_items.item_code, stock_items.description
				LIMIT $limit_result;";
		$stmt = mysqli_query($this->con,$sql);

		if($stmt === false){
			$this->result = mysqli_error($this->con);
			trigger_error(mysqli_error($this->con));
			return false;
		}
		
		while($row = mysqli_fetch_assoc($stmt)){
			$result[] = $row;
		}
		
		$this->result = $result;
		$this->encodeResult();
	}
	
	public function getResult(){
		$output = '<table><thead><tr><th>fields</th><th>value</th></tr></thead><tbody>';
		$result = $this->result;
		if(sizeof($result) >= 1){
			foreach($result as $row){
				foreach($row as $k => $col){
					$output .= '<tr><td>' .$k .'</td>'
						.'<td>' .$col .'</td></tr>';
				}
				unset($col); unset($k);
			}
		} else {
			$output .= '<p>Item Not Found</p>';
		}
		
		$output .= '</tbody></table>';
		
		return $output;
	}

	private function encodeResult(){
		$delimiter = $this->delimiter;
		$new_result = [];
		$new_row = [];
		$image = [];
		//modify image column to html <img>
		foreach($this->result as $r => $row){
			$image = [];
			$new_row = [];
			foreach($row as $k => $col){
				if($k === 'image'){
					if($col === null || strlen(trim($col)) === 0){
						$new_row[$k] = $col;
						continue;
					}
					$image = explode($delimiter, $col);
					$new_row[$k] = '';
					foreach($image as $i => $src){
						$new_row[$k] .= '<label class="lblImage" for="' .$r .$i 
						.'"><img src="' .$src
						.'" class="liststock" alt="image" />'
						.'<input type="checkbox" name="row[' 
						.$r 
						.'][image][]" id="' 
						.$r .$i 
						.'" value="' 
						.$i
						.'" /></label>';
					}
					unset($src); unset($i);
					continue;
				}
				$new_row[$k] = htmlspecialchars($col, ENT_QUOTES, 'UTF-8');
			}
			unset($col); unset($k);

			$new_result[] = $new_row;
		}
		unset($r);
		
		$this->result = $new_result;
	}


}
