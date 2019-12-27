<?php 
require('StockManager.php');
class StockBookmark extends StockManager{
	private $bookmark_id;

	function __construct($con){
		parent::__construct($con);
		$this->bookmark_id = 0;
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

	public function setQueryArg(string $words){
		$v = '';
		$word = explode(' ', trim($words)); 
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

		$query_arg = implode(' AND ', $args);
		$this->query_arg = trim($query_arg); 
	}
	
	public function execute_query(int $current_page = 1, int $limit_result = 0, bool $showImage = true){
		$result = [];
		$limit_result = $limit_result === 0 ? $this->limit_result : $limit_result;
		$limit_offset = 0;
		$result_num_rows = 0;
		$this->calQuery_time();
		$delimiter = $this->delimiter;
		$bookmark_id = mysqli_real_escape_string($this->con, $this->bookmark_id);
		
		$current_page = mysqli_real_escape_string($this->con, $current_page);
		$limit_result = mysqli_real_escape_string($this->con, $limit_result);
		
		$query_arg = strlen(trim($this->query_arg)) === 0 ? '' : 'AND ' .$this->query_arg; 
		
		$sql = "SELECT COUNT(stock_items.id)AS count_id FROM stock_items 
		INNER JOIN bookmarks_items ON stock_items.id = bookmarks_items.item_id 
		WHERE bookmarks_items.status = 1 $query_arg 
		AND bookmarks_items.bookmark_id = $bookmark_id;";
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
		
		$sql = "SELECT bookmarks.id, stock_items.id, stock_items.item_code, stock_items.description, stock_items.uom, stock_items.item_group, GROUP_CONCAT(CONCAT(stock_images.directory, stock_images.image)SEPARATOR '$delimiter')AS image FROM bookmarks 
				INNER JOIN bookmarks_items ON bookmarks.id = bookmarks_items.bookmark_id 
				INNER JOIN stock_items ON stock_items.id = bookmarks_items.item_id 
				LEFT JOIN stock_images ON stock_items.id IN (stock_images.item) 
				WHERE bookmarks_items.status = 1 $query_arg 
				GROUP BY bookmark_id, stock_items.id, stock_items.item_code, stock_items.description, stock_items.uom 
				HAVING bookmarks.id = $bookmark_id
				LIMIT $limit_offset, $limit_result;"; 
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
		$this->encodeResult();
	}
	
	public function getResult(){
		$output = '<table id="liststock">';
		$id = '';
		$chkbox = '<input type="checkbox" class="checkbox" value="' .$id .'"/>';
		$txtComment = '<input type="text" class="comment">';
		$result = $this->result;
		if(sizeof($result) >= 1){
			$output .= '<thead><tr><th>' .implode('<span></span></th><th>', array_keys($result[0])) .'<th>Remark</th><th onclick="setAllCheckboxes(this)">Select</th></tr></thead>';
			foreach($result as $r => $row){ 
				$id = $row['id'];
				$chkbox = '<input type="checkbox" class="checkbox" ROW="' 
					.$r
					.'" name="row[' 
					.$r
					.'][id]" value="' .$id .'"/>';
				$txtComment = '<input type="text" class="comment" minlength="1" maxlength="255" placeholder="remarks..." name="row[' 
					.$r 
					.'][comment]" />';

				$output .= '<tr><td>'
						.implode('</td><td>',$row)
						.'</td><td>' .$txtComment .'</td><td>' 
						.$chkbox .'</td></tr>';
			}
		} else {
			$output .= '<p>No bookmark items...</p>';
		}
		
		$output .= '</table>';
		
		return $output; 
	}
	
	public function setBookmark_id(int $bookmark_id){
		$this->bookmark_id = $bookmark_id;
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
