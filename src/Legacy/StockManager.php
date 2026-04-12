<?php 
class StockManager{
	protected $con;
	protected $query_arg;
	protected $result;
	protected $result_pages;
	protected $limit_result;
	public $query_time;
	public $query_num_rows;
	protected $delimiter;
	protected $total_num_rows;
	

	function __construct(mysqli $con){
		$this->con = $con;
		$this->query_arg = null;
		$this->result = [];
		$this->result_pages = [];
		$this->limit_result = 500;
		$this->query_time = 0.00;
		$this->query_num_rows = 0;
		$this->delimiter = '||';
		$this->total_num_rows = $this->getCount_stock();
		//row[k][FIELDNAME];
	}
	
	
	protected function setResult_pages(int $current_page = 1, int $limit_result = 0){
		$pages = [];
		$limit_result = $limit_result === 0 ? $this->limit_result : $limit_result;
		$num_pages = ceil($this->query_num_rows / $limit_result);
		
		for($i=0;$i<$num_pages;++$i){
			$pages[] = 1 + $i;
			continue;
		}
		
		$this->result_pages = $pages;
	}
	
	protected function getCount_stock(){
		$result = 0;
		$sql = 'SELECT COUNT(id)AS count_id FROM stock_items;';
		$stmt = mysqli_query($this->con,$sql);
		if(!$stmt){ 
			return false;
		}
		while($row = mysqli_fetch_assoc($stmt)){
				$result = intval($row['count_id']);
		}
		return intval($result);
	}
	
	
	public function getTotal_num_stock(){
		return $this->total_num_rows;
	}
	
	public function getResult_pages(){
		return $this->result_pages;
	}
	
	private function encodeResult(bool $showImage){
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
					if($showImage === true){
						if($col === null || strlen(trim($col)) === 0){
							$new_row[$k] = $col;
							continue;
						}
						$image = explode($delimiter, $col);
						$new_row[$k] = '<div class="item_images">';
						foreach($image as $i => $src){
							$new_row[$k] .= '<img src="' .$src
							.'" class="liststock" alt="image" />';
						}
						unset($src); unset($i);
						$new_row[$k] .= '</div>';
						continue;
					} else {
						if(strlen(trim($col)) === 0 || $col === null){
							$new_row[$k] = 0;
							continue;
						}
						$new_row[$k] = sizeof(explode(',', $col));
						continue;
					}
					
				}
				$new_row[$k] = htmlspecialchars($col, ENT_QUOTES, 'UTF-8');
			}
			unset($col); unset($k);

			$new_result[] = $new_row;
		}
		unset($r);
		
		$this->result = $new_result;
	}
	
	protected function calQuery_time(string $StartEnd = 'start'){
		if(strtolower($StartEnd) === 'start'){
			$this->query_time = 0.00;
		}
		
		list($usec, $sec) = explode(' ', microtime());
		$this->query_time = ((float)$usec + (float)$sec) - $this->query_time;
	}
	
	public function getQuery_time(){
		return $this->query_time;
	}

	public function setQueryArg(string $words){
		$word = trim($words);
		if(strlen($word) === 0){
			$this->query_arg = '';
			return;
		}

		$v = '';
		$word = explode(' ', $word); 
		$args = [];
		$query_arg = '';
		
		if(sizeof($word) === 0 || strlen(trim($words)) === 0){
			return '';
		}
		
		foreach($word as $w){
			$v = trim($w);
			if($v === null || strlen($v) === 0){
				continue;
			}
			
			$v = mysqli_real_escape_string($this->con, trim($v));
			$args[] = "(stock_items.item_code LIKE '%$v%' OR stock_items.description LIKE '%$v%')";
		} 
		unset($w);
		
			
		$query_arg = 'WHERE ' .implode(' AND ', $args);
		$this->query_arg = trim($query_arg);
	}
	
	public function execute_query(int $current_page = 1, int $limit_result = 0, bool $showImage = true){
		$result = [];
		$limit_result = $limit_result === 0 ? $this->limit_result : $limit_result;
		$limit_offset = 0;
		$result_num_rows = 0;
		$this->calQuery_time();
		$delimiter = $this->delimiter;
		
		$current_page = mysqli_real_escape_string($this->con, $current_page);
		$limit_result = mysqli_real_escape_string($this->con, $limit_result);
		
		$query_arg = $this->query_arg; 
		
		$sql = "SELECT COUNT(stock_items.id)AS count_id FROM stock_items 
				INNER JOIN stock ON stock_items.id = stock.id 
				$query_arg;";
		$stmt = mysqli_query($this->con,$sql);
		if($stmt === false){
			$this->result = mysqli_error($this->con);
			trigger_error(mysqli_error($this->con));
			return false;
		}
		
		while($row = mysqli_fetch_assoc($stmt)){
			$result_num_rows = intval($row['count_id']);
		}
		unset($row);
		
		mysqli_free_result($stmt); unset($stmt); unset($sql);

		$limit_offset = (($current_page -1) * $limit_result);
		
		$sql = "SELECT stock_items.id, stock_items.item_code, stock_items.description, stock_items.uom, stock_items.item_group, stock.quantity, GROUP_CONCAT(CONCAT(stock_images.directory, stock_images.image)SEPARATOR '$delimiter')AS image FROM stock_items 
				INNER JOIN stock ON stock_items.id = stock.id 
				LEFT JOIN stock_images ON stock_items.id IN (stock_images.item) 
				$query_arg 
				GROUP BY stock_items.id 
				LIMIT $limit_offset, $limit_result;";
		if($showImage === false){
			$sql = "SELECT stock_items.id, stock_items.item_code, stock_items.description, stock_items.uom, stock_items.item_group, stock.quantity, stock_items.image 
				FROM stock_items 
				INNER JOIN stock ON stock_items.id = stock.id 
				$query_arg 
				LIMIT $limit_offset, $limit_result;";
		}
		
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
		$this->query_num_rows = $result_num_rows;
		mysqli_free_result($stmt);
		$this->setResult_pages($current_page, $limit_result);
		$this->calQuery_time('end');
		$this->encodeResult($showImage);
		
		//return $stmt;
	}
	
	public function getResult(){
		$output = '<table id="liststock">';
		$id = '';
		$btnEdit = '<a href="itemdetail?id=' .$id .'">Edit</a>';
		$chkBookmark = '<input type="checkbox" class="checkbox" value="' .$id .'"/>';
		$result = $this->result;
		if(sizeof($result) >= 1){
			$output .= '<thead><tr><th>' .implode('<span></span></th><th>', array_keys($result[0])) .'<th>Modify</th><th onclick="setAllCheckboxes(this)">mark</th></tr></thead>';
			foreach($result as $r => $row){ 
				$id = $row['id'];
				$btnEdit = '<a href="itemdetail?id=' .$id .'">Edit</a>';
				$chkBookmark = '<input type="checkbox" class="checkbox" ROW="' 
					.$r
					.'" name="row[' 
					.$r
					.'][id]" value="' .$id .'"/>';

				$output .= '<tr><td>'
						.implode('</td><td>',$row)
						.'</td><td>' .$btnEdit .'</td><td>' 
						.$chkBookmark .'</td></tr>';
			}
		} else {
			$output .= '<p>No Results...</p>';
		}
		
		$output .= '</table>';
		
		return $output; 
	}

}
